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

        // Duração original do dependente (em segundos). Default: 1 dia.
        $duration = 86400;
        $startTs = self::toTimestamp($depStart);
        $endTs = self::toTimestamp($depEnd);
        if ($startTs !== null && $endTs !== null && $endTs >= $startTs) {
            $duration = $endTs - $startTs;
        }

        // Novo início = fim do predecessor + folga.
        $newStart = $predEndTs + ($lagDays * 86400);
        $newEnd = $newStart + $duration;

        // Teto do prazo final: o fim recalculado não ultrapassa o due_date.
        $dueTs = self::toTimestamp($depDue);
        if ($dueTs !== null && $newEnd > $dueTs) {
            $newEnd = $dueTs;
            // Recua o início para manter a duração, mas sem ir antes do término
            // do predecessor (a dependência tem precedência sobre a duração).
            $candidateStart = $newEnd - $duration;
            $newStart = max($candidateStart, $predEndTs + ($lagDays * 86400));
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
}
