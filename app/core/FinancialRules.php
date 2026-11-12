<?php

/**
 * Regras puras (sem banco/HTTP) do Controle Financeiro (super_admin).
 *
 * Centraliza e torna testável:
 *  - tipos de lançamento (ganhos, gastos, salários, empréstimos, transferências)
 *    e sua direção padrão (entra/sai);
 *  - validação/normalização de um lançamento manual;
 *  - montagem das parcelas de uma compra parcelada (reaproveita o split de
 *    FinanceRules para os centavos baterem);
 *  - resumo do painel (recebido, a receber, pago, a pagar, saldo);
 *  - mapeamento status Asaas -> status interno (importação das contas).
 */
class FinancialRules
{
    public const KINDS = ['ganho', 'gasto', 'salario', 'emprestimo', 'transferencia'];

    /** Direção padrão por tipo (empréstimo pode ser in ou out). */
    public const DEFAULT_DIRECTION = [
        'ganho'         => 'in',
        'gasto'         => 'out',
        'salario'       => 'out',
        'emprestimo'    => 'out',
        'transferencia' => 'out',
    ];

    /** Tipos que permitem parcelamento (compra no cartão, entrada parcelada, empréstimo). */
    public const INSTALLABLE_KINDS = ['ganho', 'gasto', 'emprestimo'];

    /** Tipos que permitem recorrência (mensal/anual que se repetem). */
    public const RECURRING_KINDS = ['ganho', 'gasto', 'salario'];

    /** Direção válida para o tipo informado. */
    public static function directionFor(string $kind, ?string $direction): string
    {
        $d = strtolower(trim((string)$direction));
        if (in_array($d, ['in', 'out'], true)) return $d;
        return self::DEFAULT_DIRECTION[$kind] ?? 'out';
    }

