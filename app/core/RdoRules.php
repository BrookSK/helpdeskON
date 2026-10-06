<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo RDO (Relatório Diário).
 *
 * Fonte única e testável para: whitelist/normalização de status, rótulos,
 * visibilidade por papel, controle de prazo, bloqueio e fluxo de aprovação.
 *
 * Visibilidade:
 *   - super_admin: vê o relatório de qualquer pessoa.
 *   - developer:   vê apenas o próprio (mesmo tendo acesso amplo ao sistema).
 *   - demais papéis internos: veem apenas o próprio.
 *
 * Prazo e bloqueio:
 *   - O horário limite é configurável (padrão 19:00:00) e lido de settings.
 *   - Após o prazo do dia, o relatório fica is_locked=1 e qualquer edição
 *     direta é recusada; o usuário pode solicitar desbloqueio ao admin.
 *   - Relatórios de dias anteriores exigem aprovação para qualquer edição.
 */
class RdoRules
{
    // =========================================================================
    // Constantes de status e configuração
    // =========================================================================

    /** Status válidos, em ordem de fluxo. */
    public const STATUSES = ['em_andamento', 'finalizado'];

    /** Rótulos amigáveis de status. */
    public const STATUS_LABELS = [
        'em_andamento' => 'Em andamento',
        'finalizado'   => 'Finalizado',
    ];

    /** Tipos de colaborador aceitos. */
    public const COLLABORATOR_KINDS = ['colaborador', 'prestador'];

    /** Papéis que podem ver o RDO de QUALQUER pessoa (visão global). */
    public const GLOBAL_VIEW_ROLES = ['super_admin'];

    /** Papéis que podem aprovar/recusar revisões. */
    public const REVIEWER_ROLES = ['super_admin'];

    /** Horário limite padrão (HH:MM:SS). Sobrescrito pela config rdo_deadline_time. */
    public const DEFAULT_DEADLINE = '19:00:00';

    /** Chave da configuração de horário limite na tabela settings. */
    public const DEADLINE_SETTING_KEY = 'rdo_deadline_time';

    /** Tipos válidos de revisão. */
    public const REVIEW_TYPES = ['late_fill', 'post_deadline', 'edit_request', 'unlock_request'];

    /** Status válidos de revisão. */
    public const REVIEW_STATUSES = ['pending', 'approved', 'rejected'];

    // =========================================================================
    // Status
    // =========================================================================

    /** Um status é válido para persistência? */
    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    /** Normaliza o status: retorna o valor se válido, senão o default. */
    public static function normalizeStatus($value, string $default = 'em_andamento'): string
    {
        return in_array($value, self::STATUSES, true) ? $value : $default;
    }

    /** Rótulo amigável de um status. */
    public static function label($status): string
    {
        return self::STATUS_LABELS[$status] ?? (string) $status;
    }

    // =========================================================================
    // Colaboradores
    // =========================================================================

    /** Normaliza o tipo de colaborador. */
    public static function normalizeCollaboratorKind($value, string $default = 'colaborador'): string
    {
        return in_array($value, self::COLLABORATOR_KINDS, true) ? $value : $default;
    }

    // =========================================================================
    // Empresa / projeto
    // =========================================================================

