<?php

/**
 * Regras puras (sem banco/HTTP) dos filtros e relatórios do Controle
 * Financeiro (super_admin).
 *
 *  - normalizeFilters: valida o que vem da URL (período, tipo, direção,
 *    situação, categoria, conta, cartão, pessoa, texto, agrupamento).
 *  - matches: aplica os filtros a uma linha (inclusive às previstas dos
 *    recorrentes, que não vêm do banco).
 *  - totals / groupTotals: totais da lista filtrada e o relatório agrupado.
 *  - exportRows: linhas da planilha.
 *
 * Situação (status):
 *  - paid      = pago
 *  - pending   = em aberto (inclui atrasado e previsto)
 *  - overdue   = em aberto com vencimento antes de hoje
 *  - projected = só as ocorrências previstas dos recorrentes
 */
class FinancialReportRules
{
    public const STATUSES = ['paid', 'pending', 'overdue', 'projected'];
    public const GROUPS = ['category', 'kind', 'account', 'card', 'person', 'month', 'status'];
    public const KIND_LABELS = [
        'ganho' => 'Ganho', 'gasto' => 'Gasto', 'salario' => 'Salário',
        'emprestimo' => 'Empréstimo', 'transferencia' => 'Transferência',
    ];

    /**
     * Normaliza os filtros. Período: from/to (datas) têm prioridade; senão o
     * mês (YYYY-MM); senão o mês atual. Datas invertidas são trocadas.
     */
    public static function normalizeFilters(array $in, string $today): array
    {
        $date = function ($v): ?string {
            $v = trim((string)$v);
            if ($v === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1) return null;
            [$y, $m, $d] = array_map('intval', explode('-', $v));
            return checkdate($m, $d, $y) ? $v : null;
        };
        $id = fn($v) => (is_numeric($v) && (int)$v > 0) ? (int)$v : null;

        $from = $date($in['from'] ?? '');
        $to = $date($in['to'] ?? '');
        $month = FinancialRules::validMonth($in['month'] ?? null) ? (string)$in['month'] : substr($today, 0, 7);

        if ($from !== null || $to !== null) {
            // Só uma ponta: completa com o mesmo mês da outra.
            if ($from === null) $from = substr((string)$to, 0, 7) . '-01';
            if ($to === null) $to = date('Y-m-t', strtotime((string)$from));
            if ($from > $to) [$from, $to] = [$to, $from];
            $mode = 'range';
        } else {
            $from = $month . '-01';
            $to = date('Y-m-t', strtotime($from));
            $mode = 'month';
        }

        $kind = (string)($in['kind'] ?? '');
        $direction = (string)($in['direction'] ?? '');
        $status = (string)($in['status'] ?? '');
        $group = (string)($in['group'] ?? '');

        return [
            'mode'        => $mode,
            'month'       => $mode === 'month' ? $month : substr($from, 0, 7),
            'from'        => $from,
            'to'          => $to,
            'kind'        => in_array($kind, FinancialRules::KINDS, true) ? $kind : '',
            'direction'   => in_array($direction, ['in', 'out'], true) ? $direction : '',
            'status'      => in_array($status, self::STATUSES, true) ? $status : '',
            'category_id' => $id($in['category_id'] ?? null),
            'account_id'  => $id($in['account_id'] ?? null),
            'card_id'     => $id($in['card_id'] ?? null),
            'user_id'     => $id($in['user_id'] ?? null),
            'source'      => in_array(($in['source'] ?? ''), ['manual', 'asaas'], true) ? (string)$in['source'] : '',
            'q'           => mb_substr(trim((string)($in['q'] ?? '')), 0, 100),
            'group'       => in_array($group, self::GROUPS, true) ? $group : '',
        ];
    }

    /** Algum filtro além do período está ativo? */
    public static function hasActiveFilters(array $f): bool
    {
        foreach (['kind', 'direction', 'status', 'source', 'q'] as $k) if (($f[$k] ?? '') !== '') return true;
        foreach (['category_id', 'account_id', 'card_id', 'user_id'] as $k) if (!empty($f[$k])) return true;
        return false;
    }

    /** A linha passa nos filtros (período, situação e demais)? */
    public static function matches(array $r, array $f, string $today): bool
    {
        $due = substr((string)($r['due_date'] ?? ''), 0, 10);
        if ($due === '' || $due < $f['from'] || $due > $f['to']) return false;

        if ($f['kind'] !== '' && ($r['kind'] ?? '') !== $f['kind']) return false;
        if ($f['direction'] !== '' && ($r['direction'] ?? '') !== $f['direction']) return false;
        if ($f['source'] !== '' && ($r['source'] ?? '') !== $f['source']) return false;
        foreach (['category_id', 'account_id', 'card_id', 'user_id'] as $k) {
            if (!empty($f[$k]) && (int)($r[$k] ?? 0) !== (int)$f[$k]) return false;
        }

        $status = $r['status'] ?? 'pending';
        $projected = !empty($r['is_projected']);
        switch ($f['status']) {
            case 'paid':      if ($status !== 'paid') return false; break;
            case 'pending':   if ($status !== 'pending') return false; break;
            case 'overdue':   if ($status !== 'pending' || $projected || $due >= $today) return false; break;
            case 'projected': if (!$projected) return false; break;
        }

        if ($f['q'] !== '') {
            $hay = mb_strtolower(implode(' ', [
                $r['description'] ?? '', $r['category_name'] ?? '', $r['category'] ?? '',
                $r['person_name'] ?? '', $r['bank_account_name'] ?? '', $r['card_name'] ?? '',
            ]));
            if (mb_strpos($hay, mb_strtolower($f['q'])) === false) return false;
        }
        return true;
    }

