-- Migration 008: Tabelas do módulo de Planejamento (Cards, Comentários, Anexos)
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Tabela principal de cards de planejamento
CREATE TABLE IF NOT EXISTS planning_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NULL COMMENT 'Vinculado a uma demanda (opcional)',
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NULL COMMENT 'Conteúdo rich text (HTML)',
    company_id INT NULL,
    assigned_to INT NULL COMMENT 'Responsável pelo card',
    created_by INT NOT NULL COMMENT 'Quem criou',
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'waiting_client', 'completed', 'denied', 'archived') NOT NULL DEFAULT 'open',
    due_date DATETIME NULL COMMENT 'Data/hora de prazo',
    position INT NOT NULL DEFAULT 0 COMMENT 'Posição no Kanban',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de comentários dos cards
CREATE TABLE IF NOT EXISTS planning_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES planning_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de anexos dos cards
CREATE TABLE IF NOT EXISTS planning_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES planning_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para performance
CREATE INDEX idx_planning_cards_status ON planning_cards(status, position);
CREATE INDEX idx_planning_cards_assigned ON planning_cards(assigned_to);
CREATE INDEX idx_planning_cards_company ON planning_cards(company_id);
CREATE INDEX idx_planning_cards_due_date ON planning_cards(due_date);
CREATE INDEX idx_planning_cards_ticket ON planning_cards(ticket_id);
CREATE INDEX idx_planning_comments_card ON planning_comments(card_id, created_at);
