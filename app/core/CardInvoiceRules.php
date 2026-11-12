<?php

/**
 * Regras puras (sem banco/HTTP) do ciclo de fatura de cartão de crédito.
 *
 * Uma compra no cartão não sai do caixa na data da compra: ela entra na
 * fatura que ainda está aberta e só é paga no vencimento dessa fatura.
 *
 * Regras:
 *  - Compra ANTES do dia de fechamento entra na fatura que fecha neste mês.
 *  - Compra NO dia de fechamento ou depois entra na fatura do mês seguinte
 *    (o dia de fechamento é o "melhor dia de compra").
 *  - Vencimento: se o dia de vencimento é maior que o de fechamento, a fatura
 *    vence no mesmo mês em que fecha (ex.: fecha 5, vence 15). Senão, vence no
 *    mês seguinte ao fechamento (ex.: fecha 14, vence 1).
 *  - Dias que não existem no mês (29-31) são ajustados para o último dia.
 *  - Compra parcelada: a 1ª parcela cai na fatura calculada acima e cada
 *    parcela seguinte cai na fatura do mês seguinte.
 *  - Cartão sem fechamento/vencimento cadastrado: não há ciclo, a data de
 *    vencimento é a própria data da compra.
 */
class CardInvoiceRules
{
    /** O cartão tem ciclo de fatura configurado? */
    public static function hasCycle(?int $closingDay, ?int $dueDay): bool
    {
        return $closingDay !== null && $closingDay >= 1 && $closingDay <= 31
            && $dueDay !== null && $dueDay >= 1 && $dueDay <= 31;
    }

    /**
     * Fatura em que uma compra cai.
     *
     * @return array{closing_date:string,due_date:string,invoice_month:string}
     *   invoice_month = mês (YYYY-MM) do vencimento, que é o mês do caixa.
     */
    public static function invoiceFor(string $purchaseDate, ?int $closingDay, ?int $dueDay): array
    {
        $ts = strtotime($purchaseDate);
        if ($ts === false) $ts = time();
        $purchase = date('Y-m-d', $ts);

        if (!self::hasCycle($closingDay, $dueDay)) {
            return [
                'closing_date'  => $purchase,
                'due_date'      => $purchase,
                'invoice_month' => substr($purchase, 0, 7),
            ];
        }

        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        $d = (int)date('j', $ts);

        // Mês em que a fatura dessa compra fecha.
        $closeThisMonth = self::clampDay($y, $m, (int)$closingDay);
        [$cy, $cm] = ($d < $closeThisMonth) ? [$y, $m] : self::addMonths($y, $m, 1);
        $closingDate = sprintf('%04d-%02d-%02d', $cy, $cm, self::clampDay($cy, $cm, (int)$closingDay));

        // Mês em que essa fatura vence.
        [$dy, $dm] = ((int)$dueDay > (int)$closingDay) ? [$cy, $cm] : self::addMonths($cy, $cm, 1);
        $dueDate = sprintf('%04d-%02d-%02d', $dy, $dm, self::clampDay($dy, $dm, (int)$dueDay));

        return [
            'closing_date'  => $closingDate,
            'due_date'      => $dueDate,
            'invoice_month' => substr($dueDate, 0, 7),
        ];
    }

    /**
     * Vencimentos de cada parcela de uma compra parcelada no cartão.
     * A 1ª cai na fatura da compra; as demais, uma por mês, no dia de
     * vencimento do cartão (ajustado ao tamanho do mês).
     *
     * @return string[] datas Y-m-d, uma por parcela
     */
    public static function installmentDueDates(string $purchaseDate, ?int $closingDay, ?int $dueDay, int $count): array
    {
        $count = max(1, min(48, $count));
        $first = self::invoiceFor($purchaseDate, $closingDay, $dueDay)['due_date'];
        $y = (int)substr($first, 0, 4);
        $m = (int)substr($first, 5, 2);
        // Sem ciclo, repete o dia da própria compra.
        $day = self::hasCycle($closingDay, $dueDay) ? (int)$dueDay : (int)substr($first, 8, 2);

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            [$iy, $im] = self::addMonths($y, $m, $i);
            $out[] = sprintf('%04d-%02d-%02d', $iy, $im, self::clampDay($iy, $im, $day));
        }
        return $out;
    }

    /** Dia ajustado ao último dia do mês quando não existe (ex.: 31 em abril). */
    public static function clampDay(int $year, int $month, int $day): int
    {
        $last = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        return max(1, min($day, $last));
    }

    /** Soma meses a (ano, mês) sem o estouro do strtotime('+1 month'). */
    public static function addMonths(int $year, int $month, int $add): array
    {
        $idx = ($year * 12 + ($month - 1)) + $add;
        return [intdiv($idx, 12), ($idx % 12) + 1];
    }
}
