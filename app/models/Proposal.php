<?php

/**
 * Proposta/orçamento comercial (Fase 3). Model fino sobre Database.
 *
 * Mantém a proposta, seus itens e o histórico de eventos. O total é sempre
 * recalculado a partir dos itens (fonte: ProposalRules), nunca confiando num
 * valor solto do formulário.
 */
class Proposal
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
            "SELECT p.*, wc.contact_name AS crm_contact_name, u.name AS created_by_name
             FROM proposals p
             LEFT JOIN whatsapp_contacts wc ON p.contact_id = wc.id
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function findByToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') return null;
        return $this->db->fetch("SELECT * FROM proposals WHERE public_token = ? LIMIT 1", [$token]);
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT p.*, wc.contact_name AS crm_contact_name, u.name AS created_by_name
                FROM proposals p
                LEFT JOIN whatsapp_contacts wc ON p.contact_id = wc.id
                LEFT JOIN users u ON p.created_by = u.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND p.status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['contact_id'])) { $sql .= " AND p.contact_id = ?"; $params[] = (int)$filters['contact_id']; }
        $sql .= " ORDER BY p.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        if (empty($data['public_token'])) $data['public_token'] = $this->generateToken();
        return $this->db->insert('proposals', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('proposals', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('proposals', 'id = ?', [$id]);
    }

    // ================= Itens =================

    public function getItems($proposalId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM proposal_items WHERE proposal_id = ? ORDER BY position ASC, id ASC",
            [$proposalId]
        );
    }

    public function addItem($data)
    {
        return $this->db->insert('proposal_items', $data);
    }

    public function clearItems($proposalId)
    {
        return $this->db->delete('proposal_items', 'proposal_id = ?', [$proposalId]);
    }

    /**
     * Substitui todos os itens da proposta pelos informados e recalcula o total.
     * Cada item: description (obrigatório), scope, hours, hourly_rate, amount,
     * is_hosting, service_id. amount é recalculado por ProposalRules.
     *
     * @return float total recalculado
     */
    public function replaceItems($proposalId, array $items): float
    {
        $this->clearItems($proposalId);
        $pos = 0;
        foreach ($items as $it) {
            $desc = trim((string)($it['description'] ?? ''));
            if ($desc === '') continue; // ignora linhas vazias
            $amount = ProposalRules::itemAmount($it['hours'] ?? null, $it['hourly_rate'] ?? null, $it['amount'] ?? ($it['fixed_amount'] ?? null));
            $this->addItem([
                'proposal_id' => $proposalId,
                'service_id'  => !empty($it['service_id']) ? (int)$it['service_id'] : null,
                'description' => mb_substr($desc, 0, 255),
                'scope'       => isset($it['scope']) ? trim((string)$it['scope']) : null,
                'hours'       => ($it['hours'] ?? '') !== '' ? (float)$it['hours'] : null,
                'hourly_rate' => ($it['hourly_rate'] ?? '') !== '' ? (float)$it['hourly_rate'] : null,
                'amount'      => $amount,
                'is_hosting'  => !empty($it['is_hosting']) ? 1 : 0,
                'position'    => $pos++,
            ]);
        }
        $total = $this->recalcTotal($proposalId);
        return $total;
    }

    /** Recalcula e persiste o total da proposta a partir dos itens salvos. */
    public function recalcTotal($proposalId): float
    {
        $items = $this->getItems($proposalId);
        $total = ProposalRules::total($items);
        $this->update($proposalId, ['total' => $total]);
        return $total;
    }

    // ================= Eventos / histórico =================

    public function addEvent($proposalId, $userId, $type, $description = null)
    {
        return $this->db->insert('proposal_events', [
            'proposal_id' => $proposalId,
            'user_id'     => $userId,
            'event_type'  => $type,
            'description' => $description,
        ]);
    }

    public function getEvents($proposalId, $limit = 50)
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name
             FROM proposal_events e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.proposal_id = ?
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT " . (int)$limit,
            [$proposalId]
        );
    }

    /**
     * Muda o status registrando o evento. Valida a transição por ProposalRules.
     * @return bool true se mudou; false se a transição é inválida.
     */
    public function changeStatus($proposalId, $newStatus, $userId = null, $extra = []): bool
    {
        $p = $this->findById($proposalId);
        if (!$p) return false;
        $from = $p['status'];
        if (!ProposalRules::canTransition($from, $newStatus)) return false;

        $data = array_merge(['status' => $newStatus], $extra);
        $this->update($proposalId, $data);
        $this->addEvent($proposalId, $userId, 'status', "Status: {$from} -> {$newStatus}");
        return true;
    }
}
