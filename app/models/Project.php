<?php

/**
 * Projeto entregue + garantia (Fase 9). Model fino sobre Database.
 * Reaproveita ProjectRules para status, cálculo de garantia e bloqueio de
 * chamados pós-garantia.
 */
class Project
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, c.name AS company_name
             FROM projects p
             LEFT JOIN companies c ON p.company_id = c.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT p.*, c.name AS company_name
                FROM projects p
                LEFT JOIN companies c ON p.company_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND p.status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['company_id'])) { $sql .= " AND p.company_id = ?"; $params[] = (int)$filters['company_id']; }
        $sql .= " ORDER BY p.id DESC";
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
    }

    public function getByCompany($companyId)
    {
        return $this->db->fetchAll("SELECT * FROM projects WHERE company_id = ? ORDER BY id DESC", [$companyId]);
    }

    public function create($data)
    {
        $data['contract_type'] = ProjectRules::normalizeContractType($data['contract_type'] ?? 'outro');
        $data['status'] = ProjectRules::normalizeStatus($data['status'] ?? ProjectRules::STATUS_PLANNING);
        if (!isset($data['warranty_days'])) $data['warranty_days'] = ProjectRules::DEFAULT_WARRANTY_DAYS;
        return $this->db->insert('projects', $data);
    }

    public function update($id, $data)
    {
        if (isset($data['contract_type'])) $data['contract_type'] = ProjectRules::normalizeContractType($data['contract_type']);
        if (isset($data['status'])) $data['status'] = ProjectRules::normalizeStatus($data['status']);
        return $this->db->update('projects', $data, 'id = ?', [$id]);
    }

    /**
     * Marca o projeto como entregue: calcula o fim da garantia (se o tipo tem
     * garantia) e muda o status para 'delivered' (ou 'warranty' se houver janela).
     */
    public function markDelivered($id, $userId = null, ?string $deliveredAt = null): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $deliveredAt = $deliveredAt ?: date('Y-m-d H:i:s');
        $warrantyEnds = ProjectRules::warrantyEndDate($p['contract_type'], $deliveredAt, (int)$p['warranty_days']);
        $status = ($warrantyEnds && ProjectRules::warrantyActive($warrantyEnds, $deliveredAt))
            ? ProjectRules::STATUS_WARRANTY
            : ProjectRules::STATUS_DELIVERED;
        $this->update($id, [
            'status' => $status,
            'delivered_at' => $deliveredAt,
            'warranty_ends_at' => $warrantyEnds,
        ]);
        $this->addEvent($id, $userId, 'delivered', 'Projeto entregue' . ($warrantyEnds ? (' — garantia até ' . $warrantyEnds) : ' (sem garantia)'));
        return true;
    }

    public function setSupportContract($id, bool $active, $userId = null): bool
    {
        if (!$this->findById($id)) return false;
        $this->update($id, ['support_contract' => $active ? 1 : 0]);
        $this->addEvent($id, $userId, 'support', $active ? 'Contrato de suporte ativado' : 'Contrato de suporte desativado');
        return true;
    }

    /** O cliente pode abrir chamado para este projeto? (ProjectRules). */
    public function canOpenTicket($id): bool
    {
        $p = $this->findById($id);
        return $p ? ProjectRules::canOpenTicket($p) : false;
    }

    // ================= Eventos =================

    public function addEvent($projectId, $userId, $type, $description = null)
    {
        return $this->db->insert('project_events', [
            'project_id' => $projectId,
            'user_id' => $userId,
            'event_type' => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($projectId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM project_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.project_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$projectId]
        );
    }
}