    /**
     * Normaliza/valida um lançamento manual.
     * Entrada: array com kind, direction?, category, description, amount,
     * installments, due_date, card_id?, status?, paid?
     * Saída: ['ok'=>bool, 'errors'=>[...], 'data'=>array|null]
     */
    public static function validateTransaction(array $in): array
    {
        $errors = [];
        $kind = (string)($in['kind'] ?? '');
        if (!in_array($kind, self::KINDS, true)) {
            $errors[] = 'Tipo de lançamento inválido.';
        }
        $amount = FinanceRules::money($in['amount'] ?? 0);
        if ($amount <= 0) $errors[] = 'Informe um valor maior que zero.';

        $installments = (int)($in['installments'] ?? 1);
        $installments = max(1, min(48, $installments));
        $cardId = !empty($in['card_id']) ? (int)$in['card_id'] : null;
        $accountId = !empty($in['account_id']) ? (int)$in['account_id'] : null;
        $categoryId = !empty($in['category_id']) ? (int)$in['category_id'] : null;
        $userId = !empty($in['user_id']) ? (int)$in['user_id'] : null;
        if ($installments > 1 && !in_array($kind, self::INSTALLABLE_KINDS, true)) {
            $errors[] = 'Parcelamento não é permitido para este tipo.';
        }
        if ($installments > 1 && $kind === 'gasto' && $cardId === null) {
            $errors[] = 'Informe o cartão para gastos parcelados.';
        }

        // Recorrência: só em ganho/gasto e apenas à vista (não parcelado).
        $cycle = strtolower(trim((string)($in['recurring_cycle'] ?? '')));
        $recurringCycle = in_array($cycle, ['monthly', 'yearly'], true) ? $cycle : null;
        if ($recurringCycle !== null && !in_array($kind, self::RECURRING_KINDS, true)) {
            $errors[] = 'Recorrência só é permitida em ganhos, gastos e salários.';
        }
        if ($recurringCycle !== null && $installments > 1) {
            $errors[] = 'Lançamento recorrente não pode ser parcelado.';
        }
        $recurringUntil = null;
        $ru = trim((string)($in['recurring_until'] ?? ''));
        if ($ru !== '') {
            if (self::validMonth($ru)) {
                // A tela manda só o mês ("repetir até março/2027"): vale o mês
                // inteiro, então o fim é o último dia dele. Antes virava dia 1º
                // e a ocorrência do próprio mês final ficava de fora.
                $recurringUntil = date('Y-m-t', strtotime($ru . '-01'));
            } else {
                $t2 = strtotime($ru);
                if ($t2 === false) $errors[] = 'Data final da recorrência inválida.';
                else $recurringUntil = date('Y-m-d', $t2);
            }
        }

        // Data do empréstimo (quando pegou/concedeu) — opcional.
        $loanDate = null;
        $ld = trim((string)($in['loan_date'] ?? ''));
        if ($ld !== '') {
            $t3 = strtotime($ld);
            if ($t3 === false) $errors[] = 'Data do empréstimo inválida.';
            else $loanDate = date('Y-m-d', $t3);
        }

        $due = trim((string)($in['due_date'] ?? ''));
        $dueDate = null;
        if ($due !== '') {
            $t = strtotime($due);
            $dueDate = $t ? date('Y-m-d', $t) : null;
            if ($dueDate === null) $errors[] = 'Data inválida.';
        }
        if ($dueDate === null && $installments > 1) {
            $errors[] = 'Informe a data da primeira parcela.';
        }

        $status = ($in['status'] ?? 'pending') === 'paid' ? 'paid' : 'pending';

        if (!empty($errors)) return ['ok' => false, 'errors' => $errors, 'data' => null];

        return [
            'ok' => true,
            'errors' => [],
            'data' => [
                'kind'            => $kind,
                'direction'       => self::directionFor($kind, $in['direction'] ?? null),
                'category'        => mb_substr(trim((string)($in['category'] ?? '')), 0, 80) ?: null,
                'category_id'     => $categoryId,
                'description'     => mb_substr(trim((string)($in['description'] ?? '')), 0, 255) ?: null,
                'amount'          => $amount,
                'installments'    => $installments,
                'card_id'         => $cardId,
                'account_id'      => $accountId,
                'user_id'         => $userId,
                'due_date'        => $dueDate ?: date('Y-m-d'),
                'recurring_cycle' => $recurringCycle,
                'recurring_until' => $recurringUntil,
                'loan_date'       => $loanDate,
                'status'          => $status,
            ],
        ];
    }

    /**
     * Monta as parcelas de uma compra parcelada: valores iguais com ajuste de
     * centavos na última (FinanceRules::splitInstallments) e vencimentos
     * mensais a partir da primeira data.
     *
     * @return array<int,array> cada item: installment_no, installment_total,
     * amount, due_date.
     */
    public static function buildInstallments(float $total, int $count, string $firstDue): array
    {
        $count = max(1, min(48, $count));
        $values = FinanceRules::splitInstallments($total, $count);
        $start = strtotime($firstDue);
        $out = [];
        foreach ($values as $i => $v) {
            $due = date('Y-m-d', strtotime('+' . $i . ' month', $start));
            $out[] = [
                'installment_no'    => $i + 1,
                'installment_total' => $count,
                'amount'            => $v,
                'due_date'          => $due,
            ];
        }
        return $out;
    }

    /**
     * Resumo do painel a partir dos lançamentos.
     * in/paid = recebido | in/pending = a receber
     * out/paid = pago    | out/pending = a pagar
     * saldo = recebido - pago | projetado = saldo + a receber - a pagar
     */
    public static function summary(array $rows): array
    {
        $s = [
            'received'   => 0.0,
            'to_receive' => 0.0,
            'paid'       => 0.0,
            'to_pay'     => 0.0,
            'loans_out'  => 0.0, // empréstimos tomados ainda pendentes
            'loans_in'   => 0.0, // empréstimos concedidos a receber
        ];
        foreach ($rows as $r) {
            $amt = (float)($r['amount'] ?? 0);
            $status = $r['status'] ?? 'pending';
            $dir = $r['direction'] ?? 'out';
            $kind = $r['kind'] ?? '';
            if ($status === 'cancelled') continue;
            if ($status === 'paid') {
                if ($dir === 'in') $s['received'] += $amt; else $s['paid'] += $amt;
            } else {
                if ($dir === 'in') $s['to_receive'] += $amt; else $s['to_pay'] += $amt;
                if ($kind === 'emprestimo') {
                    if ($dir === 'out') $s['loans_out'] += $amt; else $s['loans_in'] += $amt;
                }
            }
        }
        $s['balance'] = round($s['received'] - $s['paid'], 2);
        $s['projected'] = round($s['balance'] + $s['to_receive'] - $s['to_pay'], 2);
        return $s;
    }

