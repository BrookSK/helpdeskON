<?php

class AgendaMeeting
{
    private $db;

    // Status em ordem de fluxo (colunas do Kanban)
    public static $statuses = ['a_agendar', 'agendada', 'confirmada', 'realizada', 'convertida', 'remarcada', 'cancelada'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT m.*, u.name AS assigned_name, c.name AS created_by_name,
                    wc.contact_name AS crm_contact_name, wc.phone AS crm_contact_phone
             FROM agenda_meetings m
             LEFT JOIN users u ON m.assigned_to = u.id
             LEFT JOIN users c ON m.created_by = c.id
             LEFT JOIN whatsapp_contacts wc ON m.contact_id = wc.id
             WHERE m.id = ?",
            [$id]
        );
    }

    public function getList($filters = [])
    {
        $sql = "SELECT m.*, u.name AS assigned_name,
                       wc.contact_name AS crm_contact_name, wc.phone AS crm_contact_phone
                FROM agenda_meetings m
                LEFT JOIN users u ON m.assigned_to = u.id
                LEFT JOIN whatsapp_contacts wc ON m.contact_id = wc.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND m.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        $sql .= " ORDER BY m.meeting_at IS NULL, m.meeting_at ASC, m.position ASC, m.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getGroupedByStatus($filters = [])
    {
        $grouped = [];
        foreach (self::$statuses as $s) $grouped[$s] = [];
        foreach ($this->getList($filters) as $m) {
            $grouped[$m['status']][] = $m;
        }
        return $grouped;
    }

    public function getForCalendar($start, $end, $filters = [])
    {
        $sql = "SELECT m.*, u.name AS assigned_name, wc.contact_name AS crm_contact_name
                FROM agenda_meetings m
                LEFT JOIN users u ON m.assigned_to = u.id
                LEFT JOIN whatsapp_contacts wc ON m.contact_id = wc.id
                WHERE m.meeting_at BETWEEN ? AND ?";
        $params = [$start, $end];
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND m.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        $sql .= " ORDER BY m.meeting_at ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('agenda_meetings', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('agenda_meetings', $data, 'id = ?', [$id]);
    }

    public function updateStatus($id, $status, $position = 0)
    {
        return $this->db->update('agenda_meetings', ['status' => $status, 'position' => $position], 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('agenda_meetings', 'id = ?', [$id]);
    }

    // --- Participantes internos da reunião ---

    /**
     * Define os participantes da reunião (substitui os existentes).
     */
    public function setParticipants($meetingId, array $userIds)
    {
        $this->db->delete('agenda_meeting_participants', 'meeting_id = ?', [$meetingId]);
        foreach ($userIds as $uid) {
            if (!$uid) continue;
            $this->db->insert('agenda_meeting_participants', [
                'meeting_id' => (int)$meetingId,
                'user_id' => (int)$uid,
            ]);
        }
    }

    /**
     * Retorna os participantes (users) de uma reunião.
     */
    public function getParticipants($meetingId)
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.phone, u.role
             FROM agenda_meeting_participants p
             JOIN users u ON p.user_id = u.id
             WHERE p.meeting_id = ?
             ORDER BY u.name",
            [$meetingId]
        );
    }

    /**
     * Retorna nome, e-mail e telefone dos participantes internos de uma reunião,
     * usado para disparar notificações (WhatsApp + e-mail).
     */
    public function getParticipantContacts($meetingId)
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.phone
             FROM agenda_meeting_participants p
             JOIN users u ON p.user_id = u.id
             WHERE p.meeting_id = ?
             ORDER BY u.name",
            [$meetingId]
        );
    }

    /**
     * Retorna apenas os emails dos participantes internos de uma reunião.
     */
    public function getParticipantEmails($meetingId)
    {
        $rows = $this->db->fetchAll(
            "SELECT u.email FROM agenda_meeting_participants p
             JOIN users u ON p.user_id = u.id
             WHERE p.meeting_id = ? AND u.email IS NOT NULL AND u.email <> ''",
            [$meetingId]
        );
        return array_column($rows, 'email');
    }

    // --- Métricas de performance para o Dashboard Comercial ---

    /**
     * Conta reuniões por status para um ou todos os usuários em um período.
     * Retorna array associativo: [user_id => [status => count, ...], ...]
     */
    public function getPerformanceStats($startDate = null, $endDate = null, $userId = null)
    {
        $sql = "SELECT m.assigned_to, u.name AS user_name, m.status, COUNT(*) AS total
                FROM agenda_meetings m
                JOIN users u ON m.assigned_to = u.id
                WHERE m.assigned_to IS NOT NULL";
        $params = [];

        if ($startDate) {
            $sql .= " AND m.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $sql .= " AND m.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        if ($userId) {
            $sql .= " AND m.assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY m.assigned_to, u.name, m.status ORDER BY u.name, m.status";
        $rows = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($rows as $r) {
            $uid = $r['assigned_to'];
            if (!isset($result[$uid])) {
                $result[$uid] = ['user_name' => $r['user_name'], 'total' => 0];
                foreach (self::$statuses as $s) $result[$uid][$s] = 0;
            }
            $result[$uid][$r['status']] = (int)$r['total'];
            $result[$uid]['total'] += (int)$r['total'];
        }
        return $result;
    }

    /**
     * Contatos únicos atendidos (com reunião marcada) por usuário em um período.
     */
    public function getUniqueContactsByUser($startDate = null, $endDate = null, $userId = null)
    {
        $sql = "SELECT m.assigned_to, COUNT(DISTINCT m.contact_id) AS contacts
                FROM agenda_meetings m
                WHERE m.assigned_to IS NOT NULL AND m.contact_id IS NOT NULL";
        $params = [];

        if ($startDate) {
            $sql .= " AND m.created_at >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $sql .= " AND m.created_at <= ?";
            $params[] = $endDate . ' 23:59:59';
        }
        if ($userId) {
            $sql .= " AND m.assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " GROUP BY m.assigned_to";
        $rows = $this->db->fetchAll($sql, $params);

        $result = [];
        foreach ($rows as $r) {
            $result[$r['assigned_to']] = (int)$r['contacts'];
        }
        return $result;
    }

    /**
     * Série mensal de reuniões por status (últimos N meses), opcionalmente por usuário.
     */
    public function getMonthlyTrend($months = 6, $userId = null)
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $sql = "SELECT status, COUNT(*) as total FROM agenda_meetings
                    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
            $params = [$ym];
            if ($userId) {
                $sql .= " AND assigned_to = ?";
                $params[] = $userId;
            }
            $sql .= " GROUP BY status";
            $rows = $this->db->fetchAll($sql, $params);

            $entry = ['month' => $ym, 'label' => date('m/Y', strtotime($ym . '-01'))];
            foreach (self::$statuses as $s) $entry[$s] = 0;
            foreach ($rows as $r) {
                $entry[$r['status']] = (int)$r['total'];
            }
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Métricas de fechamento: quantas reuniões convertidas cada usuário fechou ele mesmo vs terceiros.
     * Retorna: [user_id => ['closed_self' => X, 'closed_by_others' => Y], ...]
     */
    public function getClosingStats($startDate = null, $endDate = null, $userId = null)
    {
        // Fechamentos onde o próprio responsável fechou (assigned_to == closed_by)
        $sqlSelf = "SELECT m.assigned_to AS user_id, COUNT(*) AS total
                    FROM agenda_meetings m
                    WHERE m.status = 'convertida' AND m.closed_by IS NOT NULL AND m.assigned_to = m.closed_by";
        $params = [];
        if ($startDate) { $sqlSelf .= " AND m.created_at >= ?"; $params[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlSelf .= " AND m.created_at <= ?"; $params[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlSelf .= " AND m.assigned_to = ?"; $params[] = $userId; }
        $sqlSelf .= " GROUP BY m.assigned_to";
        $selfRows = $this->db->fetchAll($sqlSelf, $params);

        // Fechamentos onde outra pessoa fechou (assigned_to != closed_by) — perspectiva do prospector
        $sqlOther = "SELECT m.assigned_to AS user_id, COUNT(*) AS total
                     FROM agenda_meetings m
                     WHERE m.status = 'convertida' AND m.closed_by IS NOT NULL AND m.assigned_to != m.closed_by";
        $params2 = [];
        if ($startDate) { $sqlOther .= " AND m.created_at >= ?"; $params2[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlOther .= " AND m.created_at <= ?"; $params2[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlOther .= " AND m.assigned_to = ?"; $params2[] = $userId; }
        $sqlOther .= " GROUP BY m.assigned_to";
        $otherRows = $this->db->fetchAll($sqlOther, $params2);

        // Fechamentos que o user realizou para outros (ele como closed_by mas não é o assigned_to)
        $sqlClosedFor = "SELECT m.closed_by AS user_id, COUNT(*) AS total
                         FROM agenda_meetings m
                         WHERE m.status = 'convertida' AND m.closed_by IS NOT NULL AND m.assigned_to != m.closed_by";
        $params3 = [];
        if ($startDate) { $sqlClosedFor .= " AND m.created_at >= ?"; $params3[] = $startDate . ' 00:00:00'; }
        if ($endDate) { $sqlClosedFor .= " AND m.created_at <= ?"; $params3[] = $endDate . ' 23:59:59'; }
        if ($userId) { $sqlClosedFor .= " AND m.closed_by = ?"; $params3[] = $userId; }
        $sqlClosedFor .= " GROUP BY m.closed_by";
        $closedForRows = $this->db->fetchAll($sqlClosedFor, $params3);

        $result = [];
        foreach ($selfRows as $r) {
            $uid = $r['user_id'];
            if (!isset($result[$uid])) $result[$uid] = ['closed_self' => 0, 'closed_by_others' => 0, 'closed_for_others' => 0];
            $result[$uid]['closed_self'] = (int)$r['total'];
        }
        foreach ($otherRows as $r) {
            $uid = $r['user_id'];
            if (!isset($result[$uid])) $result[$uid] = ['closed_self' => 0, 'closed_by_others' => 0, 'closed_for_others' => 0];
            $result[$uid]['closed_by_others'] = (int)$r['total'];
        }
        foreach ($closedForRows as $r) {
            $uid = $r['user_id'];
            if (!isset($result[$uid])) $result[$uid] = ['closed_self' => 0, 'closed_by_others' => 0, 'closed_for_others' => 0];
            $result[$uid]['closed_for_others'] = (int)$r['total'];
        }
        return $result;
    }
}