    /**
     * Normaliza o projeto/obra (company_id) do RDO. Vazio, zero, negativo ou
     * não-numérico viram null ("sem projeto"). Caso contrário, o inteiro.
     */
    public static function normalizeCompanyId($value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    // =========================================================================
    // Ocorrências
    // =========================================================================

    /**
     * Deriva a flag has_occurrence a partir do texto de ocorrências: se houver
     * qualquer conteúdo não vazio, marca 1.
     */
    public static function deriveHasOccurrence(?string $occurrences): int
    {
        return (trim((string) $occurrences) !== '') ? 1 : 0;
    }

    // =========================================================================
    // Visibilidade por papel
    // =========================================================================

    /** O papel enxerga relatórios de TODAS as pessoas? */
    public static function hasGlobalView(?string $role): bool
    {
        return $role !== null && in_array($role, self::GLOBAL_VIEW_ROLES, true);
    }

    /**
     * O visualizador ($viewerRole/$viewerId) pode ver o relatório cujo dono é
     * $ownerId? super_admin vê todos; qualquer outro papel (inclusive developer)
     * só vê o próprio.
     */
    public static function canViewReportOf(?string $viewerRole, $viewerId, $ownerId): bool
    {
        if (self::hasGlobalView($viewerRole)) {
            return true;
        }
        return (int) $viewerId === (int) $ownerId && (int) $viewerId > 0;
    }

    // =========================================================================
    // Aprovação / revisão
    // =========================================================================

    /** O papel pode aprovar/recusar revisões? */
    public static function canReview(?string $role): bool
    {
        return $role !== null && in_array($role, self::REVIEWER_ROLES, true);
    }

    // =========================================================================
    // Prazo e bloqueio
    // =========================================================================

    /**
     * Retorna o horário limite configurado (HH:MM:SS), ou o padrão se ausente.
     * Lê a config via Config::get() quando disponível; senão usa a constante.
     *
     * @param  string|null $override  Horário já carregado (para injeção em testes).
     */
    public static function getDeadline(?string $override = null): string
    {
        if ($override !== null) {
            return self::normalizeDeadlineTime($override);
        }
        if (class_exists('Config')) {
            $v = Config::get(self::DEADLINE_SETTING_KEY);
            if ($v !== null && $v !== '') {
                return self::normalizeDeadlineTime($v);
            }
        }
        return self::DEFAULT_DEADLINE;
    }

    /**
     * Garante que o horário está no formato HH:MM:SS.
     * Aceita "HH:MM" e completa com ":00". Valor inválido retorna o padrão.
     */
    public static function normalizeDeadlineTime(string $value): string
    {
        $v = trim($value);
        // Aceita HH:MM ou HH:MM:SS
        if (preg_match('/^\d{2}:\d{2}$/', $v)) {
            $v .= ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
            return $v;
        }
        return self::DEFAULT_DEADLINE;
    }

    /**
     * O preenchimento ocorreu dentro do prazo para a data do relatório?
     *
     * Regra:
     *  - Se $reportDate === hoje E $nowTime <= $deadlineTime → dentro do prazo.
     *  - Se $reportDate === hoje E $nowTime >  $deadlineTime → fora do prazo.
     *  - Se $reportDate <  hoje (dia anterior)               → fora do prazo.
     *  - Se $reportDate >  hoje (data futura)                → dentro do prazo
     *    (não bloqueia criação antecipada).
     *
     * @param string $reportDate   Formato Y-m-d.
     * @param string $nowTime      Horário atual HH:MM:SS (injetável em testes).
     * @param string $today        Data atual Y-m-d     (injetável em testes).
     * @param string $deadlineTime Horário limite HH:MM:SS.
     */
    public static function isWithinDeadline(
        string $reportDate,
        string $nowTime,
        string $today,
        string $deadlineTime
    ): bool {
        if ($reportDate > $today) {
            // Data futura: não bloqueia.
            return true;
        }
        if ($reportDate < $today) {
            // Dia anterior: sempre fora do prazo.
            return false;
        }
        // Mesmo dia: compara horários como strings "HH:MM:SS".
        return $nowTime <= $deadlineTime;
    }

    /**
     * O relatório requer aprovação para ser editado?
     *
     * Sim, se:
     *  - A data do relatório é anterior a hoje (independentemente do bloqueio), OU
     *  - O relatório já está bloqueado (is_locked = 1).
     *
     * @param string $reportDate  Formato Y-m-d.
     * @param int    $isLocked    Valor do campo is_locked (0 ou 1).
     * @param string $today       Data atual Y-m-d (injetável em testes).
     */
    public static function requiresApprovalForEdit(
        string $reportDate,
        int $isLocked,
        string $today
    ): bool {
        return $reportDate < $today || $isLocked === 1;
    }

    /**
     * A criação/primeiro preenchimento deste relatório gera pendência de revisão?
     *
     * Sim, se:
     *  - $reportDate < $today (preenchimento retroativo — "post_deadline"), OU
     *  - $reportDate === $today E $nowTime > $deadlineTime (passou do horário — "late_fill").
     *
     * @param string $reportDate   Formato Y-m-d.
     * @param string $nowTime      Horário atual HH:MM:SS.
     * @param string $today        Data atual Y-m-d.
     * @param string $deadlineTime Horário limite HH:MM:SS.
     * @return string|null  Tipo de revisão ('late_fill' | 'post_deadline') ou null se não precisa.
     */
    public static function reviewTypeForCreate(
        string $reportDate,
        string $nowTime,
        string $today,
        string $deadlineTime
    ): ?string {
        if ($reportDate < $today) {
            return 'post_deadline';
        }
        if ($reportDate === $today && $nowTime > $deadlineTime) {
            return 'late_fill';
        }
        return null;
    }

    /**
     * Monta o snapshot dos campos editáveis de um relatório para persistir
     * no histórico ou em um draft de edição.
     *
     * @param array $report  Linha da tabela daily_reports.
     * @return string  JSON pronto para gravar em snapshot_json / payload_json.
     */
    public static function buildSnapshot(array $report): string
    {
        return json_encode([
            'title'         => $report['title']         ?? null,
            'activities'    => $report['activities']    ?? null,
            'occurrences'   => $report['occurrences']   ?? null,
            'pending_tasks' => $report['pending_tasks'] ?? null,
            'next_day_plan' => $report['next_day_plan'] ?? null,
            'status'        => $report['status']        ?? null,
            'company_id'    => isset($report['company_id']) ? (int) $report['company_id'] : null,
            'report_date'   => $report['report_date']   ?? null,
        ], JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // Ausências (dias úteis sem relatório)
    // =========================================================================

    /** Nº de dias no período de varredura de ausências (padrão: 30). */
    public const MISSING_SCAN_DAYS = 30;

    /**
     * Lista os dias ÚTEIS (segunda a sexta) de um período [start, end],
     * inclusivo, em formato Y-m-d e ordem crescente.
     *
     * Função pura (não lê relógio nem banco): as datas-limite são injetadas,
     * o que facilita o teste e torna a regra a fonte única de verdade sobre
     * "quais dias exigem relatório".
     *
     * @param string $start Data inicial Y-m-d (inclusive).
     * @param string $end   Data final   Y-m-d (inclusive).
     * @return string[]     Lista de datas úteis Y-m-d (vazia se $start > $end).
     */
    public static function businessDaysInRange(string $start, string $end): array
    {
        $out = [];
        if ($start > $end) {
            return $out;
        }
        $cur = strtotime($start . ' 00:00:00');
        $max = strtotime($end . ' 00:00:00');
        if ($cur === false || $max === false) {
            return $out;
        }
        while ($cur <= $max) {
            // date('N'): 1 (segunda) a 7 (domingo). Dias úteis = 1..5.
            if ((int) date('N', $cur) <= 5) {
                $out[] = date('Y-m-d', $cur);
            }
            $cur = strtotime('+1 day', $cur);
        }
        return $out;
    }

    /**
     * A partir das datas úteis do período e do conjunto de datas que o usuário
     * JÁ possui relatório, retorna as datas úteis sem relatório (ausências),
     * em ordem decrescente (mais recentes primeiro).
     *
     * @param string[] $businessDays Datas úteis do período (Y-m-d).
     * @param string[] $filledDates  Datas Y-m-d que já têm relatório.
     * @return string[]              Datas úteis sem relatório, desc.
     */
    public static function missingDates(array $businessDays, array $filledDates): array
    {
        $filled = array_flip($filledDates);
        $missing = [];
        foreach ($businessDays as $d) {
            if (!isset($filled[$d])) {
                $missing[] = $d;
            }
        }
        rsort($missing);
        return $missing;
    }
}
