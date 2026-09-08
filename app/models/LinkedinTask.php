<?php

/**
 * Fila de ações LinkedIn (Minhas Ações).
 *
 * Cada tarefa nasce de uma etapa LinkedIn de uma sequência: o motor cria a tarefa
 * (com a mensagem já preparada pela IA) e PAUSA o participante. O vendedor executa
 * MANUALMENTE (abrir perfil + colar + enviar) e confirma com "ENVIEI"; a confirmação
 * retoma a sequência.
 *
 * O CRM guarda apenas a URL pública do perfil e os dados da tarefa — nunca senha,
 * cookie, token de sessão ou credencial. Nenhuma automação de LinkedIn.
 *
 * Tabela real: linkedin_tasks (migration 080).
 */
class LinkedinTask
{
    private $db;

    // Estados possíveis
    const S_READY   = 'ready';    // aguardando ação humana
    const S_OPENED  = 'opened';   // perfil aberto (ABRIR+COPIAR) — ainda não enviado
    const S_SENT    = 'sent';     // vendedor confirmou o envio
    const S_SKIPPED = 'skipped';  // pulada
    const S_REPLIED = 'replied';  // lead respondeu (registrado manualmente)

    // Ações
    const A_CONNECT  = 'connect';
    const A_MESSAGE  = 'message';
    const A_FOLLOWUP = 'followup';
    const A_FINAL    = 'final';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM linkedin_tasks WHERE id = ?", [$id]);
    }

    /** Tarefa aberta (ready/opened) de um par participante+nó, se existir. */
    public function findOpenByParticipantNode($participantId, $nodeId)
    {
        return $this->db->fetch(
            "SELECT * FROM linkedin_tasks
             WHERE participant_id = ? AND node_id = ? AND status IN ('ready','opened')
             ORDER BY id DESC LIMIT 1",
            [$participantId, $nodeId]
        );
    }

    public function create(array $data)
    {
        return $this->db->insert('linkedin_tasks', $data);
    }

    public function update($id, array $data)
    {
        return $this->db->update('linkedin_tasks', $data, 'id = ?', [$id]);
    }

    /**
     * Cria a tarefa de forma idempotente por (participant_id, node_id): se já houver
     * uma tarefa aberta desse par, retorna o id existente em vez de duplicar.
     * @return int id da tarefa
     */
    public function createIdempotent(array $data)
    {
        if (!empty($data['participant_id']) && !empty($data['node_id'])) {
            $existing = $this->findOpenByParticipantNode($data['participant_id'], $data['node_id']);
            if ($existing) return (int) $existing['id'];
        }
        return (int) $this->create($data);
    }

    /**
     * Lista de tarefas para a fila "Minhas Ações", com filtros.
     * $filters: [
     *   'scope'   => 'today'|'overdue'|'all' (default all, dentre as pendentes),
     *   'status'  => 'ready'|'opened'|'sent'|'skipped'|'replied'|'pending',
     *   'assigned_to' => int (0/null = todos),
     *   'action_type' => 'connect'|'message'|'followup'|'final',
     *   'limit', 'offset'
     * ]
     */
    public function getList(array $filters = [])
    {
        [$where, $params] = $this->buildWhere($filters);

        $limit = min(200, max(1, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = "SELECT t.*,
                       COALESCE(wc.contact_name, wc.push_name, 'Lead') AS lead_name,
                       wc.lead_email, wc.phone,
                       s.name AS sequence_name,
                       u.name AS assigned_name,
                       b.need AS lead_need, b.notes AS lead_notes
                FROM linkedin_tasks t
                JOIN whatsapp_contacts wc ON t.contact_id = wc.id
                LEFT JOIN email_sequences s ON t.sequence_id = s.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN commercial_briefings b ON b.contact_id = wc.id
                WHERE $where
                ORDER BY (t.status = 'ready' OR t.status = 'opened') DESC,
                         t.due_at IS NULL, t.due_at ASC, t.id ASC
                LIMIT $limit OFFSET $offset";
        return $this->db->fetchAll($sql, $params);
    }

    public function countList(array $filters = [])
    {
        [$where, $params] = $this->buildWhere($filters);
        $r = $this->db->fetch("SELECT COUNT(*) AS t FROM linkedin_tasks t WHERE $where", $params);
        return (int) ($r['t'] ?? 0);
    }

    /**
     * Contadores por estado/escopo, para os indicadores da tela.
     * @return array {pending, today, overdue, sent, skipped, replied}
     */
    public function counters($assignedTo = null)
    {
        $params = [];
        $scopeSql = '';
        if (!empty($assignedTo)) { $scopeSql = ' AND assigned_to = ?'; $params[] = (int) $assignedTo; }

        $row = $this->db->fetch(
            "SELECT
                SUM(status IN ('ready','opened')) AS pending,
                SUM(status IN ('ready','opened') AND (due_at IS NULL OR DATE(due_at) <= CURDATE())) AS today,
                SUM(status IN ('ready','opened') AND due_at IS NOT NULL AND due_at < NOW()) AS overdue,
                SUM(status = 'sent') AS sent,
                SUM(status = 'skipped') AS skipped,
                SUM(status = 'replied') AS replied
             FROM linkedin_tasks
             WHERE 1=1 $scopeSql",
            $params
        );
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'today'   => (int) ($row['today'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'sent'    => (int) ($row['sent'] ?? 0),
            'skipped' => (int) ($row['skipped'] ?? 0),
            'replied' => (int) ($row['replied'] ?? 0),
        ];
    }

    /** Marca o perfil como aberto (ABRIR+COPIAR). NÃO conclui a tarefa. */
    public function markOpened($id)
    {
        return $this->update($id, [
            'status' => self::S_OPENED,
            'profile_opened' => 1,
            'opened_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Marca como enviada (ENVIEI). */
    public function markSent($id, $userId, $finalMessage = null)
    {
        $data = [
            'status' => self::S_SENT,
            'sent_at' => date('Y-m-d H:i:s'),
            'sent_by' => $userId,
        ];
        if ($finalMessage !== null) $data['final_message'] = $finalMessage;
        return $this->update($id, $data);
    }

    public function markSkipped($id)
    {
        return $this->update($id, ['status' => self::S_SKIPPED, 'skipped_at' => date('Y-m-d H:i:s')]);
    }

    public function markReplied($id)
    {
        return $this->update($id, ['status' => self::S_REPLIED, 'replied_at' => date('Y-m-d H:i:s')]);
    }

    // ---- helpers ----

    private function buildWhere(array $filters)
    {
        $where = '1=1';
        $params = [];

        $status = $filters['status'] ?? '';
        if ($status === 'pending') {
            $where .= " AND t.status IN ('ready','opened')";
        } elseif (in_array($status, [self::S_READY, self::S_OPENED, self::S_SENT, self::S_SKIPPED, self::S_REPLIED], true)) {
            $where .= ' AND t.status = ?';
            $params[] = $status;
        }

        $scope = $filters['scope'] ?? '';
        if ($scope === 'today') {
            $where .= " AND t.status IN ('ready','opened') AND (t.due_at IS NULL OR DATE(t.due_at) <= CURDATE())";
        } elseif ($scope === 'overdue') {
            $where .= " AND t.status IN ('ready','opened') AND t.due_at IS NOT NULL AND t.due_at < NOW()";
        }

        if (!empty($filters['assigned_to'])) {
            $where .= ' AND t.assigned_to = ?';
            $params[] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['action_type'])) {
            $where .= ' AND t.action_type = ?';
            $params[] = $filters['action_type'];
        }

        return [$where, $params];
    }
}
