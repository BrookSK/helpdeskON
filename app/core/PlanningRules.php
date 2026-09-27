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
}
