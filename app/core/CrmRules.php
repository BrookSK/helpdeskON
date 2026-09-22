<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo CRM / comercial.
 *
 * Fonte única e testável para: parse de valor monetário no formato brasileiro,
 * detecção do tipo de coluna (Fechado/Perdido), normalização de telefone para
 * discagem (um único 55) e o cálculo de comissão (fechamento + prospecção).
 */
class CrmRules
{
    /**
     * Converte um texto de valor no formato BR para float.
     *
     * Aceita: "R$ 5.000,00" => 5000.00 ; "1.234,56" => 1234.56 ;
     *         "5000" => 5000.0 ; "5,50" => 5.50 ; "" / null => null.
     *
     * Regra: o ÚLTIMO separador (',' ou '.') é o decimal; os demais são milhar.
     * Isso corrige o bug em que "R$ 5.000,00" virava 500000 (removia a vírgula).
     */
    public static function parseMoneyBR($text): ?float
    {
        if ($text === null) return null;
        $s = trim((string) $text);
        if ($s === '') return null;

        // Mantém só dígitos e separadores.
        $s = preg_replace('/[^\d.,]/', '', $s);
        if ($s === '') return null;

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        $decPos = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($decPos === -1) {
            // Sem separador: número inteiro puro.
            return (float) $s;
        }

        $decSep = $s[$decPos];
        $intPart = substr($s, 0, $decPos);
        $fracPart = substr($s, $decPos + 1);

        // Se o "decimal" tem mais de 2 dígitos, provavelmente é milhar, não decimal
        // (ex.: "5.000" => 5000, não 5.0). Nesse caso trata tudo como inteiro.
        if (strlen($fracPart) > 2 || strlen($fracPart) === 0) {
            $digits = preg_replace('/\D/', '', $s);
            return $digits === '' ? null : (float) $digits;
        }

        // Remove separadores de milhar da parte inteira.
        $intPart = preg_replace('/\D/', '', $intPart);
        $fracPart = preg_replace('/\D/', '', $fracPart);
        $normalized = ($intPart === '' ? '0' : $intPart) . '.' . $fracPart;
        return (float) $normalized;
    }

    /** A coluna (pelo nome) representa "negócio fechado/ganho"? */
    public static function isClosedColumn(?string $columnName): bool
    {
        $n = mb_strtolower(trim((string) $columnName));
        return in_array($n, ['fechado', 'ganho', 'convertido', 'fechado/ganho'], true);
    }

    /** A coluna (pelo nome) representa "perdido"? */
    public static function isLostColumn(?string $columnName): bool
    {
        $n = mb_strtolower(trim((string) $columnName));
        return in_array($n, ['perdido', 'perdido/descartado', 'descartado'], true);
    }

    /**
     * Normaliza um número para discagem, garantindo um ÚNICO prefixo 55 (Brasil).
     * Remove tudo que não é dígito e colapsa "55" repetidos no início.
     */
    public static function normalizeDialNumber($phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') return '';
        // Colapsa 55 repetidos no início (ex.: "5555119..." => "55119...").
        while (strpos($digits, '5555') === 0) {
            $digits = substr($digits, 2);
        }
        // Garante exatamente um 55 no começo.
        if (strpos($digits, '55') !== 0) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    /**
     * Calcula a comissão de um comercial a partir dos valores agregados.
     *
     * @param float $closedValue     Soma dos valores dos leads que ELE fechou.
     * @param float $prospectedValue Soma dos valores que ele prospectou e outro fechou.
     * @param float $closingPct      % de comissão de fechamento.
     * @param float $prospPct        % de comissão de prospecção.
     * @param float $legacyPct       % legado (fallback quando closingPct = 0).
     * @return array{closing:float, prospection:float, total:float}
     */
    public static function commission(
        float $closedValue,
        float $prospectedValue,
        float $closingPct,
        float $prospPct,
        float $legacyPct = 0.0
    ): array {
        $effectiveClosing = $closingPct ?: $legacyPct;
        $closing = $closedValue * $effectiveClosing / 100;
        $prospection = $prospectedValue * $prospPct / 100;
        return [
            'closing' => $closing,
            'prospection' => $prospection,
            'total' => $closing + $prospection,
        ];
    }
}
