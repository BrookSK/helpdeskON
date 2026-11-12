<?php
/**
 * Regras puras das datas da demanda (tickets.data_inicio, data_termino,
 * data_prevista_entrega).
 *
 * As datas são definidas pela EQUIPE, nunca pelo cliente:
 *  - Cliente: o formulário de nova demanda não mostra os campos e o backend
 *    ignora qualquer valor enviado (as datas ficam NULL até a equipe definir).
 *  - Equipe criando demanda (super_admin/developer): as três datas continuam
 *    obrigatórias no cadastro.
 *  - Equipe editando a demanda: datas opcionais (vazio limpa a data).
 */
final class DemandDateRules
{
    /** Campos de data da demanda, na ordem exibida. */
    public const FIELDS = ['data_inicio', 'data_termino', 'data_prevista_entrega'];

    /** Papéis da equipe que podem definir/editar as datas de uma demanda. */
    public const TEAM_ROLES = ['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst'];

    /** O papel pode ver e definir as datas da demanda? (cliente nunca pode) */
    public static function canSetDates(?string $role): bool
    {
        return in_array((string)$role, self::TEAM_ROLES, true);
    }

    /** Data válida no formato Y-m-d (rejeita datas inexistentes como 2026-02-30). */
    public static function isValidDate($value): bool
    {
        if (!is_string($value) || $value === '') return false;
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    /**
     * Lê e valida as três datas a partir da entrada (ex.: $_POST).
     *
     * @param bool $required true = as três são obrigatórias; false = vazio vira NULL.
     * @return array{ok:bool, error:?string, data:array<string,?string>}
     */
    public static function parse(array $in, bool $required): array
    {
        $data = [];
        foreach (self::FIELDS as $f) {
            $v = trim((string)($in[$f] ?? ''));
            $data[$f] = $v === '' ? null : $v;
        }

        if ($required && in_array(null, $data, true)) {
            return ['ok' => false, 'error' => 'Informe a data de início, a data de término e a previsão de término.', 'data' => $data];
        }
        foreach ($data as $v) {
            if ($v !== null && !self::isValidDate($v)) {
                return ['ok' => false, 'error' => 'Datas inválidas. Verifique a data de início, de término e a previsão de término.', 'data' => $data];
            }
        }
        return ['ok' => true, 'error' => null, 'data' => $data];
    }

    /**
     * Datas a gravar na criação da demanda conforme o papel de quem cria.
     * Cliente: nada é gravado (ignora o que vier no POST). Equipe: obrigatórias.
     *
     * @return array{ok:bool, error:?string, data:array<string,?string>}
     */
    public static function forCreate(?string $role, array $in): array
    {
        if (!self::canSetDates($role)) {
            return ['ok' => true, 'error' => null, 'data' => []];
        }
        return self::parse($in, true);
    }
}
