<?php

/**
 * Lançamentos do Controle Financeiro (super_admin).
 * Ganhos, gastos, salários, empréstimos, transferências + parcelas geradas.
 * Compras parceladas criam N linhas em fin_installments; pagar a transação
 * quita a próxima parcela pendente (quando todas quitam, marca como paga).
 */
class FinancialTransaction
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM fin_transactions WHERE id = ?", [$id]);
    }

    /**
     * Lista lançamentos (mais recentes primeiro). Filtros opcionais:
     * kind, status, month (YYYY-MM sobre due_date), source.
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT t.*, c.name AS card_name, a.name AS asaas_account_name
                FROM fin_transactions t
                LEFT JOIN fin_credit_cards c ON c.id = t.card_id
                LEFT JOIN finance_accounts a ON a.id = t.asaas_account_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['kind'])) {
            $sql .= " AND t.kind = ?";
            $params[] = $filters['kind'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['source'])) {
            $sql .= " AND t.source = ?";
            $params[] = $filters['source'];
        }
        if (!empty($filters['month']) && FinancialRules::validMonth($filters['month'])) {
            $sql .= " AND DATE_FORMAT(t.due_date, '%Y-%m') = ?";
            $params[] = $filters['month'];
        }
        $sql .= " ORDER BY t.due_date DESC, t.id DESC LIMIT 500";
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Progresso das parcelas: '2/5' e próxima vencendo. */
    public function installmentProgress(int $transactionId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT installment_no, installment_total, amount, due_date, status
                 FROM fin_installments WHERE transaction_id = ? ORDER BY installment_no",
                [$transactionId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Soma das parcelas pendentes por cartão (fatura prevista).
     * @return array<int,float> card_id => total pendente
     */
    public function pendingInstallmentsByCard(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT i.transaction_id, t.card_id, SUM(i.amount) AS total
                 FROM fin_installments i
                 JOIN fin_transactions t ON t.id = i.transaction_id
                 WHERE i.status = 'pending' AND t.card_id IS NOT NULL
                 GROUP BY t.card_id"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) $out[(int)$r['card_id']] = (float)$r['total'];
        return $out;
    }

    public function create(array $data)
    {
        $id = $this->db->insert('fin_transactions', $data);
        return (int)$id;
    }

    /** Cria o lançamento + parcelas (quando installments > 1) numa tacada. */
    public function createWithInstallments(array $data): int
    {
        $id = $this->create($data);
        if ((int)$data['installments'] > 1) {
            $plan = FinancialRules::buildInstallments((float)$data['amount'], (int)$data['installments'], (string)$data['due_date']);
            foreach ($plan as $p) {
                $this->db->insert('fin_installments', [
                    'transaction_id'  => $id,
                    'installment_no'  => $p['installment_no'],
                    'installment_total' => $p['installment_total'],
                    'amount'           => $p['amount'],
                    'due_date'         => $p['due_date'],
                    'status'           => 'pending',
                ]);
            }
        }
        return $id;
    }

    /**
     * Marca como pago. Com parcelas: quita a próxima pendente e só fecha a
     * transação quando a última é paga. Retorna true quando a transação ficou
     * totalmente paga.
     */
    public function markPaid(int $id): bool
    {
        $t = $this->findById($id);
        if (!$t) return false;
        $now = date('Y-m-d H:i:s');

        $pending = $this->db->fetch(
            "SELECT id FROM fin_installments WHERE transaction_id = ? AND status = 'pending' ORDER BY installment_no LIMIT 1",
            [$id]
        );
        if ($pending) {
            $this->db->update('fin_installments', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [(int)$pending['id']]);
            $left = $this->db->fetch(
                "SELECT COUNT(*) AS c FROM fin_installments WHERE transaction_id = ? AND status = 'pending'",
                [$id]
            );
            $done = (int)($left['c'] ?? 0) === 0;
            if ($done) {
                $this->db->update('fin_transactions', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [$id]);
            }
            return $done;
        }

        if ($t['status'] !== 'paid') {
            $this->db->update('fin_transactions', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [$id]);
        }
        return true;
    }

    /** Estorna o pagamento: transação e parcelas voltam a pendentes. */
    public function markUnpaid(int $id): void
    {
        $now = null;
        $this->db->update('fin_transactions', ['status' => 'pending', 'paid_at' => $now], 'id = ?', [$id]);
        $this->db->update('fin_installments', ['status' => 'pending', 'paid_at' => $now], 'transaction_id = ?', [$id]);
    }

    public function remove(int $id): void
    {
        // fin_installments tem ON DELETE CASCADE.
        $this->db->delete('fin_transactions', 'id = ?', [$id]);
    }

    /** Já existe lançamento importado com este ID do Asaas? (dedupe) */
    public function existsByAsaasId(string $asaasPaymentId): bool
    {
        $row = $this->db->fetch("SELECT id FROM fin_transactions WHERE asaas_payment_id = ?", [$asaasPaymentId]);
        return (bool)$row;
    }
}