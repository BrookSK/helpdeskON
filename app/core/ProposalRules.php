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
     * Valor de um item. Com horas e valor/hora: quantidade × horas × valor/hora.
     * Sem horas: quantidade × valor fixo. Quantidade vazia vale 1. Nunca negativo.
     *
     * @param float|string|null $hours
     * @param float|string|null $hourlyRate
     * @param float|string|null $fixedAmount Valor fixo quando não há horas.
     * @param float|string|null $quantity
     */
    public static function itemAmount($hours, $hourlyRate, $fixedAmount = null, $quantity = null): float
    {
        $q = self::quantity($quantity);
        $h = self::num($hours);
        $r = self::num($hourlyRate);
        if ($h > 0 && $r > 0) {
            return round($q * $h * $r, 2);
        }
        $fixed = self::num($fixedAmount);
        return $fixed > 0 ? round($q * $fixed, 2) : 0.0;
    }

    /** Quantidade do item. Vazio = 1. Negativo vira 0. */
    public static function quantity($v): float
    {
        if ($v === null || $v === '') return 1.0;
        $n = self::num($v);
        if ($n < 0) return 0.0;
        return round($n, 2);
    }

    /**
     * Previsão de valor do catálogo: horas × valor/hora, ou só o valor/hora
     * quando o serviço não tem horas estimadas.
     */
    public static function forecast($hours, $hourlyRate): ?float
    {
        $h = self::num($hours);
        $r = self::num($hourlyRate);
        if ($h > 0 && $r > 0) return round($h * $r, 2);
        if ($r > 0) return round($r, 2);
        return null;
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
                $sum += self::itemAmount(
                    $it['hours'] ?? null,
                    $it['hourly_rate'] ?? null,
                    $it['fixed_amount'] ?? null,
                    $it['quantity'] ?? null
                );
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

    /** Data Y-m-d ou null. Aceita também d/m/Y. */
    public static function normalizeDate($value): ?string
    {
        $v = trim((string)$value);
        if ($v === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $v : null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) {
            return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]) : null;
        }
        return null;
    }

    /** Fim pode ser igual ao início. Datas ausentes não invalidam. */
    public static function scheduleOrderOk($start, $end): bool
    {
        $s = self::normalizeDate($start);
        $e = self::normalizeDate($end);
        if ($s === null || $e === null) return true;
        return $e >= $s;
    }

    /**
     * Cronograma por serviço. Cada linha ganha a posição da barra (0–100)
     * dentro da janela que vai do início ao fim do desenvolvimento.
     *
     * @param array<int,array> $items
     * @return array{start:?string,end:?string,days:int,rows:array<int,array>}
     */
    public static function timeline(array $items, $devStart = null, $devEnd = null): array
    {
        $min = self::normalizeDate($devStart);
        $max = self::normalizeDate($devEnd);
        $rows = [];
        foreach ($items as $it) {
            $s = self::normalizeDate($it['schedule_start'] ?? null);
            $e = self::normalizeDate($it['schedule_end'] ?? null);
            foreach ([$s, $e] as $d) {
                if (!$d) continue;
                if ($min === null || $d < $min) $min = $d;
                if ($max === null || $d > $max) $max = $d;
            }
            $rows[] = [
                'description' => (string)($it['description'] ?? ''),
                'start' => $s,
                'end' => $e,
            ];
        }
        $days = 0;
        if ($min && $max) {
            $days = (int)round((strtotime($max . ' 12:00:00') - strtotime($min . ' 12:00:00')) / 86400);
        }
        foreach ($rows as &$row) {
            $row['bar'] = self::timelineBar($row['start'], $row['end'], $min, $max);
        }
        unset($row);
        return ['start' => $min, 'end' => $max, 'days' => $days, 'rows' => $rows];
    }

    /**
     * Blocos fixos padrão (texto institucional). O comercial edita em
     * Propostas → Conteúdo fixo; serviços e valores não ficam aqui.
     *
     * @return array<int,array{block_key:string,title:string,body:string,placement:string,position:int}>
     */
    public static function defaultBlocks(): array
    {
        return [
            [
                'block_key' => 'about',
                'title' => 'Quem somos',
                'placement' => 'before',
                'position' => 1,
                'body' => "A ON Solutions Brasil desenvolve software sob medida para organizar processos, reduzir retrabalho e dar visibilidade à operação.\n\nCada proposta parte de um escopo fechado: o que será feito, em quanto tempo e por qual investimento. O que está descrito aqui é o combinado desta contratação.",
            ],
            [
                'block_key' => 'method',
                'title' => 'Como conduzimos o projeto',
                'placement' => 'before',
                'position' => 2,
                'body' => "O trabalho segue quatro momentos: alinhamento do escopo, desenvolvimento das entregas, homologação com você e entrada em operação.\n\nO cronograma desta proposta é organizado por serviço. Cada entrega tem início e fim próprios, dentro do período geral de desenvolvimento.",
            ],
            [
                'block_key' => 'conditions',
                'title' => 'Condições comerciais',
                'placement' => 'after',
                'position' => 3,
                'body' => "Os valores referem-se somente aos serviços listados. Mudança de escopo é orçada à parte.\n\nA validade está na capa. Desenvolvimento do zero inclui 90 dias de garantia para correção de defeitos do que foi entregue, contados da homologação. Hospedagem e suporte contínuo, quando contratados, seguem o item correspondente.",
            ],
            [
                'block_key' => 'closing',
                'title' => 'Próximos passos',
                'placement' => 'after',
                'position' => 4,
                'body' => "O aceite por este link confirma o escopo, o investimento e o cronograma. Na sequência formalizamos o contrato e iniciamos o período de desenvolvimento.",
            ],
        ];
    }

    /** Lê o snapshot JSON gravado na proposta. Lista vazia se inválido. */
    public static function decodeBlocks($json): array
    {
        if (!is_string($json) || trim($json) === '') return [];
        $data = json_decode($json, true);
        if (!is_array($data)) return [];
        $out = [];
        foreach ($data as $b) {
            if (!is_array($b)) continue;
            $title = trim((string)($b['title'] ?? ''));
            $body = trim((string)($b['body'] ?? ''));
            if ($title === '' && $body === '') continue;
            $placement = (($b['placement'] ?? '') === 'after') ? 'after' : 'before';
            $out[] = [
                'block_key' => (string)($b['block_key'] ?? ''),
                'title' => $title,
                'body' => $body,
                'placement' => $placement,
            ];
        }
        return $out;
    }

    /**
     * @return array{left:float,width:float}|null
     */
    private static function timelineBar(?string $start, ?string $end, ?string $min, ?string $max): ?array
    {
        if (!$min || !$max) return null;
        $a = $start ?: $end;
        $b = $end ?: $start;
        if (!$a || !$b) return null;
        if ($b < $a) {
            $tmp = $a;
            $a = $b;
            $b = $tmp;
        }
        $t0 = strtotime($min . ' 12:00:00');
        $t1 = strtotime($max . ' 12:00:00');
        $span = $t1 - $t0;
        if ($span <= 0) return ['left' => 0.0, 'width' => 100.0];
        $left = (strtotime($a . ' 12:00:00') - $t0) / $span * 100;
        $width = (strtotime($b . ' 12:00:00') - strtotime($a . ' 12:00:00')) / $span * 100;
        if ($width < 2.5) $width = 2.5;
        if ($left < 0) $left = 0;
        if ($left + $width > 100) $left = max(0, 100 - $width);
        return ['left' => round($left, 2), 'width' => round($width, 2)];
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
