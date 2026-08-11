-- Migration 031: Módulo de Marketing (Calendário Editorial)
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Novo papel de usuário: marketing
ALTER TABLE users
MODIFY COLUMN role ENUM('super_admin', 'attendant', 'client', 'whatsapp_agent', 'developer', 'analyst', 'comercial', 'marketing') NOT NULL DEFAULT 'client';

-- Itens do calendário editorial (demandas de conteúdo)
CREATE TABLE IF NOT EXISTS marketing_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    scheduled_at DATETIME NULL COMMENT 'Data e horário do conteúdo',
    assigned_to INT NULL COMMENT 'Responsável pela demanda',
    created_by INT NOT NULL,
    social_network VARCHAR(50) NULL COMMENT 'Rede social alvo',
    briefing LONGTEXT NULL,
    copy LONGTEXT NULL,
    status ENUM('ideia','em_producao','aguardando_aprovacao','aprovado','agendado','publicado') NOT NULL DEFAULT 'ideia',
    holiday_id INT NULL COMMENT 'Data comemorativa de origem (opcional)',
    review_notes TEXT NULL COMMENT 'Observações do superadmin ao solicitar ajustes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anexos (artes, documentos e materiais) dos itens
CREATE TABLE IF NOT EXISTS marketing_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(120) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketing_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datas comemorativas / relevantes para marketing (inseridas pela IA)
CREATE TABLE IF NOT EXISTS marketing_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    holiday_date DATE NOT NULL,
    category VARCHAR(60) NULL COMMENT 'comercial, profissional, comemorativa, etc',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_holiday (holiday_date, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_marketing_items_status ON marketing_items(status);
CREATE INDEX idx_marketing_items_assigned ON marketing_items(assigned_to);
CREATE INDEX idx_marketing_items_scheduled ON marketing_items(scheduled_at);
CREATE INDEX idx_marketing_holidays_date ON marketing_holidays(holiday_date);
