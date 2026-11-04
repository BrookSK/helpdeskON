<?php

/**
 * Regras puras (sem banco/HTTP/IA) para a MINUTA/ATA automática de reuniões.
 *
 * A partir da transcrição de uma reunião gravada, o sistema gera automaticamente:
 *  - transcrição (Whisper)  -> já existente
 *  - resumo (chat)          -> já existente
 *  - MINUTA/ATA estruturada -> esta etapa (Fase 2)
 *
 * A minuta é uma ata objetiva do que aconteceu: contexto, participantes, pontos
 * discutidos, decisões tomadas, valores/condições acordados, pendências e próximos
 * passos. É editável pela equipe e, depois, enviada em PDF ao cliente e à equipe.
 *
 * Esta classe centraliza, de forma testável:
 *  - decidir se há material suficiente para gerar a minuta;
 *  - montar as mensagens (system/user) do prompt de geração;
 *  - normalizar/sanitizar o conteúdo editado antes de salvar.
 */
class MeetingMinutesRules
{
    /** Estados da geração da minuta (espelha a coluna minutes_status). */
    public const STATUS_NONE = 'none';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    public const STATUSES = [
        self::STATUS_NONE,
        self::STATUS_PROCESSING,
        self::STATUS_DONE,
        self::STATUS_ERROR,
    ];

    /** Teto de segurança da transcrição no prompt da minuta em texto (alternativa sem JSON). */
    public const TRANSCRIPT_LIMIT = self::SPLIT_LIMIT;

    /** Acima disso a transcrição é dividida em partes (cada parte vira notas). */
    public const SPLIT_LIMIT = 350000;

    /** Campos de lista da ata estruturada (JSON em video_recordings.summary). */
    public const LIST_FIELDS = [
        'topicos', 'topicos_nao_resolvidos', 'decisoes', 'proximos_passos',
        'necessidades', 'escopo', 'prazos', 'orcamento', 'decisores',
    ];

    /** Rótulos das seções comerciais (aparecem no texto só quando preenchidas). */
    public const COMMERCIAL_LABELS = [
        'necessidades' => 'Necessidades do cliente',
        'escopo' => 'Escopo desejado',
        'prazos' => 'Prazos citados',
        'orcamento' => 'Orçamento citado',
        'decisores' => 'Decisores',
    ];

    /**
     * Normaliza a ata estruturada vinda da IA ou da tela de revisão: resumo em
     * texto e cada campo de lista como array de strings não vazias. Campo
     * ausente ou inválido vira vazio. Devolve null se a entrada não é um objeto.
     *
     * @param array|string|null $data
     */
    public static function normalizeStructured($data): ?array
    {
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return null;
        $out = ['resumo' => is_string($data['resumo'] ?? null) ? trim(self::sanitizeContent($data['resumo'], 20000)) : ''];
        foreach (self::LIST_FIELDS as $f) {
            $v = $data[$f] ?? [];
            if (is_string($v)) $v = trim($v) === '' ? [] : [$v];
            $items = [];
            foreach (is_array($v) ? $v : [] as $item) {
                if (!is_string($item) && !is_numeric($item)) continue;
                $item = trim(self::sanitizeContent((string)$item, 2000));
                if ($item !== '') $items[] = $item;
            }
            $out[$f] = array_slice($items, 0, 50);
        }
        return $out;
    }

    /** Prompt de sistema da ata estruturada (JSON), com os campos comerciais. */
    public static function structuredSystemPrompt(): string
    {
        return 'Você é um assistente especializado em gerar minutas de reunião em português do Brasil. '
            . 'Analise a transcrição fornecida, do começo ao fim, e produza uma minuta estruturada. '
            . 'Responda SOMENTE com um objeto JSON válido com estas chaves (sem nenhum texto fora do JSON): '
            . '"resumo" (string: parágrafo geral descrevendo o contexto e objetivo da reunião), '
            . '"topicos" (array de strings: principais tópicos discutidos e concluídos), '
            . '"topicos_nao_resolvidos" (array de strings: tópicos citados mas sem conclusão ou deixados em aberto), '
            . '"decisoes" (array de strings: decisões formalmente tomadas), '
            . '"proximos_passos" (array de strings: tarefas e próximos passos, com responsável e prazo quando mencionados), '
            . '"necessidades" (array de strings: necessidades e dores do cliente), '
            . '"escopo" (array de strings: o que o cliente quer que seja feito ou entregue), '
            . '"prazos" (array de strings: prazos e datas citados), '
            . '"orcamento" (array de strings: valores, faixas de investimento e condições de pagamento citados), '
            . '"decisores" (array de strings: quem decide ou aprova do lado do cliente). '
            . 'Use somente o que foi dito na transcrição. Se uma chave não tiver conteúdo, retorne array vazio [] '
            . 'e nunca invente valores, prazos ou nomes. Seja direto e evite repetir informações entre seções.';
    }

