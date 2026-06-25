<?php

class Ticket
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT t.*, 
                    c.name as client_name, c.email as client_email, c.phone as client_phone,
                    a.name as attendant_name, a.email as attendant_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             LEFT JOIN users a ON t.attendant_id = a.id
             WHERE t.id = ?",
            [$id]
        );
    }

    public function getByClient($clientId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, a.name as attendant_name
             FROM tickets t
             LEFT JOIN users a ON t.attendant_id = a.id
             WHERE t.client_id = ?
             ORDER BY t.updated_at DESC",
            [$clientId]
        );
    }

    public function getByAttendant($attendantId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, c.name as client_name, c.email as client_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE t.attendant_id = ?
             ORDER BY t.updated_at DESC",
            [$attendantId]
        );
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT t.*, c.name as client_name, a.name as attendant_name
                FROM tickets t
                LEFT JOIN users c ON t.client_id = c.id
                LEFT JOIN users a ON t.attendant_id = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['attendant_id'])) {
            $sql .= " AND t.attendant_id = ?";
            $params[] = $filters['attendant_id'];
        }

        $sql .= " ORDER BY t.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($attendantId = null)
    {
        $statuses = ['open', 'in_progress', 'waiting_client', 'completed', 'denied', 'archived'];
        $result = [];
        foreach ($statuses as $status) {
            $sql = "SELECT t.*, c.name as client_name
                    FROM tickets t
                    LEFT JOIN users c ON t.client_id = c.id
                    WHERE t.status = ?";
            $params = [$status];
            if ($attendantId) {
                $sql .= " AND (t.attendant_id = ? OR t.attendant_id IS NULL)";
                $params[] = $attendantId;
            }
            $sql .= " ORDER BY t.updated_at DESC";
            $result[$status] = $this->db->fetchAll($sql, $params);
        }
        return $result;
    }

    public function create($data)
    {
        return $this->db->insert('tickets', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('tickets', $data, 'id = ?', [$id]);
    }

    public function updateStatus($id, $status)
    {
        $data = ['status' => $status];
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        return $this->db->update('tickets', $data, 'id = ?', [$id]);
    }

    public function assignAttendant($ticketId, $attendantId)
    {
        return $this->db->update('tickets', ['attendant_id' => $attendantId, 'status' => 'in_progress'], 'id = ?', [$ticketId]);
    }

    public function countByStatus($userId = null, $role = null)
    {
        $sql = "SELECT status, COUNT(*) as total FROM tickets WHERE 1=1";
        $params = [];
        if ($userId && $role === 'client') {
            $sql .= " AND client_id = ?";
            $params[] = $userId;
        } elseif ($userId && $role === 'attendant') {
            $sql .= " AND (attendant_id = ? OR attendant_id IS NULL)";
            $params[] = $userId;
        }
        $sql .= " GROUP BY status";
        $rows = $this->db->fetchAll($sql, $params);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = $row['total'];
        }
        return $counts;
    }
}
