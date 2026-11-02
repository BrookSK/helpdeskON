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
        $sql = "SELECT t.*, c.name AS card_name, a.name AS asaas_account_name,
                       b.name AS bank_account_name, cat.name AS category_name
                FROM fin_transactions t
                LEFT JOIN fin_credit_cards c ON c.id = t.card_id
                LEFT JOIN finance_accounts a ON a.id = t.asaas_account_id
                LEFT JOIN fin_bank_accounts b ON b.id = t.account_id
                LEFT JOIN fin_categories cat ON cat.id = t.category_id
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

    /**
     * Cria o lançamento + parcelas (quando installments > 1) numa tacada.
     * $dueDates (opcional): vencimento de cada parcela já calculado (ex.: as
     * faturas do cartão, via CardInvoiceRules). Sem ele, as parcelas vencem
     * mensalmente a partir de due_date.
     */
    public function createWithInstallments(array $data, ?array $dueDates = null): int
    {
        $id = $this->create($data);
        if ((int)$data['installments'] > 1) {
            $plan = FinancialRules::buildInstallments((float)$data['amount'], (int)$data['installments'], (string)$data['due_date']);
            if ($dueDates !== null && count($dueDates) === count($plan)) {
                foreach ($plan as $i => $p) $plan[$i]['due_date'] = $dueDates[$i];
            }
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

        // Recorrente: ao pagar, nasce a PRÓXIMA ocorrência (mesma configuração,
        // nova data), respeitando o fim (recurring_until). O histórico continua
        // visível; a próxima aparece como pendente no mês seguinte.
        if (!empty($t['recurring_cycle'])) {
            $nextDue = FinancialRules::recurringNextDate((string)$t['due_date'], (string)$t['recurring_cycle']);
            if (FinancialRules::recurringContinues($nextDue, $t['recurring_until'] ?? null)) {
                $clone = [
                    'kind'            => $t['kind'],
                    'direction'       => $t['direction'],
                    'category'        => $t['category'],
                    'category_id'     => $t['category_id'] ?? null,
                    'description'     => $t['description'],
                    'amount'          => $t['amount'],
                    'installments'    => 1,
                    'recurring_cycle' => $t['recurring_cycle'],
                    'recurring_until' => $t['recurring_until'],
                    'due_date'        => $nextDue,
                    'status'          => 'pending',
                    'card_id'         => $t['card_id'],
                    'account_id'      => $t['account_id'] ?? null,
                    'asaas_account_id' => $t['asaas_account_id'],
                    'asaas_payment_id' => null,
                    'source'          => $t['source'],
                    'created_by'      => $t['created_by'],
                ];
                // Compra recorrente no cartão: a próxima compra também anda um ciclo.
                if (array_key_exists('purchase_date', $t)) {
                    $clone['purchase_date'] = !empty($t['purchase_date'])
                        ? FinancialRules::recurringNextDate((string)$t['purchase_date'], (string)$t['recurring_cycle'])
                        : null;
                }
                $this->db->insert('fin_transactions', $clone);
            }
        }
        return true;
    }

    /** Atualiza campos de um lançamento (o controller filtra o que é editável). */
    public function update(int $id, array $data): void
    {
        if (empty($data)) return;
        $this->db->update('fin_transactions', $data, 'id = ?', [$id]);
    }

    /**
     * Faturas por cartão: total e total pendente por mês de vencimento.
     * Junta as parcelas (compras parceladas) e as compras à vista no cartão.
     *
     * @return array<int,array<string,array>> card_id => [ 'YYYY-MM' => [total, pending, items] ]
     */
    public function invoicesByCard(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT t.card_id, DATE_FORMAT(i.due_date, '%Y-%m') AS ym,
                        SUM(i.amount) AS total,
                        SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END) AS pending,
                        COUNT(*) AS items
                 FROM fin_installments i
                 JOIN fin_transactions t ON t.id = i.transaction_id
                 WHERE t.card_id IS NOT NULL AND t.status <> 'cancelled' AND i.due_date IS NOT NULL
                 GROUP BY t.card_id, ym
                 UNION ALL
                 SELECT t.card_id, DATE_FORMAT(t.due_date, '%Y-%m') AS ym,
                        SUM(t.amount) AS total,
                        SUM(CASE WHEN t.status = 'pending' THEN t.amount ELSE 0 END) AS pending,
                        COUNT(*) AS items
                 FROM fin_transactions t
                 WHERE t.card_id IS NOT NULL AND t.installments <= 1
                   AND t.status <> 'cancelled' AND t.due_date IS NOT NULL
                 GROUP BY t.card_id, ym"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $card = (int)$r['card_id'];
            $ym = (string)$r['ym'];
            if (!isset($out[$card][$ym])) $out[$card][$ym] = ['total' => 0.0, 'pending' => 0.0, 'items' => 0];
            $out[$card][$ym]['total']   = round($out[$card][$ym]['total'] + (float)$r['total'], 2);
            $out[$card][$ym]['pending'] = round($out[$card][$ym]['pending'] + (float)$r['pending'], 2);
            $out[$card][$ym]['items']  += (int)$r['items'];
        }
        foreach ($out as $card => $months) {
            ksort($months);
            $out[$card] = $months;
        }
        return $out;
    }

    /**
     * Paga a fatura de um cartão num mês (YYYY-MM de vencimento): quita as
     * parcelas e as compras à vista desse cartão que vencem no mês. Compras
     * parceladas só fecham quando a última parcela é quitada.
     *
     * @return int quantidade de itens quitados
     */
    public function payInvoice(int $cardId, string $ym): int
    {
        if (!FinancialRules::validMonth($ym)) return 0;
        $now = date('Y-m-d H:i:s');
        $count = 0;

        $insts = $this->db->fetchAll(
            "SELECT i.id, i.transaction_id
             FROM fin_installments i
             JOIN fin_transactions t ON t.id = i.transaction_id
             WHERE t.card_id = ? AND i.status = 'pending' AND t.status <> 'cancelled'
               AND DATE_FORMAT(i.due_date, '%Y-%m') = ?",
            [$cardId, $ym]
        );
        $touched = [];
        foreach ($insts as $i) {
            $this->db->update('fin_installments', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [(int)$i['id']]);
            $touched[(int)$i['transaction_id']] = true;
            $count++;
        }
        foreach (array_keys($touched) as $txId) {
            $left = $this->db->fetch(
                "SELECT COUNT(*) AS c FROM fin_installments WHERE transaction_id = ? AND status = 'pending'",
                [$txId]
            );
            if ((int)($left['c'] ?? 0) === 0) {
                $this->db->update('fin_transactions', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [$txId]);
            }
        }

        $singles = $this->db->fetchAll(
            "SELECT id FROM fin_transactions
             WHERE card_id = ? AND installments <= 1 AND status = 'pending'
               AND DATE_FORMAT(due_date, '%Y-%m') = ?",
            [$cardId, $ym]
        );
        foreach ($singles as $s) {
            // markPaid também gera a próxima ocorrência quando é recorrente.
            $this->markPaid((int)$s['id']);
            $count++;
        }
        return $count;
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

    /**
     * Lançamentos vinculados a contas bancárias, agrupados por account_id.
     * Usado para calcular o saldo de cada conta (FinancialAccountRules).
     * Traz apenas as colunas necessárias (direction, amount, status).
     *
     * @return array<int,array<int,array>> account_id => [ linhas ]
     */
    public function movementsByAccount(): array
    {
        // À vista: a linha do lançamento. Parcelado: cada parcela conta
        // sozinha (parcela paga sai da conta; as pendentes ainda não).
        try {
            $rows = $this->db->fetchAll(
                "SELECT account_id, direction, amount, status
                 FROM fin_transactions
                 WHERE account_id IS NOT NULL AND installments <= 1
                 UNION ALL
                 SELECT t.account_id, t.direction, i.amount,
                        CASE WHEN t.status = 'cancelled' THEN 'cancelled' ELSE i.status END AS status
                 FROM fin_installments i
                 JOIN fin_transactions t ON t.id = i.transaction_id
                 WHERE t.account_id IS NOT NULL AND t.installments > 1"
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['account_id']][] = $r;
        }
        return $out;
    }

    /** Já existe lançamento importado com este ID do Asaas? (dedupe) */
    public function existsByAsaasId(string $asaasPaymentId): bool
    {
        $row = $this->db->fetch("SELECT id FROM fin_transactions WHERE asaas_payment_id = ?", [$asaasPaymentId]);
        return (bool)$row;
    }
}