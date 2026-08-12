-- Migration 034: Detalhes dos canais do Buffer (idempotente)
-- Execute manualmente no MySQL
-- Observação: a API do Buffer não expõe contagem de seguidores; o campo followers
-- fica disponível para preenchimento manual/futuro, mas não é populado pela sincronização.

USE helpdesk_on;

DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing(
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

CALL add_col_if_missing('buffer_channels', 'username', 'VARCHAR(150) NULL');
CALL add_col_if_missing('buffer_channels', 'followers', 'INT NULL');
CALL add_col_if_missing('buffer_channels', 'external_link', 'VARCHAR(500) NULL');
CALL add_col_if_missing('buffer_channels', 'channel_type', 'VARCHAR(50) NULL');
CALL add_col_if_missing('buffer_channels', 'is_disconnected', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL add_col_if_missing('buffer_channels', 'is_queue_paused', 'TINYINT(1) NOT NULL DEFAULT 0');

DROP PROCEDURE IF EXISTS add_col_if_missing;
