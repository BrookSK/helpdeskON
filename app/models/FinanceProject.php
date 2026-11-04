<?php

/**
 * Projeto financeiro + cobranças (Fase 5). Model fino sobre Database.
 * O "projeto" nasce do contrato assinado e controla a entrada que destrava o
 * onboarding (via FinanceRules::entryIsPaid).
 */
class FinanceProject
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM finance_projects WHERE id = ?", [$id]);
    }

    /** Projeto financeiro vinculado a um contrato (idempotência da criação automática). */
    public function findByContract($contractId)
    {
        $contractId = (int)$contractId;
        if ($contractId <= 0) return null;
        return $this->db->fetch("SELECT * FROM finance_projects WHERE contract_id = ? ORDER BY id DESC LIMIT 1", [$contractId]);
    }

    public function getAll(array $filters = [])
    {
        $sql = "SELECT * FROM finance_projects WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['company_id'])) { $sql .= " AND company_id = ?"; $params[] = (int)$filters['company_id']; }
        $sql .= " ORDER BY id DESC";
        try { return $this->db->fetchAll($sql, $params); } catch (\Throwable $e) { return []; }
    }

    public function create($data)
    {
        return $this->db->insert('finance_projects', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('finance_projects', $data, 'id = ?', [$id]);
    }

    /** Cria um projeto financeiro a partir de um contrato assinado. */
    public function createFromContract(array $contract, float $total, $userId): int
    {
        return $this->create([
            'contract_id' => $contract['id'] ?? null,
            'proposal_id' => $contract['proposal_id'] ?? null,
            'company_id'  => $contract['company_id'] ?? null,
            'contact_id'  => $contract['contact_id'] ?? null,
            'title'       => 'Financeiro — ' . ($contract['title'] ?? 'Contrato'),
            'total_value' => round($total, 2),
            'status'      => 'open',
            'created_by'  => $userId,
        ]);
    }

    // ================= Cobranças =================

    public function getCharges($projectId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM finance_charges WHERE project_id = ? ORDER BY id ASC",
            [$projectId]
        );
    }

    public function findChargeById($id)
    {
        return $this->db->fetch("SELECT * FROM finance_charges WHERE id = ?", [$id]);
    }

    public function findChargeByAsaasId($asaasId)
    {
        $asaasId = trim((string)$asaasId);
        if ($asaasId === '') return null;
        return $this->db->fetch("SELECT * FROM finance_charges WHERE asaas_charge_id = ? LIMIT 1", [$asaasId]);
    }

    public function addCharge($data)
    {
        return $this->db->insert('finance_charges', $data);
    }

    /**
     * Grava o plano de cobranças (FinanceRules::buildChargePlan), associando a
     * conta Asaas por finalidade. Só as cobranças pendentes que nunca foram ao
     * Asaas são substituídas; pagas e emitidas ficam (FinanceRules::mergePlan).
     *
     * @param array $plan itens de buildChargePlan
     * @param array $accountByKind ['entry'=>id,'installment'=>id,'recurring'=>id]
     * @return array{kept:int,removed:int,added:int}
     */
    public function replaceCharges($projectId, array $plan, array $accountByKind = []): array
    {
        $merge = FinanceRules::mergePlan($this->getCharges($projectId), $plan);
        foreach ($merge['remove_ids'] as $id) {
            $this->db->query(
                "DELETE FROM finance_charges WHERE id = ? AND status = 'pending' AND asaas_charge_id IS NULL
                 AND asaas_subscription_id IS NULL AND issuing_at IS NULL",
                [$id]
            );
        }
        foreach ($merge['add'] as $c) {
            $kind = $c['kind'] ?? 'installment';
            $this->addCharge([
                'project_id' => $projectId,
                'account_id' => $accountByKind[$kind] ?? null,
                'kind' => $kind,
                'description' => $c['description'] ?? null,
                'amount' => round((float)($c['amount'] ?? 0), 2),
                'installment_no' => $c['installment_no'] ?? null,
                'installment_total' => $c['installment_total'] ?? null,
                'due_date' => $c['due_date'] ?? null,
                'method' => $c['method'] ?? null,
                'recurring_cycle' => $c['recurring_cycle'] ?? null,
                'recurring_start' => $c['recurring_start'] ?? null,
                'status' => 'pending',
            ]);
        }
        return ['kept' => count($merge['keep']), 'removed' => count($merge['remove_ids']), 'added' => count($merge['add'])];
    }

    /**
     * Marca uma cobrança como paga (uma vez só) e, se a entrada ficou paga,
     * passa o projeto para 'entry_paid'. Retorna true se o projeto destravou o
     * onboarding (entrada paga), mesmo que a cobrança já estivesse paga.
     */
    public function markChargePaid($chargeId, ?int $userId = null, string $origin = 'manual'): bool
    {
        $charge = $this->findChargeById($chargeId);
        if (!$charge) return false;
        $changed = $this->db->query(
            "UPDATE finance_charges SET status = 'paid', paid_at = NOW() WHERE id = ? AND status <> 'paid'",
            [$chargeId]
        )->rowCount();
        $projectId = (int)$charge['project_id'];
        if ($changed) {
            $how = $origin === 'asaas' ? 'baixa automática do Asaas' : 'marcada como paga manualmente';
            $this->addEvent($projectId, (int)$chargeId, $userId, 'paid',
                ($charge['description'] ?: 'Cobrança') . ' de R$ ' . number_format((float)$charge['amount'], 2, ',', '.') . ': ' . $how);
        }

        if (FinanceRules::entryIsPaid($this->getCharges($projectId))) {
            $proj = $this->findById($projectId);
            if ($proj && $proj['status'] === 'open') {
                $this->update($projectId, ['status' => 'entry_paid', 'entry_paid_at' => date('Y-m-d H:i:s')]);
                $this->addEvent($projectId, null, $userId, 'entry_paid', 'Entrada paga: onboarding liberado');
            }
            return true;
        }
        return false;
    }

    // ================= Eventos =================

    public function addEvent($projectId, $chargeId, $userId, string $type, ?string $description = null)
    {
        return $this->db->insert('finance_events', [
            'project_id' => $projectId, 'charge_id' => $chargeId, 'user_id' => $userId,
            'event_type' => $type, 'description' => $description !== null ? mb_substr($description, 0, 500) : null,
        ]);
    }

    public function getEvents($projectId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT e.*, u.name AS user_name FROM finance_events e LEFT JOIN users u ON e.user_id = u.id
             WHERE e.project_id = ? ORDER BY e.created_at DESC, e.id DESC LIMIT " . max(1, $limit),
            [$projectId]
        );
    }

    // ================= Plano sugerido =================

    /**
     * Sugestão do plano a partir da versão aceita da proposta (D4): pontual
     * líquido, mensalidade do plano aceito e entrada pelo percentual configurado
     * em finance_entry_percent (50% quando vazio). Null sem proposta.
     */
    public function suggestionForProposal($proposalId): ?array
    {
        $proposal = $proposalId ? (new Proposal())->findById((int)$proposalId) : null;
        if (!$proposal) return null;
        $items = (new Proposal())->getItems((int)$proposal['id']);
        $data = ProposalRules::buildPresentationData($proposal, $items);
        $monthly = (float)$data['commercial']['monthly']['net'];
        $months = null;
        foreach ($data['plan_options'] as $opt) {
            if ($opt['key'] === ($proposal['accepted_plan'] ?? null)) {
                $monthly = (float)$opt['monthly'];
                $months = (int)$opt['months'];
                break;
            }
        }
        $pct = class_exists('Config') ? Config::get('finance_entry_percent') : null;
        return FinanceRules::suggestFromProposal((float)$data['commercial']['once']['net'], $monthly, $pct, $proposal['dev_end'] ?? null, $months)
            + ['entry_percent_configured' => trim((string)$pct) !== ''];
    }

    /** O onboarding pode começar? (entrada paga). */
    public function canStartOnboarding($projectId): bool
    {
        return FinanceRules::entryIsPaid($this->getCharges($projectId));
    }
}
