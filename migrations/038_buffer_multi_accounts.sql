-- Migration 038: Suporte a múltiplas API keys do Buffer (idempotente)
-- Cada conta Buffer (API key) traz seus próprios canais; todos aparecem como cards.
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Contas Buffer (uma por API key)
CREATE TABLE IF NOT EXISTS buffer_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NULL COMMENT 'Nome amigável da conta/key',
    api_key VARCHAR(255) NOT NULL,
    organization_id VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_key (api_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vincula cada canal cacheado à conta Buffer de origem
DROP PROCEDURE IF EXISTS add_col_if_missing_038;
DELIMITER //
CREATE PROCEDURE add_col_if_missing_038(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL add_col_if_missing_038('buffer_channels', 'buffer_account_id', 'INT NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing_038;
