<?php

/**
 * Regras puras (sem banco/HTTP) para as VISÕES do Relatório Diário (RDO):
 * Kanban (colunas por empresa) e Calendário (eventos por dia).
 *
 * O objetivo é concentrar aqui, de forma testável, a lógica de agrupamento e
 * normalização que antes ficaria espalhada no JavaScript da tela. O controller
 * continua devolvendo a mesma lista de `rdo/list`; estas funções apenas
 * reorganizam/validam esses itens.
 *
 * Convenções de item: cada relatório é um array associativo com, no mínimo,
 * as chaves `id`, `report_date`, `status`, `company_id`, `company_name`.
 */
class RdoViewRules
{
    /** Visões disponíveis na tela de RDO, na ordem dos botões. */
    public const VIEWS = ['calendar', 'kanban', 'list'];

    /** Visão padrão ao abrir a tela (requisito: calendário é o default). */
    public const DEFAULT_VIEW = 'calendar';

    /** Chave usada para a coluna "sem empresa/cliente" no Kanban. */
    public const NO_COMPANY_KEY = 'none';

    /** Rótulo da coluna "sem empresa/cliente". */
    public const NO_COMPANY_LABEL = 'Sem cliente';

    /** Uma visão é válida? */
    public static function isValidView($value): bool
    {
        return in_array($value, self::VIEWS, true);
    }

    /** Normaliza a visão: retorna o valor se válido, senão a visão padrão. */
    public static function normalizeView($value): string
    {
        return self::isValidView($value) ? $value : self::DEFAULT_VIEW;
    }

    /**
     * Chave de agrupamento de um item por empresa. Itens sem company_id (vazio,
     * zero, negativo ou não-numérico) caem na coluna especial NO_COMPANY_KEY.
     * Caso contrário, "c" + id (para não colidir com a chave "none").
     */
    public static function companyKey($item): string
    {
        $id = $item['company_id'] ?? null;
        if ($id === null || $id === '' || !is_numeric($id) || (int) $id <= 0) {
            return self::NO_COMPANY_KEY;
        }
        return 'c' . (int) $id;
    }

    /**
     * Agrupa relatórios em colunas por empresa (Kanban opção B).
     *
     * Retorna uma lista ordenada de colunas, cada uma:
     *   ['key' => 'c3', 'company_id' => 3, 'company_name' => 'ACME', 'items' => [...]]
     *
     * Ordenação: empresas em ordem alfabética (case-insensitive, locale pt),
     * e a coluna "Sem cliente" sempre por último. Dentro de cada coluna, a
     * ordem dos itens é preservada tal como veio (o backend já ordena por
     * data desc).
     */
    public static function groupByCompany(array $items): array
    {
        $cols = [];
        foreach ($items as $it) {
            $key = self::companyKey($it);
            if (!isset($cols[$key])) {
                $isNone = ($key === self::NO_COMPANY_KEY);
                $cols[$key] = [
                    'key' => $key,
                    'company_id' => $isNone ? null : (int) $it['company_id'],
                    'company_name' => $isNone
                        ? self::NO_COMPANY_LABEL
                        : (string) ($it['company_name'] ?? self::NO_COMPANY_LABEL),
                    'items' => [],
                ];
            }
            $cols[$key]['items'][] = $it;
        }

        $list = array_values($cols);
        usort($list, static function ($a, $b) {
            // "Sem cliente" sempre por último.
            $aNone = ($a['key'] === self::NO_COMPANY_KEY);
            $bNone = ($b['key'] === self::NO_COMPANY_KEY);
            if ($aNone !== $bNone) {
                return $aNone ? 1 : -1;
            }
            return strcasecmp((string) $a['company_name'], (string) $b['company_name']);
        });

        return $list;
    }

    /**
     * Agrupa relatórios por dia (YYYY-MM-DD) a partir de report_date, para o
     * calendário. Itens sem report_date válido são ignorados (não têm dia).
     *
     * Retorna um mapa: ['2026-10-05' => [item, item, ...], ...].
     */
    public static function eventsByDay(array $items): array
    {
        $byDay = [];
        foreach ($items as $it) {
            $day = self::dayKey($it['report_date'] ?? null);
            if ($day === null) {
                continue;
            }
            $byDay[$day][] = $it;
        }
        return $byDay;
    }

    /**
     * Extrai a chave de dia (YYYY-MM-DD) de um report_date. Aceita tanto
     * "2026-10-05" quanto "2026-10-05 13:40:00"/"2026-10-05T13:40". Retorna
     * null se não houver um prefixo de data válido.
     */
    public static function dayKey($reportDate): ?string
    {
        if (!is_string($reportDate) || $reportDate === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $reportDate, $m)) {
            return $m[1];
        }
        return null;
    }
}
