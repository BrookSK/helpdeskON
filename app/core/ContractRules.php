<?php

/**
 * Regras puras (sem banco/HTTP) do CONTRATO (Fase 4).
 *
 * Fluxo em duas etapas, como alinhado:
 *   draft (elaboração)
 *     -> client_review (enviado ao cliente para APROVAR o conteúdo)
 *        -> approved (cliente aprovou) -> awaiting_signature (enviado à ClickSign)
 *           -> signed (webhook confirmou a assinatura)
 *        -> client_rejected (cliente pediu ajuste, com motivo) -> volta a draft
 *   cancelled: encerra a qualquer momento (não-terminal -> cancelled).
 */
class ContractRules
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CLIENT_REVIEW = 'client_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_AWAITING_SIGNATURE = 'awaiting_signature';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_CLIENT_REJECTED = 'client_rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_CLIENT_REVIEW, self::STATUS_APPROVED,
        self::STATUS_AWAITING_SIGNATURE, self::STATUS_SIGNED,
        self::STATUS_CLIENT_REJECTED, self::STATUS_CANCELLED,
    ];

    /** Terminais: assinado (sucesso) e cancelado. */
    public const TERMINAL = [self::STATUS_SIGNED, self::STATUS_CANCELLED];

    /** @var array<string,string[]> */
    private const TRANSITIONS = [
        self::STATUS_DRAFT              => [self::STATUS_CLIENT_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_CLIENT_REVIEW      => [self::STATUS_APPROVED, self::STATUS_CLIENT_REJECTED, self::STATUS_CANCELLED],
        // Cliente pediu ajuste: volta à elaboração.
        self::STATUS_CLIENT_REJECTED    => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        // Aprovado -> envia para assinatura.
        self::STATUS_APPROVED           => [self::STATUS_AWAITING_SIGNATURE, self::STATUS_CANCELLED],
        // Aguardando assinatura -> assinado (webhook) ou cancelado.
        self::STATUS_AWAITING_SIGNATURE => [self::STATUS_SIGNED, self::STATUS_CANCELLED],
        self::STATUS_SIGNED             => [],
        self::STATUS_CANCELLED          => [],
    ];

    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_DRAFT;
    }

    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    public static function isTerminal($status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public static function canTransition($from, $to): bool
    {
        if (!self::isValidStatus($from) || !self::isValidStatus($to)) return false;
        if ($from === $to) return true;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Pode editar o corpo? Só enquanto não foi para assinatura nem terminou. */
    public static function canEditBody($status): bool
    {
        return in_array($status, [self::STATUS_DRAFT, self::STATUS_CLIENT_REJECTED], true);
    }

    /** Pode enviar para assinatura? Apenas quando aprovado pelo cliente. */
    public static function canSendToSignature($status): bool
    {
        return $status === self::STATUS_APPROVED;
    }

    /** Motivo do ajuste é obrigatório ao recusar. Retorna saneado ou null. */
    public static function sanitizeRejectReason($reason): ?string
    {
        $r = trim((string)$reason);
        return $r === '' ? null : $r;
    }
}
