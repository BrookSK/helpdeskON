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
                    a.name as attendant_name, a.email as attendant_email,
                    tr.name as technical_name, tr.email as technical_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             LEFT JOIN users a ON t.attendant_id = a.id
             LEFT JOIN users tr ON t.technical_responsible_id = tr.id
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

    public function getByCompany($companyId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, a.name as attendant_name, c.name as client_name
             FROM tickets t
             LEFT JOIN users a ON t.attendant_id = a.id
             LEFT JOIN users c ON t.client_id = c.id
             WHERE c.company_id = ?
             ORDER BY t.updated_at DESC",
            [$companyId]
        );
    }

    public function getByAttendant($attendantId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, c.name as client_name, c.email as client_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE t.attendant_id = ? OR t.technical_responsible_id = ?
             ORDER BY t.updated_at DESC",
            [$attendantId, $attendantId]
        );
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT t.*, c.name as client_name, a.name as attendant_name, tr.name as technical_name
                FROM tickets t
                LEFT JOIN users c ON t.client_id = c.id
                LEFT JOIN users a ON t.attendant_id = a.id
                LEFT JOIN users tr ON t.technical_responsible_id = tr.id
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
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND t.status NOT IN ('completed', 'archived')";
        }
        if (!empty($filters['allowed_companies'])) {
            $ids = $filters['allowed_companies'];
            if (count($ids) === 1 && $ids[0] == 0) {
                $sql .= " AND c.company_id IS NULL";
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND (c.company_id IS NULL OR c.company_id IN ($placeholders))";
                $params = array_merge($params, $ids);
            }
        }

        $sql .= " ORDER BY t.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($attendantId = null, $allowedCompanies = null)
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];
        foreach ($statuses as $status) {
            $sql = "SELECT t.*, c.name as client_name, tr.name as technical_name
                    FROM tickets t
                    LEFT JOIN users c ON t.client_id = c.id
                    LEFT JOIN users tr ON t.technical_responsible_id = tr.id
                    WHERE t.status = ?";
            $params = [$status];
            if ($attendantId) {
                $sql .= " AND (t.attendant_id = ? OR t.technical_responsible_id = ? OR t.attendant_id IS NULL)";
                $params[] = $attendantId;
                $params[] = $attendantId;
            }
            if ($allowedCompanies !== null) {
                if (count($allowedCompanies) === 1 && $allowedCompanies[0] == 0) {
                    $sql .= " AND c.company_id IS NULL";
                } else {
                    $placeholders = implode(',', array_fill(0, count($allowedCompanies), '?'));
                    $sql .= " AND (c.company_id IS NULL OR c.company_id IN ($placeholders))";
                    $params = array_merge($params, $allowedCompanies);
                }
            }
            $sql .= " ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.updated_at DESC";
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

    public function assignTechnical($ticketId, $technicalId)
    {
        return $this->db->update('tickets', ['technical_responsible_id' => $technicalId ?: null], 'id = ?', [$ticketId]);
    }

    /**
     * Tickets em que o usuário é atendente OU responsável técnico, agrupados por status.
     * Usado no kanban para que devs/analistas/atendentes vejam suas atividades.
     */
    public function getGroupedByAssignee($userId)
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];
        foreach ($statuses as $status) {
            $result[$status] = $this->db->fetchAll(
                "SELECT t.*, c.name as client_name, tr.name as technical_name, a.name as attendant_name
                 FROM tickets t
                 LEFT JOIN users c ON t.client_id = c.id
                 LEFT JOIN users tr ON t.technical_responsible_id = tr.id
                 LEFT JOIN users a ON t.attendant_id = a.id
                 WHERE t.status = ? AND (t.attendant_id = ? OR t.technical_responsible_id = ?)
                 ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.updated_at DESC",
                [$status, $userId, $userId]
            );
        }
        return $result;
    }

    /**
     * Contagem por status de todos os tickets de uma empresa (usado no painel do responsável).
     */
    public function countByCompany($companyId)
    {
        $rows = $this->db->fetchAll(
            "SELECT t.status, COUNT(*) as total
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE c.company_id = ?
             GROUP BY t.status",
            [$companyId]
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = $row['total'];
        }
        return $counts;
    }

    public function countByStatus($userId = null, $role = null)
    {
        $sql = "SELECT status, COUNT(*) as total FROM tickets WHERE 1=1";
        $params = [];
        if ($userId && $role === 'client') {
            $sql .= " AND client_id = ?";
            $params[] = $userId;
        } elseif ($userId && $role === 'attendant') {
            $sql .= " AND (attendant_id = ? OR technical_responsible_id = ? OR attendant_id IS NULL)";
            $params[] = $userId;
            $params[] = $userId;
        } elseif ($userId && in_array($role, ['developer', 'analyst'])) {
            $sql .= " AND (attendant_id = ? OR technical_responsible_id = ?)";
            $params[] = $userId;
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
