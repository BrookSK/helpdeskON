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

    public function clearCharges($projectId)
    {
        return $this->db->delete('finance_charges', 'project_id = ?', [$projectId]);
    }

    /**
     * Substitui o plano de cobranças do projeto pelo plano calculado
     * (FinanceRules::buildChargePlan), associando a conta Asaas por finalidade.
     *
     * @param array $plan itens de buildChargePlan
     * @param array $accountByKind ['entry'=>id,'installment'=>id,'recurring'=>id]
     */
    public function replaceCharges($projectId, array $plan, array $accountByKind = []): void
    {
        $this->clearCharges($projectId);
        foreach ($plan as $c) {
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
    }

    /**
     * Marca uma cobrança como paga e, se for a entrada (ou destravar pela regra),
     * atualiza o projeto para 'entry_paid'. Retorna true se o projeto destravou.
     */
    public function markChargePaid($chargeId): bool
    {
        $charge = $this->findChargeById($chargeId);
        if (!$charge) return false;
        $this->db->update('finance_charges', ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')], 'id = ?', [$chargeId]);

        $projectId = (int)$charge['project_id'];
        $charges = $this->getCharges($projectId);
        if (FinanceRules::entryIsPaid($charges)) {
            $proj = $this->findById($projectId);
            if ($proj && $proj['status'] === 'open') {
                $this->update($projectId, ['status' => 'entry_paid', 'entry_paid_at' => date('Y-m-d H:i:s')]);
            }
            return true;
        }
        return false;
    }

    /** O onboarding pode começar? (entrada paga). */
    public function canStartOnboarding($projectId): bool
    {
        return FinanceRules::entryIsPaid($this->getCharges($projectId));
    }
}
