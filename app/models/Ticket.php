<?php

class Ticket
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT t.*, 
                    c.name as client_name, c.email as client_email, c.phone as client_phone,
                    a.name as attendant_name, a.email as attendant_email,
                    tr.name as technical_name, tr.email as technical_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             LEFT JOIN users a ON t.attendant_id = a.id
             LEFT JOIN users tr ON t.technical_responsible_id = tr.id
             WHERE t.id = ?",
            [$id]
        );
    }

    public function getByClient($clientId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, a.name as attendant_name
             FROM tickets t
             LEFT JOIN users a ON t.attendant_id = a.id
             WHERE t.client_id = ?
             ORDER BY t.updated_at DESC",
            [$clientId]
        );
    }

    public function getByCompany($companyId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, a.name as attendant_name, c.name as client_name
             FROM tickets t
             LEFT JOIN users a ON t.attendant_id = a.id
             LEFT JOIN users c ON t.client_id = c.id
             WHERE c.company_id = ?
             ORDER BY t.updated_at DESC",
            [$companyId]
        );
    }

    public function getByAttendant($attendantId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, c.name as client_name, c.email as client_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE t.attendant_id = ? OR t.technical_responsible_id = ?
             ORDER BY t.updated_at DESC",
            [$attendantId, $attendantId]
        );
    }

    /**
     * Tickets atribuídos ao usuário OU criados por ele (usado pelo papel Comercial).
     */
    public function getByAttendantOrCreator($userId)
    {
        return $this->db->fetchAll(
            "SELECT t.*, c.name as client_name, c.email as client_email
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE t.attendant_id = ? OR t.technical_responsible_id = ? OR t.client_id = ?
             ORDER BY t.updated_at DESC",
            [$userId, $userId, $userId]
        );
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT t.*, c.name as client_name, a.name as attendant_name, tr.name as technical_name
                FROM tickets t
                LEFT JOIN users c ON t.client_id = c.id
                LEFT JOIN users a ON t.attendant_id = a.id
                LEFT JOIN users tr ON t.technical_responsible_id = tr.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['attendant_id'])) {
            $sql .= " AND t.attendant_id = ?";
            $params[] = $filters['attendant_id'];
        }
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND t.status NOT IN ('completed', 'archived')";
        }
        if (!empty($filters['hide_archived'])) {
            $sql .= " AND t.status <> 'archived'";
        }
        if (!empty($filters['allowed_companies'])) {
            $ids = $filters['allowed_companies'];
            if (count($ids) === 1 && $ids[0] == 0) {
                $sql .= " AND c.company_id IS NULL";
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND (c.company_id IS NULL OR c.company_id IN ($placeholders))";
                $params = array_merge($params, $ids);
            }
        }

        $sql .= " ORDER BY t.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($attendantId = null, $allowedCompanies = null)
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];
        foreach ($statuses as $status) {
            $sql = "SELECT t.*, c.name as client_name, tr.name as technical_name
                    FROM tickets t
                    LEFT JOIN users c ON t.client_id = c.id
                    LEFT JOIN users tr ON t.technical_responsible_id = tr.id
                    WHERE t.status = ?";
            $params = [$status];
            if ($attendantId) {
                $sql .= " AND (t.attendant_id = ? OR t.technical_responsible_id = ? OR t.attendant_id IS NULL)";
                $params[] = $attendantId;
                $params[] = $attendantId;
            }
            if ($allowedCompanies !== null) {
                if (count($allowedCompanies) === 1 && $allowedCompanies[0] == 0) {
                    $sql .= " AND c.company_id IS NULL";
                } else {
                    $placeholders = implode(',', array_fill(0, count($allowedCompanies), '?'));
                    $sql .= " AND (c.company_id IS NULL OR c.company_id IN ($placeholders))";
                    $params = array_merge($params, $allowedCompanies);
                }
            }
            $sql .= " ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.updated_at DESC";
            $result[$status] = $this->db->fetchAll($sql, $params);
        }
        return $result;
    }

    public function create($data)
    {
        // Admissão na criação (demanda #251, Opção A): só consideramos admissão
        // quando o ticket já nasce EM INÍCIO DE TRABALHO ('in_progress'), como um
        // card criado já em andamento. Nascer direto em um status "posterior"
        // (ex.: 'completed', 'em_homologacao') é um salto sem trabalho registrado
        // e NÃO deve carimbar admissão — carimbá-la no instante da criação daria
        // tempo de admissão/tratamento incorreto. Um admitted_at explícito no
        // $data sempre é respeitado.
        if (!array_key_exists('admitted_at', $data)
            && ($data['status'] ?? 'open') === 'in_progress') {
            $data['admitted_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        }
        return $this->db->insert('tickets', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('tickets', $data, 'id = ?', [$id]);
    }

    /**
     * Status que representam a demanda EM TRABALHO efetivo (demanda #251, Opção A).
     *
     * A "admissão" é o momento em que a demanda entra em tratamento. Isso começa
     * em 'in_progress' e segue nos status posteriores que representam continuidade
     * do tratamento (revisão interna, homologação, aprovado para produção) e a
     * própria conclusão — pois um ticket que chegou a esses status necessariamente
     * já foi trabalhado.
     *
     * NÃO representam admissão: 'open' (ainda não iniciado), 'waiting_client'
     * (espera, não trabalho ativo), 'denied' e 'archived' (saídas sem tratamento).
     * A simples atribuição de atendente também NÃO conta como admissão.
     */
    public const WORK_STATUSES = [
        'in_progress',
        'em_revisao_interna',
        'em_homologacao',
        'aprovado_producao',
        'completed',
    ];

    /**
     * Indica se um status representa a demanda em trabalho efetivo (ver WORK_STATUSES).
     */
    public static function isWorkStatus($status)
    {
        return in_array($status, self::WORK_STATUSES, true);
    }

    /**
     * Decide se uma transição de status representa a ADMISSÃO (início do trabalho).
     *
     * A admissão acontece quando a demanda começa a ser tratada. Isso é verdade
     * quando o novo status é 'in_progress' (início natural do trabalho) OU quando
     * ela já estava em trabalho e apenas avança para outro status de tratamento
     * (ex.: in_progress -> em_homologacao -> completed).
     *
     * NÃO é admissão quando a demanda PULA de um status de não-trabalho direto
     * para um status "posterior" — por exemplo 'open' -> 'completed'. Nesse caso
     * nunca houve início de trabalho registrado, então carimbar a admissão no
     * próprio momento da conclusão geraria tempo de admissão/tratamento incorreto
     * (≈ 0). Esses saltos ficam sem admissão de propósito.
     *
     * @param string|null $previousStatus status antes da transição
     * @param string      $newStatus       status após a transição
     */
    public static function isAdmissionTransition($previousStatus, $newStatus)
    {
        if (!self::isWorkStatus($newStatus)) {
            return false;
        }
        // Início natural do trabalho.
        if ($newStatus === 'in_progress') {
            return true;
        }
        // Continuidade: só admite ao avançar se JÁ vinha de um status de trabalho.
        // Evita saltos como open/waiting_client/denied/archived -> completed.
        return self::isWorkStatus($previousStatus);
    }

    /**
     * Backfill ESTIMADO de admitted_at para o histórico (demanda #251).
     *
     * Preenche admitted_at dos tickets antigos que ainda estão NULL e que já foram
     * efetivamente trabalhados (exclui 'open', 'denied', 'archived'), usando a melhor
     * estimativa do início do trabalho:
     *   1) menor planning_cards.start_date do ticket; senão
     *   2) tickets.updated_at (fallback).
     * Nunca antes de created_at nem depois de completed_at (evita tempo negativo).
     *
     * Espelha a migration 132. É idempotente: só toca tickets com admitted_at NULL,
     * então rodar de novo não altera o que já foi carimbado (real ou estimado).
     * Usado pela migration (SQL) e reaproveitável em testes.
     *
     * @return int linhas afetadas no passo de estimativa
     */
    public function backfillEstimatedAdmittedAt()
    {
        $stmt = $this->db->query(
            "UPDATE tickets t
             SET t.admitted_at = GREATEST(
                     t.created_at,
                     COALESCE(
                         (SELECT MIN(pc.start_date)
                            FROM planning_cards pc
                           WHERE pc.ticket_id = t.id
                             AND pc.start_date IS NOT NULL),
                         t.updated_at
                     )
                 )
             WHERE t.admitted_at IS NULL
               AND t.status NOT IN ('open', 'denied', 'archived')"
        );

        // Teto: admissão nunca depois da conclusão.
        $this->db->query(
            "UPDATE tickets t
             SET t.admitted_at = t.completed_at
             WHERE t.completed_at IS NOT NULL
               AND t.admitted_at IS NOT NULL
               AND t.admitted_at > t.completed_at"
        );

        return $stmt ? $stmt->rowCount() : 0;
    }

    public function updateStatus($id, $status)
    {
        // Precisamos do status anterior para distinguir início/continuidade de
        // trabalho de um salto direto (ex.: open -> completed).
        $previous = $this->db->fetch("SELECT status FROM tickets WHERE id = ?", [$id]);
        $previousStatus = $previous['status'] ?? null;

        $data = ['status' => $status];
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        // Admissão (demanda #251, Opção A): carimba admitted_at apenas quando a
        // transição representa início/continuidade de trabalho. Idempotente —
        // preserva o primeiro instante de admissão.
        if (self::isAdmissionTransition($previousStatus, $status)) {
            $this->stampAdmittedAt($id);
        }
        $result = $this->db->update('tickets', $data, 'id = ?', [$id]);

        // Callback da API de demandas (mão dupla): se o status mudou e a empresa
        // do ticket tem callback configurado, ENFILEIRA o aviso ao sistema
        // externo. Best-effort — jamais quebra a atualização de status (este é o
        // ponto de estrangulamento por onde TODOS os fluxos de mudança de status
        // passam: Kanban, tela de tickets, edição de card).
        $this->enqueueApiStatusCallback((int)$id, $previousStatus, (string)$status);

        return $result;
    }

    public function assignAttendant($ticketId, $attendantId)
    {
        // A atribuição em si NÃO é admissão (demanda #251, Opção A). O ticket vai
        // para 'in_progress', e é essa entrada em trabalho que carimba a admissão.
        $previous = $this->db->fetch("SELECT status FROM tickets WHERE id = ?", [$ticketId]);
        $previousStatus = $previous['status'] ?? null;

        $this->db->update('tickets', ['attendant_id' => $attendantId, 'status' => 'in_progress'], 'id = ?', [$ticketId]);
        $result = $this->stampAdmittedAt($ticketId);

        // Este caminho muda o status para 'in_progress' sem passar por
        // updateStatus(); então também enfileira o callback aqui.
        $this->enqueueApiStatusCallback((int)$ticketId, $previousStatus, 'in_progress');

        return $result;
    }

    /**
     * Enfileira (best-effort) o callback de mudança de status da API de demandas.
     * Isolado em um método próprio para ser reaproveitado pelos caminhos que
     * mudam status (updateStatus/assignAttendant) e para não poluir a lógica de
     * admissão. Nunca lança exceção.
     */
    private function enqueueApiStatusCallback(int $ticketId, ?string $previousStatus, string $newStatus): void
    {
        if (!class_exists('ApiCallbackService')) {
            return;
        }
        try {
            (new ApiCallbackService())->enqueueStatusChange($ticketId, $previousStatus, $newStatus);
        } catch (\Throwable $e) {
            // Silencioso: o callback é complementar e não pode afetar o fluxo.
        }
    }

    /**
     * Registra o instante da ADMISSÃO da demanda (criação -> início do trabalho),
     * usado pelos indicadores de performance (demanda #251). Idempotente: só grava
     * quando ainda não há admitted_at, preservando o primeiro momento em que a
     * demanda entrou em trabalho. Nunca grava antes de created_at.
     *
     * Chamar apenas quando o ticket estiver (ou estiver entrando) em um status de
     * trabalho — ver WORK_STATUSES / isWorkStatus().
     */
    public function stampAdmittedAt($ticketId, $when = null)
    {
        $when = $when ?: date('Y-m-d H:i:s');
        return $this->db->query(
            "UPDATE tickets
             SET admitted_at = ?
             WHERE id = ?
               AND admitted_at IS NULL",
            [$when, $ticketId]
        );
    }

    /**
     * Retorna todos os atendentes vinculados a uma demanda (tabela de junção).
     */
    public function getAttendants($ticketId)
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.role
             FROM ticket_attendants ta
             INNER JOIN users u ON ta.user_id = u.id
             WHERE ta.ticket_id = ?
             ORDER BY u.name ASC",
            [$ticketId]
        );
    }

    /**
     * Define o conjunto de atendentes de uma demanda, sincronizando também o
     * atendente principal (attendant_id) para manter compatibilidade.
     */
    public function setAttendants($ticketId, array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $this->db->query("DELETE FROM ticket_attendants WHERE ticket_id = ?", [$ticketId]);
        foreach ($userIds as $uid) {
            $this->db->query(
                "INSERT IGNORE INTO ticket_attendants (ticket_id, user_id) VALUES (?, ?)",
                [$ticketId, $uid]
            );
        }
        $primary = $userIds[0] ?? null;
        $this->db->update('tickets', ['attendant_id' => $primary], 'id = ?', [$ticketId]);
        return $userIds;
    }

    public function assignTechnical($ticketId, $technicalId)
    {
        return $this->db->update('tickets', ['technical_responsible_id' => $technicalId ?: null], 'id = ?', [$ticketId]);
    }

    /**
     * Tickets em que o usuário é atendente OU responsável técnico, agrupados por status.
     * Usado no kanban para que devs/analistas/atendentes vejam suas atividades.
     */
    public function getGroupedByAssignee($userId)
    {
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $result = [];
        foreach ($statuses as $status) {
            $result[$status] = $this->db->fetchAll(
                "SELECT t.*, c.name as client_name, tr.name as technical_name, a.name as attendant_name
                 FROM tickets t
                 LEFT JOIN users c ON t.client_id = c.id
                 LEFT JOIN users tr ON t.technical_responsible_id = tr.id
                 LEFT JOIN users a ON t.attendant_id = a.id
                 WHERE t.status = ? AND (t.attendant_id = ? OR t.technical_responsible_id = ?)
                 ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.updated_at DESC",
                [$status, $userId, $userId]
            );
        }
        return $result;
    }

    /**
     * Contagem por status de todos os tickets de uma empresa (usado no painel do responsável).
     */
    public function countByCompany($companyId)
    {
        $rows = $this->db->fetchAll(
            "SELECT t.status, COUNT(*) as total
             FROM tickets t
             LEFT JOIN users c ON t.client_id = c.id
             WHERE c.company_id = ?
             GROUP BY t.status",
            [$companyId]
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = $row['total'];
        }
        return $counts;
    }

    public function countByStatus($userId = null, $role = null)
    {
        $sql = "SELECT status, COUNT(*) as total FROM tickets WHERE 1=1";
        $params = [];
        if ($userId && $role === 'client') {
            $sql .= " AND client_id = ?";
            $params[] = $userId;
        } elseif ($userId && $role === 'attendant') {
            $sql .= " AND (attendant_id = ? OR technical_responsible_id = ? OR attendant_id IS NULL)";
            $params[] = $userId;
            $params[] = $userId;
        } elseif ($userId && in_array($role, ['developer', 'analyst'])) {
            $sql .= " AND (attendant_id = ? OR technical_responsible_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }
        $sql .= " GROUP BY status";
        $rows = $this->db->fetchAll($sql, $params);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = $row['total'];
        }
        return $counts;
    }

    /**
     * Métricas operacionais para o dashboard de performance.
     * Retorna estatísticas de resolução de tickets por atendente.
     */
    public function getOperationalMetrics($startDate, $endDate, $attendantId = null)
    {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';

        // Filtro opcional por atendente. Mantido idêntico às demais consultas
        // da tela (respeita o filtro já existente na interface).
        $attendantFilter = '';
        $attParam = [];
        if ($attendantId) {
            $attendantFilter = ' AND t.attendant_id = ?';
            $attParam = [$attendantId];
        }

        // ── Tickets recebidos/admitidos no período ────────────────────────────
        // Contabiliza pela DATA DE ADMISSÃO (criação -> admissão). Apenas tickets
        // que possuem admitted_at entram. É a base da "taxa de conclusão".
        $admitted = $this->db->fetch(
            "SELECT COUNT(*) as total FROM tickets t
             WHERE t.admitted_at IS NOT NULL
               AND t.admitted_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        // ── Tickets concluídos no período ─────────────────────────────────────
        // Somente tickets efetivamente concluídos (completed_at preenchido).
        $completed = $this->db->fetch(
            "SELECT COUNT(*) as total FROM tickets t
             WHERE t.completed_at IS NOT NULL
               AND t.completed_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        // ── Concluídos QUE TAMBÉM foram admitidos (têm admitted_at) ───────────
        // Base do numerador da taxa de conclusão: mantém numerador e denominador
        // sobre a mesma população admitida. Tickets antigos concluídos sem
        // admitted_at (pré-implementação) não entram, então a taxa nunca passa
        // de 100% durante a transição.
        $completedAdmitted = $this->db->fetch(
            "SELECT COUNT(*) as total FROM tickets t
             WHERE t.completed_at IS NOT NULL
               AND t.admitted_at IS NOT NULL
               AND t.completed_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        // ── Tickets pendentes ─────────────────────────────────────────────────
        // Ainda não concluídos/negados/arquivados, criados até o fim do período.
        $pending = $this->db->fetch(
            "SELECT COUNT(*) as total FROM tickets t
             WHERE t.status NOT IN ('completed', 'archived', 'denied')
               AND t.created_at <= ?" . $attendantFilter,
            array_merge([$end], $attParam)
        );

        // ── Tempo médio de ADMISSÃO (criação -> admissão) ─────────────────────
        // Apenas tickets que possuem data de admissão. Janela pela admissão.
        // Usa diferença em MINUTOS (timestamp exato, sem truncar horas) e converte
        // para horas em PHP. Sem tickets elegíveis, AVG retorna NULL -> "sem dados".
        $avgAdmission = $this->db->fetch(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.admitted_at)) as avg_minutes
             FROM tickets t
             WHERE t.admitted_at IS NOT NULL
               AND t.admitted_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        // ── Tempo médio de TRATAMENTO (admissão -> conclusão) ─────────────────
        // Apenas tickets concluídos que também possuem data de admissão.
        // Tickets não admitidos ou não concluídos não entram.
        $avgTreatment = $this->db->fetch(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.admitted_at, t.completed_at)) as avg_minutes
             FROM tickets t
             WHERE t.completed_at IS NOT NULL
               AND t.admitted_at IS NOT NULL
               AND t.completed_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        // ── Tempo médio TOTAL (criação -> conclusão) ──────────────────────────
        // Apenas tickets concluídos.
        $avgTotal = $this->db->fetch(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.completed_at)) as avg_minutes
             FROM tickets t
             WHERE t.completed_at IS NOT NULL
               AND t.completed_at BETWEEN ? AND ?" . $attendantFilter,
            array_merge([$start, $end], $attParam)
        );

        $admittedTotal = (int)($admitted['total'] ?? 0);
        $completedTotal = (int)($completed['total'] ?? 0);
        $completedAdmittedTotal = (int)($completedAdmitted['total'] ?? 0);

        // Concluídos "legados": concluídos no período que NÃO têm data de admissão
        // (demandas anteriores à medição ou saltos open->completed). Ficam de fora
        // da taxa e dos tempos de admissão/tratamento; servem para a tela avisar
        // o usuário e explicar divergências (ex.: "concluiu 3, admitiu 0").
        $completedLegacyTotal = max(0, $completedTotal - $completedAdmittedTotal);

        // Taxa de conclusão = concluídos (admitidos) ÷ recebidos/admitidos × 100.
        // Numerador e denominador restritos à população que possui admitted_at,
        // para não estourar 100% enquanto existirem tickets antigos sem admissão.
        // Sem admitidos no período => SEM BASE de cálculo (null): a view mostra
        // "—" em vez de "0%", que pareceria erro quando há concluídos legados.
        $completionRate = $admittedTotal > 0
            ? round($completedAdmittedTotal / $admittedTotal * 100, 1)
            : null;

        return [
            'admitted' => $admittedTotal,
            'completed' => $completedTotal,
            'completed_legacy' => $completedLegacyTotal,
            'pending' => (int)($pending['total'] ?? 0),
            // null = sem tickets elegíveis (a view exibe "—", não "0h").
            'avg_admission_hours' => self::minutesToHours($avgAdmission['avg_minutes'] ?? null),
            'avg_treatment_hours' => self::minutesToHours($avgTreatment['avg_minutes'] ?? null),
            'avg_total_hours' => self::minutesToHours($avgTotal['avg_minutes'] ?? null),
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Converte uma média em minutos (ou NULL) para horas com 1 casa decimal.
     * Preserva o NULL: ausência de dados NÃO vira 0 — a tela mostra "—".
     */
    private static function minutesToHours($minutes)
    {
        if ($minutes === null) {
            return null;
        }
        return round(((float)$minutes) / 60, 1);
    }

    /**
     * Métricas operacionais por profissional (tabela comparativa) — demanda #251.
     *
     * Para cada profissional exibe: tickets recebidos/admitidos, concluídos,
     * pendentes, atrasados, e os tempos médios de admissão, tratamento e total.
     * Todos os tempos usam os timestamps reais do fluxo (created_at, admitted_at,
     * completed_at); nenhum usa "primeira resposta".
     *
     * "Atrasado" = ticket ainda não concluído cujo card de planejamento vinculado
     * tem due_date vencida (planning_cards.due_date < NOW()).
     */
    public function getOperationalMetricsByAttendant($startDate, $endDate)
    {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';

        $sql = "SELECT
                    a.id as user_id,
                    a.name as user_name,
                    -- recebidos/admitidos no período (pela data de admissão)
                    COUNT(CASE WHEN t.admitted_at IS NOT NULL
                               AND t.admitted_at BETWEEN ? AND ? THEN 1 END) as admitted,
                    -- concluídos no período
                    COUNT(CASE WHEN t.completed_at IS NOT NULL
                               AND t.completed_at BETWEEN ? AND ? THEN 1 END) as completed,
                    -- concluídos que também foram admitidos (numerador da taxa)
                    COUNT(CASE WHEN t.completed_at IS NOT NULL AND t.admitted_at IS NOT NULL
                               AND t.completed_at BETWEEN ? AND ? THEN 1 END) as completed_admitted,
                    -- pendentes (não concluídos/negados/arquivados)
                    COUNT(CASE WHEN t.status NOT IN ('completed', 'archived', 'denied') THEN 1 END) as pending,
                    -- atrasados: pendentes com prazo do planejamento vencido.
                    -- Usa EXISTS (e não JOIN) para não duplicar linhas do ticket
                    -- caso haja mais de um card vinculado.
                    COUNT(CASE WHEN t.status NOT IN ('completed', 'archived', 'denied')
                               AND EXISTS (
                                   SELECT 1 FROM planning_cards pc
                                   WHERE pc.ticket_id = t.id
                                     AND pc.due_date IS NOT NULL
                                     AND pc.due_date < NOW()
                               ) THEN 1 END) as overdue,
                    -- tempo médio de admissão (criação -> admissão), em MINUTOS
                    AVG(CASE WHEN t.admitted_at IS NOT NULL AND t.admitted_at BETWEEN ? AND ?
                        THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.admitted_at) END) as avg_admission_minutes,
                    -- tempo médio de tratamento (admissão -> conclusão), só concluídos e admitidos
                    AVG(CASE WHEN t.completed_at IS NOT NULL AND t.admitted_at IS NOT NULL
                             AND t.completed_at BETWEEN ? AND ?
                        THEN TIMESTAMPDIFF(MINUTE, t.admitted_at, t.completed_at) END) as avg_treatment_minutes,
                    -- tempo médio total (criação -> conclusão), só concluídos
                    AVG(CASE WHEN t.completed_at IS NOT NULL AND t.completed_at BETWEEN ? AND ?
                        THEN TIMESTAMPDIFF(MINUTE, t.created_at, t.completed_at) END) as avg_total_minutes
                FROM users a
                INNER JOIN tickets t ON t.attendant_id = a.id
                WHERE a.role IN ('super_admin', 'attendant', 'developer', 'analyst', 'whatsapp_agent')
                  AND a.is_active = 1
                GROUP BY a.id, a.name
                HAVING admitted > 0 OR completed > 0 OR pending > 0
                ORDER BY completed DESC, admitted DESC";

        $params = [
            $start, $end, // admitted
            $start, $end, // completed
            $start, $end, // completed_admitted
            $start, $end, // avg_admission
            $start, $end, // avg_treatment
            $start, $end, // avg_total
        ];

        $rows = $this->db->fetchAll($sql, $params);

        return array_map(function ($row) {
            $row['admitted'] = (int)($row['admitted'] ?? 0);
            $row['completed'] = (int)($row['completed'] ?? 0);
            $completedAdmitted = (int)($row['completed_admitted'] ?? 0);
            // Concluídos sem data de admissão (legados / saltos): ficam fora da
            // taxa e dos tempos de admissão/tratamento; usados pela tela para avisar.
            $row['completed_legacy'] = max(0, $row['completed'] - $completedAdmitted);
            $row['pending'] = (int)($row['pending'] ?? 0);
            $row['overdue'] = (int)($row['overdue'] ?? 0);
            // null quando o profissional não tem tickets elegíveis para o tempo
            // (a view exibe "—"). Diferença em minutos convertida para horas.
            $row['avg_admission_hours'] = self::minutesToHours($row['avg_admission_minutes'] ?? null);
            $row['avg_treatment_hours'] = self::minutesToHours($row['avg_treatment_minutes'] ?? null);
            $row['avg_total_hours'] = self::minutesToHours($row['avg_total_minutes'] ?? null);
            // Taxa restrita à população admitida (mesmo critério do card geral).
            // Sem admitidos => sem base (null): a view exibe "—", não "0%".
            $row['completion_rate'] = $row['admitted'] > 0
                ? round($completedAdmitted / $row['admitted'] * 100, 1)
                : null;
            unset($row['completed_admitted'], $row['avg_admission_minutes'],
                  $row['avg_treatment_minutes'], $row['avg_total_minutes']);
            return $row;
        }, $rows);
    }

    /**
     * Distribuição de tickets por status para o período.
     */
    public function getStatusDistribution($startDate, $endDate, $attendantId = null)
    {
        $sql = "SELECT t.status, COUNT(*) as total
                FROM tickets t
                WHERE t.created_at BETWEEN ? AND ?";
        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

        if ($attendantId) {
            $sql .= " AND t.attendant_id = ?";
            $params[] = $attendantId;
        }

        $sql .= " GROUP BY t.status ORDER BY total DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
