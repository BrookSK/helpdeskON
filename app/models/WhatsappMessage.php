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

    // --- Métricas de performance comercial ---

    /**
     * Conta mensagens enviadas e recebidas por responsável do contato em um período.
     * Retorna: [user_id => ['sent' => X, 'received' => Y, 'contacts_messaged' => Z], ...]
     */
    public function getMessageStatsByUser($startDate = null, $endDate = null, $userId = null)
    {
        $sql = "SELECT c.assigned_to,
                       SUM(CASE WHEN m.from_me = 1 THEN 1 ELSE 0 END) AS sent,
                       SUM(CASE WHEN m.from_me = 0 THEN 1 ELSE 0 END) AS received,
                       COUNT(DISTINCT m.contact_id) AS contacts_messaged
                FROM whatsapp_messages m
                JOIN whatsapp_contacts c ON m.contact_id = c.id
                WHERE c.assigned_to IS NOT NULL AND COALESCE(c.is_group, 0) = 0";
        $params = [];

        if ($startDate) {
            $sql .= " AND m.timestamp >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $sql .= " AND m.timestamp <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        if ($userId) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY c.assigned_to";
        $rows = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($rows as $r) {
            $result[$r['assigned_to']] = [
                'sent' => (int)$r['sent'],
                'received' => (int)$r['received'],
                'contacts_messaged' => (int)$r['contacts_messaged'],
            ];
        }
        return $result;
    }

    /**
     * Contatos que o responsável chamou (enviou pelo menos 1 mensagem) vs não responderam.
     * Retorna: [user_id => ['contacted' => X, 'no_reply' => Y], ...]
     */
    public function getContactResponseStats($startDate = null, $endDate = null, $userId = null)
    {
        // Contatos que o responsável enviou mensagem (from_me = 1)
        $sqlContacted = "SELECT c.assigned_to, COUNT(DISTINCT m.contact_id) AS contacted
                         FROM whatsapp_messages m
                         JOIN whatsapp_contacts c ON m.contact_id = c.id
                         WHERE c.assigned_to IS NOT NULL AND COALESCE(c.is_group, 0) = 0 AND m.from_me = 1";
        $params = [];
        if ($startDate) { $sqlContacted .= " AND m.timestamp >= ?"; $params[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlContacted .= " AND m.timestamp <= ?"; $params[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlContacted .= " AND c.assigned_to = ?"; $params[] = $userId; }
        $sqlContacted .= " GROUP BY c.assigned_to";
        $contactedRows = $this->db->fetchAll($sqlContacted, $params);

        // Contatos que responderam (from_me = 0 com pelo menos 1 from_me = 1 no período)
        $sqlReplied = "SELECT c.assigned_to, COUNT(DISTINCT m.contact_id) AS replied
                       FROM whatsapp_messages m
                       JOIN whatsapp_contacts c ON m.contact_id = c.id
                       WHERE c.assigned_to IS NOT NULL AND COALESCE(c.is_group, 0) = 0 AND m.from_me = 0
                         AND m.contact_id IN (
                             SELECT DISTINCT m2.contact_id FROM whatsapp_messages m2
                             JOIN whatsapp_contacts c2 ON m2.contact_id = c2.id
                             WHERE m2.from_me = 1 AND c2.assigned_to IS NOT NULL";
        $params2 = [];
        if ($startDate) { $sqlReplied .= " AND m2.timestamp >= ?"; $params2[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlReplied .= " AND m2.timestamp <= ?"; $params2[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlReplied .= " AND c2.assigned_to = ?"; $params2[] = $userId; }
        $sqlReplied .= ")";
        if ($startDate) { $sqlReplied .= " AND m.timestamp >= ?"; $params2[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlReplied .= " AND m.timestamp <= ?"; $params2[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlReplied .= " AND c.assigned_to = ?"; $params2[] = $userId; }
        $sqlReplied .= " GROUP BY c.assigned_to";
        $repliedRows = $this->db->fetchAll($sqlReplied, $params2);

        // Monta resultado
        $contacted = [];
        foreach ($contactedRows as $r) $contacted[$r['assigned_to']] = (int)$r['contacted'];
        $replied = [];
        foreach ($repliedRows as $r) $replied[$r['assigned_to']] = (int)$r['replied'];

        $result = [];
        foreach ($contacted as $uid => $cnt) {
            $rep = $replied[$uid] ?? 0;
            $result[$uid] = [
                'contacted' => $cnt,
                'replied' => $rep,
                'no_reply' => $cnt - $rep,
            ];
        }
        return $result;
    }
}
