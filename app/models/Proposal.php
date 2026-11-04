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
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
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

    // ================= Versões =================

    /** Todas as versões da proposta (R01, R02...), da mais antiga à vigente. */
    public function getRevisions(array $proposal): array
    {
        $root = (int)($proposal['root_proposal_id'] ?? 0) ?: (int)$proposal['id'];
        return $this->db->fetchAll(
            "SELECT id, revision_no, status, total, created_at, sent_at, responded_at, superseded_by
             FROM proposals WHERE root_proposal_id = ? OR id = ?
             ORDER BY revision_no ASC, id ASC",
            [$root, $root]
        );
    }

    /**
     * Cria a próxima versão em rascunho copiando a proposta, os itens e o texto
     * fixo gravado (fixed_snapshot). A anterior ganha superseded_by e fica só
     * para leitura. Devolve o id da nova versão, ou null se não cabe versão
     * (status que se edita na própria linha) ou se já foi substituída.
     */
    public function createRevision($proposalId, $userId = null): ?int
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $old = $this->db->fetch("SELECT * FROM proposals WHERE id = ? FOR UPDATE", [$proposalId]);
            if (!$old || !empty($old['superseded_by']) || !ProposalRules::requiresNewRevision($old['status'])) {
                $pdo->rollBack();
                return null;
            }
            $root = (int)($old['root_proposal_id'] ?: $old['id']);
            $max = $this->db->fetch("SELECT MAX(revision_no) AS n FROM proposals WHERE root_proposal_id = ? OR id = ?", [$root, $root]);
            $next = max((int)($max['n'] ?? 1), (int)$old['revision_no']) + 1;

            $row = $old;
            foreach (['id', 'created_at', 'updated_at', 'sent_at', 'responded_at', 'reject_reason', 'accepted_plan',
                      'accepted_name', 'accepted_ip', 'accepted_user_agent', 'accepted_revision', 'superseded_by'] as $k) {
                unset($row[$k]);
            }
            $row['public_token'] = $this->generateToken();
            $row['status'] = ProposalRules::STATUS_DRAFT;
            $row['root_proposal_id'] = $root;
            $row['revision_no'] = $next;
            $row['created_by'] = $userId ?: $old['created_by'];
            $newId = (int)$this->db->insert('proposals', $row);

            foreach ($this->getItems($old['id']) as $it) {
                unset($it['id'], $it['created_at']);
                $it['proposal_id'] = $newId;
                $this->db->insert('proposal_items', $it);
            }
            $this->update($old['id'], ['superseded_by' => $newId]);
            $label = ProposalRules::revisionLabel($next);
            $this->addEvent($old['id'], $userId, 'superseded', 'Substituída pela versão ' . $label . ' (#' . $newId . ')');
            $this->addEvent($newId, $userId, 'revision', 'Versão ' . $label . ' criada a partir da ' . ProposalRules::revisionLabel($old['revision_no']) . ' (#' . $old['id'] . ')');
            $pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
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
     * Cada item: description (obrigatório), scope, quantity, hours, hourly_rate,
     * amount, billing, is_hosting, service_id, schedule_start, schedule_end.
     * amount é recalculado por ProposalRules.
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
            $amount = ProposalRules::itemAmount(
                $it['hours'] ?? null,
                $it['hourly_rate'] ?? null,
                $it['amount'] ?? ($it['fixed_amount'] ?? null),
                $it['quantity'] ?? null
            );
            $this->addItem([
                'proposal_id'    => $proposalId,
                'service_id'     => !empty($it['service_id']) ? (int)$it['service_id'] : null,
                'description'    => mb_substr($desc, 0, 255),
                'scope'          => isset($it['scope']) ? trim((string)$it['scope']) : null,
                'quantity'       => ProposalRules::quantity($it['quantity'] ?? null),
                'hours'          => ($it['hours'] ?? '') !== '' ? (float)$it['hours'] : null,
                'hourly_rate'    => ($it['hourly_rate'] ?? '') !== '' ? (float)$it['hourly_rate'] : null,
                'amount'         => $amount,
                'billing'        => ProposalRules::normalizeBilling($it['billing'] ?? null),
                'renewal'        => ProposalRules::normalizeRenewal($it['renewal'] ?? null),
                'recurrence_period' => ProposalRules::normalizeRecurrence($it['recurrence_period'] ?? null),
                'is_hosting'     => !empty($it['is_hosting']) ? 1 : 0,
                'schedule_start' => ProposalRules::normalizeDate($it['schedule_start'] ?? null),
                'schedule_end'   => ProposalRules::normalizeDate($it['schedule_end'] ?? null),
                'position'       => $pos++,
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
