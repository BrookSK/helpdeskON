<?php

/**
 * Provisionamento de infra (Fase 7). Model fino sobre Database.
 * Reaproveita ProvisioningRules para etapas padrão, conclusão e modo (auto/manual).
 */
class Provisioning
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
             FROM provisionings p
             LEFT JOIN companies c ON p.company_id = c.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT p.*, c.name AS company_name
                FROM provisionings p
                LEFT JOIN companies c ON p.company_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND p.status = ?"; $params[] = $filters['status']; }
        $sql .= " ORDER BY p.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('provisionings', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('provisionings', $data, 'id = ?', [$id]);
    }

    /**
     * Cria o provisionamento a partir de um onboarding, já com as etapas padrão.
     * O modo (auto/manual) de cada etapa vem de ProvisioningRules (capacidade
     * atual da API). Status inicial 'pending'.
     */
    public function createFromOnboarding(array $onboarding, $userId): int
    {
        $id = $this->create([
            'onboarding_id' => $onboarding['id'] ?? null,
            'project_id'    => $onboarding['project_id'] ?? null,
            'company_id'    => $onboarding['company_id'] ?? null,
            'pipeline_mode' => ProvisioningRules::PIPELINE_OUT,
            'status'        => ProvisioningRules::STATUS_PENDING,
            'created_by'    => $userId,
        ]);
        $pos = 0;
        foreach (ProvisioningRules::defaultSteps() as $s) {
            $this->db->insert('provisioning_steps', [
                'provisioning_id' => $id,
                'step_key' => $s['step_key'],
                'title' => $s['title'],
                'mode' => $s['mode'],
                'required' => $s['required'],
                'status' => 'pending',
                'position' => $pos++,
            ]);
        }
        $this->addEvent($id, $userId, 'created', 'Provisionamento criado a partir do onboarding');
        return $id;
    }

    // ================= Etapas =================

    public function getSteps($provisioningId)
    {
        return $this->db->fetchAll(
            "SELECT s.*, u.name AS responsible_name
             FROM provisioning_steps s
             LEFT JOIN users u ON s.responsible_id = u.id
             WHERE s.provisioning_id = ? ORDER BY s.position ASC, s.id ASC",
            [$provisioningId]
        );
    }

    public function findStep($id)
    {
        return $this->db->fetch("SELECT * FROM provisioning_steps WHERE id = ?", [$id]);
    }

    public function updateStep($id, $data)
    {
        return $this->db->update('provisioning_steps', $data, 'id = ?', [$id]);
    }

    /**
     * Conclui uma etapa, validando pela regra: etapa 'auto' exige apiOk=true;
     * 'manual' é concluída pelo responsável. Retorna false se não permitido.
     */
    public function completeStep($stepId, bool $apiOk = false, $userId = null, ?string $externalRef = null): bool
    {
        $step = $this->findStep($stepId);
        if (!$step) return false;
        if (!ProvisioningRules::canCompleteStep($step, $apiOk)) return false;
        $data = ['status' => 'done', 'done_at' => date('Y-m-d H:i:s'), 'blocked_reason' => null];
        if ($externalRef !== null) $data['external_ref'] = $externalRef;
        $this->updateStep($stepId, $data);
        $this->addEvent((int)$step['provisioning_id'], $userId, 'step_done', 'Etapa concluída: ' . ($step['title'] ?? $step['step_key']));
        return true;
    }

    /** Marca uma etapa como bloqueada por pendência manual (gap de API). */
    public function blockStep($stepId, string $reason, $userId = null): bool
    {
        $step = $this->findStep($stepId);
        if (!$step) return false;
        $this->updateStep($stepId, ['status' => 'blocked', 'blocked_reason' => $reason]);
        $this->addEvent((int)$step['provisioning_id'], $userId, 'step_blocked', ($step['title'] ?? $step['step_key']) . ': ' . $reason);
        return true;
    }

    public function findStepByKey($provisioningId, string $stepKey)
    {
        return $this->db->fetch(
            "SELECT * FROM provisioning_steps WHERE provisioning_id = ? AND step_key = ? LIMIT 1",
            [$provisioningId, $stepKey]
        );
    }

    // ================= Status do provisionamento =================

    public function start($provisioningId, $userId = null): bool
    {
        $p = $this->findById($provisioningId);
        if (!$p) return false;
        if ($p['status'] === ProvisioningRules::STATUS_PENDING) {
            $this->update($provisioningId, ['status' => ProvisioningRules::STATUS_IN_PROGRESS]);
            $this->addEvent($provisioningId, $userId, 'started', 'Provisionamento iniciado');
        }
        return true;
    }

    public function finish($provisioningId, $userId = null): bool
    {
        $steps = $this->getSteps($provisioningId);
        if (!ProvisioningRules::canFinish($steps)) return false;
        $this->update($provisioningId, ['status' => ProvisioningRules::STATUS_DONE]);
        $this->addEvent($provisioningId, $userId, 'finished', 'Provisionamento concluído');
        return true;
    }

    public function pendingRequired($provisioningId): array
    {
        return ProvisioningRules::pendingRequired($this->getSteps($provisioningId));
    }

    // ================= Eventos =================

    public function addEvent($provisioningId, $userId, $type, $description = null)
    {
        return $this->db->insert('provisioning_events', [
            'provisioning_id' => $provisioningId,
            'user_id' => $userId,
            'event_type' => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($provisioningId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM provisioning_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.provisioning_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$provisioningId]
        );
    }

    public function findByCompany($companyId)
    {
        return $this->db->fetch("SELECT * FROM provisionings WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
    }
}
