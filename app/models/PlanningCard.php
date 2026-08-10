<?php

class PlanningCard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT pc.*, 
                    u.name as assigned_name,
                    tr.name as technical_name,
                    an.name as analyst_name,
                    cb.name as created_by_name,
                    co.name as company_name,
                    t.title as ticket_title
             FROM planning_cards pc
             LEFT JOIN users u ON pc.assigned_to = u.id
             LEFT JOIN users tr ON pc.technical_responsible_id = tr.id
             LEFT JOIN users an ON pc.analyst_id = an.id
             LEFT JOIN users cb ON pc.created_by = cb.id
             LEFT JOIN companies co ON pc.company_id = co.id
             LEFT JOIN tickets t ON pc.ticket_id = t.id
             WHERE pc.id = ?",
            [$id]
        );
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT pc.*, 
                       u.name as assigned_name,
                       co.name as company_name
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND pc.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['company_id'])) {
            $sql .= " AND pc.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND pc.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }
        if (!empty($filters['allowed_companies'])) {
            $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        $sql .= " ORDER BY pc.position ASC, pc.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($filters = [])
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];

        foreach ($statuses as $status) {
            $sql = "SELECT pc.*, 
                           u.name as assigned_name,
                           co.name as company_name
                    FROM planning_cards pc
                    LEFT JOIN users u ON pc.assigned_to = u.id
                    LEFT JOIN companies co ON pc.company_id = co.id
                    WHERE pc.status = ?";
            $params = [$status];

            if (!empty($filters['company_id'])) {
                $sql .= " AND pc.company_id = ?";
                $params[] = $filters['company_id'];
            }
            if (!empty($filters['assigned_to'])) {
                $sql .= " AND pc.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }
            if (!empty($filters['allowed_companies'])) {
                $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
                $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
                $params = array_merge($params, $filters['allowed_companies']);
            }

            $sql .= " ORDER BY pc.position ASC, pc.updated_at DESC";
            $result[$status] = $this->db->fetchAll($sql, $params);
        }

        return $result;
    }

    public function getForCalendar($startDate, $endDate, $filters = [])
    {
        // Buscar cards que tenham qualquer data dentro do range:
        // - due_date (prazo/entrega) dentro do range
        // - start_date/end_date (range desenvolvimento) que intersecte o range
        $sql = "SELECT pc.*, 
                       u.name as assigned_name,
                       co.name as company_name
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                WHERE (
                    (pc.due_date IS NOT NULL AND pc.due_date >= ? AND pc.due_date <= ?)
                    OR (pc.start_date IS NOT NULL AND pc.end_date IS NOT NULL AND pc.start_date <= ? AND pc.end_date >= ?)
                    OR (pc.start_date IS NOT NULL AND pc.end_date IS NULL AND pc.start_date >= ? AND pc.start_date <= ?)
                )";
        $params = [$startDate, $endDate, $endDate, $startDate, $startDate, $endDate];

        if (!empty($filters['company_id'])) {
            $sql .= " AND pc.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND pc.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }
        if (!empty($filters['allowed_companies'])) {
            $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        $sql .= " ORDER BY pc.start_date ASC, pc.due_date ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('planning_cards', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('planning_cards', $data, 'id = ?', [$id]);
    }

    public function updateStatus($id, $status)
    {
        return $this->db->update('planning_cards', ['status' => $status], 'id = ?', [$id]);
    }

    public function updatePosition($id, $position, $status = null)
    {
        $data = ['position' => $position];
        if ($status) {
            $data['status'] = $status;
        }
        return $this->db->update('planning_cards', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('planning_cards', 'id = ?', [$id]);
    }

    // Criar card automaticamente a partir de um ticket
    public function createFromTicket($ticket)
    {
        $clientUser = $this->db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);

        return $this->create([
            'ticket_id' => $ticket['id'],
            'title' => $ticket['title'],
            'description' => '<p>' . nl2br(htmlspecialchars($ticket['description'])) . '</p>',
            'company_id' => $clientUser['company_id'] ?? null,
            'assigned_to' => $ticket['attendant_id'] ?? null,
            'technical_responsible_id' => $ticket['technical_responsible_id'] ?? null,
            'created_by' => $ticket['client_id'],
            'priority' => $ticket['priority'],
            'status' => $ticket['status'] ?? 'open',
            'position' => 0,
        ]);
    }

    // Sincronizar status do card quando ticket muda
    public function syncFromTicket($ticketId, $status)
    {
        $card = $this->db->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$ticketId]);
        if ($card) {
            $this->updateStatus($card['id'], $status);
        }
    }

    // Comentários
    public function getComments($cardId)
    {
        return $this->db->fetchAll(
            "SELECT pc.*, u.name as user_name, u.avatar as user_avatar
             FROM planning_comments pc
             LEFT JOIN users u ON pc.user_id = u.id
             WHERE pc.card_id = ?
             ORDER BY pc.created_at ASC",
            [$cardId]
        );
    }

    public function addComment($cardId, $userId, $message)
    {
        return $this->db->insert('planning_comments', [
            'card_id' => $cardId,
            'user_id' => $userId,
            'message' => $message,
        ]);
    }

    // Anexos
    public function getAttachments($cardId)
    {
        return $this->db->fetchAll(
            "SELECT pa.*, u.name as user_name
             FROM planning_attachments pa
             LEFT JOIN users u ON pa.user_id = u.id
             WHERE pa.card_id = ?
             ORDER BY pa.created_at DESC",
            [$cardId]
        );
    }

    public function addAttachment($data)
    {
        return $this->db->insert('planning_attachments', $data);
    }

    public function deleteAttachment($id)
    {
        return $this->db->delete('planning_attachments', 'id = ?', [$id]);
    }

    public function findAttachment($id)
    {
        return $this->db->fetch("SELECT * FROM planning_attachments WHERE id = ?", [$id]);
    }

    // Obter empresas que o usuário tem acesso
    public static function getUserAllowedCompanies($userId, $role)
    {
        // Super admin tem acesso a tudo
        if ($role === 'super_admin') {
            return null; // null = sem restrição
        }

        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT company_id FROM user_company_access WHERE user_id = ?", [$userId]);

        if (empty($rows)) {
            // Se não tem nenhum registro, não tem acesso a nenhuma empresa específica
            // mas pode ver cards sem empresa
            return [0]; // retorna 0 para filtrar apenas cards sem company
        }

        return array_column($rows, 'company_id');
    }

    // Gerenciar acesso do usuário a empresas
    public static function setUserCompanyAccess($userId, $companyIds)
    {
        $db = Database::getInstance();
        // Remover acessos antigos
        $db->delete('user_company_access', 'user_id = ?', [$userId]);
        // Inserir novos
        foreach ($companyIds as $companyId) {
            if ($companyId) {
                $db->insert('user_company_access', [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                ]);
            }
        }
    }

    public static function getUserCompanyAccessIds($userId)
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT company_id FROM user_company_access WHERE user_id = ?", [$userId]);
        return array_column($rows, 'company_id');
    }
}
