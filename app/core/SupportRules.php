<?php

/**
 * Regras puras (sem banco/HTTP) do fluxo de SUPORTE de produção.
 *
 * Centraliza a classificação de gravidade do chamado de suporte e os prazos
 * internos de análise correspondentes, de forma testável. Segue o mesmo padrão
 * de AgendaRules/TicketAccess (classe pura, fonte única de verdade).
 *
 * Importante (decisão da demanda):
 *  - Suporte trata um problema que JÁ está em produção. É diferente de uma nova
 *    demanda de desenvolvimento (que segue o fluxo de escopo/homologação).
 *  - A gravidade é independente da prioridade (low/medium/high/urgent): são dois
 *    eixos. A gravidade define o PRAZO DE ANÁLISE; a prioridade segue existindo.
 *  - O prazo previsto de RESOLUÇÃO (30 min a 48h) é definido pela equipe caso a
 *    caso; aqui só validamos a faixa.
 */
class SupportRules
{
    /** Níveis de gravidade válidos (ordem do mais grave para o menos grave). */
    public const SEVERITIES = ['critico', 'alto', 'medio', 'baixo'];

    /**
     * Prazo interno de ANÁLISE por gravidade, em minutos.
     *   Crítico: 30 min | Alto: 2h | Médio: 4h | Baixo: 8h
     */
    public const ANALYSIS_SLA_MINUTES = [
        'critico' => 30,
        'alto'    => 120,
        'medio'   => 240,
        'baixo'   => 480,
    ];

    /** Faixa do prazo previsto de RESOLUÇÃO (minutos): de 30 min a 48h. */
    public const RESOLUTION_MIN_MINUTES = 30;
    public const RESOLUTION_MAX_MINUTES = 2880; // 48 * 60

    /** Rótulos amigáveis das gravidades. */
    public const SEVERITY_LABELS = [
        'critico' => 'Crítico',
        'alto'    => 'Alto',
        'medio'   => 'Médio',
        'baixo'   => 'Baixo',
    ];

    /** Descrição da situação de cada gravidade (conforme guia de suporte). */
    public const SEVERITY_SITUATIONS = [
        'critico' => 'Produção parada ou operação impedida',
        'alto'    => 'Função importante com falha, sem alternativa',
        'medio'   => 'Falha com alternativa de uso',
        'baixo'   => 'Problema pontual, visual ou dúvida de uso',
    ];

    public static function isValidSeverity($severity): bool
    {
        return is_string($severity) && in_array($severity, self::SEVERITIES, true);
    }

    /** Normaliza a gravidade; retorna null se inválida/vazia (não é suporte). */
    public static function normalizeSeverity($severity): ?string
    {
        if (!is_string($severity)) {
            return null;
        }
        $s = strtolower(trim($severity));
        return in_array($s, self::SEVERITIES, true) ? $s : null;
    }

    public static function severityLabel(?string $severity): string
    {
        $s = self::normalizeSeverity($severity);
        return $s !== null ? self::SEVERITY_LABELS[$s] : '—';
    }

    public static function severitySituation(?string $severity): string
    {
        $s = self::normalizeSeverity($severity);
        return $s !== null ? self::SEVERITY_SITUATIONS[$s] : '';
    }

    /** Minutos do SLA de análise para a gravidade; null se gravidade inválida. */
    public static function analysisSlaMinutes(?string $severity): ?int
    {
        $s = self::normalizeSeverity($severity);
        return $s !== null ? self::ANALYSIS_SLA_MINUTES[$s] : null;
    }

    /**
     * Calcula o prazo-limite de análise (deadline) a partir do instante de
     * abertura e da gravidade. Retorna timestamp (Y-m-d H:i:s) ou null se a
     * gravidade for inválida.
     *
     * @param string      $openedAt instante de abertura (parseável por strtotime)
     * @param string|null $severity gravidade
     */
    public static function analysisDueAt(string $openedAt, ?string $severity): ?string
    {
        $minutes = self::analysisSlaMinutes($severity);
        if ($minutes === null) {
            return null;
        }
        $base = strtotime($openedAt);
        if ($base === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $base + $minutes * 60);
    }

    /**
     * Valida um prazo de resolução informado (em minutos) contra a faixa 30min–48h.
     */
    public static function isValidResolutionMinutes($minutes): bool
    {
        if (!is_int($minutes) && !(is_string($minutes) && ctype_digit($minutes))) {
            return false;
        }
        $m = (int) $minutes;
        return $m >= self::RESOLUTION_MIN_MINUTES && $m <= self::RESOLUTION_MAX_MINUTES;
    }

    /**
     * Indica se uma demanda é de suporte, a partir da categoria (a "tag").
     * A categoria 'suporte' marca o chamado como suporte de produção.
     */
    public static function isSupportCategory(?string $category): bool
    {
        return is_string($category) && strtolower(trim($category)) === 'suporte';
    }
}
