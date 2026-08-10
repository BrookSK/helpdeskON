<?php

class WhatsappContact
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM whatsapp_contacts WHERE id = ?", [$id]);
    }

    public function findByJid($instanceId, $remoteJid)
    {
        return $this->db->fetch(
            "SELECT * FROM whatsapp_contacts WHERE instance_id = ? AND remote_jid = ?",
            [$instanceId, $remoteJid]
        );
    }

    // =========================================
    // BRIEFING COMERCIAL
    // =========================================

    public function getBriefing($contactId)
    {
        return $this->db->fetch("SELECT * FROM commercial_briefings WHERE contact_id = ?", [$contactId]);
    }

    public function saveBriefing($contactId, $data, $userId = null)
    {
        $existing = $this->getBriefing($contactId);
        if ($existing) {
            $this->db->update('commercial_briefings', $data, 'contact_id = ?', [$contactId]);
            return $existing['id'];
        }
        $data['contact_id'] = $contactId;
        $data['created_by'] = $userId;
        return $this->db->insert('commercial_briefings', $data);
    }

    /**
     * Retorna todos os grupos de WhatsApp conhecidos (para seleção em dropdowns).
     * Independente da instância — usado para vincular grupos a empresas.
     */
    public function getAllGroups()
    {
        return $this->db->fetchAll(
            "SELECT c.id, c.instance_id, c.remote_jid,
                    COALESCE(c.contact_name, c.push_name, c.remote_jid) as name
             FROM whatsapp_contacts c
             WHERE c.is_group = 1
             ORDER BY name ASC"
        );
    }

    /**
     * Busca um grupo pelo remote_jid (qualquer instância).
     */
    public function findGroupByJid($remoteJid)
    {
        return $this->db->fetch(
            "SELECT * FROM whatsapp_contacts WHERE remote_jid = ? AND is_group = 1 LIMIT 1",
            [$remoteJid]
        );
    }

    /**
     * Retorna contatos com filtros (para lista do chat)
     * @param string $type 'contacts', 'groups' ou 'all'
     */
    public function getAll($instanceId, $filters = [], $type = 'contacts')
    {
        $sql = "SELECT c.*, 
                    (SELECT GROUP_CONCAT(l.name SEPARATOR ', ') 
                     FROM whatsapp_contact_labels cl 
                     JOIN whatsapp_labels l ON cl.label_id = l.id 
                     WHERE cl.contact_id = c.id) as labels,
                    u.name as assigned_name,
                    lm.message_text as last_message_text,
                    lm.message_type as last_message_type,
                    lm.from_me as last_message_from_me,
                    lm.sender_name as last_message_sender,
                    crm.board_name as crm_board_name,
                    crm.column_name as crm_column_name
                FROM whatsapp_contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                LEFT JOIN (
                    SELECT cc.contact_id, b.name as board_name, col.name as column_name
                    FROM crm_cards cc
                    JOIN crm_columns col ON cc.column_id = col.id
                    JOIN crm_boards b ON col.board_id = b.id
                    WHERE cc.contact_id IS NOT NULL
                    GROUP BY cc.contact_id
                ) crm ON crm.contact_id = c.id
                LEFT JOIN (
                    SELECT m1.contact_id, m1.message_text, m1.message_type, m1.from_me, m1.sender_name
                    FROM whatsapp_messages m1
                    INNER JOIN (
                        SELECT contact_id, MAX(id) as max_id
                        FROM whatsapp_messages
                        GROUP BY contact_id
                    ) m2 ON m1.contact_id = m2.contact_id AND m1.id = m2.max_id
                ) lm ON lm.contact_id = c.id
                WHERE c.instance_id = ?";
        $params = [$instanceId];

        // Filtro por tipo (contatos individuais vs grupos)
        if ($type === 'contacts') {
            $sql .= " AND c.is_group = 0";
        } elseif ($type === 'groups') {
            $sql .= " AND c.is_group = 1";
        }

        // Filtro por status de atendimento
        if (!empty($filters['service_status'])) {
            $sql .= " AND c.service_status = ?";
            $params[] = $filters['service_status'];
        }

        // Filtro por atendente atribuído
        if (!empty($filters['assigned_to'])) {
            if ($filters['assigned_to'] === 'unassigned') {
                $sql .= " AND c.assigned_to IS NULL";
            } else {
                $sql .= " AND c.assigned_to = ?";
                $params[] = $filters['assigned_to'];
            }
        }

        // Filtro por etiqueta
        if (!empty($filters['label_id'])) {
            $sql .= " AND c.id IN (SELECT contact_id FROM whatsapp_contact_labels WHERE label_id = ?)";
            $params[] = $filters['label_id'];
        }

        // Filtro por busca (nome ou telefone)
        if (!empty($filters['search'])) {
            $sql .= " AND (c.contact_name LIKE ? OR c.push_name LIKE ? OR c.phone LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Ocultar arquivados por padrão
        if (empty($filters['show_archived'])) {
            $sql .= " AND c.is_archived = 0";
        }

        $sql .= " ORDER BY c.last_message_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Atualizar status de atendimento
     */
    public function updateServiceStatus($id, $status)
    {
        $validStatuses = ['em_atendimento', 'aguardando', 'concluido', 'novo'];
        if (!in_array($status, $validStatuses)) return false;
        return $this->db->update('whatsapp_contacts', ['service_status' => $status], 'id = ?', [$id]);
    }

    /**
     * Cria ou atualiza um contato (upsert)
     * No update, NÃO sobrescreve contact_name se já editado manualmente
     */
    public function upsert($instanceId, $remoteJid, $data, $pushName = null)
    {
        $existing = $this->findByJid($instanceId, $remoteJid);

        if ($existing) {
            $updateData = array_filter($data, fn($v) => $v !== null);
            // Não sobrescrever contact_name se já foi definido manualmente
            unset($updateData['contact_name']);
            if (!empty($updateData)) {
                $this->db->update('whatsapp_contacts', $updateData, 'id = ?', [$existing['id']]);
            }
            return $existing['id'];
        }

        // Novo contato — definir contact_name a partir do pushName
        $insertData = array_merge([
            'instance_id' => $instanceId,
            'remote_jid' => $remoteJid,
            'contact_name' => $pushName ?: null,
        ], $data);

        return $this->db->insert('whatsapp_contacts', $insertData);
    }

    /**
     * Atualiza observações internas
     */
    public function updateNotes($id, $notes)
    {
        return $this->db->update('whatsapp_contacts', ['internal_notes' => $notes], 'id = ?', [$id]);
    }

    /**
     * Atribuir contato a um atendente
     */
    public function assignTo($id, $userId)
    {
        return $this->db->update('whatsapp_contacts', ['assigned_to' => $userId], 'id = ?', [$id]);
    }

    /**
     * Arquivar/desarquivar contato
     */
    public function toggleArchive($id)
    {
        $contact = $this->findById($id);
        if (!$contact) return false;
        $newStatus = $contact['is_archived'] ? 0 : 1;
        return $this->db->update('whatsapp_contacts', ['is_archived' => $newStatus], 'id = ?', [$id]);
    }

    /**
     * Atualizar última mensagem
     */
    public function updateLastMessage($id, $timestamp)
    {
        return $this->db->update('whatsapp_contacts', [
            'last_message_at' => $timestamp,
        ], 'id = ?', [$id]);
    }

    /**
     * Incrementar contador de não lidas
     */
    public function incrementUnread($id)
    {
        $this->db->query("UPDATE whatsapp_contacts SET unread_count = unread_count + 1 WHERE id = ?", [$id]);
    }

    /**
     * Zerar não lidas
     */
    public function clearUnread($id)
    {
        return $this->db->update('whatsapp_contacts', ['unread_count' => 0], 'id = ?', [$id]);
    }

    // =========================================
    // ETIQUETAS
    // =========================================

    public function getLabels($contactId)
    {
        return $this->db->fetchAll(
            "SELECT l.* FROM whatsapp_labels l
             JOIN whatsapp_contact_labels cl ON l.id = cl.label_id
             WHERE cl.contact_id = ?",
            [$contactId]
        );
    }

    public function addLabel($contactId, $labelId)
    {
        // Ignora duplicatas (UNIQUE KEY)
        try {
            $this->db->insert('whatsapp_contact_labels', [
                'contact_id' => $contactId,
                'label_id' => $labelId,
            ]);
        } catch (\Exception $e) {
            // Já existe
        }
    }

    public function removeLabel($contactId, $labelId)
    {
        $this->db->delete('whatsapp_contact_labels', 'contact_id = ? AND label_id = ?', [$contactId, $labelId]);
    }

    // =========================================
    // LABELS CRUD
    // =========================================

    public function getAllLabels()
    {
        return $this->db->fetchAll("SELECT * FROM whatsapp_labels ORDER BY name ASC");
    }

    public function createLabel($name, $color, $createdBy)
    {
        return $this->db->insert('whatsapp_labels', [
            'name' => $name,
            'color' => $color,
            'created_by' => $createdBy,
        ]);
    }

    public function deleteLabel($id)
    {
        return $this->db->delete('whatsapp_labels', 'id = ?', [$id]);
    }
}
