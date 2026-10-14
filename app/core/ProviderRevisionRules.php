<?php

/**
 * Regras puras (sem banco/HTTP) da REVISÃO DE VALOR do prestador.
 *
 * Qualquer ajuste de valor/condição do prestador passa por uma revisão que
 * precisa da aprovação de um gestor antes de valer. Aqui ficam os estados, a
 * validação do pedido e quem pode aprovar.
 */
class ProviderRevisionRules
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    /** Papéis que podem APROVAR/RECUSAR uma revisão de valor (gestores). */
    public const APPROVER_ROLES = ['super_admin', 'developer'];

    public static function normalizeStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PENDING;
    }

    /** Uma revisão só pode ser avaliada enquanto estiver pendente. */
    public static function canReview(?string $status): bool
    {
        return self::normalizeStatus($status) === self::STATUS_PENDING;
    }

    /** O papel pode aprovar/recusar a revisão? */
    public static function roleCanApprove(?string $role): bool
    {
        return is_string($role) && in_array($role, self::APPROVER_ROLES, true);
    }

    /**
     * Valida um pedido de revisão. Exige motivo e pelo menos uma mudança
     * (novo valor ou novo tipo), e valor não-negativo.
     *
     * @return array{ok:bool,error:?string}
     */
    public static function validateRequest($newPayType, $newPayAmount, ?string $reason, $oldPayType, $oldPayAmount): array
    {
        $r = trim((string)$reason);
        if ($r === '') return ['ok' => false, 'error' => 'Informe a justificativa do ajuste.'];

        $type = ProviderRules::normalizePayType($newPayType);
        $amount = self::num($newPayAmount);
        if ($newPayAmount !== null && $newPayAmount !== '' && $amount < 0) {
            return ['ok' => false, 'error' => 'O valor não pode ser negativo.'];
        }

        $changedType = $type !== null && $type !== ProviderRules::normalizePayType($oldPayType);
        $changedAmount = ($newPayAmount !== null && $newPayAmount !== '') && abs($amount - self::num($oldPayAmount)) > 0.001;
        if (!$changedType && !$changedAmount) {
            return ['ok' => false, 'error' => 'Informe um novo valor ou tipo diferente do atual.'];
        }
        return ['ok' => true, 'error' => null];
    }

    private static function num($v): float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if ($v === null || $v === '') return 0.0;
        if (is_string($v) && class_exists('CrmRules')) {
            $p = CrmRules::parseMoneyBR($v);
            if ($p !== null) return (float)$p;
        }
        return (float)$v;
    }
}
