<?php

class MarketingItem
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT mi.*, u.name AS assigned_name, c.name AS created_by_name, h.title AS holiday_title
             FROM marketing_items mi
             LEFT JOIN users u ON mi.assigned_to = u.id
             LEFT JOIN users c ON mi.created_by = c.id
             LEFT JOIN marketing_holidays h ON mi.holiday_id = h.id
             WHERE mi.id = ?",
            [$id]
        );
    }

    /**
     * Lista itens para o calendário dentro de um período.
     * $filters: assigned_to, status.
     */
    public function getForCalendar($start, $end, $filters = [])
    {
        $sql = "SELECT mi.*, u.name AS assigned_name
                FROM marketing_items mi
                LEFT JOIN users u ON mi.assigned_to = u.id
                WHERE mi.scheduled_at BETWEEN ? AND ?
                  AND mi.status <> 'rejeitado'";
        $params = [$start, $end];

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND mi.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND mi.status = ?";
            $params[] = $filters['status'];
        }
        $sql .= " ORDER BY mi.scheduled_at ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Lista itens por status (para Pendências e Aprovações).
     * $filters: assigned_to, status (array ou string).
     */
    public function getList($filters = [])
    {
        $sql = "SELECT mi.*, u.name AS assigned_name, c.name AS created_by_name
                FROM marketing_items mi
                LEFT JOIN users u ON mi.assigned_to = u.id
                LEFT JOIN users c ON mi.created_by = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND mi.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['status'])) {
            $statuses = (array) $filters['status'];
            $ph = implode(',', array_fill(0, count($statuses), '?'));
            $sql .= " AND mi.status IN ($ph)";
            $params = array_merge($params, $statuses);
        }
        $sql .= " ORDER BY mi.scheduled_at IS NULL, mi.scheduled_at ASC, mi.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Itens de "Pendências": em fase de ideia OU com ajustes solicitados
     * (em produção com observação de revisão preenchida).
     * $filters: assigned_to.
     */
    public function getPendencias($filters = [])
    {
        $sql = "SELECT mi.*, u.name AS assigned_name, c.name AS created_by_name
                FROM marketing_items mi
                LEFT JOIN users u ON mi.assigned_to = u.id
                LEFT JOIN users c ON mi.created_by = c.id
                WHERE (mi.status = 'ideia'
                       OR (mi.status = 'em_producao' AND mi.review_notes IS NOT NULL AND mi.review_notes <> ''))";
        $params = [];
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND mi.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        $sql .= " ORDER BY mi.scheduled_at IS NULL, mi.scheduled_at ASC, mi.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('marketing_items', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('marketing_items', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('marketing_items', 'id = ?', [$id]);
    }

    // ===== Anexos =====
    public function getAttachments($itemId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM marketing_attachments WHERE item_id = ? ORDER BY created_at ASC",
            [$itemId]
        );
    }

    public function addAttachment($data)
    {
        return $this->db->insert('marketing_attachments', $data);
    }

    public function findAttachment($id)
    {
        return $this->db->fetch("SELECT * FROM marketing_attachments WHERE id = ?", [$id]);
    }

    public function deleteAttachment($id)
    {
        return $this->db->delete('marketing_attachments', 'id = ?', [$id]);
    }

    // ===== Datas comemorativas =====
    public function getHolidays($start, $end)
    {
        return $this->db->fetchAll(
            "SELECT * FROM marketing_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC",
            [$start, $end]
        );
    }

    public function holidayExists($date, $title)
    {
        return $this->db->fetch(
            "SELECT id FROM marketing_holidays WHERE holiday_date = ? AND title = ?",
            [$date, $title]
        );
    }

    public function addHoliday($data)
    {
        return $this->db->insert('marketing_holidays', $data);
    }

    public function findHoliday($id)
    {
        return $this->db->fetch("SELECT * FROM marketing_holidays WHERE id = ?", [$id]);
    }
}
