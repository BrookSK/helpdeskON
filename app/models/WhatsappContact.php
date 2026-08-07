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

    /**
     * Retorna contatos com filtros (para lista do chat)
     */
    public function getAll($instanceId, $filters = [])
    {
        $sql = "SELECT c.*, 
                    (SELECT GROUP_CONCAT(l.name SEPARATOR ', ') 
                     FROM whatsapp_contact_labels cl 
                     JOIN whatsapp_labels l ON cl.label_id = l.id 
                     WHERE cl.contact_id = c.id) as labels,
                    u.name as assigned_name
                FROM whatsapp_contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                WHERE c.instance_id = ? AND c.is_group = 0";
        $params = [$instanceId];

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
     * Cria ou atualiza um contato (upsert)
     */
    public function upsert($instanceId, $remoteJid, $data)
    {
        $existing = $this->findByJid($instanceId, $remoteJid);

        if ($existing) {
            $updateData = array_filter($data, fn($v) => $v !== null);
            if (!empty($updateData)) {
                $this->db->update('whatsapp_contacts', $updateData, 'id = ?', [$existing['id']]);
            }
            return $existing['id'];
        }

        $insertData = array_merge([
            'instance_id' => $instanceId,
            'remote_jid' => $remoteJid,
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
