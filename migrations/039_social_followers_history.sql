-- Migration 039: Histórico diário de seguidores (Meta/LinkedIn)
-- Armazena um snapshot por dia de cada conta, permitindo comparação temporal.
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS social_followers_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL COMMENT 'FK para social_accounts',
    snapshot_date DATE NOT NULL COMMENT 'Data do snapshot (1 registro por dia por conta)',
    followers INT NOT NULL DEFAULT 0,
    follows INT NULL COMMENT 'Seguindo (apenas IG)',
    extra_json TEXT NULL COMMENT 'Métricas adicionais do dia (impressions, reach, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_date (account_id, snapshot_date),
    INDEX idx_snapshot_date (snapshot_date),
    FOREIGN KEY (account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
