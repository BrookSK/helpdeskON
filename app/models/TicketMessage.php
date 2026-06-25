<?php

class TicketMessage
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByTicket($ticketId)
    {
        return $this->db->fetchAll(
            "SELECT m.*, u.name as user_name, u.role as user_role, u.avatar
             FROM ticket_messages m
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.ticket_id = ?
             ORDER BY m.created_at ASC",
            [$ticketId]
        );
    }

    public function create($data)
    {
        return $this->db->insert('ticket_messages', $data);
    }

    public function markAsRead($ticketId, $userId)
    {
        return $this->db->query(
            "UPDATE ticket_messages SET is_read = 1 WHERE ticket_id = ? AND user_id != ?",
            [$ticketId, $userId]
        );
    }

    public function getUnreadCount($userId)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM ticket_messages m
             INNER JOIN tickets t ON m.ticket_id = t.id
             WHERE m.is_read = 0 AND m.user_id != ?
             AND (t.client_id = ? OR t.attendant_id = ?)",
            [$userId, $userId, $userId]
        );
        return $result['total'] ?? 0;
    }
}
