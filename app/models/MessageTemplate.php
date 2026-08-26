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
     * Substitui as variáveis do template pelos dados do contato.
     * $contact: array com contact_name/push_name, lead_email; $company opcional.
     * As variáveis extras (cargo, cidade, empresa, setor, linkedin) são lidas do
     * briefing comercial (campo notes/need) quando disponíveis.
     */
    public static function render($text, $contact = [], $company = null)
    {
        if (strpos((string) $text, '{{') === false) return (string) $text;

        $name = $contact['contact_name'] ?? ($contact['push_name'] ?? ($contact['name'] ?? ''));
        $first = trim(explode(' ', trim($name))[0] ?? '');
        $email = $contact['lead_email'] ?? ($contact['email'] ?? '');
        $phone = $contact['phone'] ?? '';

        // Campos comerciais extras: tenta do próprio array; senão, do briefing pelo contact_id
        $extra = self::extraFields($contact);

        return strtr((string) $text, [
            '{{nome}}' => $name,
            '{{primeiro_nome}}' => $first,
            '{{email}}' => $email,
            '{{telefone}}' => $phone,
            '{{empresa}}' => $company ?? ($contact['company'] ?? $extra['empresa']),
            '{{cargo}}' => $extra['cargo'],
            '{{cidade}}' => $extra['cidade'],
            '{{estado}}' => $extra['estado'],
            '{{setor}}' => $extra['setor'],
            '{{linkedin}}' => $extra['linkedin'],
        ]);
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
