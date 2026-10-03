<?php

/**
 * Regras puras (sem banco/HTTP) do Financeiro (Fase 5).
 *
 * Centraliza e torna testável:
 *  - montagem do plano de cobranças (entrada + parcelas) com datas e rateio;
 *  - validação de método (pix/boleto/cartao) e ciclo de recorrência;
 *  - seleção da conta Asaas por finalidade;
 *  - a REGRA DE BLOQUEIO do onboarding (só avança com a entrada paga).
 *
 * Exemplo da reunião: projeto R$40.000, 50% de entrada (R$20.000) para amanhã,
 * e o restante (R$20.000) em 5 parcelas a partir de 30/45 dias.
 */
class FinanceRules
{
    public const METHODS = ['pix', 'boleto', 'cartao'];
    public const CYCLES = ['monthly', 'yearly'];
    public const KINDS = ['entry', 'installment', 'recurring'];

    public static function normalizeMethod($v): ?string
    {
        return in_array($v, self::METHODS, true) ? $v : null;
    }

    public static function normalizeCycle($v): ?string
    {
        return in_array($v, self::CYCLES, true) ? $v : null;
    }

    /**
     * Converte valor (número ou "R$ 1.234,56") em float >= 0.
     */
    public static function money($v): float
    {
        if (is_int($v) || is_float($v)) return max(0.0, (float)$v);
        if ($v === null || $v === '') return 0.0;
        if (is_string($v)) {
            if (preg_match('/^-?\d+(\.\d+)?$/', trim($v))) return max(0.0, (float)$v);
            if (class_exists('CrmRules')) {
                $p = CrmRules::parseMoneyBR($v);
                if ($p !== null) return max(0.0, (float)$p);
            }
            return max(0.0, (float)preg_replace('/[^0-9.\-]/', '', $v));
        }
        return 0.0;
    }

    /**
     * Rateia um valor em N parcelas iguais, ajustando os centavos na ÚLTIMA
     * parcela para a soma bater exatamente com o total.
     *
     * @return float[] lista com N valores (em reais).
     */
    public static function splitInstallments(float $total, int $count): array
    {
        $total = round(max(0.0, $total), 2);
        $count = max(1, (int)$count);
        $base = floor(($total / $count) * 100) / 100; // trunca a 2 casas
        $parts = array_fill(0, $count, $base);
        $acc = round($base * $count, 2);
        $parts[$count - 1] = round($parts[$count - 1] + ($total - $acc), 2);
        return $parts;
    }

    /**
     * Monta o plano de cobranças a partir dos parâmetros do fechamento.
     *
     * @param array $p [
     *   'total' => float, 'entry' => float (valor da entrada),
     *   'entry_due' => 'Y-m-d', 'entry_method' => 'pix|boleto|cartao',
     *   'installments' => int (nº de parcelas do restante),
     *   'first_installment_due' => 'Y-m-d', 'installment_interval_days' => int (ex.: 30),
     *   'installment_method' => 'pix|boleto|cartao',
     * ]
     * @return array<int,array> cada item: kind, amount, due_date, method,
     *   installment_no, installment_total, description.
     */
    public static function buildChargePlan(array $p): array
    {
        $total = self::money($p['total'] ?? 0);
        $entry = self::money($p['entry'] ?? 0);
        if ($entry > $total) $entry = $total;
        $charges = [];

        if ($entry > 0) {
            $charges[] = [
                'kind' => 'entry',
                'amount' => round($entry, 2),
                'due_date' => self::dateOrNull($p['entry_due'] ?? null),
                'method' => self::normalizeMethod($p['entry_method'] ?? null),
                'installment_no' => null,
                'installment_total' => null,
                'description' => 'Entrada',
            ];
        }

        $remaining = round($total - $entry, 2);
        $count = max(0, (int)($p['installments'] ?? 0));
        if ($remaining > 0 && $count > 0) {
            $values = self::splitInstallments($remaining, $count);
            $interval = max(0, (int)($p['installment_interval_days'] ?? 30));
            $firstDue = self::dateOrNull($p['first_installment_due'] ?? null);
            $method = self::normalizeMethod($p['installment_method'] ?? null);
            foreach ($values as $i => $val) {
                $due = null;
                if ($firstDue !== null) {
                    $due = date('Y-m-d', strtotime($firstDue . ' +' . ($interval * $i) . ' days'));
                }
                $charges[] = [
                    'kind' => 'installment',
                    'amount' => $val,
                    'due_date' => $due,
                    'method' => $method,
                    'installment_no' => $i + 1,
                    'installment_total' => $count,
                    'description' => 'Parcela ' . ($i + 1) . '/' . $count,
                ];
            }
        }
        return $charges;
    }

    /**
     * Escolhe a conta Asaas pela finalidade: tenta uma conta com purpose igual à
     * finalidade pedida; se não houver, usa a primeira ativa. Retorna o id ou null.
     *
     * @param array<int,array> $accounts linhas de finance_accounts (id, purpose, active)
     */
    public static function pickAccount(array $accounts, string $purpose): ?int
    {
        $actives = array_values(array_filter($accounts, fn($a) => (int)($a['active'] ?? 1) === 1));
        foreach ($actives as $a) {
            if (($a['purpose'] ?? '') === $purpose) return (int)$a['id'];
        }
        return $actives ? (int)$actives[0]['id'] : null;
    }

    /**
     * REGRA CENTRAL: o onboarding só pode começar quando a entrada estiver paga.
     * Recebe as cobranças do projeto; retorna true se há uma 'entry' paga OU se
     * não há entrada (projeto sem entrada exigida — ex.: só recorrente) e ao
     * menos uma cobrança paga.
     */
    public static function entryIsPaid(array $charges): bool
    {
        $entries = array_filter($charges, fn($c) => ($c['kind'] ?? '') === 'entry');
        if (!empty($entries)) {
            foreach ($entries as $c) {
                if (($c['status'] ?? '') === 'paid') return true;
            }
            return false; // tem entrada, mas não paga
        }
        // Sem entrada: exige ao menos uma cobrança paga para liberar.
        foreach ($charges as $c) {
            if (($c['status'] ?? '') === 'paid') return true;
        }
        return false;
    }

    private static function dateOrNull($v): ?string
    {
        $s = trim((string)$v);
        if ($s === '') return null;
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
