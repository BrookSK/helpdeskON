<?php

/**
 * Regras puras (sem banco) da comissão automática do CRM no Controle
 * Financeiro.
 *
 * Quando um card do CRM é fechado (lead_outcome = converted), cada comercial
 * envolvido ganha um lançamento "a pagar" no financeiro:
 *  - fechamento: quem fechou (converted_by), com o % de fechamento do cadastro
 *    (ou o % legado quando o de fechamento é zero, igual a CrmRules::commission);
 *  - prospecção: quem trouxe o lead (prospected_by), só quando foi OUTRA pessoa
 *    que fechou, com o % de prospecção do cadastro.
 * Base = valor do card (o 1º pagamento do cliente). % zero = sem lançamento.
 *
 * O vencimento acompanha o salário da pessoa (ver dueDate).
 */
class CrmCommissionRules
{
    public const ROLE_CLOSING = 'closing';
    public const ROLE_PROSPECTION = 'prospection';

    /** Dia de pagamento usado quando a pessoa não tem salário lançado. */
    public const FALLBACK_DAY = 5;

    /**
     * Comissões que o card deveria ter agora.
     *
     * @param array      $card       Linha de crm_cards (+ lead_name opcional).
     * @param array|null $closer     Usuário converted_by (id, name, commission_*).
     * @param array|null $prospector Usuário prospected_by.
     * @return array<string,array> role => [role, user_id, user_name, pct, base, amount, description]
     */
    public static function desired(array $card, ?array $closer, ?array $prospector): array
    {
        if (($card['lead_outcome'] ?? 'open') !== 'converted') return [];
        $base = round((float)($card['value'] ?? 0), 2);
        if ($base <= 0) return [];

        $lead = self::leadName($card);
        $out = [];

        if ($closer && !empty($closer['id'])) {
            $pct = (float)($closer['commission_closing_percent'] ?? 0) ?: (float)($closer['commission_percent'] ?? 0);
            $item = self::item(self::ROLE_CLOSING, $closer, $pct, $base, $lead);
            if ($item) $out[self::ROLE_CLOSING] = $item;
        }

        $closerId = (int)($card['converted_by'] ?? 0);
        if ($prospector && !empty($prospector['id']) && (int)$prospector['id'] !== $closerId) {
            $pct = (float)($prospector['commission_prospection_percent'] ?? 0);
            $item = self::item(self::ROLE_PROSPECTION, $prospector, $pct, $base, $lead);
            if ($item) $out[self::ROLE_PROSPECTION] = $item;
        }
        return $out;
    }

    private static function item(string $role, array $user, float $pct, float $base, string $lead): ?array
    {
        if ($pct <= 0) return null;
        $amount = round($base * $pct / 100, 2);
        if ($amount <= 0) return null;
        return [
            'role'        => $role,
            'user_id'     => (int)$user['id'],
            'user_name'   => (string)($user['name'] ?? ''),
            'pct'         => $pct,
            'base'        => $base,
            'amount'      => $amount,
            'description' => self::description($role, $lead, $pct, $base),
        ];
    }

    /** Nome do lead para a descrição: contato do WhatsApp, senão título do card. */
    public static function leadName(array $card): string
    {
        $name = trim((string)($card['lead_name'] ?? ''));
        if ($name === '') $name = trim((string)($card['title'] ?? ''));
        if ($name === '') $name = 'Card #' . (int)($card['id'] ?? 0);
        return $name;
    }