    /** Prompt de sistema das notas de uma parte (transcrição acima de SPLIT_LIMIT). */
    public static function notesSystemPrompt(): string
    {
        return 'Você recebe UMA PARTE da transcrição de uma reunião longa, em português do Brasil. '
            . 'Escreva notas objetivas em tópicos com tudo o que importa desta parte: assuntos, decisões, '
            . 'pendências, tarefas, necessidades do cliente, escopo, prazos, valores e quem decide. '
            . 'Mantenha as marcas de tempo [mm:ss] dos pontos principais. Não invente nada.';
    }

    /**
     * Divide a transcrição em partes de até $max caracteres, sem cortar linhas.
     * Uma transcrição dentro do limite volta como parte única.
     *
     * @return string[]
     */
    public static function splitTranscript(string $transcript, int $max = self::SPLIT_LIMIT): array
    {
        if (mb_strlen($transcript) <= $max) return [$transcript];
        $parts = [];
        $cur = '';
        foreach (explode("\n", $transcript) as $line) {
            // Linha maior que o limite (raro): corta em pedaços.
            while (mb_strlen($line) > $max) {
                if ($cur !== '') { $parts[] = $cur; $cur = ''; }
                $parts[] = mb_substr($line, 0, $max);
                $line = mb_substr($line, $max);
            }
            $candidate = $cur === '' ? $line : $cur . "\n" . $line;
            if (mb_strlen($candidate) > $max) {
                $parts[] = $cur;
                $cur = $line;
            } else {
                $cur = $candidate;
            }
        }
        if ($cur !== '') $parts[] = $cur;
        return $parts;
    }

