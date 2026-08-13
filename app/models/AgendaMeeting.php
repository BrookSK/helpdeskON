<?php

class AgendaMeeting
{
    private $db;

    // Status em ordem de fluxo (colunas do Kanban)
    public static $statuses = ['a_agendar', 'agendada', 'confirmada', 'realizada', 'remarcada', 'cancelada'];

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
}