    /** Totais de uma lista de linhas (canceladas ficam de fora). */
    public static function totals(array $rows): array
    {
        $t = ['count' => 0, 'in' => 0.0, 'out' => 0.0, 'net' => 0.0,
              'paid_in' => 0.0, 'paid_out' => 0.0, 'open_in' => 0.0, 'open_out' => 0.0];
        foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'cancelled') continue;
            $amt = (float)($r['amount'] ?? 0);
            $in = ($r['direction'] ?? 'out') === 'in';
            $paid = ($r['status'] ?? '') === 'paid';
            $t['count']++;
            if ($in) { $t['in'] += $amt; $t[$paid ? 'paid_in' : 'open_in'] += $amt; }
            else     { $t['out'] += $amt; $t[$paid ? 'paid_out' : 'open_out'] += $amt; }
        }
        foreach ($t as $k => $v) if ($k !== 'count') $t[$k] = round($v, 2);
        $t['net'] = round($t['in'] - $t['out'], 2);
        return $t;
    }

    /** Rótulo do grupo de uma linha. */
    public static function groupLabel(array $r, string $group): string
    {
        switch ($group) {
            case 'category': return (string)($r['category_name'] ?? '') ?: ((string)($r['category'] ?? '') ?: 'Sem categoria');
            case 'kind':     return self::KIND_LABELS[$r['kind'] ?? ''] ?? (string)($r['kind'] ?? '—');
            case 'account':  return (string)($r['bank_account_name'] ?? '') ?: 'Sem conta';
            case 'card':     return (string)($r['card_name'] ?? '') ?: 'Sem cartão';
            case 'person':   return (string)($r['person_name'] ?? '') ?: 'Sem pessoa';
            case 'month':    return substr((string)($r['due_date'] ?? ''), 0, 7) ?: '—';
            case 'status':
                if (!empty($r['is_projected'])) return 'Previsto';
                return ['paid' => 'Pago', 'pending' => 'Em aberto', 'cancelled' => 'Cancelado'][$r['status'] ?? ''] ?? '—';
        }
        return '—';
    }

    /**
     * Relatório agrupado: uma linha por grupo com quantidade, entradas,
     * saídas, saldo, pago e em aberto. Ordenado pelo maior movimento
     * (entradas + saídas); por mês, em ordem cronológica.
     *
     * @return array<int,array> cada item: label + chaves de totals()
     */
    public static function groupTotals(array $rows, string $group): array
    {
        if (!in_array($group, self::GROUPS, true)) return [];
        $buckets = [];
        foreach ($rows as $r) {
            $buckets[self::groupLabel($r, $group)][] = $r;
        }
        $out = [];
        foreach ($buckets as $label => $items) {
            $out[] = ['label' => (string)$label] + self::totals($items);
        }
        usort($out, $group === 'month'
            ? fn($a, $b) => strcmp($a['label'], $b['label'])
            : fn($a, $b) => (($b['in'] + $b['out']) <=> ($a['in'] + $a['out'])) ?: strcmp($a['label'], $b['label']));
        return $out;
    }

    /** Cabeçalho e linhas da planilha (valores formatados em R$). */
    public static function exportRows(array $rows): array
    {
        $money = fn($v) => number_format((float)$v, 2, ',', '.');
        $headers = ['Vencimento', 'Data da compra', 'Tipo', 'Direção', 'Categoria', 'Descrição', 'Pessoa',
                    'Conta', 'Cartão', 'Parcela', 'Valor (R$)', 'Situação', 'Pago em', 'Origem'];
        $lines = [];
        foreach ($rows as $r) {
            $parcela = !empty($r['installment_no']) ? ((int)$r['installment_no'] . '/' . (int)($r['installments'] ?? 1)) : '';
            $lines[] = [
                (string)($r['due_date'] ?? ''),
                (string)($r['purchase_date'] ?? ''),
                self::KIND_LABELS[$r['kind'] ?? ''] ?? (string)($r['kind'] ?? ''),
                ($r['direction'] ?? '') === 'in' ? 'Entrada' : 'Saída',
                self::groupLabel($r, 'category'),
                (string)($r['description'] ?? ''),
                (string)($r['person_name'] ?? ''),
                (string)($r['bank_account_name'] ?? ''),
                (string)($r['card_name'] ?? ''),
                $parcela,
                (($r['direction'] ?? '') === 'in' ? '' : '-') . $money($r['amount'] ?? 0),
                self::groupLabel($r, 'status'),
                !empty($r['paid_at']) ? substr((string)$r['paid_at'], 0, 10) : '',
                ($r['source'] ?? '') === 'asaas' ? 'Asaas' : 'Manual',
            ];
        }
        return ['headers' => $headers, 'rows' => $lines];
    }

    /** Nome do arquivo exportado (ex.: financeiro_2026-11-01_a_2026-11-30.xlsx). */
    public static function fileName(array $f, string $ext = 'xlsx'): string
    {
        return 'financeiro_' . $f['from'] . '_a_' . $f['to'] . '.' . $ext;
    }
}
