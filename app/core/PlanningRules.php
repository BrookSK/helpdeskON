<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo Planejamento (Kanban de cards).
 *
 * Fonte única e testável para duas decisões:
 *  1) Um card está DENTRO da janela de vencimento (ex.: faltando <24h para o
 *     due_date) e, portanto, deve gerar lembrete ao responsável?
 *  2) A data/horário agendado de um card MUDOU de fato (due_date/start_date/
 *     end_date), para avisar os superadmins?
 *
 * Mantido no padrão das demais *Rules (AgendaRules, CrmRules, RdoRules): métodos
 * public static, deterministas e cobertos por testes de unidade.
 */
class PlanningRules
{
    /**
     * Status que NÃO devem gerar lembrete de vencimento — a demanda já saiu do
     * fluxo ativo (concluída, negada ou arquivada). "waiting_client" continua
     * ativo, pois o prazo ainda corre.
     */
    public const INACTIVE_STATUSES = ['completed', 'denied', 'archived'];

    /**
     * O prazo (due_date) está na janela de lembrete: entre AGORA e AGORA+horas.
     *
     * Regras:
     *  - due nulo/vazio => false (sem prazo, sem lembrete).
     *  - due já vencido (<= now) => false (a janela é para o que ainda VAI vencer;
     *    lembrete de "falta menos de Xh" não faz sentido depois de vencido).
     *  - due estritamente após now e até now+hours (inclusive o limite) => true.
     *
     * Comparação feita por timestamp (strtotime), então aceita "Y-m-d H:i:s".
     *
     * @param string|null $dueAt Data/hora de vencimento do card.
     * @param string      $now   Momento de referência ("Y-m-d H:i:s").
     * @param int         $hours Tamanho da janela em horas (padrão 24).
     */
    public static function isWithinDueWindow(?string $dueAt, string $now, int $hours = 24): bool
    {
        if ($dueAt === null || trim($dueAt) === '') {
            return false;
        }
        $due = strtotime($dueAt);
        $ref = strtotime($now);
        if ($due === false || $ref === false) {
            return false;
        }
        if ($hours <= 0) {
            return false;
        }
        $limit = $ref + ($hours * 3600);
        // Estritamente futuro (ainda não venceu) e dentro do limite (inclusive).
        return $due > $ref && $due <= $limit;
    }

    /**
     * O card deve receber o lembrete de vencimento agora?
     *
     * Combina três condições:
     *  - o prazo está na janela (isWithinDueWindow);
     *  - o status ainda é ativo (não concluído/negado/arquivado);
     *  - o lembrete ainda não foi enviado para este prazo (reminderSentAt vazio).
     *
     * @param string|null $dueAt          Vencimento do card.
     * @param string|null $status         Status atual do card.
     * @param string|null $reminderSentAt Quando o lembrete já foi enviado (ou null).
     * @param string      $now            Momento de referência.
     * @param int         $hours          Janela em horas.
     */
    public static function shouldSendDueReminder(
        ?string $dueAt,
        ?string $status,
        ?string $reminderSentAt,
        string $now,
        int $hours = 24
    ): bool {
        if (!self::isWithinDueWindow($dueAt, $now, $hours)) {
            return false;
        }
        if (in_array((string) $status, self::INACTIVE_STATUSES, true)) {
            return false;
        }
        if ($reminderSentAt !== null && trim($reminderSentAt) !== '') {
            return false;
        }
        return true;
    }

