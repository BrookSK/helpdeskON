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
                       co.name as company_name,
                       cb.name as created_by_name,
                       cb.role as created_by_role
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN users cb ON pc.created_by = cb.id
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

        $sql .= " ORDER BY FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.due_date IS NULL, pc.due_date ASC, pc.position ASC, pc.id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($filters = [])
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];

        foreach ($statuses as $status) {
            $sql = "SELECT pc.*, 
                           u.name as assigned_name,
                           co.name as company_name,
                           cb.name as created_by_name
                    FROM planning_cards pc
                    LEFT JOIN users u ON pc.assigned_to = u.id
                    LEFT JOIN companies co ON pc.company_id = co.id
                    LEFT JOIN users cb ON pc.created_by = cb.id
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

            $sql .= " ORDER BY ";
            if (!empty($filters['order']) && $filters['order'] === 'overdue') {
                // Vencidos primeiro (prazo no passado), ordenados do mais antigo
                $sql .= "CASE WHEN pc.due_date IS NOT NULL AND pc.due_date < NOW() THEN 0 ELSE 1 END, pc.due_date ASC, FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.position ASC, pc.id ASC";
            } elseif (!empty($filters['order']) && $filters['order'] === 'priority') {
                $sql .= "FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.due_date IS NULL, pc.due_date ASC, pc.position ASC, pc.id ASC";
            } elseif (!empty($filters['order']) && $filters['order'] === 'newest') {
                $sql .= "pc.id DESC";
            } else {
                $sql .= "FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.due_date IS NULL, pc.due_date ASC, pc.position ASC, pc.id ASC";
            }
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

    /**
     * Cards em atraso: prazo (due_date) no passado e não concluídos/arquivados.
     */
    public function getOverdue($limit = 10)
    {
        return $this->db->fetchAll(
            "SELECT pc.*, u.name as assigned_name, co.name as company_name, t.id as ticket_ref
             FROM planning_cards pc
             LEFT JOIN users u ON pc.assigned_to = u.id
             LEFT JOIN companies co ON pc.company_id = co.id
             LEFT JOIN tickets t ON pc.ticket_id = t.id
             WHERE pc.due_date IS NOT NULL
               AND pc.due_date < NOW()
               AND pc.status NOT IN ('completed', 'archived')
             ORDER BY pc.due_date ASC
             LIMIT " . intval($limit)
        );
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

    /**
     * Reordena todos os cards de uma coluna (status) com posições sequenciais.
     * Recebe os IDs na ordem desejada e atribui position = 0, 1, 2, ...
     */
    public function reorderCards($ids, $status)
    {
        foreach ($ids as $position => $id) {
            $this->db->update('planning_cards', [
                'position' => $position,
                'status' => $status,
            ], 'id = ?', [$id]);
        }
    }

    public function delete($id)
    {
        return $this->db->delete('planning_cards', 'id = ?', [$id]);
    }

    /**
     * Exclui permanentemente o card e, se houver, a demanda (ticket) vinculada
     * junto com suas mensagens, anexos e notas internas.
     */
    public function deletePermanent($id, $ticketId = null)
    {
        // Remover o card de planejamento (comentários/anexos saem via cascade)
        $this->db->delete('planning_cards', 'id = ?', [$id]);

        if ($ticketId) {
            // Remover dependências do ticket antes do próprio ticket
            $this->db->delete('ticket_messages', 'ticket_id = ?', [$ticketId]);
            $this->db->delete('ticket_attachments', 'ticket_id = ?', [$ticketId]);
            $this->db->query("DELETE FROM ticket_internal_notes WHERE ticket_id = ?", [$ticketId]);
            $this->db->query("DELETE FROM notifications WHERE ticket_id = ?", [$ticketId]);
            $this->db->delete('tickets', 'id = ?', [$ticketId]);
        }

        return true;
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

        // Usuário marcado para "sempre ver todas as empresas" — sem restrição
        $u = $db->fetch("SELECT see_all_companies FROM users WHERE id = ?", [$userId]);
        if (!empty($u['see_all_companies'])) {
            return null;
        }

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

    // ========================
    // TASKS INTERNAS DO CARD
    // ========================

    public function getTasks($cardId)
    {
        $tasks = $this->db->fetchAll(
            "SELECT pt.*, u.name as created_by_name, cu.name as completed_by_name
             FROM planning_tasks pt
             LEFT JOIN users u ON pt.created_by = u.id
             LEFT JOIN users cu ON pt.completed_by = cu.id
             WHERE pt.card_id = ?
             ORDER BY pt.position ASC, pt.created_at ASC",
            [$cardId]
        );

        // Carregar imagens de cada task
        foreach ($tasks as &$task) {
            $task['images'] = $this->getTaskImages($task['id']);
        }

        return $tasks;
    }

    public function findTask($taskId)
    {
        return $this->db->fetch("SELECT * FROM planning_tasks WHERE id = ?", [$taskId]);
    }

    public function createTask($data)
    {
        return $this->db->insert('planning_tasks', $data);
    }

    public function updateTask($taskId, $data)
    {
        return $this->db->update('planning_tasks', $data, 'id = ?', [$taskId]);
    }

    public function toggleTaskComplete($taskId, $userId)
    {
        $task = $this->findTask($taskId);
        if (!$task) return false;

        if ($task['is_completed']) {
            // Desmarcar
            return $this->db->update('planning_tasks', [
                'is_completed' => 0,
                'completed_by' => null,
                'completed_at' => null,
            ], 'id = ?', [$taskId]);
        } else {
            // Marcar como concluída
            return $this->db->update('planning_tasks', [
                'is_completed' => 1,
                'completed_by' => $userId,
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
        }
    }

    public function deleteTask($taskId)
    {
        return $this->db->delete('planning_tasks', 'id = ?', [$taskId]);
    }

    // Imagens das tasks
    public function getTaskImages($taskId)
    {
        return $this->db->fetchAll(
            "SELECT pti.*, u.name as user_name
             FROM planning_task_images pti
             LEFT JOIN users u ON pti.user_id = u.id
             WHERE pti.task_id = ?
             ORDER BY pti.created_at ASC",
            [$taskId]
        );
    }

    public function addTaskImage($data)
    {
        return $this->db->insert('planning_task_images', $data);
    }

    public function findTaskImage($imageId)
    {
        return $this->db->fetch("SELECT * FROM planning_task_images WHERE id = ?", [$imageId]);
    }

    public function deleteTaskImage($imageId)
    {
        return $this->db->delete('planning_task_images', 'id = ?', [$imageId]);
    }

    public function getTaskCountForCard($cardId)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total, SUM(is_completed) as completed FROM planning_tasks WHERE card_id = ?",
            [$cardId]
        );
        return [
            'total' => (int)($result['total'] ?? 0),
            'completed' => (int)($result['completed'] ?? 0),
        ];
    }

    /**
     * Busca TODOS os cards de planejamento vinculados à empresa do cliente.
     * Busca por múltiplos caminhos:
     * 1. Cards com company_id = empresa do cliente
     * 2. Cards vinculados a tickets criados por usuários da empresa
     * 3. Cards criados por usuários da empresa
     * Retorna apenas campos não-sensíveis.
     */
    public function getAllForClientCompany($companyId, $filters = [])
    {
        $sql = "SELECT DISTINCT pc.id, pc.title, pc.status, pc.priority, co.name as company_name
                FROM planning_cards pc
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN tickets t ON pc.ticket_id = t.id
                LEFT JOIN users tc ON t.client_id = tc.id
                LEFT JOIN users cb ON pc.created_by = cb.id
                WHERE (
                    pc.company_id = ?
                    OR tc.company_id = ?
                    OR (cb.company_id = ? AND cb.role = 'client')
                )";
        $params = [$companyId, $companyId, $companyId];

        if (!empty($filters['status'])) {
            $sql .= " AND pc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND pc.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }

        $sql .= " ORDER BY FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.id DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
