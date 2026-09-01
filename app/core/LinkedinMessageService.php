<?php

/**
 * Geração da mensagem de uma ação LinkedIn.
 *
 * A mensagem é preparada ANTES de a tarefa aparecer para o vendedor. Usa a IA já
 * existente (OpenAiClient) e SOMENTE dados reais do lead (contato + briefing). Se a
 * IA não estiver configurada ou falhar, faz fallback para o template renderizado
 * (MessageTemplate::render) ou para a mensagem-base do nó.
 *
 * REGRA ANTI-ALUCINAÇÃO: a IA é instruída a NÃO inventar contexto. Nunca afirmar que
 * o vendedor viu uma publicação, acompanha a empresa, notou contratações, etc. Só
 * pode usar os dados fornecidos (nome, cargo, empresa, setor, tamanho, localização).
 *
 * NENHUMA automação de LinkedIn: este serviço apenas GERA texto. O envio é manual.
 */
class LinkedinMessageService
{
    private $db;

    // Rótulos legíveis das ações (para o objetivo padrão do prompt)
    private static $actionLabels = [
        'connect'  => 'solicitação de conexão',
        'message'  => 'primeira mensagem',
        'followup' => 'follow-up',
        'final'    => 'mensagem final',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gera a mensagem para uma etapa LinkedIn.
     *
     * @param int   $contactId  Lead (whatsapp_contacts)
     * @param array $node       Nó da sequência: data = {action_type, objective, tone,
     *                          cta, max_length, template_id, body}
     * @return array { message: string, source: 'ai'|'template'|'base', warning: ?string }
     */
    public function generate($contactId, array $node)
    {
        $data = $node['data'] ?? $node; // aceita o nó inteiro ou só o data
        $contact = $this->loadContact($contactId);
        $facts = $this->realFacts($contact);

        $actionType = $data['action_type'] ?? 'message';
        $objective  = trim((string) ($data['objective'] ?? ''));
        $tone       = trim((string) ($data['tone'] ?? ''));
        $cta        = trim((string) ($data['cta'] ?? ''));
        $maxLength  = (int) ($data['max_length'] ?? 0);

        // Mensagem-base: template LinkedIn cadastrado (template_id) ou body inline.
        $baseTemplate = '';
        if (!empty($data['template_id'])) {
            $tpl = $this->db->fetch("SELECT body FROM message_templates WHERE id = ? AND channel = 'linkedin'", [(int) $data['template_id']]);
            if ($tpl) $baseTemplate = (string) $tpl['body'];
        }
        if ($baseTemplate === '') $baseTemplate = (string) ($data['body'] ?? '');

        // Renderiza variáveis reais na base (mesmo renderizador da plataforma).
        $renderedBase = MessageTemplate::render($baseTemplate, $contact);

        // Tenta a IA. Se indisponível/erro, usa a base renderizada.
        $ai = new OpenAiClient();
        if (!$ai->isConfigured()) {
            return [
                'message' => $this->enforceLimit($renderedBase, $maxLength),
                'source'  => $renderedBase !== '' ? 'template' : 'base',
                'warning' => 'IA não configurada — usando a mensagem-base do template. Configure a OpenAI em Configurações para geração automática.',
            ];
        }

        $messages = $this->buildPrompt($facts, $actionType, $objective, $tone, $cta, $maxLength, $renderedBase);
        $res = $ai->chat($messages, [
            'model' => $data['model'] ?? 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 400,
        ]);

        if (empty($res['success']) || trim((string) $res['content']) === '') {
            return [
                'message' => $this->enforceLimit($renderedBase, $maxLength),
                'source'  => $renderedBase !== '' ? 'template' : 'base',
                'warning' => 'Não foi possível gerar com IA (' . ($res['error'] ?? 'erro') . '). Usando a mensagem-base.',
            ];
        }

        return [
            'message' => $this->enforceLimit($res['content'], $maxLength),
            'source'  => 'ai',
            'warning' => null,
        ];
    }

    // ---- Prompt (só dados reais) ----

    private function buildPrompt(array $facts, $actionType, $objective, $tone, $cta, $maxLength, $renderedBase)
    {
        $actionLabel = self::$actionLabels[$actionType] ?? 'mensagem';
        if ($objective === '') {
            $objective = 'iniciar uma conversa comercial breve e cordial';
        }

        // Lista somente os fatos REAIS disponíveis. A IA é proibida de usar qualquer
        // outra informação.
        $factLines = [];
        foreach ($facts as $label => $value) {
            if ($value !== '' && $value !== null) $factLines[] = "- {$label}: {$value}";
        }
        $factsBlock = $factLines ? implode("\n", $factLines) : '- (nenhum dado adicional além do nome)';

        $system = "Você é um SDR brasileiro experiente escrevendo uma mensagem de LinkedIn em português do Brasil. "
            . "Escreva de forma natural, cordial e objetiva, como uma pessoa real. "
            . "REGRAS OBRIGATÓRIAS: "
            . "1) Use SOMENTE os dados fornecidos sobre o lead. "
            . "2) NUNCA invente fatos. NÃO diga que viu uma publicação, que acompanha a empresa, que notou contratações, prêmios, notícias ou qualquer contexto que não esteja nos dados. "
            . "3) Não prometa preços, prazos ou resultados específicos. "
            . "4) Não use emojis em excesso (no máximo um). "
            . "5) Responda APENAS com o texto final da mensagem, sem aspas, sem assunto, sem comentários.";

        $userParts = [];
        $userParts[] = "Tipo de ação: {$actionLabel}.";
        $userParts[] = "Objetivo: {$objective}.";
        if ($tone !== '') $userParts[] = "Tom desejado: {$tone}.";
        if ($cta !== '')  $userParts[] = "Chamada para ação (CTA): {$cta}.";
        if ($maxLength > 0) $userParts[] = "Limite máximo: {$maxLength} caracteres (respeite estritamente).";
        if ($actionType === 'connect') {
            $userParts[] = "Contexto: é uma nota de convite de conexão do LinkedIn (curta, até ~300 caracteres).";
        }
        $userParts[] = "Dados REAIS do lead (use apenas estes):\n{$factsBlock}";
        if (trim($renderedBase) !== '') {
            $userParts[] = "Mensagem-base de referência (adapte e melhore, mantendo o sentido):\n" . trim($renderedBase);
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n\n", $userParts)],
        ];
    }

    /**
     * Extrai apenas fatos reais do lead. Não inventa. Puxa dados estruturados do
     * contato e do briefing comercial (mesma fonte de MessageTemplate::render).
     */
    private function realFacts($contact)
    {
        $name = $contact['contact_name'] ?? ($contact['push_name'] ?? '');
        $first = trim(explode(' ', trim($name))[0] ?? '');

        // Reaproveita a extração de campos comerciais do briefing (Cargo/Empresa/etc.)
        $extra = $this->briefingFields($contact['id'] ?? null);

        return [
            'Primeiro nome' => $first,
            'Nome completo' => $name,
            'Cargo'         => $extra['cargo'] ?? '',
            'Empresa'       => $extra['empresa'] ?? '',
            'Setor'         => $extra['setor'] ?? '',
            'Cidade'        => $extra['cidade'] ?? '',
            'Estado'        => $extra['estado'] ?? '',
        ];
    }

    private function briefingFields($contactId)
    {
        $out = ['empresa' => '', 'cargo' => '', 'cidade' => '', 'estado' => '', 'setor' => '', 'linkedin' => ''];
        if (!$contactId) return $out;
        try {
            $bf = $this->db->fetch("SELECT need, notes FROM commercial_briefings WHERE contact_id = ?", [$contactId]);
            if ($bf) {
                $out['setor'] = trim((string) ($bf['need'] ?? ''));
                $notes = (string) ($bf['notes'] ?? '');
                if (preg_match('/Cargo:\s*([^|]+)/i', $notes, $m)) $out['cargo'] = trim($m[1]);
                if (preg_match('/Empresa:\s*([^|]+)/i', $notes, $m)) $out['empresa'] = trim($m[1]);
                if (preg_match('/LinkedIn:\s*([^|]+)/i', $notes, $m)) $out['linkedin'] = trim($m[1]);
            }
        } catch (\Throwable $e) { /* silencioso */ }
        return $out;
    }

    private function loadContact($contactId)
    {
        $c = $this->db->fetch(
            "SELECT id, contact_name, push_name, lead_email, phone, linkedin_url FROM whatsapp_contacts WHERE id = ?",
            [$contactId]
        );
        return $c ?: ['id' => $contactId];
    }

    /** Aplica o limite de caracteres, se definido (>0). */
    private function enforceLimit($text, $maxLength)
    {
        $text = trim((string) $text);
        if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength));
        }
        return $text;
    }
}
