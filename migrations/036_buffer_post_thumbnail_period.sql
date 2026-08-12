-- Migration 036: Capa (thumbnail) dos posts e período das métricas por canal (idempotente)
-- Execute manualmente no MySQL

USE helpdesk_on;

DROP PROCEDURE IF EXISTS add_col_if_missing_036;
DELIMITER //
CREATE PROCEDURE add_col_if_missing_036(
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

-- Capa/thumbnail do post
CALL add_col_if_missing_036('buffer_posts', 'thumbnail', 'VARCHAR(500) NULL');

-- Intervalo do período agregado por canal
CALL add_col_if_missing_036('buffer_channel_metrics', 'period_start', 'DATE NULL');
CALL add_col_if_missing_036('buffer_channel_metrics', 'period_end', 'DATE NULL');

DROP PROCEDURE IF EXISTS add_col_if_missing_036;
