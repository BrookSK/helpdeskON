<?php

/**
 * Regras puras (sem banco/HTTP) do RECONHECIMENTO/ASSINATURA da minuta (ata)
 * pelo cliente.
 *
 * Os guias operacionais (Reuniões, Arquitetura, Captação, Entrega) tratam a
 * minuta ASSINADA pelo cliente como pré-condição do passo seguinte ("não
 * iniciar o desenvolvimento sem a minuta validada e assinada"). Esta classe
 * centraliza, de forma testável:
 *  - os estados do aceite (pendente / reconhecida / contestada);
 *  - quando é possível reconhecer ou contestar (a minuta precisa estar pronta);
 *  - a validação do motivo obrigatório ao contestar.
 *
 * O estado vive na gravação (video_recordings), que é onde a minuta é gerada e
 * persistida (campos minutes / minutes_status). O aceite espelha o padrão do
 * aceite de entrega de projeto (ProjectController::confirmAccept) por PIN.
 */
class MinutesAckRules
{
    /** Aceite ainda não realizado. */
    public const STATUS_PENDING = 'pending';
    /** Cliente reconheceu/assinou a minuta (terminal de sucesso). */
    public const STATUS_ACK = 'acknowledged';
    /** Cliente discordou; volta para a equipe refazer e reenviar. */
    public const STATUS_CONTEST = 'contested';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACK, self::STATUS_CONTEST];

    /** Estado "pronta" da minuta (espelha MeetingMinutesRules::STATUS_DONE). */
    public const MINUTES_DONE = 'done';

    /** Tamanho máximo do motivo de contestação (bate com a coluna VARCHAR(1000)). */
    public const MAX_CONTEST_REASON = 1000;

    /** Normaliza o status de aceite; desconhecido vira 'pending'. */
    public static function normalizeAckStatus($v): string
    {
        return in_array($v, self::STATUSES, true) ? $v : self::STATUS_PENDING;
    }

    /**
     * A minuta pode ser RECONHECIDA (assinada) pelo cliente?
     * Só quando: a minuta está pronta (minutes_status='done') E o aceite ainda
     * não é terminal de sucesso (não está 'acknowledged'). Uma minuta contestada
     * pode ser reconhecida depois que a equipe reenvia (o reenvio volta o aceite
     * para 'pending'; ver resetForResend).
     */
    public static function canAcknowledge(?string $minutesStatus, ?string $ackStatus): bool
    {
        if ($minutesStatus !== self::MINUTES_DONE) return false;
        return self::normalizeAckStatus($ackStatus) !== self::STATUS_ACK;
    }

    /**
     * A minuta pode ser CONTESTADA pelo cliente?
     * Só quando está pronta e ainda não foi reconhecida. Contestar de novo uma
     * minuta já contestada é permitido (idempotente — atualiza o motivo).
     */
    public static function canContest(?string $minutesStatus, ?string $ackStatus): bool
    {
        if ($minutesStatus !== self::MINUTES_DONE) return false;
        return self::normalizeAckStatus($ackStatus) !== self::STATUS_ACK;
    }

    /**
     * Valida/saneia o motivo da contestação (obrigatório). Retorna o texto
     * aparado (limitado a MAX_CONTEST_REASON) ou null se vazio/ inválido.
     */
    public static function sanitizeContestReason($reason): ?string
    {
        $r = trim((string) $reason);
        if ($r === '') return null;
        return mb_substr($r, 0, self::MAX_CONTEST_REASON);
    }

    /** A minuta já foi reconhecida pelo cliente? */
    public static function isAcknowledged(?string $ackStatus): bool
    {
        return self::normalizeAckStatus($ackStatus) === self::STATUS_ACK;
    }

    /**
     * Quando a equipe reenvia uma minuta (regeração/edição após contestação), o
     * aceite deve voltar para 'pending' — a menos que já tenha sido reconhecida
     * (nesse caso não se reabre). Fonte única para o controller decidir o reset.
     */
    public static function resetForResend(?string $ackStatus): string
    {
        return self::isAcknowledged($ackStatus) ? self::STATUS_ACK : self::STATUS_PENDING;
    }
}
