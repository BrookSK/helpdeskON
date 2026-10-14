<?php

/**
 * Onboarding do cliente (Fase 6). Model fino sobre Database.
 * Reaproveita OnboardingRules para etapas padrão, conclusão e bloqueios.
 */
class Onboarding
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT o.*, u.name AS tech_responsible_name
             FROM onboardings o
             LEFT JOIN users u ON o.tech_responsible_id = u.id
             WHERE o.id = ?",
            [$id]
        );
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT * FROM onboardings WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND status = ?"; $params[] = $filters['status']; }
        $sql .= " ORDER BY id DESC";
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        return $this->db->insert('onboardings', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('onboardings', $data, 'id = ?', [$id]);
    }

    /**
     * Cria o onboarding a partir de um projeto financeiro + contrato, já com as
     * etapas padrão. Status inicial 'blocked' (destrava só com entrada paga).
     */
    public function createFromProject(array $project, ?array $contract, $userId): int
    {
        $id = $this->create([
            'project_id'  => $project['id'] ?? null,
            'contract_id' => $contract['id'] ?? ($project['contract_id'] ?? null),
            'company_id'  => $project['company_id'] ?? null,
            'contact_id'  => $project['contact_id'] ?? null,
            'title'       => 'Onboarding — ' . ($project['title'] ?? 'Projeto'),
            'status'      => OnboardingRules::STATUS_BLOCKED,
            'created_by'  => $userId,
        ]);
        $pos = 0;
        foreach (OnboardingRules::defaultSteps() as $s) {
            $this->db->insert('onboarding_steps', [
                'onboarding_id' => $id,
                'step_key' => $s['step_key'],
                'title' => $s['title'],
                'required' => $s['required'],
                'status' => 'pending',
                'position' => $pos++,
            ]);
        }
        return $id;
    }

    /**
     * Define o tipo do projeto (zero/esteira/manutencao/outro) e o pipeline
     * (esteira_cx/fora_esteira). Se o pipeline não vier, é derivado do tipo.
     * Registra evento. Retorna o pipeline aplicado (ou null se tipo inválido).
     */
    public function setPipeline($onboardingId, ?string $projectType, ?string $pipeline, $userId = null): ?string
    {
        $type = OnboardingRules::normalizeProjectType($projectType);
        if ($type === null) return null;
        $pipe = OnboardingRules::normalizePipeline($pipeline) ?? OnboardingRules::pipelineFromProjectType($type);
        $this->update($onboardingId, ['project_type' => $type, 'pipeline_mode' => $pipe]);
        $this->addEvent($onboardingId, $userId, 'pipeline_set', 'Projeto definido: ' . $type . ' (' . $pipe . ')');
        return $pipe;
    }

    // ================= Etapas =================

    public function getSteps($onboardingId)
    {
        return $this->db->fetchAll(
            "SELECT s.*, u.name AS responsible_name
             FROM onboarding_steps s
             LEFT JOIN users u ON s.responsible_id = u.id
             WHERE s.onboarding_id = ? ORDER BY s.position ASC, s.id ASC",
            [$onboardingId]
        );
    }

    public function findStep($id)
    {
        return $this->db->fetch("SELECT * FROM onboarding_steps WHERE id = ?", [$id]);
    }

    public function updateStep($id, $data)
    {
        return $this->db->update('onboarding_steps', $data, 'id = ?', [$id]);
    }

    /**
     * Marca uma etapa como concluída, validando o requisito (OnboardingRules).
     * @return bool false se a etapa obrigatória não pode ser concluída.
     */
    public function completeStep($stepId, bool $requirementMet, $userId = null): bool
    {
        $step = $this->findStep($stepId);
        if (!$step) return false;
        if (!OnboardingRules::canCompleteStep($step, $requirementMet)) return false;
        $this->updateStep($stepId, ['status' => 'done', 'done_at' => date('Y-m-d H:i:s'), 'blocked_reason' => null]);
        $this->addEvent((int)$step['onboarding_id'], $userId, 'step_done', 'Etapa concluída: ' . ($step['title'] ?? $step['step_key']));
        return true;
    }

    // ================= Status do onboarding =================

    /**
     * Inicia o onboarding se a entrada estiver paga (entryPaid). Retorna false
     * se ainda bloqueado.
     */
    public function start($onboardingId, ?bool $entryPaid, $userId = null): bool
    {
        if (!OnboardingRules::canStart($entryPaid)) return false;
        $o = $this->findById($onboardingId);
        if (!$o || $o['status'] !== OnboardingRules::STATUS_BLOCKED) return $o && $o['status'] === OnboardingRules::STATUS_IN_PROGRESS;
        $this->update($onboardingId, ['status' => OnboardingRules::STATUS_IN_PROGRESS]);
        $this->addEvent($onboardingId, $userId, 'started', 'Onboarding iniciado (entrada paga)');
        return true;
    }

    /** Conclui o onboarding se todas as etapas obrigatórias estiverem done. */
    public function finish($onboardingId, $userId = null): bool
    {
        $steps = $this->getSteps($onboardingId);
        if (!OnboardingRules::canFinish($steps)) return false;
        $this->update($onboardingId, ['status' => OnboardingRules::STATUS_DONE]);
        $this->addEvent($onboardingId, $userId, 'finished', 'Onboarding concluído');
        return true;
    }

    public function pendingRequired($onboardingId): array
    {
        return OnboardingRules::pendingRequired($this->getSteps($onboardingId));
    }

    // ================= Pontos focais =================

    public function getContacts($onboardingId, $companyId = null)
    {
        if ($companyId) {
            return $this->db->fetchAll(
                "SELECT * FROM client_contacts WHERE onboarding_id = ? OR company_id = ? ORDER BY is_primary DESC, name",
                [$onboardingId, $companyId]
            );
        }
        return $this->db->fetchAll("SELECT * FROM client_contacts WHERE onboarding_id = ? ORDER BY is_primary DESC, name", [$onboardingId]);
    }

    public function addContact($data)
    {
        return $this->db->insert('client_contacts', $data);
    }

    // ================= Eventos =================

    public function addEvent($onboardingId, $userId, $type, $description = null)
    {
        return $this->db->insert('onboarding_events', [
            'onboarding_id' => $onboardingId,
            'user_id' => $userId,
            'event_type' => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($onboardingId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM onboarding_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.onboarding_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$onboardingId]
        );
    }
}
