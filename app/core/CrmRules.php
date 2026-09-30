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

    // Nomes canônicos das colunas terminais de perda no board de prospecção.
    // O SequenceEngine e a etiqueta manual movem o card para estas colunas.
    public const COLUMN_NOT_INTERESTED = 'Sem Interesse';
    public const COLUMN_NO_REPLY = 'Sem Resposta';

    /**
     * A coluna (pelo nome) representa "perdido"?
     *
     * Inclui as colunas terminais de prospecção ("Sem Interesse" e "Sem Resposta"):
     * assim, arrastar manualmente um card para qualquer uma delas já marca o
     * desfecho como perdido (via CrmController::moveCard), do mesmo jeito que
     * "Perdido"/"Descartado".
     */
    public static function isLostColumn(?string $columnName): bool
    {
        $n = mb_strtolower(trim((string) $columnName));
        return in_array($n, ['perdido', 'perdido/descartado', 'descartado'], true)
            || self::isNotInterestedColumn($columnName)
            || self::isNoReplyColumn($columnName);
    }

    /** A coluna (pelo nome) representa "não interessado" (respondeu recusando)? */
    public static function isNotInterestedColumn(?string $columnName): bool
    {
        $n = mb_strtolower(trim((string) $columnName));
        return in_array($n, ['sem interesse', 'não interessado', 'nao interessado', 'não interessados', 'nao interessados'], true);
    }

    /** A coluna (pelo nome) representa "sem resposta" (nunca respondeu)? */
    public static function isNoReplyColumn(?string $columnName): bool
    {
        $n = mb_strtolower(trim((string) $columnName));
        return in_array($n, ['sem resposta', 'sem retorno', 'nao respondeu', 'não respondeu'], true);
    }

    /**
     * Dois contatos são "irmãos" (a mesma pessoa em fichas diferentes) quando
     * compartilham uma chave FORTE de identidade: e-mail ou URL do LinkedIn.
     *
     * Usada para propagar o bloqueio (unsubscribed) entre duplicados: se um
     * contato recusa/entra em "Sem Interesse"/"Sem Resposta", os irmãos também
     * são bloqueados. O telefone é DELIBERADAMENTE excluído: "últimos 8 dígitos"
     * pode gerar falso positivo, e aqui a ação (bloquear envio) precisa ser
     * confiável. E-mail e LinkedIn são identificadores exatos.
     *
     * Regra pura: recebe os valores JÁ normalizados (e-mail em minúsculas sem
     * espaços; LinkedIn trimado) e compara. Vazio nunca casa.
     *
     * @param array{email?:?string,linkedin?:?string} $a
     * @param array{email?:?string,linkedin?:?string} $b
     */
    public static function isSameLead(array $a, array $b): bool
    {
        $emailA = self::normStrongKey($a['email'] ?? null);
        $emailB = self::normStrongKey($b['email'] ?? null);
        if ($emailA !== null && $emailA === $emailB) {
            return true;
        }

        $liA = self::normStrongKey($a['linkedin'] ?? null);
        $liB = self::normStrongKey($b['linkedin'] ?? null);
        if ($liA !== null && $liA === $liB) {
            return true;
        }

        return false;
    }

    /** Normaliza uma chave forte para comparação: minúsculas, trim; vazio => null. */
    private static function normStrongKey($value): ?string
    {
        $v = mb_strtolower(trim((string) $value));
        return $v !== '' ? $v : null;
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
