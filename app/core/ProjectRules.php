<?php

/**
 * Regras puras (sem banco/HTTP) de Projetos e Garantia (Fase 9).
 *
 * Garantia: tipo de contrato 'zero' (projeto fechado/entregue) tem garantia de
 * N dias (padrão 90) a partir da entrega; 'manutencao'/'suporte' seguem o
 * contrato de suporte. Define o cálculo do fim da garantia, o aviso de 15 dias
 * antes do fim e a regra de bloqueio de chamados pós-garantia sem suporte.
 */
class ProjectRules
{
    public const STATUS_PLANNING = 'planning';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_WARRANTY = 'warranty';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PLANNING, self::STATUS_IN_PROGRESS, self::STATUS_DELIVERED,
        self::STATUS_WARRANTY, self::STATUS_CLOSED, self::STATUS_CANCELLED,
    ];

    public const CONTRACT_TYPES = ['zero', 'manutencao', 'suporte', 'outro'];

    public const DEFAULT_WARRANTY_DAYS = 90;
    public const WARRANTY_WARNING_DAYS = 15;

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PLANNING;
    }

    public static function normalizeContractType($v): string
    {
        return in_array($v, self::CONTRACT_TYPES, true) ? $v : 'outro';
    }

    /** Esse tipo de contrato tem garantia de N dias? Só o 'zero' (projeto fechado). */
    public static function hasWarranty(string $contractType): bool
    {
        return self::normalizeContractType($contractType) === 'zero';
    }

    /**
     * Calcula a data de fim da garantia a partir da entrega. Retorna null se o
     * tipo não tem garantia ou a data de entrega é inválida.
     */
    public static function warrantyEndDate(string $contractType, ?string $deliveredAt, int $warrantyDays = self::DEFAULT_WARRANTY_DAYS): ?string
    {
        if (!self::hasWarranty($contractType)) return null;
        $ts = $deliveredAt ? strtotime($deliveredAt) : false;
        if ($ts === false) return null;
        $days = $warrantyDays > 0 ? $warrantyDays : self::DEFAULT_WARRANTY_DAYS;
        return date('Y-m-d H:i:s', strtotime("+{$days} days", $ts));
    }

    /** A garantia está vigente numa data de referência? */
    public static function warrantyActive(?string $warrantyEndsAt, ?string $now = null): bool
    {
        if (!$warrantyEndsAt) return false;
        $end = strtotime($warrantyEndsAt);
        $ref = $now ? strtotime($now) : time();
        return $end !== false && $ref <= $end;
    }

    /** Dias restantes de garantia (negativo se já venceu, null se sem garantia). */
    public static function warrantyDaysLeft(?string $warrantyEndsAt, ?string $now = null): ?int
    {
        if (!$warrantyEndsAt) return null;
        $end = strtotime($warrantyEndsAt);
        if ($end === false) return null;
        $ref = $now ? strtotime($now) : time();
        return (int) floor(($end - $ref) / 86400);
    }

    /** Deve avisar que a garantia está perto do fim? (<= 15 dias e ainda ativa). */
    public static function shouldWarnWarrantyEnding(?string $warrantyEndsAt, ?string $now = null): bool
    {
        $left = self::warrantyDaysLeft($warrantyEndsAt, $now);
        if ($left === null) return false;
        return $left >= 0 && $left <= self::WARRANTY_WARNING_DAYS;
    }

    /**
     * O cliente pode abrir chamado para este projeto? Pode se:
     *  - tem contrato de suporte ativo; OU
     *  - a garantia está vigente.
     * Bloqueia quando: sem suporte E (garantia vencida OU projeto sem garantia).
     *
     * @param array $project linha de projects (contract_type, warranty_ends_at, support_contract, status)
     */
    public static function canOpenTicket(array $project, ?string $now = null): bool
    {
        if ((int)($project['support_contract'] ?? 0) === 1) return true;
        $status = $project['status'] ?? '';
        if (in_array($status, [self::STATUS_CANCELLED], true)) return false;
        if (!self::hasWarranty($project['contract_type'] ?? 'outro')) {
            // Sem garantia e sem suporte -> depende: antes da entrega (planning/
            // in_progress) ainda está no projeto, então permite; após entregue/
            // fechado sem suporte, bloqueia.
            return in_array($status, [self::STATUS_PLANNING, self::STATUS_IN_PROGRESS], true);
        }
        return self::warrantyActive($project['warranty_ends_at'] ?? null, $now);
    }

    /** Motivo do bloqueio (para a UI), ou null se pode abrir. */
    public static function blockReason(array $project, ?string $now = null): ?string
    {
        if (self::canOpenTicket($project, $now)) return null;
        if (!self::hasWarranty($project['contract_type'] ?? 'outro')) {
            return 'Projeto sem garantia e sem contrato de suporte ativo.';
        }
        return 'Garantia encerrada e sem contrato de suporte ativo.';
    }

    // ===== Fluxo de Entrega: publicação, documentação e aceite =====

    /**
     * O projeto pode ser publicado em produção? Só faz sentido publicar um
     * projeto que ainda está em andamento (planning/in_progress). Projetos já
     * entregues/em garantia/encerrados não são re-publicados por este fluxo.
     *
     * @param array $project linha de projects (status)
     */
    public static function canPublish(array $project): bool
    {
        $status = $project['status'] ?? '';
        return in_array($status, [self::STATUS_PLANNING, self::STATUS_IN_PROGRESS], true);
    }

    /** A documentação/manual foi entregue ao cliente? */
    public static function documentationDelivered(array $project): bool
    {
        return !empty($project['documentation_delivered_at']);
    }

    /** O cliente realizou o aceite formal da entrega? */
    public static function clientAccepted(array $project): bool
    {
        return !empty($project['client_accepted_at']);
    }
}
