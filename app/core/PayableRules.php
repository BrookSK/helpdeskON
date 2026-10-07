<?php

/**
 * Regras puras (sem banco/HTTP) do CONTAS A PAGAR dos prestadores.
 *
 * O Guia de Contratação de Prestadores (passo "após a assinatura") exige gerar
 * os lançamentos no contas a pagar conforme o tipo de pagamento do prestador:
 *   - mensal  -> 1 lançamento recorrente (ciclo mensal);
 *   - hora    -> 1 lançamento base por hora (a liquidar conforme as horas do
 *                período — valor fica a definir no fechamento, por isso 0 aqui);
 *   - projeto -> N parcelas (parcela), dividindo o valor total informado.
 *
 * Espelha o estilo de FinanceRules (recebíveis), mas para o lado de pagáveis.
 * Mantém-se puro e testável: o model Payable só persiste o que esta classe
 * decide.
 */
class PayableRules
{
    /** Tipos de lançamento (espelha o ENUM payables.kind). */
    public const KIND_MENSAL = 'mensal';
    public const KIND_HORA = 'hora';
    public const KIND_PARCELA = 'parcela';
    public const KINDS = [self::KIND_MENSAL, self::KIND_HORA, self::KIND_PARCELA];

    /** Estados do lançamento (espelha o ENUM payables.status). */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_CANCELLED];

    /** Nº padrão de parcelas quando o prestador é "projeto" e nada é informado. */
    public const DEFAULT_INSTALLMENTS = 1;
    /** Teto de parcelas para evitar planos absurdos. */
    public const MAX_INSTALLMENTS = 60;

    public static function normalizeKind($v): ?string
    {
        return in_array($v, self::KINDS, true) ? $v : null;
    }

    public static function isValidKind($v): bool
    {
        return in_array($v, self::KINDS, true);
    }

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PENDING;
    }

    /**
     * Mapeia o pay_type do prestador (mensal/hora/projeto) para o kind do
     * lançamento (mensal/hora/parcela). "projeto" vira parcela(s).
     */
    public static function kindFromPayType(?string $payType): ?string
    {
        switch ($payType) {
            case 'mensal':  return self::KIND_MENSAL;
            case 'hora':    return self::KIND_HORA;
            case 'projeto': return self::KIND_PARCELA;
            default:        return null;
        }
    }

    /**
     * Converte um valor em float aceitando número ou string "R$ 1.234,56".
     * Nunca negativo.
     */
    public static function money($v): float
    {
        if (is_int($v) || is_float($v)) return max(0.0, round((float)$v, 2));
        if ($v === null || $v === '') return 0.0;
        $s = trim((string)$v);
        if (preg_match('/^-?\d+(\.\d+)?$/', $s)) return max(0.0, round((float)$s, 2));
        // Formato BR: remove milhar "." e troca decimal "," por "."
        $s = preg_replace('/[^0-9,.-]/', '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? max(0.0, round((float)$s, 2)) : 0.0;
    }

    /**
     * Normaliza a quantidade de parcelas para a faixa [1, MAX_INSTALLMENTS].
     */
    public static function normalizeInstallments($n): int
    {
        $n = (int)$n;
        if ($n < 1) return self::DEFAULT_INSTALLMENTS;
        return min($n, self::MAX_INSTALLMENTS);
    }

    /**
     * Monta o plano de lançamentos a partir dos dados do prestador.
     *
     * @param array $provider  linha de providers (pay_type, pay_amount, ...)
     * @param int   $installments  nº de parcelas quando kind=parcela (default 1)
     * @return array<int,array{kind:string,description:string,amount:float,recurring_cycle:?string,installment_no:?int,installment_total:?int}>
     *         lista vazia se não há como gerar (pay_type inválido/ausente).
     */
    public static function buildPlanForProvider(array $provider, int $installments = self::DEFAULT_INSTALLMENTS): array
    {
        $payType = ProviderRules::normalizePayType($provider['pay_type'] ?? null);
        $kind = self::kindFromPayType($payType);
        if ($kind === null) return [];

        $amount = self::money($provider['pay_amount'] ?? null);
        $name = trim((string)($provider['name'] ?? 'Prestador'));

        if ($kind === self::KIND_MENSAL) {
            return [[
                'kind'              => self::KIND_MENSAL,
                'description'       => "Pagamento mensal — {$name}",
                'amount'            => $amount,
                'recurring_cycle'   => 'monthly',
                'installment_no'    => null,
                'installment_total' => null,
            ]];
        }

        if ($kind === self::KIND_HORA) {
            // Por hora: valor liquidado no fechamento do período (horas x valor/hora).
            // Registra 1 lançamento base pendente; o valor fica 0 até o fechamento.
            return [[
                'kind'              => self::KIND_HORA,
                'description'       => "Pagamento por hora (a liquidar por período) — {$name}",
                'amount'            => 0.0,
                'recurring_cycle'   => null,
                'installment_no'    => null,
                'installment_total' => null,
            ]];
        }

        // Parcela (projeto): divide o total em N parcelas iguais, ajustando a
        // última para fechar a soma exatamente (evita sobra de centavos).
        $total = self::normalizeInstallments($installments);
        $base = $total > 0 ? round($amount / $total, 2) : $amount;
        $plan = [];
        $acc = 0.0;
        for ($i = 1; $i <= $total; $i++) {
            $value = ($i < $total) ? $base : round($amount - $acc, 2);
            $acc += $value;
            $plan[] = [
                'kind'              => self::KIND_PARCELA,
                'description'       => "Parcela {$i}/{$total} — {$name}",
                'amount'            => max(0.0, $value),
                'recurring_cycle'   => null,
                'installment_no'    => $i,
                'installment_total' => $total,
            ];
        }
        return $plan;
    }

    /** Soma dos valores de um plano (para conferência/exibição). */
    public static function planTotal(array $plan): float
    {
        $sum = 0.0;
        foreach ($plan as $p) $sum += (float)($p['amount'] ?? 0);
        return round($sum, 2);
    }
}
