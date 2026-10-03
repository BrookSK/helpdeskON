<?php

/**
 * Contrato comercial (Fase 4). Model fino sobre Database.
 * Mantém o contrato, seu histórico e as transições de status (via ContractRules).
 */
class Contract
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
            "SELECT c.*, p.title AS proposal_title, u.name AS created_by_name
             FROM contracts c
             LEFT JOIN proposals p ON c.proposal_id = p.id
             LEFT JOIN users u ON c.created_by = u.id
             WHERE c.id = ?",
            [$id]
        );
    }

    public function findByToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        return $this->db->fetch("SELECT * FROM contracts WHERE public_token = ? LIMIT 1", [$token]);
    }

    public function findByClickSignDocKey($key)
    {
        $key = trim((string)$key);
        if ($key === '') return null;
        return $this->db->fetch("SELECT * FROM contracts WHERE clicksign_doc_key = ? LIMIT 1", [$key]);
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT c.*, u.name AS created_by_name
                FROM contracts c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND c.status = ?"; $params[] = $filters['status']; }
        $sql .= " ORDER BY c.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        if (empty($data['public_token'])) $data['public_token'] = $this->generateToken();
        return $this->db->insert('contracts', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('contracts', $data, 'id = ?', [$id]);
    }

    // ================= Eventos =================

    public function addEvent($contractId, $userId, $type, $description = null)
    {
        return $this->db->insert('contract_events', [
            'contract_id' => $contractId,
            'user_id' => $userId,
            'event_type' => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($contractId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM contract_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.contract_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$contractId]
        );
    }

    /**
     * Muda o status validando a transição (ContractRules) e registra evento.
     * @return bool true se mudou; false se transição inválida.
     */
    public function changeStatus($contractId, $newStatus, $userId = null, $extra = []): bool
    {
        $c = $this->findById($contractId);
        if (!$c) return false;
        $from = $c['status'];
        if (!ContractRules::canTransition($from, $newStatus)) return false;

        $this->update($contractId, array_merge(['status' => $newStatus], $extra));
        $this->addEvent($contractId, $userId, 'status', "Status: {$from} -> {$newStatus}");
        return true;
    }

    /**
     * Cria um contrato a partir de uma proposta aceita, aplicando o corpo do
     * modelo escolhido. Copia os dados do cliente da proposta.
     */
    public function createFromProposal(array $proposal, ?array $template, $userId): int
    {
        $body = $template['body'] ?? '';
        return $this->create([
            'public_token' => $this->generateToken(),
            'title'        => 'Contrato — ' . ($proposal['title'] ?? 'Proposta'),
            'proposal_id'  => $proposal['id'] ?? null,
            'template_id'  => $template['id'] ?? null,
            'contact_id'   => $proposal['contact_id'] ?? null,
            'company_id'   => $proposal['company_id'] ?? null,
            'client_name'  => $proposal['client_name'] ?? null,
            'client_email' => $proposal['client_email'] ?? null,
            'client_phone' => $proposal['client_phone'] ?? null,
            'body'         => $body,
            'status'       => ContractRules::STATUS_DRAFT,
            'created_by'   => $userId,
        ]);
    }
}
