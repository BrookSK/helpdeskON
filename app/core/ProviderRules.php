<?php

/**
 * Regras puras (sem banco/HTTP) da contratação de prestadores (Fase 8).
 *
 * Define o ciclo de vida do prestador (máquina de estados), normalizações dos
 * enums (tipo de contratação, modelo, forma de pagamento) e a REGRA DE OURO do
 * encerramento: só encerra quando TODOS os acessos foram revogados — espelha o
 * requisito de "revogar todos os acessos no mesmo dia".
 */
class ProviderRules
{
    public const STATUS_PROSPECT = 'prospect';
    public const STATUS_PROPOSAL = 'proposal';
    public const STATUS_CONTRACT = 'contract';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_PROSPECT, self::STATUS_PROPOSAL, self::STATUS_CONTRACT,
        self::STATUS_ACTIVE, self::STATUS_TERMINATED, self::STATUS_CANCELLED,
    ];

    /** Estados terminais (não transicionam mais). */
    public const TERMINAL = [self::STATUS_TERMINATED, self::STATUS_CANCELLED];

    public const ENGAGEMENT_TYPES = ['clt', 'pj', 'freelancer', 'estagio', 'outro'];
    public const WORK_MODELS = ['remoto', 'hibrido', 'presencial'];
    public const PAY_TYPES = ['mensal', 'hora', 'projeto'];

    /**
     * Transições válidas do ciclo. Fluxo feliz: prospect -> proposal -> contract
     * -> active -> terminated. Cancelável de qualquer não-terminal.
     *
     * @return array<string,array<int,string>>
     */
    public static function transitions(): array
    {
        return [
            self::STATUS_PROSPECT  => [self::STATUS_PROPOSAL, self::STATUS_CANCELLED],
            self::STATUS_PROPOSAL  => [self::STATUS_CONTRACT, self::STATUS_PROSPECT, self::STATUS_CANCELLED],
            self::STATUS_CONTRACT  => [self::STATUS_ACTIVE, self::STATUS_PROPOSAL, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE    => [self::STATUS_TERMINATED],
            self::STATUS_TERMINATED => [],
            self::STATUS_CANCELLED  => [],
        ];
    }

    /** Acessos típicos a conceder/revogar (checklist sugerido). */
    public static function defaultAccessLabels(): array
    {
        return ['GitHub', 'Helpdesk', 'E-mail corporativo', 'Servidor/VPS', 'LRV Cloud'];
    }

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PROSPECT;
    }

    public static function normalizeEngagement($v): string
    {
        return in_array($v, self::ENGAGEMENT_TYPES, true) ? $v : 'pj';
    }

    public static function normalizeWorkModel($v): ?string
    {
        return in_array($v, self::WORK_MODELS, true) ? $v : null;
    }

    public static function normalizePayType($v): ?string
    {
        return in_array($v, self::PAY_TYPES, true) ? $v : null;
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        $t = self::transitions();
        return isset($t[$from]) && in_array($to, $t[$from], true);
    }

    /**
     * Pode ENCERRAR (active -> terminated)? Precisa estar ativo E com todos os
     * acessos revogados. Recebe as linhas de provider_accesses.
     *
     * @param array<int,array> $accesses
     */
    public static function canTerminate(string $status, array $accesses): bool
    {
        if ($status !== self::STATUS_ACTIVE) return false;
        return self::allAccessesRevoked($accesses);
    }

    /** Todos os acessos estão revogados (revoked_at preenchido)? */
    public static function allAccessesRevoked(array $accesses): bool
    {
        foreach ($accesses as $a) {
            if (empty($a['revoked_at'])) return false;
        }
        return true;
    }

    /** Acessos ainda ativos (não revogados) — bloqueiam o encerramento. */
    public static function pendingAccesses(array $accesses): array
    {
        $out = [];
        foreach ($accesses as $a) {
            if (empty($a['revoked_at'])) {
                $out[] = $a['access_label'] ?? 'acesso';
            }
        }
        return $out;
    }

    /** Motivo de encerramento normalizado (obrigatório, aparado, limitado). */
    public static function sanitizeReason(?string $reason): string
    {
        $r = trim((string)$reason);
        return mb_substr($r, 0, 1000);
    }
}
