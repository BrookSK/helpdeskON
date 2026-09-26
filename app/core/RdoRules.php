<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo RDO (Relatório Diário).
 *
 * Fonte única e testável para: whitelist/normalização de status, rótulos e —
 * o ponto central — a regra de VISIBILIDADE por papel:
 *   - super_admin: vê o relatório de qualquer pessoa.
 *   - developer:   vê apenas o próprio (mesmo tendo acesso amplo ao sistema).
 *   - demais papéis internos: veem apenas o próprio.
 */
class RdoRules
{
    /** Status válidos, em ordem de fluxo. */
    public const STATUSES = ['em_andamento', 'finalizado'];

    /** Rótulos amigáveis. */
    public const STATUS_LABELS = [
        'em_andamento' => 'Em andamento',
        'finalizado' => 'Finalizado',
    ];

    /** Tipos de colaborador aceitos. */
    public const COLLABORATOR_KINDS = ['colaborador', 'prestador'];

    /** Papéis que podem ver o RDO de QUALQUER pessoa (visão global). */
    public const GLOBAL_VIEW_ROLES = ['super_admin'];

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

    /** Normaliza o tipo de colaborador. */
    public static function normalizeCollaboratorKind($value, string $default = 'colaborador'): string
    {
        return in_array($value, self::COLLABORATOR_KINDS, true) ? $value : $default;
    }

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

    /**
     * Deriva a flag has_occurrence a partir do texto de ocorrências: se houver
     * qualquer conteúdo não vazio, marca 1.
     */
    public static function deriveHasOccurrence(?string $occurrences): int
    {
        return (trim((string) $occurrences) !== '') ? 1 : 0;
    }
}
