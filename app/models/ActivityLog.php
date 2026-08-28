<?php

/**
 * Consultas de auditoria: logins e ações de um usuário.
 */
class ActivityLog
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Logins de um usuário (mais recentes primeiro).
     */
    public function getLogins($userId, $limit = 100)
    {
        return $this->db->fetchAll(
            "SELECT h.*, imp.name as impersonator_name
             FROM user_login_history h
             LEFT JOIN users imp ON h.impersonated_by = imp.id
             WHERE h.user_id = ?
             ORDER BY h.created_at DESC
             LIMIT " . (int)$limit,
            [$userId]
        );
    }

    /**
     * Ações de um usuário (mais recentes primeiro), com paginação simples.
     */
    public function getActions($userId, $limit = 200, $offset = 0)
    {
        return $this->db->fetchAll(
            "SELECT * FROM user_activity_log
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT " . (int)$limit . " OFFSET " . (int)$offset,
            [$userId]
        );
    }

    public function countActions($userId)
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as total FROM user_activity_log WHERE user_id = ?",
            [$userId]
        );
        return (int)($row['total'] ?? 0);
    }

    public function countLogins($userId)
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as total FROM user_login_history WHERE user_id = ?",
            [$userId]
        );
        return (int)($row['total'] ?? 0);
    }

    public function lastLogin($userId)
    {
        return $this->db->fetch(
            "SELECT created_at, ip_address FROM user_login_history
             WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
    }
}
