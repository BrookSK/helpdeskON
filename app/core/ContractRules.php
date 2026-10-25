<?php

/**
 * Regras puras (sem banco/HTTP) do CONTRATO (Fase 4).
 *
 * Fluxo em duas etapas, como alinhado:
 *   draft (elaboração)
 *     -> client_review (enviado ao cliente para APROVAR o conteúdo)
 *        -> approved (cliente aprovou) -> awaiting_signature (enviado à ClickSign)
 *           -> signed (webhook confirmou a assinatura)
 *        -> signed (PDF assinado fora da ClickSign, enviado por upload)
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
        // Aprovado -> envia para assinatura, ou assinado fora da ClickSign (upload do PDF).
        self::STATUS_APPROVED           => [self::STATUS_AWAITING_SIGNATURE, self::STATUS_SIGNED, self::STATUS_CANCELLED],
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

    /** Tamanho máximo do PDF assinado enviado por upload. */
    public const SIGNED_FILE_MAX_BYTES = 20 * 1024 * 1024;

    /**
     * Pode receber o PDF assinado por upload? Aprovado ou aguardando assinatura
     * (assina e cria o financeiro) e assinado (só guarda o arquivo).
     */
    public static function canUploadSigned($status): bool
    {
        return in_array($status, [self::STATUS_APPROVED, self::STATUS_AWAITING_SIGNATURE, self::STATUS_SIGNED], true);
    }

    /**
     * Confere o arquivo assinado pelo tamanho e pelo conteúdo (começa com
     * "%PDF-"), não pela extensão. Devolve a mensagem de erro ou null.
     */
    public static function signedFileError(int $size, string $head): ?string
    {
        if ($size <= 0) return 'Envie o PDF do contrato assinado.';
        if ($size > self::SIGNED_FILE_MAX_BYTES) return 'O arquivo passa de 20 MB.';
        if (strncmp($head, '%PDF-', 5) !== 0) return 'O arquivo não é um PDF.';
        return null;
    }

    /** CPF (11 dígitos) ou CNPJ (14 dígitos) informado? Só confere a quantidade. */
    public static function hasClientDocument($document): bool
    {
        $digits = preg_replace('/\D/', '', (string)$document);
        return strlen($digits) === 11 || strlen($digits) === 14;
    }
}
