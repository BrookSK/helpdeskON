<?php

/**
 * Prestador (Fase 8). Model fino sobre Database. Reaproveita ProviderRules para
 * estados, transições e a regra de encerramento (revogar todos os acessos).
 */
class Provider
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT p.*, u.name AS user_name, u.is_active AS user_active
             FROM providers p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT * FROM providers WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND status = ?"; $params[] = $filters['status']; }
        $sql .= " ORDER BY id DESC";
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        $data['engagement_type'] = ProviderRules::normalizeEngagement($data['engagement_type'] ?? 'pj');
        $data['work_model'] = ProviderRules::normalizeWorkModel($data['work_model'] ?? null);
        $data['pay_type'] = ProviderRules::normalizePayType($data['pay_type'] ?? null);
        $data['status'] = ProviderRules::normalizeStatus($data['status'] ?? ProviderRules::STATUS_PROSPECT);
        return $this->db->insert('providers', $data);
    }

    public function update($id, $data)
    {
        if (isset($data['engagement_type'])) $data['engagement_type'] = ProviderRules::normalizeEngagement($data['engagement_type']);
        if (array_key_exists('work_model', $data)) $data['work_model'] = ProviderRules::normalizeWorkModel($data['work_model']);
        if (array_key_exists('pay_type', $data)) $data['pay_type'] = ProviderRules::normalizePayType($data['pay_type']);
        return $this->db->update('providers', $data, 'id = ?', [$id]);
    }

    /**
     * Muda o status validando a transição (ProviderRules). Registra evento.
     * @return bool false se a transição não for permitida.
     */
    public function changeStatus($id, string $newStatus, $userId = null): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $newStatus = ProviderRules::normalizeStatus($newStatus);
        if (!ProviderRules::canTransition($p['status'], $newStatus)) return false;
        $this->update($id, ['status' => $newStatus]);
        $this->addEvent($id, $userId, 'status', 'Status: ' . $p['status'] . ' -> ' . $newStatus);
        return true;
    }

    /**
     * Encerra o prestador: só se ativo E com todos os acessos revogados. Grava
     * motivo e data. Desativa o usuário vinculado (acesso ao sistema).
     * @return bool false se não puder encerrar.
     */
    public function terminate($id, string $reason, $userId = null): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $accesses = $this->getAccesses($id);
        if (!ProviderRules::canTerminate($p['status'], $accesses)) return false;
        $this->update($id, [
            'status' => ProviderRules::STATUS_TERMINATED,
            'termination_reason' => ProviderRules::sanitizeReason($reason),
            'terminated_at' => date('Y-m-d H:i:s'),
        ]);
        // Desativa o acesso do usuário vinculado, se houver.
        if (!empty($p['user_id'])) {
            $this->db->update('users', ['is_active' => 0], 'id = ?', [(int)$p['user_id']]);
        }
        $this->addEvent($id, $userId, 'terminated', 'Prestador encerrado. Motivo: ' . ProviderRules::sanitizeReason($reason));
        return true;
    }

    public function pendingAccesses($id): array
    {
        return ProviderRules::pendingAccesses($this->getAccesses($id));
    }

    // ================= Acessos =================

    public function getAccesses($providerId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM provider_accesses WHERE provider_id = ? ORDER BY id ASC",
            [$providerId]
        );
    }

    public function addAccess($providerId, string $label, ?string $details = null, bool $granted = true)
    {
        return $this->db->insert('provider_accesses', [
            'provider_id' => $providerId,
            'access_label' => $label,
            'details' => $details,
            'granted_at' => $granted ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function revokeAccess($accessId, $userId = null): bool
    {
        $a = $this->db->fetch("SELECT * FROM provider_accesses WHERE id = ?", [$accessId]);
        if (!$a) return false;
        $this->db->update('provider_accesses', ['revoked_at' => date('Y-m-d H:i:s')], 'id = ?', [$accessId]);
        $this->addEvent((int)$a['provider_id'], $userId, 'access_revoked', 'Acesso revogado: ' . ($a['access_label'] ?? ''));
        return true;
    }

    /** Revoga TODOS os acessos ainda ativos de uma vez (encerramento no mesmo dia). */
    public function revokeAllAccesses($providerId, $userId = null): int
    {
        $n = 0;
        foreach ($this->getAccesses($providerId) as $a) {
            if (empty($a['revoked_at'])) {
                $this->db->update('provider_accesses', ['revoked_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$a['id']]);
                $n++;
            }
        }
        if ($n > 0) $this->addEvent($providerId, $userId, 'access_revoked_all', "Revogados {$n} acesso(s)");
        return $n;
    }

    // ================= Documentos =================

    public function getDocuments($providerId)
    {
        return $this->db->fetchAll("SELECT * FROM provider_documents WHERE provider_id = ? ORDER BY id DESC", [$providerId]);
    }

    public function addDocument($providerId, string $label, ?string $filePath = null, ?string $notes = null)
    {
        return $this->db->insert('provider_documents', [
            'provider_id' => $providerId,
            'doc_label' => $label,
            'file_path' => $filePath,
            'notes' => $notes,
        ]);
    }

    // ================= Eventos =================

    public function addEvent($providerId, $userId, $type, $description = null)
    {
        return $this->db->insert('provider_events', [
            'provider_id' => $providerId,
            'user_id' => $userId,
            'event_type' => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($providerId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM provider_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.provider_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$providerId]
        );
    }
}
