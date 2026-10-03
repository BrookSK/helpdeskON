<?php

/**
 * Regras puras (sem banco/HTTP) da PROPOSTA/ORÇAMENTO comercial (Fase 3).
 *
 * Centraliza, de forma testável:
 *  - cálculo do valor de um item (horas x valor/hora, ou valor fixo);
 *  - soma do total da proposta;
 *  - normalização de status e tipo de contratação;
 *  - transições de status válidas (máquina de estados do processo comercial);
 *  - validação da recusa (motivo obrigatório).
 *
 * Fonte única de verdade: o controller e as views consomem estas funções para
 * não divergir no cálculo nem no fluxo de estados.
 */
class ProposalRules
{
    /** Estados da proposta (espelha o ENUM proposals.status). */
    public const STATUS_DRAFT = 'draft';         // em elaboração
    public const STATUS_READY = 'ready';         // montada
    public const STATUS_SENT = 'sent';           // enviada ao cliente
    public const STATUS_AWAITING = 'awaiting';   // aguardando retorno
    public const STATUS_ACCEPTED = 'accepted';   // aceita
    public const STATUS_REJECTED = 'rejected';   // recusada
    public const STATUS_CANCELLED = 'cancelled'; // cancelada

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_READY, self::STATUS_SENT,
        self::STATUS_AWAITING, self::STATUS_ACCEPTED, self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** Tipos de contratação (espelha o ENUM proposals.contract_type). */
    public const CONTRACT_TYPES = [
        'dev_zero',        // desenvolvimento do zero (tem começo/meio/fim; garantia 90 dias)
        'dev_manutencao',  // desenvolvimento/manutenção de sistema existente
        'suporte',         // só suporte
        'dev_suporte',     // desenvolvimento + suporte
        'outro',
    ];

    /** Estados que já encerraram a proposta (terminais). */
    public const TERMINAL = [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /**
     * Transições de status permitidas. A recusa pode voltar a elaboração (refazer),
     * e qualquer estado não-terminal pode ser cancelado.
     *
     * @var array<string,string[]>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_READY     => [self::STATUS_SENT, self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_SENT      => [self::STATUS_AWAITING, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_AWAITING  => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        // Recusada pode ser retomada para refazer (volta à elaboração).
        self::STATUS_REJECTED  => [self::STATUS_DRAFT, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED  => [],
        self::STATUS_CANCELLED => [],
    ];

    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_DRAFT;
    }

    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    public static function normalizeContractType($value): ?string
    {
        return in_array($value, self::CONTRACT_TYPES, true) ? $value : null;
    }

    public static function isTerminal($status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    /**
     * A transição de $from para $to é permitida? Transição para o mesmo estado é
     * sempre permitida (idempotente). Estados inválidos retornam false.
     */
    public static function canTransition($from, $to): bool
    {
        if (!self::isValidStatus($from) || !self::isValidStatus($to)) return false;
        if ($from === $to) return true;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Valor de um item. Se houver horas E valor/hora, calcula horas*valor-hora;
     * caso contrário usa o valor informado (valor fixo). Nunca negativo.
     *
     * @param float|string|null $hours
     * @param float|string|null $hourlyRate
     * @param float|string|null $fixedAmount Valor fixo quando não há horas.
     */
    public static function itemAmount($hours, $hourlyRate, $fixedAmount = null): float
    {
        $h = self::num($hours);
        $r = self::num($hourlyRate);
        if ($h > 0 && $r > 0) {
            return round($h * $r, 2);
        }
        $fixed = self::num($fixedAmount);
        return $fixed > 0 ? round($fixed, 2) : 0.0;
    }

    /**
     * Soma o total a partir de uma lista de itens já com 'amount' calculado
     * (ou calculável por hours/hourly_rate). Aceita arrays no formato do form.
     *
     * @param array<int,array> $items
     */
    public static function total(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $it) {
            if (isset($it['amount']) && $it['amount'] !== '' && $it['amount'] !== null) {
                $sum += self::num($it['amount']);
            } else {
                $sum += self::itemAmount($it['hours'] ?? null, $it['hourly_rate'] ?? null, $it['fixed_amount'] ?? null);
            }
        }
        return round($sum, 2);
    }

    /**
     * Validação da recusa: motivo é obrigatório (não vazio após trim).
     * Retorna o motivo saneado ou null se inválido.
     */
    public static function sanitizeRejectReason($reason): ?string
    {
        $r = trim((string)$reason);
        return $r === '' ? null : $r;
    }

    /**
     * Converte um valor em float aceitando número ou string "R$ 1.234,56".
     * Reaproveita o parser monetário brasileiro do CRM quando a entrada é string.
     */
    private static function num($v): float
    {
        if (is_int($v) || is_float($v)) return (float)$v;
        if ($v === null || $v === '') return 0.0;
        if (is_string($v)) {
            // Número "simples" (ponto decimal) usa casting direto; formato BR usa o parser.
            if (preg_match('/^-?\d+(\.\d+)?$/', trim($v))) return (float)$v;
            if (class_exists('CrmRules')) {
                $parsed = CrmRules::parseMoneyBR($v);
                if ($parsed !== null) return (float)$parsed;
            }
            return (float)preg_replace('/[^0-9.\-]/', '', $v);
        }
        return 0.0;
    }
}