    /**
     * Campos que podem ser editados num lançamento já gravado.
     *  - Importado do Asaas: só categoria, conta e pessoa (o resto vem da integração).
     *  - Parcelado: categoria, conta, pessoa e descrição (mudar valor/data exigiria
     *    refazer parcelas, inclusive as já pagas; nesse caso, exclua e recrie).
     *  - Já pago: categoria, conta, pessoa e descrição (valor e data travados).
     *  - Demais: categoria, conta, pessoa, descrição, valor, data e cartão.
     *
     * @return string[]
     */
    public static function editableFields(array $tx): array
    {
        if (($tx['source'] ?? 'manual') === 'asaas') {
            return ['category_id', 'account_id', 'user_id'];
        }
        if ((int)($tx['installments'] ?? 1) > 1 || ($tx['status'] ?? 'pending') !== 'pending') {
            return ['category_id', 'account_id', 'user_id', 'description'];
        }
        $fields = ['category_id', 'account_id', 'user_id', 'description', 'amount', 'date'];
        if (($tx['kind'] ?? '') === 'gasto') $fields[] = 'card_id';
        return $fields;
    }

    /**
     * Resumo do mês em regime de caixa, a partir de movimentos no nível de
     * parcela (compra à vista = 1 linha; parcelada = 1 linha por parcela).
     *
     *  - Recebido / Pago: o que foi quitado DENTRO do mês, pela data do
     *    pagamento (paid_at). Sem paid_at, usa o vencimento.
     *  - A receber / A pagar: o que está pendente e vence ATÉ o fim do mês,
     *    incluindo o que ficou atrasado de meses anteriores.
     *  - Atrasado: parte do "a pagar"/"a receber" que venceu antes do mês.
     *  - Saldo em contas: soma dos saldos atuais das contas bancárias.
     *  - Projetado: saldo em contas + a receber − a pagar.
     *
     * Cada linha: direction, kind, amount, status, due_date, paid_at.
     */
    public static function cashSummary(array $rows, string $ym, float $accountsBalance, ?string $today = null): array
    {
        $start = $ym . '-01';
        $end = date('Y-m-t', strtotime($start));
        // Atrasado = venceu antes do mês filtrado E antes de hoje (num mês
        // futuro, o que vence até lá não está atrasado, só a pagar).
        $overdueBefore = min($start, $today ?? date('Y-m-d'));
        $s = [
            'received' => 0.0, 'paid' => 0.0, 'to_receive' => 0.0, 'to_pay' => 0.0,
            'overdue_receive' => 0.0, 'overdue_pay' => 0.0,
            'loans_out' => 0.0, 'loans_in' => 0.0,
        ];
        foreach ($rows as $r) {
            $status = $r['status'] ?? 'pending';
            if ($status === 'cancelled') continue;
            $amt = (float)($r['amount'] ?? 0);
            $in = ($r['direction'] ?? 'out') === 'in';
            $due = !empty($r['due_date']) ? substr((string)$r['due_date'], 0, 10) : null;

            if ($status === 'paid') {
                $when = !empty($r['paid_at']) ? substr((string)$r['paid_at'], 0, 10) : $due;
                if ($when === null || $when < $start || $when > $end) continue;
                if ($in) $s['received'] += $amt; else $s['paid'] += $amt;
                continue;
            }

            // Pendente: entra se vence até o fim do mês (atrasado incluso).
            if ($due === null || $due > $end) continue;
            if ($in) $s['to_receive'] += $amt; else $s['to_pay'] += $amt;
            if ($due < $overdueBefore) {
                if ($in) $s['overdue_receive'] += $amt; else $s['overdue_pay'] += $amt;
            }
            if (($r['kind'] ?? '') === 'emprestimo') {
                if ($in) $s['loans_in'] += $amt; else $s['loans_out'] += $amt;
            }
        }
        foreach ($s as $k => $v) $s[$k] = round($v, 2);
        $s['accounts_balance'] = round($accountsBalance, 2);
        $s['projected'] = round($accountsBalance + $s['to_receive'] - $s['to_pay'], 2);
        return $s;
    }

