-- ============================================
-- Campos adicionais de registro das ligações do webphone
-- ============================================
-- Registra ciclo de vida da chamada (iniciada, atendida, encerrada) e duração.
-- Idempotente: usa procedure para adicionar colunas apenas se ainda não existirem.

DROP PROCEDURE IF EXISTS nvoip_add_col;
DELIMITER //
CREATE PROCEDURE nvoip_add_col(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nvoip_calls' AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE nvoip_calls ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END //
DELIMITER ;

CALL nvoip_add_col('direction', "direction VARCHAR(10) NULL COMMENT 'outbound|inbound' AFTER user_id");
CALL nvoip_add_col('answered_at', "answered_at TIMESTAMP NULL DEFAULT NULL");
CALL nvoip_add_col('ended_at', "ended_at TIMESTAMP NULL DEFAULT NULL");
CALL nvoip_add_col('duration_seconds', "duration_seconds INT NULL DEFAULT NULL");
CALL nvoip_add_col('hangup_cause', "hangup_cause VARCHAR(50) NULL DEFAULT NULL");

DROP PROCEDURE IF EXISTS nvoip_add_col;
