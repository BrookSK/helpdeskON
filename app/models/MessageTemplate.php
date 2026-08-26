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
     */
    public static function render($text, $contact = [], $company = null)
    {
        $name = $contact['contact_name'] ?? ($contact['push_name'] ?? ($contact['name'] ?? ''));
        $first = trim(explode(' ', trim($name))[0] ?? '');
        return strtr((string) $text, [
            '{{nome}}' => $name,
            '{{primeiro_nome}}' => $first,
            '{{email}}' => $contact['lead_email'] ?? ($contact['email'] ?? ''),
            '{{empresa}}' => $company ?? ($contact['company'] ?? ''),
        ]);
    }
}
