-- Migration: WhatsApp Bot + CRM Module
-- Cria todas as tabelas necessárias para o módulo de WhatsApp e CRM

-- ============================================
-- WHATSAPP: Instâncias (conexões)
-- ============================================
CREATE TABLE IF NOT EXISTS whatsapp_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) DEFAULT NULL,
    api_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(500) NOT NULL,
    owner_phone VARCHAR(20) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    connection_status ENUM('open','connected','close','connecting') DEFAULT 'close',
    qr_code TEXT DEFAULT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WHATSAPP: Contatos
-- ============================================
CREATE TABLE IF NOT EXISTS whatsapp_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    remote_jid VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    contact_name VARCHAR(200) DEFAULT NULL,
    push_name VARCHAR(200) DEFAULT NULL,
    profile_picture_url TEXT DEFAULT NULL,
    is_group TINYINT(1) DEFAULT 0,
    internal_notes TEXT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    is_archived TINYINT(1) DEFAULT 0,
    unread_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_instance_jid (instance_id, remote_jid),
    FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WHATSAPP: Mensagens
-- ============================================
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    contact_id INT NOT NULL,
    remote_jid VARCHAR(100) NOT NULL,
    message_id VARCHAR(100) DEFAULT NULL,
    from_me TINYINT(1) DEFAULT 0,
    message_type ENUM('text','image','audio','video','document','sticker','location','contact','reaction','poll','list','unknown') DEFAULT 'text',
    message_text TEXT DEFAULT NULL,
    media_url TEXT DEFAULT NULL,
    media_mime_type VARCHAR(100) DEFAULT NULL,
    media_filename VARCHAR(255) DEFAULT NULL,
    quoted_message_id VARCHAR(100) DEFAULT NULL,
    sender_name VARCHAR(200) DEFAULT NULL,
    participant_jid VARCHAR(100) DEFAULT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_message_id (instance_id, message_id),
    KEY idx_contact (contact_id),
    KEY idx_jid_time (remote_jid, timestamp),
    FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WHATSAPP: Etiquetas (Labels)
-- ============================================
CREATE TABLE IF NOT EXISTS whatsapp_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relação N:N entre contatos e etiquetas
CREATE TABLE IF NOT EXISTS whatsapp_contact_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    label_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contact_label (contact_id, label_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES whatsapp_labels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CRM: Boards (Quadros Kanban)
-- ============================================
CREATE TABLE IF NOT EXISTS crm_boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CRM: Colunas dos Boards
-- ============================================
CREATE TABLE IF NOT EXISTS crm_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (board_id) REFERENCES crm_boards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CRM: Cards (Leads/Contatos no board)
-- ============================================
CREATE TABLE IF NOT EXISTS crm_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column_id INT NOT NULL,
    contact_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    value DECIMAL(10,2) DEFAULT NULL,
    position INT DEFAULT 0,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (column_id) REFERENCES crm_columns(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CRM: Histórico de atividades do card
-- ============================================
CREATE TABLE IF NOT EXISTS crm_card_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    activity_type ENUM('note','move','create','assign','label') DEFAULT 'note',
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES crm_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Inserir configurações padrão (settings)
-- ============================================
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('evolution_api_url', ''),
('evolution_api_key', ''),
('evolution_instance_name', '');
