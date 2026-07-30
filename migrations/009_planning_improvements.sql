-- Migration 009: Melhorias no Planejamento
-- Range de desenvolvimento (start_date/end_date) e controle de acesso por empresa
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Adicionar campos de range de desenvolvimento nos cards
ALTER TABLE planning_cards ADD COLUMN start_date DATETIME NULL COMMENT 'Início do desenvolvimento' AFTER due_date;
ALTER TABLE planning_cards ADD COLUMN end_date DATETIME NULL COMMENT 'Fim do desenvolvimento' AFTER start_date;

-- Tabela de controle de acesso: quais empresas cada atendente pode ver
CREATE TABLE IF NOT EXISTS user_company_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_company (user_id, company_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_user_company_access_user ON user_company_access(user_id);
