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
     * Lista contatos individuais (leads) para seleção na Agenda/CRM.
     * Exclui grupos.
     */
    public function getLeadsForSelect()
    {
        return $this->db->fetchAll(
            "SELECT id, contact_name, phone
             FROM whatsapp_contacts
             WHERE COALESCE(is_group, 0) = 0
             ORDER BY contact_name IS NULL, contact_name ASC"
        );
    }

    /**
     * Lista os leads para a aba "Meus leads".
     * Um lead é um contato individual (não-grupo) com dados comerciais (briefing) e/ou card no CRM.
     * - super_admin: vê todos.
     * - comercial: só vê os leads atribuídos a ele (assigned_to) ou onde é responsável por card CRM.
     *
     * $filters: ['search' => string, 'temperature' => enum, 'source' => string, 'assigned_to' => int]
     */
    public function getManagedLeads($filters = [])
    {
        $sql = "SELECT c.id, c.contact_name, c.push_name, c.phone, c.assigned_to,
                       c.last_message_at, c.is_group, c.lead_source_url,
                       u.name AS assigned_name,
                       b.lead_temperature, b.lead_source, b.need, b.investment_range,
                       b.urgency, b.main_pain, b.next_step,
                       crm.board_name AS crm_board_name, crm.column_name AS crm_column_name,
                       crm.card_value AS crm_value
                FROM whatsapp_contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                LEFT JOIN commercial_briefings b ON b.contact_id = c.id
                LEFT JOIN (
                    SELECT cc.contact_id, b2.name AS board_name, col.name AS column_name, cc.value AS card_value
                    FROM crm_cards cc
                    JOIN crm_columns col ON cc.column_id = col.id
                    JOIN crm_boards b2 ON col.board_id = b2.id
                    WHERE cc.contact_id IS NOT NULL
                    GROUP BY cc.contact_id
                ) crm ON crm.contact_id = c.id
                WHERE COALESCE(c.is_group, 0) = 0";
        $params = [];

        // Arquivados no CRM (campo dedicado — não afeta o arquivamento do chat).
        // Por padrão só mostra ativos; com 'archived' mostra só os arquivados.
        if (!empty($filters['archived'])) {
            $sql .= " AND c.crm_archived = 1";
        } else {
            $sql .= " AND COALESCE(c.crm_archived, 0) = 0";
        }

        // Escopo por usuário (comercial só vê os seus)
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND c.assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }

        // Busca por nome ou telefone
        if (!empty($filters['search'])) {
            $sql .= " AND (c.contact_name LIKE ? OR c.push_name LIKE ? OR c.phone LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }

        // Filtro por temperatura
        if (!empty($filters['temperature'])) {
            $sql .= " AND b.lead_temperature = ?";
            $params[] = $filters['temperature'];
        }

        // Filtro por fonte do lead
        if (!empty($filters['source'])) {
            $sql .= " AND b.lead_source = ?";
            $params[] = $filters['source'];
        }

        $sql .= " ORDER BY c.last_message_at IS NULL, c.last_message_at DESC, c.contact_name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cria um contato/lead manual (usado quando se cadastra um cliente novo pela Agenda).
     * Usa a instância padrão disponível; gera um remote_jid sintético a partir do telefone.
     */
    public function createManualLead($name, $phone, $assignedTo = null)
    {
        $instance = $this->db->fetch("SELECT id FROM whatsapp_instances WHERE is_default = 1 LIMIT 1")
            ?: $this->db->fetch("SELECT id FROM whatsapp_instances LIMIT 1");
        if (!$instance) return null; // sem instância não é possível criar contato

        $digits = preg_replace('/\D/', '', (string) $phone);
        $jid = ($digits !== '' ? $digits : 'manual_' . uniqid()) . '@s.whatsapp.net';

        // Evita duplicar contato existente com o mesmo jid
        $existing = $this->findByJid($instance['id'], $jid);
        if ($existing) return $existing['id'];

        return $this->db->insert('whatsapp_contacts', [
            'instance_id' => $instance['id'],
            'remote_jid' => $jid,
            'phone' => $digits ?: null,
            'contact_name' => $name ?: 'Cliente',
            'assigned_to' => $assignedTo,
        ]);
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
     * @param int|array|null $instanceId Um ID, array de IDs, ou null para todas as instâncias
     * @param string $type 'contacts', 'groups' ou 'all'
     */
    public function getAll($instanceId, $filters = [], $type = 'contacts')
    {
        $sql = "SELECT c.*, 
                    wi.display_name as instance_display_name,
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
                LEFT JOIN whatsapp_instances wi ON c.instance_id = wi.id
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
                WHERE 1=1";
        $params = [];

        // Filtro por instância(s)
        if (is_array($instanceId) && !empty($instanceId)) {
            $placeholders = implode(',', array_fill(0, count($instanceId), '?'));
            $sql .= " AND c.instance_id IN ($placeholders)";
            $params = array_merge($params, $instanceId);
        } elseif (!empty($instanceId) && !is_array($instanceId)) {
            $sql .= " AND c.instance_id = ?";
            $params[] = $instanceId;
        }
        // Se $instanceId é null ou vazio, não filtra — mostra de todas

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
        if (!empty($filters['assigned_to_or_unassigned'])) {
            // Mostra contatos atribuídos ao usuário OU sem dono (para que ele possa assumir)
            $sql .= " AND (c.assigned_to = ? OR c.assigned_to IS NULL)";
            $params[] = $filters['assigned_to_or_unassigned'];
        } elseif (!empty($filters['assigned_to'])) {
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

        // Evitar duplicatas: se não achou pelo JID exato, tenta casar pelo telefone
        // (mesma pessoa pode aparecer com/sem o 9º dígito ou variações de JID)
        if (!$existing && empty($data['is_group'])) {
            $phoneDigits = preg_replace('/\D/', '', $data['phone'] ?? preg_replace('/@.*/', '', $remoteJid));
            if (!empty($phoneDigits)) {
                $last8 = substr($phoneDigits, -8);
                $existing = $this->db->fetch(
                    "SELECT * FROM whatsapp_contacts
                     WHERE instance_id = ? AND is_group = 0
                       AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ?
                     LIMIT 1",
                    [$instanceId, '%' . $last8]
                );
            }
        }

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
     * Arquivar/desarquivar contato (afeta a lista do chat do WhatsApp)
     */
    public function toggleArchive($id)
    {
        $contact = $this->findById($id);
        if (!$contact) return false;
        $newStatus = $contact['is_archived'] ? 0 : 1;
        return $this->db->update('whatsapp_contacts', ['is_archived' => $newStatus], 'id = ?', [$id]);
    }

    /**
     * Arquivar/desarquivar lead apenas na aba "Meus Leads" do CRM.
     * Usa a coluna crm_archived, sem afetar a visibilidade no chat do WhatsApp.
     */
    public function toggleCrmArchive($id)
    {
        $contact = $this->findById($id);
        if (!$contact) return false;
        $newStatus = empty($contact['crm_archived']) ? 1 : 0;
        return $this->db->update('whatsapp_contacts', ['crm_archived' => $newStatus], 'id = ?', [$id]);
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
