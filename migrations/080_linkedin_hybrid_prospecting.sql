-- Migration 080: Prospecção Híbrida — canal LinkedIn (etapa MANUAL assistida).
--
-- Adiciona ao CRM existente:
--   1) Coluna whatsapp_contacts.linkedin_url  → URL do perfil do lead (dedup,
--      elegibilidade de sequência e variáveis de mensagem). Dado REAL, nunca scraping.
--   2) Canal 'linkedin' em message_templates  → templates de conexão/mensagem/follow-up.
--   3) Tabela linkedin_tasks                  → fila de "Minhas Ações" gerada pelas
--      etapas LinkedIn das sequências. Cada tarefa é executada MANUALMENTE pelo
--      vendedor (abrir + colar + enviar + confirmar). Nenhuma automação de LinkedIn.
--
-- Idempotente: pode ser reexecutada sem duplicar.
-- NÃO armazena senha, cookie, li_at, session token ou credencial: apenas a URL
-- pública do perfil e os dados necessários para a tarefa.

-- =====================================================================
-- 1) whatsapp_contacts.linkedin_url (só cria se ainda não existir)
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'linkedin_url'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE whatsapp_contacts ADD COLUMN linkedin_url VARCHAR(500) NULL AFTER lead_email',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para deduplicação por LinkedIn (só cria se ainda não existir)
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND INDEX_NAME = 'idx_wc_linkedin_url'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE whatsapp_contacts ADD INDEX idx_wc_linkedin_url (linkedin_url)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 2) Canal 'linkedin' em message_templates (ENUM email/whatsapp → +linkedin)
-- =====================================================================
SET @enum_has_linkedin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'message_templates'
      AND COLUMN_NAME = 'channel'
      AND COLUMN_TYPE LIKE '%linkedin%'
);
SET @ddl := IF(@enum_has_linkedin = 0,
    "ALTER TABLE message_templates MODIFY COLUMN channel ENUM('email','whatsapp','linkedin') NOT NULL DEFAULT 'email'",
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 3) Fila de tarefas LinkedIn (Minhas Ações)
-- =====================================================================
-- Uma linha por etapa LinkedIn de um participante de sequência. O motor cria a
-- tarefa e PAUSA o participante; o vendedor executa manualmente e confirma; a
-- confirmação retoma a sequência.
CREATE TABLE IF NOT EXISTS linkedin_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL COMMENT 'Lead (whatsapp_contacts) — entidade única do CRM',
    sequence_id INT DEFAULT NULL COMMENT 'Sequência de origem',
    participant_id INT DEFAULT NULL COMMENT 'sequence_participants.id',
    node_id VARCHAR(64) DEFAULT NULL COMMENT 'Nó da sequência que gerou a tarefa',
    assigned_to INT DEFAULT NULL COMMENT 'Vendedor responsável',
    action_type VARCHAR(30) NOT NULL DEFAULT 'message' COMMENT 'connect | message | followup | final',
    objective VARCHAR(255) DEFAULT NULL COMMENT 'Objetivo da ação (ex.: agendar conversa)',
    linkedin_url VARCHAR(500) DEFAULT NULL COMMENT 'URL do perfil (aberta em nova aba pelo vendedor)',
    template_id INT DEFAULT NULL COMMENT 'Template LinkedIn usado como base (opcional)',
    generated_message MEDIUMTEXT DEFAULT NULL COMMENT 'Mensagem gerada pela IA (com dados reais)',
    final_message MEDIUMTEXT DEFAULT NULL COMMENT 'Mensagem efetivamente enviada (após edição humana)',
    status VARCHAR(20) NOT NULL DEFAULT 'ready' COMMENT 'ready | opened | sent | skipped | replied',
    profile_opened TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Perfil foi aberto (ABRIR+COPIAR)',
    opened_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    sent_by INT DEFAULT NULL COMMENT 'Quem confirmou o ENVIEI',
    skipped_at DATETIME DEFAULT NULL,
    replied_at DATETIME DEFAULT NULL,
    due_at DATETIME DEFAULT NULL COMMENT 'Quando a ação deve ser executada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Idempotência: uma tarefa por (participante, nó). Reexecução do cron não duplica.
    UNIQUE KEY uk_participant_node (participant_id, node_id),
    KEY idx_status (status),
    KEY idx_assigned (assigned_to, status),
    KEY idx_action (action_type),
    KEY idx_due (status, due_at),
    KEY idx_contact (contact_id),
    KEY idx_sent (sent_at),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (sequence_id) REFERENCES email_sequences(id) ON DELETE SET NULL,
    FOREIGN KEY (participant_id) REFERENCES sequence_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
