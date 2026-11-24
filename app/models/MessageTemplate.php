<?php

/**
 * Templates de mensagem (e-mail e WhatsApp) com variáveis.
 * Variáveis suportadas: {{nome}}, {{primeiro_nome}}, {{email}}, {{empresa}}.
 */
class MessageTemplate
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all($channel = null)
    {
        if ($channel) {
            return $this->db->fetchAll(
                "SELECT * FROM message_templates WHERE channel = ? ORDER BY name ASC",
                [$channel]
            );
        }
        return $this->db->fetchAll("SELECT * FROM message_templates ORDER BY channel, name ASC");
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM message_templates WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        return $this->db->insert('message_templates', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('message_templates', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('message_templates', 'id = ?', [$id]);
    }

    /**
     * Valida e normaliza os campos de um template vindos do formulário.
     * E-mail: corpo HTML formatado (limpo de script/eventos). WhatsApp/LinkedIn:
     * texto puro (se vier HTML colado, vira texto).
     * @return array dados prontos para gravar, ou ['error' => mensagem]
     */
    public static function normalizeInput(array $in): array
    {
        $channel = in_array($in['channel'] ?? '', ['email', 'whatsapp', 'linkedin'], true) ? $in['channel'] : 'email';
        $name = trim((string) ($in['name'] ?? ''));
        $body = (string) ($in['body'] ?? '');
        $subject = trim((string) ($in['subject'] ?? ''));

        if ($channel === 'email') {
            $body = EmailHtmlRules::isHtml($body) ? EmailHtmlRules::sanitize($body) : $body;
            $plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body)), ENT_QUOTES, 'UTF-8'));
        } else {
            if (EmailHtmlRules::isHtml($body)) {
                $body = preg_replace('#<br\s*/?>|</p>\s*#i', "\n", $body);
                $body = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
            }
            $body = trim($body);
            $plain = $body;
        }
        if ($name === '' || $plain === '') return ['error' => 'Informe o nome e o conteúdo do template.'];

        return [
            'channel' => $channel,
            'name' => mb_substr($name, 0, 150),
            'subject' => ($channel === 'email' && $subject !== '') ? mb_substr($subject, 0, 250) : null,
            'body' => $body,
        ];
    }

    /** Lead de exemplo para a pré-visualização dos templates. */
    public static function sampleContact(): array
    {
        return [
            'contact_name' => 'Maria Souza', 'lead_email' => 'maria@empresaexemplo.com.br', 'phone' => '(41) 99999-0000',
            'company' => 'Empresa Exemplo', 'job_title' => 'Diretora Comercial', 'linkedin_url' => 'https://www.linkedin.com/in/maria-souza',
        ];
    }

    /**
     * Substitui as variáveis do template pelos dados do contato.
     * $contact: array com contact_name/push_name, lead_email; $company opcional.
     * As variáveis extras (cargo, cidade, empresa, setor, linkedin) são lidas do
     * briefing comercial (campo notes/need) quando disponíveis.
     */
    public static function render($text, $contact = [], $company = null)
    {
        if (strpos((string) $text, '{{') === false) return (string) $text;
        return strtr((string) $text, self::variables($contact, $company));
    }

    /**
     * Igual ao render(), para corpo HTML: os valores do lead são escapados
     * (um nome com "<" ou "&" não quebra o HTML do e-mail).
     */
    public static function renderHtml($html, $contact = [], $company = null)
    {
        if (strpos((string) $html, '{{') === false) return (string) $html;
        $vars = array_map(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), self::variables($contact, $company));
        return strtr((string) $html, $vars);
    }

    /** Mapa {{variável}} => valor, a partir do contato. */
    private static function variables($contact = [], $company = null)
    {
        $name = $contact['contact_name'] ?? ($contact['push_name'] ?? ($contact['name'] ?? ''));
        $first = trim(explode(' ', trim($name))[0] ?? '');
        $email = $contact['lead_email'] ?? ($contact['email'] ?? '');
        $phone = $contact['phone'] ?? '';

        // Campos comerciais extras: tenta do próprio array; senão, do briefing pelo contact_id
        $extra = self::extraFields($contact);

        // Nome do remetente (pessoa que assina a prospecção). Ordem de preferência:
        // 1) contact['remetente_nome'] (quando o motor passa explicitamente);
        // 2) setting prospecting_sender_name; 3) smtp_from_name; 4) fallback.
        $sender = $contact['remetente_nome'] ?? self::senderName();

        // LinkedIn: prefere a coluna estruturada do contato (whatsapp_contacts.linkedin_url),
        // caindo para o valor do briefing quando ausente.
        $linkedin = !empty($contact['linkedin_url']) ? $contact['linkedin_url'] : $extra['linkedin'];

        return [
            '{{nome}}' => $name,
            '{{primeiro_nome}}' => $first,
            '{{email}}' => $email,
            '{{telefone}}' => $phone,
            '{{empresa}}' => $company ?? ($contact['company'] ?? (!empty($contact['company_name']) ? $contact['company_name'] : $extra['empresa'])),
            '{{cargo}}' => !empty($contact['job_title']) ? $contact['job_title'] : $extra['cargo'],
            '{{cidade}}' => $extra['cidade'],
            '{{estado}}' => $extra['estado'],
            '{{setor}}' => $extra['setor'],
            '{{linkedin}}' => $linkedin,
            '{{remetente_nome}}' => $sender,
        ];
    }

    /**
     * Nome do remetente (assinatura pessoal da prospecção), com cache estático.
     * settings.prospecting_sender_name > settings.smtp_from_name > fallback.
     */
    private static function senderName()
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $cached = 'ON Solutions Brasil';
        try {
            // Busca ambos e prioriza prospecting_sender_name.
            $rows = Database::getInstance()->fetchAll(
                "SELECT setting_key, setting_value FROM settings
                 WHERE setting_key IN ('prospecting_sender_name','smtp_from_name')"
            );
            $map = [];
            foreach ($rows as $row) $map[$row['setting_key']] = trim((string)$row['setting_value']);
            if (!empty($map['prospecting_sender_name'])) $cached = $map['prospecting_sender_name'];
            elseif (!empty($map['smtp_from_name'])) $cached = $map['smtp_from_name'];
        } catch (\Throwable $e) { /* usa fallback */ }
        return $cached;
    }

    /**
     * Extrai campos comerciais do briefing (gravados na importação Apollo/manual).
     * As notas do briefing seguem o padrão "Cargo: X | Empresa: Y | LinkedIn: Z".
     */
    private static function extraFields($contact)
    {
        $out = ['empresa' => '', 'cargo' => '', 'cidade' => '', 'estado' => '', 'setor' => '', 'linkedin' => ''];
        $contactId = $contact['id'] ?? ($contact['contact_id'] ?? null);
        if (!$contactId) return $out;

        try {
            $bf = Database::getInstance()->fetch(
                "SELECT need, notes FROM commercial_briefings WHERE contact_id = ?",
                [$contactId]
            );
            if ($bf) {
                $out['setor'] = $bf['need'] ?? '';
                $notes = (string) ($bf['notes'] ?? '');
                // Extrai "Rótulo: valor" das notas
                if (preg_match('/Cargo:\s*([^|]+)/i', $notes, $m)) $out['cargo'] = trim($m[1]);
                if (preg_match('/Empresa:\s*([^|]+)/i', $notes, $m)) $out['empresa'] = trim($m[1]);
                if (preg_match('/LinkedIn:\s*([^|]+)/i', $notes, $m)) $out['linkedin'] = trim($m[1]);
            }
        } catch (\Throwable $e) { /* silencioso */ }
        return $out;
    }
}
