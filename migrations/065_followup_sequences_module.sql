-- Migration 065: Módulo de Follow-up Visual de E-mails + Timeline + Score unificados.
-- O Lead é o whatsapp_contacts existente. Nada de base paralela.

-- ============================================
-- Campos de controle no Lead (contato)
-- ============================================
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='whatsapp_contacts' AND COLUMN_NAME='unsubscribed');
SET @s := IF(@c=0,'ALTER TABLE whatsapp_contacts ADD COLUMN unsubscribed TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='whatsapp_contacts' AND COLUMN_NAME='email_bounced');
SET @s := IF(@c=0,'ALTER TABLE whatsapp_contacts ADD COLUMN email_bounced TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================
-- Timeline única do Lead
-- ============================================
CREATE TABLE IF NOT EXISTS lead_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL COMMENT 'created, origin, email_sent, email_received, email_open, email_click, email_reply, bounce, sequence_start, sequence_step, sequence_stop, board_move, tag, score, note, call',
    description TEXT DEFAULT NULL,
    meta JSON DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_time (contact_id, created_at DESC),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Score comercial automático do Lead
-- ============================================
CREATE TABLE IF NOT EXISTS lead_score (
    contact_id INT PRIMARY KEY,
    score INT NOT NULL DEFAULT 0,
    classification VARCHAR(20) NOT NULL DEFAULT 'frio' COMMENT 'frio, morno, engajado, quente',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Mensagens de e-mail (enviadas E recebidas) vinculadas ao Lead
-- Unifica o histórico: manual e automático na mesma estrutura.
-- ============================================
CREATE TABLE IF NOT EXISTS email_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    email_account_id INT DEFAULT NULL,
    direction ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
    origin ENUM('manual','sequence') NOT NULL DEFAULT 'manual',
    sequence_participant_id INT DEFAULT NULL,
    node_id VARCHAR(64) DEFAULT NULL,
    message_id VARCHAR(255) DEFAULT NULL COMMENT 'Message-ID SMTP',
    in_reply_to VARCHAR(255) DEFAULT NULL,
    thread_key VARCHAR(255) DEFAULT NULL COMMENT 'chave de agrupamento (email do lead)',
    recipient_email VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(300) DEFAULT NULL,
    body MEDIUMTEXT DEFAULT NULL,
    track_token VARCHAR(64) DEFAULT NULL COMMENT 'token para pixel/redirect',
    status ENUM('queued','sent','failed','received') NOT NULL DEFAULT 'sent',
    error_message TEXT DEFAULT NULL,
    open_count INT NOT NULL DEFAULT 0,
    click_count INT NOT NULL DEFAULT 0,
    first_open_at DATETIME DEFAULT NULL,
    last_open_at DATETIME DEFAULT NULL,
    first_click_at DATETIME DEFAULT NULL,
    replied_at DATETIME DEFAULT NULL,
    sent_by INT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_track (track_token),
    KEY idx_contact (contact_id, created_at DESC),
    KEY idx_thread (thread_key),
    KEY idx_message_id (message_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Eventos de e-mail (open/click/reply/bounce) — auditoria fina
-- ============================================
CREATE TABLE IF NOT EXISTS email_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL COMMENT 'FK para email_messages.id',
    contact_id INT NOT NULL,
    event_type ENUM('open','click','reply','bounce','delivered','unsubscribe') NOT NULL,
    link_url VARCHAR(1000) DEFAULT NULL,
    user_agent VARCHAR(300) DEFAULT NULL,
    ip VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_message (message_id),
    KEY idx_contact (contact_id),
    FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Definição de sequências (fluxo visual)
-- ============================================
CREATE TABLE IF NOT EXISTS email_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    email_account_id INT DEFAULT NULL COMMENT 'conta usada para envio (default se null)',
    graph JSON DEFAULT NULL COMMENT 'nós e conexões do editor visual',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    -- Limites de envio
    daily_limit INT NOT NULL DEFAULT 100,
    window_start TIME NOT NULL DEFAULT '08:00:00',
    window_end TIME NOT NULL DEFAULT '18:00:00',
    send_weekends TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Participantes: um Lead dentro de uma sequência (estado independente)
-- ============================================
CREATE TABLE IF NOT EXISTS sequence_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sequence_id INT NOT NULL,
    contact_id INT NOT NULL,
    status ENUM('active','paused','finished','stopped','failed') NOT NULL DEFAULT 'active',
    current_node VARCHAR(64) DEFAULT NULL,
    next_run_at DATETIME DEFAULT NULL,
    stop_reason VARCHAR(60) DEFAULT NULL COMMENT 'replied, unsubscribed, bounce, manual, completed',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    added_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_seq_contact (sequence_id, contact_id),
    KEY idx_due (status, next_run_at),
    FOREIGN KEY (sequence_id) REFERENCES email_sequences(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Execução por nó (log + idempotência)
-- ============================================
CREATE TABLE IF NOT EXISTS sequence_executions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    node_id VARCHAR(64) NOT NULL,
    node_type VARCHAR(40) NOT NULL,
    attempt INT NOT NULL DEFAULT 1,
    result VARCHAR(40) DEFAULT NULL COMMENT 'done, skipped, failed, waiting',
    detail TEXT DEFAULT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_idem (participant_id, node_id, attempt),
    KEY idx_participant (participant_id),
    FOREIGN KEY (participant_id) REFERENCES sequence_participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