    /**
     * Movimento inicial de um empréstimo: o dinheiro que entra (tomado) ou
     * sai (concedido) na data do empréstimo. As parcelas vão no sentido
     * contrário (tomado: parcelas saem; concedido: parcelas entram).
     *
     * $loan = dados validados do lançamento (validateTransaction).
     * $principalRaw = valor recebido/emprestado; vazio = usa o total.
     * Retorna o lançamento a gravar, ou null quando não se aplica (não é
     * empréstimo ou não tem data do empréstimo).
     * Data do empréstimo até hoje = já aconteceu (pago); futura = pendente.
     */
    public static function loanInitialMovement(array $loan, $principalRaw, string $today): ?array
    {
        if (($loan['kind'] ?? '') !== 'emprestimo' || empty($loan['loan_date'])) return null;

        $principal = FinanceRules::money($principalRaw);
        if ($principal <= 0) $principal = (float)($loan['amount'] ?? 0);
        if ($principal <= 0) return null;

        $parcelsIn = ($loan['direction'] ?? 'out') === 'in';
        $date = (string)$loan['loan_date'];
        $happened = $date <= $today;
        $desc = trim((string)($loan['description'] ?? '')) ?: 'Empréstimo';
        $label = $parcelsIn ? 'valor emprestado' : 'valor recebido';

        return [
            'kind'         => 'emprestimo',
            'direction'    => $parcelsIn ? 'out' : 'in',
            'category'     => $loan['category'] ?? null,
            'category_id'  => $loan['category_id'] ?? null,
            'description'  => mb_substr($desc . ' (' . $label . ')', 0, 255),
            'amount'       => round($principal, 2),
            'installments' => 1,
            'card_id'      => null,
            'account_id'   => $loan['account_id'] ?? null,
            'user_id'      => $loan['user_id'] ?? null,
            'due_date'     => $date,
            'loan_date'    => $date,
            'status'       => $happened ? 'paid' : 'pending',
            'paid_at'      => $happened ? $date . ' 00:00:00' : null,
        ];
    }