    /** Ex.: "Comissão de fechamento – Padaria Sol (5% de R$ 2.000,00)". */
    public static function description(string $role, string $lead, float $pct, float $base): string
    {
        $label = $role === self::ROLE_PROSPECTION ? 'prospecção' : 'fechamento';
        $pctTxt = rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',');
        $suffix = ' (' . $pctTxt . '% de R$ ' . number_format($base, 2, ',', '.') . ')';
        $head = 'Comissão de ' . $label . ' – ';
        $room = 255 - mb_strlen($head) - mb_strlen($suffix);
        if (mb_strlen($lead) > $room) $lead = rtrim(mb_substr($lead, 0, max(1, $room - 1))) . '…';
        return $head . $lead . $suffix;
    }

    /**
     * Vencimento "junto com o salário".
     *
     * $salaryRows = lançamentos de salário da pessoa (due_date, status), sem os
     * de comissão. Regra:
     *  1) salário ainda pendente com vencimento no dia do fechamento ou depois:
     *     vence junto com o mais próximo deles;
     *  2) senão, pelo dia do salário mais recente: próxima data com esse dia
     *     que seja no fechamento ou depois e depois daquele salário (dias 29–31
     *     viram o último dia em meses mais curtos);
     *  3) pessoa sem salário lançado: dia 5 do mês seguinte ao fechamento.
     */
    public static function dueDate(string $closingDate, array $salaryRows): string
    {
        $closing = date('Y-m-d', strtotime($closingDate) ?: time());

        $pending = [];
        $latest = null;
        foreach ($salaryRows as $r) {
            $due = (string)($r['due_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) continue;
            if (($r['status'] ?? '') === 'cancelled') continue;
            if (($r['status'] ?? '') === 'pending' && $due >= $closing) $pending[] = $due;
            if ($latest === null || $due > $latest) $latest = $due;
        }
        if (!empty($pending)) {
            sort($pending);
            return $pending[0];
        }

        if ($latest !== null) {
            $day = (int)substr($latest, 8, 2);
            $ym = substr($closing, 0, 7);
            for ($i = 0; $i < 36; $i++) {
                $first = $ym . '-01';
                $last = (int)date('t', strtotime($first));
                $cand = $ym . '-' . str_pad((string)min($day, $last), 2, '0', STR_PAD_LEFT);
                if ($cand >= $closing && $cand > $latest) return $cand;
                $ym = date('Y-m', strtotime($first . ' +1 month'));
            }
        }

        $next = date('Y-m', strtotime(substr($closing, 0, 7) . '-01 +1 month'));
        return $next . '-' . str_pad((string)self::FALLBACK_DAY, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Compara o que o card deveria ter ($desired) com os lançamentos já
     * ligados a ele ($existing: id, commission_role, user_id, amount, status,
     * description; sem os cancelados).
     *
     *  - pendente: acompanha o card (valor, pessoa, descrição) ou é cancelado
     *    quando a comissão deixou de existir (card reaberto/perdido, % zerado);
     *  - pago: nunca é alterado; se deixou de valer, vai para "paidOrphans"
     *    (aviso), e não gera outro;
     *  - sem lançamento: cria, só quando $allowCreate (o card acabou de ser
     *    fechado). Editar o valor de um card fechado não recria um lançamento
     *    que o financeiro excluiu.
     *
     * @return array{create:array,update:array,cancel:int[],paidOrphans:int[]}
     *         update: lista de [id, changes, desired].
     */
    public static function plan(array $desired, array $existing, bool $allowCreate): array
    {
        $plan = ['create' => [], 'update' => [], 'cancel' => [], 'paidOrphans' => []];

        $byRole = [];
        foreach ($existing as $e) {
            $role = (string)($e['commission_role'] ?? '');
            $byRole[$role][] = $e;
        }

        foreach ([self::ROLE_CLOSING, self::ROLE_PROSPECTION] as $role) {
            $want = $desired[$role] ?? null;
            $rows = $byRole[$role] ?? [];
            $paid = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'paid'));
            $pending = array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'pending'));

            if ($want === null) {
                foreach ($pending as $p) $plan['cancel'][] = (int)$p['id'];
                foreach ($paid as $p) $plan['paidOrphans'][] = (int)$p['id'];
                continue;
            }

            if (!empty($paid)) {
                // Já pago: fica como está; pendentes extras saem.
                foreach ($pending as $p) $plan['cancel'][] = (int)$p['id'];
                continue;
            }

            if (!empty($pending)) {
                $keep = array_shift($pending);
                foreach ($pending as $p) $plan['cancel'][] = (int)$p['id'];
                $changes = [];
                if (round((float)$keep['amount'], 2) !== $want['amount']) $changes['amount'] = $want['amount'];
                if ((int)($keep['user_id'] ?? 0) !== $want['user_id']) $changes['user_id'] = $want['user_id'];
                if ((string)($keep['description'] ?? '') !== $want['description']) $changes['description'] = $want['description'];
                if (!empty($changes)) $plan['update'][] = [(int)$keep['id'], $changes, $want];
                continue;
            }

            if ($allowCreate) $plan['create'][] = $want;
        }

        // Linhas com papel desconhecido (não deveria acontecer): pendentes saem.
        foreach ($byRole as $role => $rows) {
            if (in_array($role, [self::ROLE_CLOSING, self::ROLE_PROSPECTION], true)) continue;
            foreach ($rows as $r) {
                if (($r['status'] ?? '') === 'pending') $plan['cancel'][] = (int)$r['id'];
            }
        }
        return $plan;
    }
}
