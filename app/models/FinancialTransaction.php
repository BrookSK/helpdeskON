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
     * kind, status, month (YYYY-MM), source.
     *
     * Com filtro de mês, lançamento parcelado aparece UMA VEZ POR PARCELA, no
     * mês do vencimento de cada parcela: due_date, amount, status e paid_at da
     * linha passam a ser os da parcela; total_amount guarda o valor total e
     * installment_id/installment_no identificam a parcela. Sem filtro de mês,
     * cada lançamento aparece uma vez (como antes).
     */
    public function getAll(array $filters = []): array
    {
        $select = "SELECT t.*, c.name AS card_name, a.name AS asaas_account_name,
                       b.name AS bank_account_name, cat.name AS category_name,
                       pu.name AS person_name";
        $joins = " LEFT JOIN users pu ON pu.id = t.user_id
                LEFT JOIN fin_credit_cards c ON c.id = t.card_id
                LEFT JOIN finance_accounts a ON a.id = t.asaas_account_id
                LEFT JOIN fin_bank_accounts b ON b.id = t.account_id
                LEFT JOIN fin_categories cat ON cat.id = t.category_id";

        // Filtros comuns (sobre o lançamento). Status é aplicado à linha.
        $common = '';
        $commonParams = [];
        if (!empty($filters['kind'])) { $common .= " AND t.kind = ?"; $commonParams[] = $filters['kind']; }
        if (!empty($filters['source'])) { $common .= " AND t.source = ?"; $commonParams[] = $filters['source']; }
        if (!empty($filters['direction'])) { $common .= " AND t.direction = ?"; $commonParams[] = $filters['direction']; }
        foreach (['category_id', 'account_id', 'card_id', 'user_id'] as $col) {
            if (!empty($filters[$col])) { $common .= " AND t.{$col} = ?"; $commonParams[] = (int)$filters[$col]; }
        }
        // Situação: só paid/pending vão para o SQL; "atrasado" e "previsto"
        // são refinados por FinancialReportRules::matches no controller.
        $sqlStatus = in_array(($filters['status'] ?? ''), ['paid', 'pending'], true) ? $filters['status']
            : (($filters['status'] ?? '') === 'overdue' ? 'pending' : '');

        // Período: from/to (intervalo) ou month (mês inteiro).
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if (empty($from) || empty($to)) {
            $month = (!empty($filters['month']) && FinancialRules::validMonth($filters['month'])) ? $filters['month'] : null;
            if ($month !== null) {
                $from = $month . '-01';
                $to = date('Y-m-t', strtotime($from));
            } else {
                $from = $to = null;
            }
        }
        $limit = ($from !== null && substr($from, 0, 7) !== substr((string)$to, 0, 7)) ? 3000 : 500;

        if ($from === null) {
            $sql = $select . ", t.due_date AS row_due, t.amount AS row_amount, t.status AS row_status,
                       t.paid_at AS row_paid_at, NULL AS installment_id, NULL AS installment_no
                FROM fin_transactions t" . $joins . " WHERE 1=1" . $common;
            $params = $commonParams;
            if ($sqlStatus !== '') { $sql .= " AND t.status = ?"; $params[] = $sqlStatus; }
            $sql .= " ORDER BY row_due DESC, id DESC LIMIT 500";
        } else {
            // À vista: pelo vencimento do lançamento.
            $single = $select . ", t.due_date AS row_due, t.amount AS row_amount, t.status AS row_status,
                       t.paid_at AS row_paid_at, NULL AS installment_id, NULL AS installment_no
                FROM fin_transactions t" . $joins . "
                WHERE t.installments <= 1 AND t.due_date BETWEEN ? AND ?" . $common;
            $p1 = array_merge([$from, $to], $commonParams);
            if ($sqlStatus !== '') { $single .= " AND t.status = ?"; $p1[] = $sqlStatus; }

            // Parcelado: cada parcela no mês do vencimento dela.
            $parcel = $select . ", i.due_date AS row_due, i.amount AS row_amount,
                       CASE WHEN t.status = 'cancelled' THEN 'cancelled' ELSE i.status END AS row_status,
                       i.paid_at AS row_paid_at, i.id AS installment_id, i.installment_no AS installment_no
                FROM fin_installments i
                JOIN fin_transactions t ON t.id = i.transaction_id" . $joins . "
                WHERE t.installments > 1 AND i.due_date BETWEEN ? AND ?" . $common;
            $p2 = array_merge([$from, $to], $commonParams);
            if ($sqlStatus !== '') { $parcel .= " AND i.status = ?"; $p2[] = $sqlStatus; }

            $sql = "(" . $single . ") UNION ALL (" . $parcel . ") ORDER BY row_due DESC, id DESC LIMIT " . $limit;
            $params = array_merge($p1, $p2);
        }

        try {
            return $this->mapRows($this->db->fetchAll($sql, $params));
        } catch (\Throwable $e) {
            // Banco sem a coluna user_id (migration 169 pendente): lista sem a
            // pessoa em vez de esconder todos os lançamentos.
            if (stripos($e->getMessage(), 'user_id') === false) return [];
            if (!empty($filters['user_id'])) return []; // filtro por pessoa sem a coluna
            $fallback = str_replace(
                ["pu.name AS person_name", "LEFT JOIN users pu ON pu.id = t.user_id"],
                ["NULL AS person_name", ""],
                $sql
            );
            try {
                return $this->mapRows($this->db->fetchAll($fallback, $params));
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }

    /**
     * Converte as colunas row_* na visão da linha (parcela ou lançamento):
     * due_date/amount/status/paid_at da linha; total_amount e tx_status
     * guardam os valores do lançamento inteiro.
     */
    private function mapRows(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['total_amount'] = $r['amount'];
            $r['tx_status'] = $r['status'];
            $r['due_date'] = $r['row_due'];
            $r['amount'] = $r['row_amount'];
            $r['status'] = $r['row_status'];
            $r['paid_at'] = $r['row_paid_at'];
            $r['installment_id'] = $r['installment_id'] !== null ? (int)$r['installment_id'] : null;
            $r['installment_no'] = $r['installment_no'] !== null ? (int)$r['installment_no'] : null;
            unset($r['row_due'], $r['row_amount'], $r['row_status'], $r['row_paid_at']);
        }
        unset($r);
        return $rows;
    }

    /**
     * Quita UMA parcela específica. Fecha o lançamento quando não sobra
     * parcela pendente. Retorna true quando o lançamento ficou todo pago.
     */
    public function payInstallment(int $installmentId): bool
    {
        $i = $this->db->fetch("SELECT id, transaction_id, status FROM fin_installments WHERE id = ?", [$installmentId]);
        if (!$i) return false;
        $now = date('Y-m-d H:i:s');
        if ($i['status'] !== 'paid') {
            $this->db->update('fin_installments', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [$installmentId]);
        }
        $left = $this->db->fetch(
            "SELECT COUNT(*) AS c FROM fin_installments WHERE transaction_id = ? AND status = 'pending'",
            [(int)$i['transaction_id']]
        );
        $done = (int)($left['c'] ?? 0) === 0;
        if ($done) {
            $this->db->update('fin_transactions', ['status' => 'paid', 'paid_at' => $now], 'id = ?', [(int)$i['transaction_id']]);
        }
        return $done;
    }

    /** Estorna UMA parcela; o lançamento volta a pendente. */
    public function unpayInstallment(int $installmentId): void
    {
        $i = $this->db->fetch("SELECT transaction_id FROM fin_installments WHERE id = ?", [$installmentId]);
        if (!$i) return;
        $this->db->update('fin_installments', ['status' => 'pending', 'paid_at' => null], 'id = ?', [$installmentId]);
        $this->db->update('fin_transactions', ['status' => 'pending', 'paid_at' => null], 'id = ?', [(int)$i['transaction_id']]);
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
            // Não duplica se a próxima ocorrência já foi gravada (ex.: paga
            // antecipadamente a partir da previsão).
            // A pessoa entra na comparação: o mês seguinte de OUTRA pessoa com
            // a mesma descrição não pode bloquear o desta.
            $sameSeries = "kind = ? AND direction = ? AND recurring_cycle = ? AND due_date = ?
                   AND status <> 'cancelled' AND installments <= 1
                   AND LOWER(TRIM(COALESCE(description, ''))) = ?
                   AND COALESCE(card_id, 0) = ?";
            $seriesParams = [
                $t['kind'], $t['direction'], $t['recurring_cycle'], $nextDue,
                mb_strtolower(trim((string)($t['description'] ?? ''))), (int)($t['card_id'] ?? 0),
            ];
            if (array_key_exists('user_id', $t)) {
                $sameSeries .= " AND COALESCE(user_id, 0) = ?";
                $seriesParams[] = (int)($t['user_id'] ?? 0);
            }
            $exists = $this->db->fetch("SELECT id FROM fin_transactions WHERE {$sameSeries}", $seriesParams);
            if (!$exists && FinancialRules::recurringContinues($nextDue, $t['recurring_until'] ?? null)) {
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
                if (array_key_exists('user_id', $t)) $clone['user_id'] = $t['user_id'];
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

    /**
     * Movimentos no nível de parcela para o resumo de caixa: lançamento à
     * vista = 1 linha; parcelado = 1 linha por parcela (valor, vencimento e
     * pagamento da própria parcela). Cancelados ficam de fora.
     */
    public function cashMovements(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT t.kind, t.direction, t.amount, t.status, t.due_date, t.paid_at
                 FROM fin_transactions t
                 WHERE t.installments <= 1 AND t.status <> 'cancelled'
                 UNION ALL
                 SELECT t.kind, t.direction, i.amount, i.status, i.due_date, i.paid_at
                 FROM fin_installments i
                 JOIN fin_transactions t ON t.id = i.transaction_id
                 WHERE t.installments > 1 AND t.status <> 'cancelled'"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Lançamentos recorrentes à vista (base para projetar os meses futuros),
     * com os nomes de categoria, conta e pessoa que a lista exibe.
     */
    public function recurringRows(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT t.*, cat.name AS category_name, b.name AS bank_account_name, c.name AS card_name
                 FROM fin_transactions t
                 LEFT JOIN fin_categories cat ON cat.id = t.category_id
                 LEFT JOIN fin_bank_accounts b ON b.id = t.account_id
                 LEFT JOIN fin_credit_cards c ON c.id = t.card_id
                 WHERE t.recurring_cycle IS NOT NULL AND t.installments <= 1 AND t.status <> 'cancelled'"
            );
        } catch (\Throwable $e) {
            return [];
        }
        // Nome da pessoa (coluna user_id só existe após a migration 169).
        $ids = [];
        foreach ($rows as $r) if (!empty($r['user_id'])) $ids[(int)$r['user_id']] = true;
        $names = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            try {
                foreach ($this->db->fetchAll("SELECT id, name FROM users WHERE id IN ($in)", array_keys($ids)) as $u) {
                    $names[(int)$u['id']] = $u['name'];
                }
            } catch (\Throwable $e) {}
        }
        foreach ($rows as &$r) {
            $r['person_name'] = !empty($r['user_id']) ? ($names[(int)$r['user_id']] ?? null) : null;
        }
        unset($r);
        return $rows;
    }

    /**
     * Grava como PAGA uma ocorrência prevista de um recorrente, na data
     * informada. Não cria a seguinte: a projeção continua a partir dela.
     * Retorna o id criado, ou null se a data não é uma ocorrência válida da
     * série ou se essa ocorrência já existe.
     */
    public function payProjected(int $fromId, string $date): ?int
    {
        $base = $this->findById($fromId);
        if (!$base || empty($base['recurring_cycle']) || (int)$base['installments'] > 1) return null;

        $key = FinancialRules::recurringChainKey($base);
        $chain = array_values(array_filter($this->recurringRows(), fn($r) => FinancialRules::recurringChainKey($r) === $key));
        foreach ($chain as $r) {
            if (substr((string)$r['due_date'], 0, 10) === $date) return null; // já existe
        }
        $valid = array_column(FinancialRules::projectRecurring($chain, $date), 'due_date');
        if (!in_array($date, $valid, true)) return null;

        $row = [
            'kind' => $base['kind'], 'direction' => $base['direction'],
            'category' => $base['category'], 'category_id' => $base['category_id'] ?? null,
            'description' => $base['description'], 'amount' => $base['amount'],
            'installments' => 1, 'recurring_cycle' => $base['recurring_cycle'],
            'recurring_until' => $base['recurring_until'], 'due_date' => $date,
            'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
            'card_id' => $base['card_id'], 'account_id' => $base['account_id'] ?? null,
            'asaas_account_id' => $base['asaas_account_id'], 'asaas_payment_id' => null,
            'source' => $base['source'], 'created_by' => $base['created_by'],
        ];
        if (array_key_exists('user_id', $base)) $row['user_id'] = $base['user_id'];
        return $this->create($row);
    }

    /** Já existe lançamento importado com este ID do Asaas? (dedupe) */
    public function existsByAsaasId(string $asaasPaymentId): bool
    {
        $row = $this->db->fetch("SELECT id FROM fin_transactions WHERE asaas_payment_id = ?", [$asaasPaymentId]);
        return (bool)$row;
    }
}