    /** Normaliza um status; valor desconhecido vira 'none'. */
    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_NONE;
    }

    /**
     * Há material suficiente para gerar a minuta? Exige uma transcrição com algum
     * conteúdo real (não só espaços/pontuação). Sem transcrição, não há ata.
     */
    public static function canGenerate(?string $transcript): bool
    {
        if (!is_string($transcript)) return false;
        $stripped = preg_replace('/[\p{P}\p{Z}\s]+/u', '', $transcript);
        return is_string($stripped) && $stripped !== '';
    }

    /**
     * Mensagem de sistema do prompt da minuta. Define o papel e o formato fixo
     * da ata, em português do Brasil. Mantida como fonte única (testável) para o
     * formato não divergir entre pontos de chamada.
     */
    public static function systemPrompt(): string
    {
        return 'Você redige ATAS (minutas) de reuniões em português do Brasil, de forma objetiva, '
            . 'profissional e fiel ao que foi dito — sem inventar informação. Produza a ata em Markdown '
            . 'com as seções, nesta ordem: '
            . '1) **Contexto da reunião** (data/assunto, de forma breve); '
            . '2) **Participantes** (quem falou, se identificável); '
            . '3) **Pontos discutidos** (bullets objetivos); '
            . '4) **Decisões tomadas** (o que ficou acordado); '
            . '5) **Valores e condições** (preços, prazos, formas de pagamento mencionados — '
            . 'se não houver, escreva "Não foram tratados valores."); '
            . '6) **Pendências / o que ficou em aberto**; '
            . '7) **Próximos passos** (tarefas e responsáveis quando citados). '
            . 'Não repita a transcrição literal; sintetize. Se algo não foi tratado, diga explicitamente.';
    }

    /**
     * Mensagem do usuário: contexto da reunião + transcrição (truncada ao limite).
     *
     * @param string      $transcript Transcrição com marcas [mm:ss].
     * @param string|null $title      Título/assunto da reunião, se houver.
     * @param string|null $dateLabel  Data legível da reunião, se houver.
     */
    public static function userPrompt(string $transcript, ?string $title = null, ?string $dateLabel = null): string
    {
        $header = 'Gere a ATA da reunião a seguir.';
        if ($title !== null && trim($title) !== '') {
            $header .= ' Assunto: ' . trim($title) . '.';
        }
        if ($dateLabel !== null && trim($dateLabel) !== '') {
            $header .= ' Data: ' . trim($dateLabel) . '.';
        }
        $body = mb_substr($transcript, 0, self::TRANSCRIPT_LIMIT);
        return $header . "\n\nTranscrição (formato [mm:ss] texto):\n\n" . $body;
    }

    /**
     * Monta o array de mensagens (role/content) para o endpoint de chat.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public static function buildMessages(string $transcript, ?string $title = null, ?string $dateLabel = null): array
    {
        return [
            ['role' => 'system', 'content' => self::systemPrompt()],
            ['role' => 'user', 'content' => self::userPrompt($transcript, $title, $dateLabel)],
        ];
    }

    /**
     * Converte a ata ESTRUTURADA (JSON com resumo/topicos/topicos_nao_resolvidos/
     * decisoes/proximos_passos) no TEXTO Markdown da minuta — SEM chamar IA.
     *
     * Isso unifica os dois formatos de ata que coexistiam: a tela estruturada lê
     * o JSON; a página pública/PDF e o envio por link usam este texto. Com uma
     * única geração de IA (o JSON), derivamos o texto aqui, economizando a 2ª
     * chamada ao modelo.
     *
     * Aceita o array já decodificado OU a string JSON. Retorna '' se não houver
     * conteúdo aproveitável.
     *
     * @param array|string|null $structured JSON (string) ou array decodificado.
     */
    public static function textFromStructured($structured): string
    {
        $data = is_string($structured) ? json_decode($structured, true) : $structured;
        if (!is_array($data)) return '';

        $resumo  = trim((string)($data['resumo'] ?? ''));
        $toArr   = function ($v) {
            return array_values(array_filter(array_map(
                fn($x) => trim((string)$x),
                is_array($v) ? $v : []
            ), fn($x) => $x !== ''));
        };
        $topicos    = $toArr($data['topicos'] ?? []);
        $naoResolv  = $toArr($data['topicos_nao_resolvidos'] ?? []);
        $decisoes   = $toArr($data['decisoes'] ?? []);
        $proximos   = $toArr($data['proximos_passos'] ?? []);
        $comercial  = [];
        foreach (self::COMMERCIAL_LABELS as $key => $label) {
            $items = $toArr($data[$key] ?? []);
            if ($items) $comercial[$label] = $items;
        }

        // Sem absolutamente nada aproveitável, não há texto.
        if ($resumo === '' && !$topicos && !$naoResolv && !$decisoes && !$proximos && !$comercial) return '';

        $bullets = function (array $items, string $vazio): string {
            if (!$items) return $vazio . "\n";
            return implode("\n", array_map(fn($i) => '- ' . $i, $items)) . "\n";
        };

        $out  = "## Resumo\n" . ($resumo !== '' ? $resumo : 'Sem resumo.') . "\n\n";
        $out .= "## Pontos discutidos\n" . $bullets($topicos, 'Nenhum ponto registrado.') . "\n";
        $out .= "## Decisões tomadas\n" . $bullets($decisoes, 'Nenhuma decisão registrada.') . "\n";
        $out .= "## Tópicos em aberto\n" . $bullets($naoResolv, 'Nenhum tópico em aberto.') . "\n";
        $out .= "## Próximos passos\n" . $bullets($proximos, 'Nenhum próximo passo identificado.');
        foreach ($comercial as $label => $items) {
            $out .= "\n## {$label}\n" . $bullets($items, '');
        }

        return self::sanitizeContent($out);
    }

    /**
     * Normaliza o conteúdo da minuta recebido para salvar: converte CRLF/CR em LF,
     * remove espaços à direita e limita o tamanho total (defesa). Retorna string
     * limpa (pode ser vazia — o chamador decide se vazio é aceitável).
     */
    public static function sanitizeContent(?string $content, int $maxLen = 200000): string
    {
        if (!is_string($content)) return '';
        $c = str_replace(["\r\n", "\r"], "\n", $content);
        $c = rtrim($c);
        if (mb_strlen($c) > $maxLen) {
            $c = mb_substr($c, 0, $maxLen);
        }
        return $c;
    }
}
