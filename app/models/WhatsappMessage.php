<?php

class WhatsappMessage
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM whatsapp_messages WHERE id = ?", [$id]);
    }

    /**
     * Buscar mensagens de um contato (paginado)
     */
    public function getByContact($contactId, $limit = 50, $beforeId = null)
    {
        $sql = "SELECT * FROM whatsapp_messages WHERE contact_id = ?";
        $params = [$contactId];

        if ($beforeId) {
            $sql .= " AND id < ?";
            $params[] = $beforeId;
        }

        $sql .= " ORDER BY timestamp DESC LIMIT " . intval($limit);

        $messages = $this->db->fetchAll($sql, $params);
        return array_reverse($messages); // Retorna na ordem cronológica
    }

    /**
     * Buscar mensagens por JID
     */
    public function getByJid($instanceId, $remoteJid, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT * FROM whatsapp_messages 
             WHERE instance_id = ? AND remote_jid = ? 
             ORDER BY timestamp DESC LIMIT ?",
            [$instanceId, $remoteJid, $limit]
        );
    }

    /**
     * Salvar mensagem recebida (de webhook ou enviada)
     */
    public function create($data)
    {
        // Evitar duplicatas pelo message_id
        if (!empty($data['message_id'])) {
            $existing = $this->db->fetch(
                "SELECT id FROM whatsapp_messages WHERE instance_id = ? AND message_id = ?",
                [$data['instance_id'], $data['message_id']]
            );
            if ($existing) return $existing['id'];
        }

        return $this->db->insert('whatsapp_messages', $data);
    }

    /**
     * Buscar novas mensagens após um ID (polling)
     */
    public function getNewMessages($contactId, $afterId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM whatsapp_messages 
             WHERE contact_id = ? AND id > ?
             ORDER BY timestamp ASC",
            [$contactId, $afterId]
        );
    }

    /**
     * Marcar mensagens como lidas
     */
    public function markAsRead($contactId)
    {
        $this->db->query(
            "UPDATE whatsapp_messages SET is_read = 1 WHERE contact_id = ? AND from_me = 0 AND is_read = 0",
            [$contactId]
        );
    }

    /**
     * Conta total de mensagens de um contato
     */
    public function countByContact($contactId)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM whatsapp_messages WHERE contact_id = ?",
            [$contactId]
        );
        return $result['total'] ?? 0;
    }
}
