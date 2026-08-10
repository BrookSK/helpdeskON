<?php

class CrmBoard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================
    // BOARDS
    // =========================================

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM crm_boards WHERE id = ?", [$id]);
    }

    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT b.*, u.name as created_by_name,
                    (SELECT COUNT(*) FROM crm_cards c 
                     JOIN crm_columns col ON c.column_id = col.id 
                     WHERE col.board_id = b.id) as total_cards
             FROM crm_boards b
             LEFT JOIN users u ON b.created_by = u.id
             WHERE b.is_active = 1
             ORDER BY b.created_at DESC"
        );
    }

    public function create($data)
    {
        return $this->db->insert('crm_boards', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('crm_boards', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->update('crm_boards', ['is_active' => 0], 'id = ?', [$id]);
    }

    // =========================================
    // COLUNAS
    // =========================================

    public function getColumns($boardId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM crm_columns WHERE board_id = ? ORDER BY position ASC",
            [$boardId]
        );
    }

    public function findColumn($id)
    {
        return $this->db->fetch("SELECT * FROM crm_columns WHERE id = ?", [$id]);
    }

    public function createColumn($data)
    {
        // Pegar próxima posição
        $last = $this->db->fetch(
            "SELECT MAX(position) as max_pos FROM crm_columns WHERE board_id = ?",
            [$data['board_id']]
        );
        $data['position'] = ($last['max_pos'] ?? -1) + 1;

        return $this->db->insert('crm_columns', $data);
    }

    public function updateColumn($id, $data)
    {
        return $this->db->update('crm_columns', $data, 'id = ?', [$id]);
    }

    public function deleteColumn($id)
    {
        return $this->db->delete('crm_columns', 'id = ?', [$id]);
    }

    public function reorderColumns($boardId, $columnIds)
    {
        foreach ($columnIds as $position => $columnId) {
            $this->db->update('crm_columns', ['position' => $position], 'id = ? AND board_id = ?', [$columnId, $boardId]);
        }
    }

    // =========================================
    // CARDS
    // =========================================

    public function getCards($columnId)
    {
        return $this->db->fetchAll(
            "SELECT c.*, u.name as assigned_name, wc.contact_name, wc.phone as contact_phone,
                    cb.investment_range, cb.lead_temperature,
                    l.name as label_name, l.color as label_color
             FROM crm_cards c
             LEFT JOIN users u ON c.assigned_to = u.id
             LEFT JOIN whatsapp_contacts wc ON c.contact_id = wc.id
             LEFT JOIN commercial_briefings cb ON cb.contact_id = c.contact_id
             LEFT JOIN whatsapp_labels l ON c.label_id = l.id
             WHERE c.column_id = ?
             ORDER BY c.position ASC",
            [$columnId]
        );
    }

    public function getCardsByBoard($boardId)
    {
        return $this->db->fetchAll(
            "SELECT c.*, col.name as column_name, col.color as column_color,
                    u.name as assigned_name, wc.contact_name, wc.phone as contact_phone
             FROM crm_cards c
             JOIN crm_columns col ON c.column_id = col.id
             LEFT JOIN users u ON c.assigned_to = u.id
             LEFT JOIN whatsapp_contacts wc ON c.contact_id = wc.id
             WHERE col.board_id = ?
             ORDER BY col.position ASC, c.position ASC",
            [$boardId]
        );
    }

    public function findCard($id)
    {
        return $this->db->fetch(
            "SELECT c.*, col.name as column_name, col.board_id,
                    u.name as assigned_name, wc.contact_name, wc.phone as contact_phone,
                    wc.remote_jid, cb.investment_range
             FROM crm_cards c
             JOIN crm_columns col ON c.column_id = col.id
             LEFT JOIN users u ON c.assigned_to = u.id
             LEFT JOIN whatsapp_contacts wc ON c.contact_id = wc.id
             LEFT JOIN commercial_briefings cb ON cb.contact_id = c.contact_id
             WHERE c.id = ?",
            [$id]
        );
    }

    public function createCard($data)
    {
        // Pegar próxima posição na coluna
        $last = $this->db->fetch(
            "SELECT MAX(position) as max_pos FROM crm_cards WHERE column_id = ?",
            [$data['column_id']]
        );
        $data['position'] = ($last['max_pos'] ?? -1) + 1;

        $cardId = $this->db->insert('crm_cards', $data);

        // Registrar atividade
        $this->addActivity($cardId, $data['created_by'] ?? null, 'create', 'Card criado');

        return $cardId;
    }

    public function updateCard($id, $data)
    {
        return $this->db->update('crm_cards', $data, 'id = ?', [$id]);
    }

    public function moveCard($cardId, $newColumnId, $position = 0)
    {
        $card = $this->findCard($cardId);
        $this->db->update('crm_cards', [
            'column_id' => $newColumnId,
            'position' => $position,
        ], 'id = ?', [$cardId]);

        // Log de movimentação
        $newCol = $this->findColumn($newColumnId);
        $this->addActivity($cardId, null, 'move', "Movido para \"{$newCol['name']}\"");
    }

    public function deleteCard($id)
    {
        return $this->db->delete('crm_cards', 'id = ?', [$id]);
    }

    /**
     * Primeira coluna (menor position) de um board.
     */
    public function getFirstColumn($boardId)
    {
        return $this->db->fetch(
            "SELECT * FROM crm_columns WHERE board_id = ? ORDER BY position ASC LIMIT 1",
            [$boardId]
        );
    }

    /**
     * Move para a primeira coluna os cards cuja data de retomada já chegou.
     * Retorna a quantidade movida.
     */
    public function processFollowUps()
    {
        $cards = $this->db->fetchAll(
            "SELECT c.id, col.board_id
             FROM crm_cards c
             JOIN crm_columns col ON c.column_id = col.id
             WHERE c.follow_up_at IS NOT NULL
               AND c.follow_up_at <= CURDATE()
               AND c.lead_outcome = 'open'"
        );
        $moved = 0;
        foreach ($cards as $c) {
            $first = $this->getFirstColumn($c['board_id']);
            if ($first) {
                $this->db->update('crm_cards', [
                    'column_id' => $first['id'],
                    'position' => 0,
                    'follow_up_at' => null,
                ], 'id = ?', [$c['id']]);
                $this->addActivity($c['id'], null, 'move', 'Retomada de contato — movido para a primeira coluna');
                $moved++;
            }
        }
        return $moved;
    }

    /**
     * Estatísticas do CRM para o dashboard.
     */
    public function getDashboardStats()
    {
        $total = $this->db->fetch("SELECT COUNT(*) as t FROM crm_cards")['t'] ?? 0;
        $withLabel = $this->db->fetch("SELECT COUNT(*) as t FROM crm_cards WHERE label_id IS NOT NULL")['t'] ?? 0;
        $converted = $this->db->fetch("SELECT COUNT(*) as t FROM crm_cards WHERE lead_outcome = 'converted'")['t'] ?? 0;
        $lost = $this->db->fetch("SELECT COUNT(*) as t FROM crm_cards WHERE lead_outcome = 'lost'")['t'] ?? 0;
        $totalValue = $this->db->fetch("SELECT COALESCE(SUM(value),0) as v FROM crm_cards WHERE lead_outcome = 'converted'")['v'] ?? 0;
        $open = $this->db->fetch("SELECT COUNT(*) as t FROM crm_cards WHERE lead_outcome = 'open'")['t'] ?? 0;

        return [
            'total' => (int)$total,
            'with_label' => (int)$withLabel,
            'converted' => (int)$converted,
            'lost' => (int)$lost,
            'open' => (int)$open,
            'total_converted_value' => (float)$totalValue,
        ];
    }

    // =========================================
    // ATIVIDADES
    // =========================================

    public function getActivities($cardId, $limit = 20)
    {
        return $this->db->fetchAll(
            "SELECT a.*, u.name as user_name
             FROM crm_card_activities a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.card_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$cardId, $limit]
        );
    }

    public function addActivity($cardId, $userId, $type, $description)
    {
        return $this->db->insert('crm_card_activities', [
            'card_id' => $cardId,
            'user_id' => $userId,
            'activity_type' => $type,
            'description' => $description,
        ]);
    }
}
