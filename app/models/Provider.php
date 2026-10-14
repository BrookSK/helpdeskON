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

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
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

    public function findByToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        return $this->db->fetch("SELECT * FROM providers WHERE public_token = ? LIMIT 1", [$token]);
    }

    public function findByClickSignDocKey($key)
    {
        $key = trim((string)$key);
        if ($key === '') return null;
        return $this->db->fetch("SELECT * FROM providers WHERE clicksign_doc_key = ? LIMIT 1", [$key]);
    }

    /** Garante um token público para o link da proposta (idempotente). */
    public function ensureToken($id): string
    {
        $p = $this->findById($id);
        if ($p && !empty($p['public_token'])) return $p['public_token'];
        $token = $this->generateToken();
        $this->db->update('providers', ['public_token' => $token], 'id = ?', [$id]);
        return $token;
    }

    // ================= Proposta por link =================

    /** Marca a proposta como enviada (carimba data) e avança o status p/ proposal. */
    public function markProposalSent($id, $userId = null): void
    {
        $this->db->update('providers', ['proposal_sent_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $p = $this->findById($id);
        if ($p && $p['status'] === ProviderRules::STATUS_PROSPECT) {
            $this->changeStatus($id, ProviderRules::STATUS_PROPOSAL, $userId);
        }
        $this->addEvent($id, $userId, 'proposal_sent', 'Proposta enviada ao prestador');
    }

    /** Prestador aceitou a proposta (via link público). Avança p/ contract. */
    public function acceptProposal($id): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $this->db->update('providers', [
            'proposal_accepted_at' => date('Y-m-d H:i:s'),
            'proposal_responded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        if ($p['status'] === ProviderRules::STATUS_PROPOSAL) {
            $this->changeStatus($id, ProviderRules::STATUS_CONTRACT, null);
        }
        $this->addEvent($id, null, 'proposal_accepted', 'Prestador aceitou a proposta');
        return true;
    }

    /** Prestador recusou a proposta (motivo obrigatório). Volta p/ prospect. */
    public function rejectProposal($id, string $reason): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $this->db->update('providers', [
            'proposal_reject_reason' => ProviderRules::sanitizeReason($reason),
            'proposal_responded_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        if ($p['status'] === ProviderRules::STATUS_PROPOSAL) {
            $this->changeStatus($id, ProviderRules::STATUS_PROSPECT, null);
        }
        $this->addEvent($id, null, 'proposal_rejected', 'Prestador recusou. Motivo: ' . ProviderRules::sanitizeReason($reason));
        return true;
    }

    // ================= Assinatura (ClickSign) =================

    public function setSignatureKeys($id, string $docKey, ?string $requestKey): void
    {
        $this->db->update('providers', [
            'clicksign_doc_key' => $docKey,
            'clicksign_request_key' => $requestKey,
        ], 'id = ?', [$id]);
    }

    /** Marca o contrato do prestador como assinado e ativa o prestador. */
    public function markSigned($id): bool
    {
        $p = $this->findById($id);
        if (!$p) return false;
        $this->db->update('providers', ['signed_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        if ($p['status'] === ProviderRules::STATUS_CONTRACT) {
            $this->changeStatus($id, ProviderRules::STATUS_ACTIVE, null);
        }
        $this->addEvent($id, null, 'signed', 'Contrato do prestador assinado');
        return true;
    }

    // ================= Revisão de valor =================

    public function getRevisions($providerId)
    {
        return $this->db->fetchAll(
            "SELECT r.*, ur.name AS requested_by_name, ua.name AS reviewed_by_name
             FROM provider_revisions r
             LEFT JOIN users ur ON r.requested_by = ur.id
             LEFT JOIN users ua ON r.reviewed_by = ua.id
             WHERE r.provider_id = ? ORDER BY r.id DESC",
            [$providerId]
        );
    }

    public function findRevision($id)
    {
        return $this->db->fetch("SELECT * FROM provider_revisions WHERE id = ?", [$id]);
    }

    public function pendingRevision($providerId)
    {
        return $this->db->fetch(
            "SELECT * FROM provider_revisions WHERE provider_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$providerId]
        );
    }

    public function createRevision($providerId, array $data): int
    {
        $p = $this->findById($providerId);
        $id = $this->db->insert('provider_revisions', [
            'provider_id'   => $providerId,
            'old_pay_type'  => $p['pay_type'] ?? null,
            'old_pay_amount' => $p['pay_amount'] ?? null,
            'new_pay_type'  => ProviderRules::normalizePayType($data['new_pay_type'] ?? null),
            'new_pay_amount' => isset($data['new_pay_amount']) && $data['new_pay_amount'] !== '' ? $data['new_pay_amount'] : null,
            'reason'        => trim((string)($data['reason'] ?? '')) ?: null,
            'status'        => ProviderRevisionRules::STATUS_PENDING,
            'requested_by'  => $data['requested_by'] ?? null,
        ]);
        $this->addEvent($providerId, $data['requested_by'] ?? null, 'revision_requested', 'Revisão de valor solicitada (aguarda aprovação do gestor)');
        return $id;
    }

    /** Aprova a revisão: aplica o novo valor/tipo ao prestador. */
    public function approveRevision($revisionId, $userId, ?string $notes = null): bool
    {
        $rev = $this->findRevision($revisionId);
        if (!$rev || !ProviderRevisionRules::canReview($rev['status'])) return false;
        $this->db->update('provider_revisions', [
            'status' => ProviderRevisionRules::STATUS_APPROVED,
            'reviewed_by' => $userId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ], 'id = ?', [$revisionId]);
        $upd = [];
        if (!empty($rev['new_pay_type'])) $upd['pay_type'] = $rev['new_pay_type'];
        if ($rev['new_pay_amount'] !== null) $upd['pay_amount'] = $rev['new_pay_amount'];
        if ($upd) $this->update((int)$rev['provider_id'], $upd);
        $this->addEvent((int)$rev['provider_id'], $userId, 'revision_approved', 'Revisão de valor aprovada pelo gestor');
        return true;
    }

    public function rejectRevision($revisionId, $userId, ?string $notes = null): bool
    {
        $rev = $this->findRevision($revisionId);
        if (!$rev || !ProviderRevisionRules::canReview($rev['status'])) return false;
        $this->db->update('provider_revisions', [
            'status' => ProviderRevisionRules::STATUS_REJECTED,
            'reviewed_by' => $userId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ], 'id = ?', [$revisionId]);
        $this->addEvent((int)$rev['provider_id'], $userId, 'revision_rejected', 'Revisão de valor recusada pelo gestor');
        return true;
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

    public function addDocument($providerId, string $label, ?string $filePath = null, ?string $notes = null, ?string $docType = null)
    {
        return $this->db->insert('provider_documents', [
            'provider_id' => $providerId,
            'doc_label' => $label,
            'doc_type' => $docType ?: null,
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
