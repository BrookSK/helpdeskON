<?php

/**
 * Regras puras (sem banco/HTTP) de contas bancárias e categorias do Controle
 * Financeiro (super_admin). Separadas de FinancialRules para manter cada classe
 * focada e facilmente testável.
 *
 * Decisões de negócio implementadas aqui:
 *  - Saldo da conta = saldo inicial + entradas PAGAS − saídas PAGAS vinculadas
 *    à conta. Lançamentos pendentes NÃO entram no saldo (entram no "previsto").
 *  - Lançamentos sem conta (account_id nulo) e importados do Asaas não afetam
 *    o saldo de nenhuma conta.
 *  - Gasto no cartão NÃO baixa da conta no ato: o dinheiro sai quando a fatura
 *    é paga (fora do escopo atual). Por isso o saldo considera apenas o
 *    account_id do próprio lançamento, nunca a conta do cartão.
 */
class FinancialAccountRules
{
    public const CATEGORY_KINDS = ['ganho', 'gasto', 'ambos'];

    /**
     * Normaliza/valida uma conta bancária.
     * Entrada: name, bank?, opening_balance?
     * Saída: ['ok'=>bool, 'errors'=>[...], 'data'=>array|null]
     */
    public static function validateBankAccount(array $in): array
    {
        $errors = [];
        $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 120);
        if ($name === '') $errors[] = 'Informe o nome da conta.';

        $bank = mb_substr(trim((string)($in['bank'] ?? '')), 0, 120) ?: null;

        // Saldo inicial pode ser negativo (conta já no vermelho). money() aceita
        // "R$ 1.234,56" mas zera negativos, então tratamos o sinal aqui.
        $raw = $in['opening_balance'] ?? 0;
        $negative = is_numeric($raw) ? ((float)$raw < 0) : (preg_match('/^\s*(R\$\s*)?-/', (string)$raw) === 1);
        $abs = is_numeric($raw) ? abs((float)$raw) : FinanceRules::money(preg_replace('/-/', '', (string)$raw, 1));
        $opening = round($negative ? -$abs : $abs, 2);

        if (!empty($errors)) return ['ok' => false, 'errors' => $errors, 'data' => null];

        return [
            'ok' => true,
            'errors' => [],
            'data' => [
                'name'            => $name,
                'bank'            => $bank,
                'opening_balance' => $opening,
                'active'          => 1,
            ],
        ];
    }

    /**
     * Normaliza/valida uma categoria.
     * Entrada: name, kind?
     * Saída: ['ok'=>bool, 'errors'=>[...], 'data'=>array|null]
     */
    public static function validateCategory(array $in): array
    {
        $errors = [];
        $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 80);
        if ($name === '') $errors[] = 'Informe o nome da categoria.';

        $kind = strtolower(trim((string)($in['kind'] ?? 'ambos')));
        if (!in_array($kind, self::CATEGORY_KINDS, true)) $kind = 'ambos';

        if (!empty($errors)) return ['ok' => false, 'errors' => $errors, 'data' => null];

        return [
            'ok' => true,
            'errors' => [],
            'data' => ['name' => $name, 'kind' => $kind, 'active' => 1],
        ];
    }

    /**
     * A categoria (de kind $catKind) pode ser usada num lançamento do tipo
     * $txKind? 'ambos' vale para qualquer tipo; caso contrário precisa bater.
     * Tipos sem categoria dedicada (salario/emprestimo/transferencia) aceitam
     * qualquer categoria 'ambos'.
     */
    public static function categoryMatchesKind(string $catKind, string $txKind): bool
    {
        if ($catKind === 'ambos') return true;
        return $catKind === $txKind;
    }

    /**
     * Saldo atual de UMA conta a partir do saldo inicial e das linhas de
     * lançamento já vinculadas à conta. Considera apenas status 'paid'.
     *
     * @param float $opening saldo inicial
     * @param array $rows    linhas com ['direction'=>'in|out','amount'=>float,'status'=>...]
     */
    public static function accountBalance(float $opening, array $rows): float
    {
        $balance = $opening;
        foreach ($rows as $r) {
            if (($r['status'] ?? 'pending') !== 'paid') continue;
            $amt = (float)($r['amount'] ?? 0);
            $dir = ($r['direction'] ?? 'out') === 'in' ? 'in' : 'out';
            $balance += ($dir === 'in') ? $amt : -$amt;
        }
        return round($balance, 2);
    }

    /**
     * Saldo PREVISTO de uma conta: saldo atual + entradas pendentes − saídas
     * pendentes. Útil para projeção. Considera 'pending' e 'paid' (ignora
     * 'cancelled').
     */
    public static function accountProjectedBalance(float $opening, array $rows): float
    {
        $balance = $opening;
        foreach ($rows as $r) {
            $status = $r['status'] ?? 'pending';
            if ($status === 'cancelled') continue;
            $amt = (float)($r['amount'] ?? 0);
            $dir = ($r['direction'] ?? 'out') === 'in' ? 'in' : 'out';
            $balance += ($dir === 'in') ? $amt : -$amt;
        }
        return round($balance, 2);
    }

    /**
     * Consolida saldos de várias contas a partir de:
     *  - $accounts: linhas de fin_bank_accounts (id, name, opening_balance...)
     *  - $movByAccount: array account_id => array de linhas de lançamento
     *
     * @return array<int,array> cada conta com saldo e previsto calculados.
     */
    public static function summarizeAccounts(array $accounts, array $movByAccount): array
    {
        $out = [];
        foreach ($accounts as $acc) {
            $id = (int)($acc['id'] ?? 0);
            $opening = (float)($acc['opening_balance'] ?? 0);
            $rows = $movByAccount[$id] ?? [];
            $acc['balance']   = self::accountBalance($opening, $rows);
            $acc['projected'] = self::accountProjectedBalance($opening, $rows);
            $out[] = $acc;
        }
        return $out;
    }
}