    /** Status Asaas -> status interno do lançamento. */
    public static function asaasStatusToInternal(?string $status): string
    {
        $s = strtoupper(trim((string)$status));
        if (in_array($s, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) return 'paid';
        if (in_array($s, ['OVERDUE', 'REFUNDED'], true)) return 'cancelled';
        return 'pending'; // PENDING, AWAITING_CHARGEBACK etc.
    }

    /**
     * Mapeia um payment do Asaas (GET /payments) para um lançamento interno.
     * Retorna null quando o registro não é importável (ex.: sem id).
     */
    public static function importFromAsaasPayment(array $payment, int $accountId): ?array
    {
        $id = trim((string)($payment['id'] ?? ''));
        if ($id === '') return null;
        $paid = self::asaasStatusToInternal($payment['status'] ?? null) === 'paid';
        $paymentDate = trim((string)($payment['paymentDate'] ?? ''));
        $paidAt = null;
        if ($paid && $paymentDate !== '') {
            $t = strtotime($paymentDate);
            if ($t) $paidAt = date('Y-m-d H:i:s', $t);
        }
        $due = trim((string)($payment['dueDate'] ?? ''));
        return [
            'kind'             => 'ganho',
            'direction'        => 'in',
            'category'         => 'Asaas',
            'description'      => mb_substr(trim((string)($payment['description'] ?? '')) ?: ('Cobrança Asaas ' . $id), 0, 255),
            'amount'           => round((float)($payment['value'] ?? 0), 2),
            'installments'     => 1,
            'status'           => self::asaasStatusToInternal($payment['status'] ?? null),
            'due_date'         => $due !== '' ? date('Y-m-d', strtotime($due)) : null,
            'paid_at'          => $paidAt,
            'asaas_account_id' => $accountId,
            'asaas_payment_id' => $id,
            'source'           => 'asaas',
        ];
    }

    /** Mês (YYYY-MM) é válido para filtro. */
    public static function validMonth(?string $m): bool
    {
        return is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1;
    }

    /** Próxima ocorrência de um recorrente a partir da data base. */
    public static function recurringNextDate(string $dueDate, string $cycle): string
    {
        return date('Y-m-d', strtotime($dueDate . ' +' . ($cycle === 'yearly' ? 12 : 1) . ' month'));
    }

    /**
     * Datas das próximas ocorrências de um recorrente, depois de $fromDue
     * (exclusiva) e até $upTo (inclusiva), respeitando o fim da recorrência.
     * $max limita o tamanho (proteção para recorrência sem fim).
     *
     * @return string[] datas Y-m-d
     */
    public static function recurringOccurrences(string $fromDue, string $cycle, ?string $until, string $upTo, int $max = 120): array
    {
        $out = [];
        $d = $fromDue;
        for ($i = 0; $i < $max; $i++) {
            $d = self::recurringNextDate($d, $cycle);
            if ($d > $upTo || !self::recurringContinues($d, $until)) break;
            $out[] = $d;
        }
        return $out;
    }

    /**
     * Chave que identifica uma série recorrente (as ocorrências criadas ao
     * pagar copiam tipo, direção, descrição, ciclo, cartão e pessoa).
     * A pessoa faz parte da chave: dois salários com a mesma descrição
     * ("Salário") para pessoas diferentes são séries diferentes.
     */
    public static function recurringChainKey(array $r): string
    {
        return implode('|', [
            $r['kind'] ?? '',
            $r['direction'] ?? '',
            mb_strtolower(trim((string)($r['description'] ?? ''))),
            $r['recurring_cycle'] ?? '',
            (string)($r['card_id'] ?? ''),
            (string)($r['user_id'] ?? ''),
        ]);
    }

    /**
     * Ocorrências PREVISTAS (ainda não gravadas) dos recorrentes até $upTo.
     * Para cada série, parte da ocorrência mais recente (maior vencimento),
     * esteja paga ou pendente, e projeta as seguintes. Cada linha prevista é
     * uma cópia da mais recente com: due_date da ocorrência, status pending,
     * is_projected = true e projected_from = id da mais recente.
     *
     * @param array $rows lançamentos recorrentes à vista, não cancelados
     */
    public static function projectRecurring(array $rows, string $upTo): array
    {
        $latest = [];
        foreach ($rows as $r) {
            if (empty($r['recurring_cycle']) || empty($r['due_date'])) continue;
            if (($r['status'] ?? '') === 'cancelled' || (int)($r['installments'] ?? 1) > 1) continue;
            $k = self::recurringChainKey($r);
            $cur = $latest[$k] ?? null;
            if ($cur === null || $r['due_date'] > $cur['due_date']
                || ($r['due_date'] === $cur['due_date'] && (int)$r['id'] > (int)$cur['id'])) {
                $latest[$k] = $r;
            }
        }

        $out = [];
        foreach ($latest as $head) {
            $dates = self::recurringOccurrences(
                substr((string)$head['due_date'], 0, 10),
                (string)$head['recurring_cycle'],
                !empty($head['recurring_until']) ? (string)$head['recurring_until'] : null,
                $upTo
            );
            foreach ($dates as $d) {
                $row = $head;
                $row['due_date'] = $d;
                $row['status'] = 'pending';
                $row['paid_at'] = null;
                $row['purchase_date'] = null;
                $row['is_projected'] = true;
                $row['projected_from'] = (int)$head['id'];
                $row['installment_id'] = null;
                $row['installment_no'] = null;
                $row['total_amount'] = $head['amount'];
                $out[] = $row;
            }
        }
        return $out;
    }

    /** A recorrência continua após a data-base? (sem fim = sempre) */
    public static function recurringContinues(?string $nextDue, ?string $until): bool
    {
        if ($nextDue === null) return false;
        if ($until === null) return true;
        return $nextDue <= $until;
    }

    /**
     * Valida uma comissão vinculada a um ganho.
     * Entrada: transaction_id, user_id?, recipient?, amount? , percent?,
     * installments, first_due_date, note?
     * Valor fixo OU percentual sobre o ganho (amount do ganho passado em
     * ganhoAmount). Retorna ['ok','errors','data'].
     */
    public static function validateCommission(array $in, float $ganhoAmount): array
    {
        $errors = [];
        $txId = (int)($in['transaction_id'] ?? 0);
        if ($txId <= 0) $errors[] = 'Selecione o ganho de origem.';

        $userId = !empty($in['user_id']) ? (int)$in['user_id'] : null;
        $recipient = mb_substr(trim((string)($in['recipient'] ?? '')), 0, 120) ?: null;
        if ($userId === null && $recipient === null) $errors[] = 'Informe para quem é a comissão.';

        $amount = 0.0;
        $percent = trim((string)($in['percent'] ?? ''));
        if ($percent !== '') {
            $pct = FinanceRules::money($percent);
            if ($pct <= 0 || $pct > 100) $errors[] = 'Percentual deve estar entre 0 e 100.';
            else $amount = round($ganhoAmount * ($pct / 100), 2);
        } else {
            $amount = FinanceRules::money($in['amount'] ?? 0);
        }
        if ($amount <= 0) $errors[] = 'Informe o valor da comissão (fixo ou % sobre o ganho).';

        $installments = max(1, min(48, (int)($in['installments'] ?? 1)));

        $due = trim((string)($in['first_due_date'] ?? ''));
        $firstDue = null;
        if ($due !== '') {
            $t = strtotime($due);
            if ($t === false) $errors[] = 'Data da 1ª parcela inválida.';
            else $firstDue = date('Y-m-d', $t);
        }
        if ($firstDue === null) $firstDue = date('Y-m-d');

        if (!empty($errors)) return ['ok' => false, 'errors' => $errors, 'data' => null];

        return [
            'ok' => true,
            'errors' => [],
            'data' => [
                'transaction_id'  => $txId,
                'user_id'        => $userId,
                'recipient'      => $recipient,
                'amount'         => $amount,
                'installments'    => $installments,
                'first_due_date' => $firstDue,
                'next_due_date'  => $firstDue,
                'note'           => mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null,
            ],
        ];
    }

    /** Próximo vencimento de uma comissão após quitar a parcela nº paidCount. */
    public static function commissionNextDue(string $firstDue, int $paidCount): string
    {
        return date('Y-m-d', strtotime($firstDue . ' +' . $paidCount . ' month'));
    }

    /** Total ainda pendente de uma comissão (centavos ajustados). */
    public static function commissionRemaining(float $amount, int $installments, int $paid): float
    {
        $installments = max(1, $installments);
        $paid = min($paid, $installments);
        return round($amount - ($amount * $paid / $installments), 2);
    }

    /** Resumo das comissões: total a pagar (pendente) e já pago. */
    public static function summaryCommissions(array $rows): array
    {
        $s = ['to_pay' => 0.0, 'paid' => 0.0];
        foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'cancelled') continue;
            $amt = (float)($r['amount'] ?? 0);
            $n = max(1, (int)($r['installments'] ?? 1));
            $p = (int)($r['installments_paid'] ?? 0);
            $s['to_pay'] += self::commissionRemaining($amt, $n, $p);
            $s['paid'] += round($amt - self::commissionRemaining($amt, $n, $p), 2);
        }
        return $s;
    }
}