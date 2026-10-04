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

    /**
     * Registra a publicação em produção. Grava published_at e registra evento.
     * Não altera a garantia — a garantia é calculada no markDelivered/aceite.
     */
    public function markPublished($id, $userId = null, ?string $publishedAt = null): bool
    {
        if (!$this->findById($id)) return false;
        $publishedAt = $publishedAt ?: date('Y-m-d H:i:s');
        $this->update($id, ['published_at' => $publishedAt]);
        $this->addEvent($id, $userId, 'published', 'Projeto publicado em produção');
        return true;
    }

    /** Registra a entrega da documentação/manual ao cliente. */
    public function markDocumentation($id, ?string $manualUrl, $userId = null): bool
    {
        if (!$this->findById($id)) return false;
        $manualUrl = $manualUrl !== null ? (trim($manualUrl) ?: null) : null;
        $this->update($id, [
            'manual_url' => $manualUrl,
            'documentation_delivered_at' => date('Y-m-d H:i:s'),
        ]);
        $this->addEvent($id, $userId, 'documentation',
            'Documentação/manual entregue ao cliente' . ($manualUrl ? (' — ' . $manualUrl) : ''));
        return true;
    }

    /** Vincula uma reunião de entrega (agenda_meetings) ao projeto. */
    public function linkDeliveryMeeting($id, int $meetingId, ?string $meetingAt, $userId = null): bool
    {
        if (!$this->findById($id)) return false;
        $this->update($id, [
            'delivery_meeting_id' => $meetingId,
            'delivery_meeting_at' => $meetingAt,
        ]);
        $this->addEvent($id, $userId, 'delivery_meeting_linked',
            'Reunião de entrega vinculada' . ($meetingAt ? (' — ' . $meetingAt) : ''));
        return true;
    }

    /** Gera e grava um token para o link público de aceite formal. */
    public function setAcceptanceToken($id, $userId = null): ?string
    {
        if (!$this->findById($id)) return null;
        $token = bin2hex(random_bytes(16));
        $this->update($id, ['acceptance_token' => $token]);
        $this->addEvent($id, $userId, 'acceptance_link', 'Link de aceite gerado e enviado ao cliente');
        return $token;
    }

    /** Busca um projeto pelo token de aceite (link público). */
    public function findByAcceptanceToken($token)
    {
        $token = trim((string) $token);
        if ($token === '') return null;
        return $this->db->fetch(
            "SELECT p.*, c.name AS company_name
             FROM projects p
             LEFT JOIN companies c ON p.company_id = c.id
             WHERE p.acceptance_token = ? LIMIT 1",
            [$token]
        );
    }

    /**
     * Registra o aceite formal do cliente e inicia a garantia a partir desta
     * data (o Guia define que a entrega/entrada em produção marca o início).
     * Reusa a mesma lógica de cálculo de garantia do markDelivered.
     */
    public function registerClientAcceptance($id, int $acceptedByUserId): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $now = date('Y-m-d H:i:s');
        $warrantyEnds = ProjectRules::warrantyEndDate($p['contract_type'], $now, (int)$p['warranty_days']);
        $status = ($warrantyEnds && ProjectRules::warrantyActive($warrantyEnds, $now))
            ? ProjectRules::STATUS_WARRANTY
            : ProjectRules::STATUS_DELIVERED;
        $this->update($id, [
            'client_accepted_at' => $now,
            'client_accepted_by' => $acceptedByUserId,
            'status' => $status,
            'delivered_at' => $p['delivered_at'] ?: $now,
            'warranty_ends_at' => $warrantyEnds,
        ]);
        $this->addEvent($id, $acceptedByUserId, 'client_accepted',
            'Cliente aceitou formalmente a entrega' . ($warrantyEnds ? (' — garantia até ' . $warrantyEnds) : ''));
        return true;
    }

    /** Marca que o aviso de 15 dias antes do fim da garantia foi enviado. */
    public function markWarrantyWarnSent($id): bool
    {
        if (!$this->findById($id)) return false;
        $this->update($id, ['warranty_warn_sent_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    /**
     * Projetos com garantia próxima do fim (<= N dias), ainda em garantia e que
     * ainda não receberam o aviso. Usado pelo cron warrantyWarnings.
     */
    public function getWarrantyEndingSoon(int $withinDays = 15): array
    {
        $now = date('Y-m-d H:i:s');
        $limit = date('Y-m-d H:i:s', strtotime("+{$withinDays} days"));
        return $this->db->fetchAll(
            "SELECT p.*, c.name AS company_name
             FROM projects p
             LEFT JOIN companies c ON p.company_id = c.id
             WHERE p.status = 'warranty'
               AND p.warranty_ends_at IS NOT NULL
               AND p.warranty_ends_at >= ?
               AND p.warranty_ends_at <= ?
               AND p.warranty_warn_sent_at IS NULL",
            [$now, $limit]
        );
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