    /**
     * Normaliza um valor de data/hora para comparação estável.
     *
     * Retorna "Y-m-d H:i:s" quando parseável; null quando vazio/nulo; e a própria
     * string (trim) quando não parseável, para nunca "esconder" uma diferença.
     */
    public static function normalizeDateTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        return $ts === false ? $s : date('Y-m-d H:i:s', $ts);
    }

    /**
     * A data/horário agendado do card mudou entre o estado antigo e o novo?
     *
     * Compara os campos due_date, start_date e end_date (após normalização).
     * Só considera um campo se ele ESTIVER PRESENTE em $new — assim uma edição
     * parcial (que não mexe em datas) não dispara aviso. null e "" são tratados
     * como equivalentes (ambos = "sem data").
     *
     * @param array $old Estado atual do card (com as chaves de data).
     * @param array $new Campos que estão sendo gravados (só as chaves presentes).
     */
    public static function scheduleChanged(array $old, array $new): bool
    {
        foreach (['due_date', 'start_date', 'end_date'] as $field) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $before = self::normalizeDateTime($old[$field] ?? null);
            $after = self::normalizeDateTime($new[$field]);
            if ($before !== $after) {
                return true;
            }
        }
        return false;
    }

    /**
     * O due_date especificamente mudou? Usado para decidir se o carimbo de
     * lembrete (due_reminder_sent_at) deve ser zerado — assim um novo prazo
     * volta a ser elegível ao lembrete de 24h.
     */
    public static function dueDateChanged(array $old, array $new): bool
    {
        if (!array_key_exists('due_date', $new)) {
            return false;
        }
        $before = self::normalizeDateTime($old['due_date'] ?? null);
        $after = self::normalizeDateTime($new['due_date']);
        return $before !== $after;
    }

    // =====================================================================
    // ATIVIDADE PREDECESSORA — recálculo de datas em cascata
    // =====================================================================

    /**
     * Calcula as novas datas (start_date/end_date) de um card DEPENDENTE a
     * partir do término do seu predecessor, preservando a DURAÇÃO original do
     * dependente e respeitando o prazo final (due_date) como teto intocável.
     *
     * Regra de negócio (ex.: construir → emassar → pintar o muro):
     *  - O dependente só pode começar DEPOIS que o predecessor termina. O novo
     *    início é o fim do predecessor + folga (lagDays), em dias corridos.
     *  - A duração do dependente é preservada: novo fim = novo início + duração
     *    que ele já tinha (end - start). Se o dependente não tinha start/end
     *    definidos, assume duração de 1 dia (apenas desloca para após o
     *    predecessor).
     *  - O prazo final (due_date) do dependente NUNCA é ultrapassado pelo
     *    recálculo automático. Se o novo fim estourar o due_date, o fim é
     *    "grampeado" no due_date (comprimindo a janela). O início acompanha
     *    (fim - duração), mas nunca é empurrado para antes do fim do
     *    predecessor — ou seja, em caso de aperto, a janela encolhe em vez de
     *    violar a dependência.
     *
     * Entradas e saída usam o formato "Y-m-d H:i:s". Retorna um array com as
     * chaves 'start_date' e 'end_date' (strings) a serem gravadas, ou null
     * quando não há o que recalcular (sem fim de predecessor).
     *
     * @param string|null $predecessorEnd Fim (end_date) do predecessor.
     * @param string|null $depStart       Início atual do dependente.
     * @param string|null $depEnd         Fim atual do dependente.
     * @param string|null $depDue         Prazo final (due_date) do dependente.
     * @param int         $lagDays        Folga em dias entre fim do predecessor
     *                                    e início do dependente.
     * @return array{start_date:string,end_date:string}|null
     */
    public static function recalcDependentDates(
        ?string $predecessorEnd,
        ?string $depStart,
        ?string $depEnd,
        ?string $depDue,
        int $lagDays = 0
    ): ?array {
        $predEndTs = self::toTimestamp($predecessorEnd);
        if ($predEndTs === null) {
            // Sem término do predecessor não há âncora para a cascata.
            return null;
        }

        if ($lagDays < 0) {
            $lagDays = 0;
        }

        [$newStart, $newEnd, $duration, $anchor] = self::computeRawDependentDates(
            $predEndTs,
            $depStart,
            $depEnd,
            $lagDays
        );

        // Teto do prazo final: o fim recalculado não ultrapassa o due_date.
        $dueTs = self::toTimestamp($depDue);
        if ($dueTs !== null && $newEnd > $dueTs) {
            $newEnd = $dueTs;
            // Recua o início para manter a duração, mas sem ir antes do término
            // do predecessor (a dependência tem precedência sobre a duração).
            $candidateStart = $newEnd - $duration;
            $newStart = max($candidateStart, $anchor);
            // Se ainda assim o início ficou após o fim (janela negativa por um
            // deadline muito apertado), encosta início no fim (janela zero).
            if ($newStart > $newEnd) {
                $newStart = $newEnd;
            }
        }

        return [
            'start_date' => date('Y-m-d H:i:s', $newStart),
            'end_date'   => date('Y-m-d H:i:s', $newEnd),
        ];
    }

    /**
     * Cálculo "cru" das datas do dependente a partir do fim do predecessor,
     * SEM aplicar o teto do due_date. Usado tanto pelo recalcDependentDates
     * (que aplica o teto depois) quanto pela versão com recesso (em que o
     * recesso tem prioridade sobre o due e, portanto, o teto não deve cortar
     * antes do deslocamento).
     *
     * @return array{0:int,1:int,2:int,3:int} [newStart, newEnd, duration, anchor]
     *         anchor = início mínimo permitido (fim do predecessor + folga).
     */
    private static function computeRawDependentDates(
        int $predEndTs,
        ?string $depStart,
        ?string $depEnd,
        int $lagDays
    ): array {
        // Duração original do dependente (em segundos). Default: 1 dia.
        $duration = self::DAY;
        $startTs = self::toTimestamp($depStart);
        $endTs = self::toTimestamp($depEnd);
        if ($startTs !== null && $endTs !== null && $endTs >= $startTs) {
            $duration = $endTs - $startTs;
        }

        $anchor = $predEndTs + ($lagDays * self::DAY);
        $newStart = $anchor;
        $newEnd = $newStart + $duration;

        return [$newStart, $newEnd, $duration, $anchor];
    }

    /**
     * Detecta se definir $predecessorId como predecessor de $cardId criaria um
     * ciclo (A→B→A) na cadeia de dependências. Recebe um mapa
     * [cardId => predecessorId|null] com o estado atual e simula a nova aresta.
     *
     * Retorna true quando o vínculo é PROIBIDO (gera ciclo ou aponta para si
     * mesmo). Puro: não toca banco; o chamador monta o mapa.
     *
     * @param int                     $cardId        Card que receberá o predecessor.
     * @param int|null                $predecessorId Predecessor pretendido.
     * @param array<int,int|null>     $predecessorMap Estado atual (cardId => predId).
     */
    public static function wouldCreateCycle(int $cardId, ?int $predecessorId, array $predecessorMap): bool
    {
        if ($predecessorId === null) {
            return false;
        }
        if ($predecessorId === $cardId) {
            return true;
        }
        // Caminha pela cadeia de predecessores a partir do predecessor pretendido.
        // Se em algum ponto chegarmos de volta ao próprio card, há ciclo.
        $map = $predecessorMap;
        $map[$cardId] = $predecessorId; // aplica a aresta pretendida
        $visited = [];
        $cursor = $predecessorId;
        while ($cursor !== null) {
            if ($cursor === $cardId) {
                return true;
            }
            if (isset($visited[$cursor])) {
                // Ciclo pré-existente na cadeia — trava para não repetir infinito.
                return true;
            }
            $visited[$cursor] = true;
            $cursor = $map[$cursor] ?? null;
        }
        return false;
    }

    /**
     * Converte um valor de data/hora em timestamp, ou null se vazio/inválido.
     * Auxiliar interno do recálculo de cascata.
     */
    private static function toTimestamp($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        return $ts === false ? null : $ts;
    }

    // =====================================================================
    // RECESSO — deslocamento automático de datas para fora de períodos parados
    // =====================================================================

    private const DAY = 86400;

    /**
     * Normaliza a lista de períodos de recesso para pares de timestamp
     * [inicioDia00:00, fimDia23:59:59], ordenados por início e sem entradas
     * inválidas. Cada recesso cobre dias INTEIROS (start_date e end_date são
     * DATE, inclusive em ambas as pontas).
     *
     * Aceita itens no formato ['start_date' => 'Y-m-d'|..., 'end_date' => ...].
     *
     * @param array $recessPeriods
     * @return array<int,array{start:int,end:int}>
     */
    public static function normalizeRecessPeriods(array $recessPeriods): array
    {
        $out = [];
        foreach ($recessPeriods as $r) {
            $s = self::toTimestamp($r['start_date'] ?? null);
            $e = self::toTimestamp($r['end_date'] ?? null);
            if ($s === null || $e === null) {
                continue;
            }
            // Expande para o dia inteiro: início 00:00:00, fim 23:59:59.
            $s = strtotime(date('Y-m-d 00:00:00', $s));
            $e = strtotime(date('Y-m-d 23:59:59', $e));
            if ($e < $s) {
                continue;
            }
            $out[] = ['start' => $s, 'end' => $e];
        }
        usort($out, fn($a, $b) => $a['start'] <=> $b['start']);
        return $out;
    }

    /**
     * Desloca um intervalo [start,end] (timestamps) para fora dos períodos de
     * recesso, aplicando a regra de negócio acordada:
     *
     *  (1) Se o INÍCIO cai dentro de um recesso, o bloco inteiro é empurrado
     *      para começar no primeiro instante APÓS o fim desse recesso
     *      (preservando a duração).
     *  (2) Se o bloco ATRAVESSA um recesso (começa antes e termina dentro ou
     *      depois), o FIM é estendido pelos dias de recesso "engolidos" — o
     *      card pausa durante o recesso e retoma depois.
     *
     * Reaplica em laço até estabilizar (um deslocamento pode encostar em outro
     * recesso adjacente). Puro e determinístico.
     *
     * @param int   $start Timestamp de início.
     * @param int   $end   Timestamp de fim (>= start).
     * @param array $periods Saída de normalizeRecessPeriods().
     * @return array{start:int,end:int,shifted:bool}
     */
    private static function shiftTimestampsOutOfRecess(int $start, int $end, array $periods): array
    {
        if (empty($periods)) {
            return ['start' => $start, 'end' => $end, 'shifted' => false];
        }

        $origStart = $start;
        $origEnd = $end;

        // Passo 1: se o INÍCIO cai dentro de um recesso, empurra-o para o
        // primeiro instante após o recesso. Repete porque recessos adjacentes
        // podem encadear. Converge sempre (só avança no tempo).
        $moved = true;
        while ($moved) {
            $moved = false;
            foreach ($periods as $p) {
                if ($start >= $p['start'] && $start <= $p['end']) {
                    $start = $p['end'] + 1;
                    $moved = true;
                }
            }
        }

        // Passo 2: preserva a duração de CALENDÁRIO original (origEnd-origStart)
        // e avança essa duração a partir do novo início, PULANDO os recessos que
        // aparecerem — o card "pausa" no recesso e retoma depois. Modelo por
        // avanço de duração: determinístico, sem risco de laço.
        $duration = max(0, $origEnd - $origStart);
        $end = self::advanceSkippingRecess($start, $duration, $periods);

        $shifted = ($start !== $origStart) || ($end !== $origEnd);
        return ['start' => $start, 'end' => $end, 'shifted' => $shifted];
    }

    /**
     * Avança $duration segundos a partir de $from, pulando os recessos que o
     * intervalo percorrido atravessar. Para cada recesso cujo início caia
     * dentro do intervalo, o fim é empurrado pela duração do recesso — pausando
     * a contagem durante o período parado. Determinístico (sem laço aberto).
     *
     * @param array<int,array{start:int,end:int}> $periods Ordenados por início.
     */
    private static function advanceSkippingRecess(int $from, int $duration, array $periods): int
    {
        $end = $from + $duration;
        foreach ($periods as $p) {
            // Recesso que começa dentro do intervalo [from, end] engole tempo:
            // empurra o fim pela duração do recesso (+1s de borda inclusiva).
            if ($p['start'] >= $from && $p['start'] <= $end) {
                $end += ($p['end'] - $p['start']) + 1;
            }
        }
        return $end;
    }

    /**
     * Versão pública, com datas em string "Y-m-d H:i:s". Útil para o front/
     * controller e para testes. Retorna as datas deslocadas e a flag 'shifted'.
     *
     * @return array{start_date:string,end_date:string,shifted:bool}|null
     *         null se as datas de entrada forem inválidas.
     */
    public static function shiftOutOfRecess(?string $start, ?string $end, array $recessPeriods): ?array
    {
        $s = self::toTimestamp($start);
        $e = self::toTimestamp($end);
        if ($s === null || $e === null) {
            return null;
        }
        if ($e < $s) {
            $e = $s;
        }
        $periods = self::normalizeRecessPeriods($recessPeriods);
        $r = self::shiftTimestampsOutOfRecess($s, $e, $periods);
        return [
            'start_date' => date('Y-m-d H:i:s', $r['start']),
            'end_date'   => date('Y-m-d H:i:s', $r['end']),
            'shifted'    => $r['shifted'],
        ];
    }

    /**
     * Como recalcDependentDates, mas aplicando o recesso ao resultado.
     *
     * Ordem: calcula as datas base (dependência + duração + teto do due_date);
     * depois desloca para fora dos recessos. Conforme acordado, o RECESSO TEM
     * PRIORIDADE sobre o due_date: se o deslocamento por recesso ultrapassar o
     * prazo, o fim PODE passar do due_date e isso é sinalizado em 'exceeds_due'.
     *
     * @return array{start_date:string,end_date:string,shifted:bool,exceeds_due:bool}|null
     */
    public static function recalcDependentDatesWithRecess(
        ?string $predecessorEnd,
        ?string $depStart,
        ?string $depEnd,
        ?string $depDue,
        int $lagDays,
        array $recessPeriods
    ): ?array {
        $predEndTs = self::toTimestamp($predecessorEnd);
        if ($predEndTs === null) {
            // Sem término do predecessor não há âncora para a cascata.
            return null;
        }
        if ($lagDays < 0) {
            $lagDays = 0;
        }

        // Datas CRUAS (sem teto do due): como o recesso tem prioridade sobre o
        // due, não cortamos no prazo antes de deslocar — senão o card ficaria
        // espremido antes do recesso e o deslocamento nunca aconteceria.
        [$rawStart, $rawEnd] = self::computeRawDependentDates($predEndTs, $depStart, $depEnd, $lagDays);

        $shift = self::shiftOutOfRecess(
            date('Y-m-d H:i:s', $rawStart),
            date('Y-m-d H:i:s', $rawEnd),
            $recessPeriods
        );
        if ($shift === null) {
            // Datas inválidas (não deveria ocorrer): cai no cálculo base com teto.
            $base = self::recalcDependentDates($predecessorEnd, $depStart, $depEnd, $depDue, $lagDays);
            if ($base === null) {
                return null;
            }
            return [
                'start_date'  => $base['start_date'],
                'end_date'    => $base['end_date'],
                'shifted'     => false,
                'exceeds_due' => false,
            ];
        }

        // Se o recesso NÃO deslocou nada, o due_date volta a ser teto (mesmo
        // comportamento do recalcDependentDates base). O recesso só "vence" o
        // due quando de fato empurra o card para fora de um período parado.
        if (!$shift['shifted']) {
            $base = self::recalcDependentDates($predecessorEnd, $depStart, $depEnd, $depDue, $lagDays);
            return [
                'start_date'  => $base['start_date'],
                'end_date'    => $base['end_date'],
                'shifted'     => false,
                'exceeds_due' => false,
            ];
        }

        // Houve deslocamento por recesso: recesso tem prioridade sobre o due.
        // Apenas sinalizamos se o fim ultrapassou o prazo final.
        $exceedsDue = false;
        $dueTs = self::toTimestamp($depDue);
        if ($dueTs !== null) {
            $endTs = self::toTimestamp($shift['end_date']);
            if ($endTs !== null && $endTs > $dueTs) {
                $exceedsDue = true;
            }
        }

        return [
            'start_date'  => $shift['start_date'],
            'end_date'    => $shift['end_date'],
            'shifted'     => true,
            'exceeds_due' => $exceedsDue,
        ];
    }

    // =====================================================================
    // HISTÓRICO — diff legível entre o estado antigo e o novo de um card
    // =====================================================================

    /**
     * Campos auditados no histórico, com rótulo amigável e tipo de formatação.
     * tipo: 'datetime' (Y-m-d H:i -> d/m/Y H:i), 'date' (Y-m-d -> d/m/Y),
     * 'map' (traduz via mapa de labels), 'raw' (texto puro).
     */
    private const HISTORY_FIELDS = [
        'title'                    => ['label' => 'Título',                    'type' => 'raw'],
        'status'                   => ['label' => 'Status',                    'type' => 'map'],
        'priority'                 => ['label' => 'Prioridade',                'type' => 'map'],
        'assigned_to'              => ['label' => 'Responsável',               'type' => 'raw'],
        'technical_responsible_id' => ['label' => 'Responsável técnico',       'type' => 'raw'],
        'analyst_id'               => ['label' => 'Analista',                  'type' => 'raw'],
        'company_id'               => ['label' => 'Empresa',                   'type' => 'raw'],
        'due_date'                 => ['label' => 'Prazo de entrega',          'type' => 'datetime'],
        'start_date'               => ['label' => 'Início do desenvolvimento', 'type' => 'datetime'],
        'end_date'                 => ['label' => 'Fim do desenvolvimento',    'type' => 'datetime'],
        'client_start_date'        => ['label' => 'Início (cliente)',          'type' => 'date'],
        'client_end_date'          => ['label' => 'Fim (cliente)',            'type' => 'date'],
        'predecessor_id'           => ['label' => 'Atividade predecessora',    'type' => 'raw'],
        'predecessor_lag_days'     => ['label' => 'Folga do predecessor (dias)', 'type' => 'raw'],
        'cx_hub_number'            => ['label' => 'CX Hub (número)',           'type' => 'raw'],
        'cx_hub_name'              => ['label' => 'CX Hub (nome)',             'type' => 'raw'],
        'branch_name'              => ['label' => 'Branch',                    'type' => 'raw'],
        'branch_name_2'            => ['label' => 'Branch 2',                   'type' => 'raw'],
        'pr_number'                => ['label' => 'PR',                        'type' => 'raw'],
    ];

    private const STATUS_LABELS = [
        'open' => 'Aberto', 'in_progress' => 'Em andamento',
        'em_revisao_interna' => 'Em revisão interna', 'waiting_client' => 'Aguardando cliente',
        'em_homologacao' => 'Em homologação', 'aprovado_producao' => 'Aprovado p/ produção',
        'completed' => 'Concluído', 'denied' => 'Negado', 'archived' => 'Arquivado',
    ];

    private const PRIORITY_LABELS = [
        'low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente',
    ];

    /**
     * Compara o estado antigo ($old, card completo) com os campos que serão
     * gravados ($new) e devolve a lista de alterações auditáveis.
     *
     * Só considera campos presentes em $new (edição parcial não inventa
     * mudança). Valores null e "" são equivalentes ("não definido"). Datas são
     * normalizadas antes de comparar, para "2026-01-01 09:00:00" e
     * "2026-01-01T09:00" não contarem como diferentes.
     *
     * Cada item: ['field','label','old','new'] com old/new já FORMATADOS para
     * exibição (data em d/m/Y, status/prioridade traduzidos). O chamador grava
     * esses valores legíveis no histórico.
     *
     * @return array<int,array{field:string,label:string,old:string,new:string}>
     */
    public static function diffCardChanges(array $old, array $new): array
    {
        $changes = [];
        foreach (self::HISTORY_FIELDS as $field => $meta) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $beforeRaw = $old[$field] ?? null;
            $afterRaw = $new[$field];

            if (!self::valueChanged($field, $beforeRaw, $afterRaw, $meta['type'])) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $meta['label'],
                'old'   => self::formatHistoryValue($beforeRaw, $meta['type']),
                'new'   => self::formatHistoryValue($afterRaw, $meta['type']),
            ];
        }
        return $changes;
    }

    /** Dois valores de um campo são diferentes (considerando tipo)? */
    private static function valueChanged(string $field, $before, $after, string $type): bool
    {
        if ($type === 'datetime' || $type === 'date') {
            return self::normalizeDateTime($before) !== self::normalizeDateTime($after);
        }
        // Normaliza null/""/espaços para comparação de igualdade textual.
        $b = $before === null ? '' : trim((string) $before);
        $a = $after === null ? '' : trim((string) $after);
        return $b !== $a;
    }

    /** Formata um valor para exibição no histórico conforme o tipo. */
    private static function formatHistoryValue($value, string $type): string
    {
        if ($value === null || trim((string) $value) === '') {
            return 'Não definido';
        }
        $v = trim((string) $value);

        if ($type === 'datetime') {
            $ts = strtotime($v);
            return $ts === false ? $v : date('d/m/Y H:i', $ts);
        }
        if ($type === 'date') {
            $ts = strtotime($v);
            return $ts === false ? $v : date('d/m/Y', $ts);
        }
        if ($type === 'map') {
            return self::STATUS_LABELS[$v] ?? self::PRIORITY_LABELS[$v] ?? $v;
        }
        return $v;
    }
}
