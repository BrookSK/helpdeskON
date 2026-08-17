-- ============================================
-- Ramal SIP por usuário (webphone Nvoip)
-- ============================================
-- Cada operador do CRM pode ter o próprio ramal SIP, evitando conflito (erro 480)
-- quando vários usuários usam o webphone ao mesmo tempo.
-- Idempotente via procedure.

DROP PROCEDURE IF EXISTS user_add_col;
DELIMITER //
CREATE PROCEDURE user_add_col(IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = col
    ) THEN
        SET @s = CONCAT('ALTER TABLE users ADD COLUMN ', ddl);
        PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END //
DELIMITER ;

CALL user_add_col('sip_user', "sip_user VARCHAR(50) NULL COMMENT 'Ramal SIP Nvoip do usuário'");
CALL user_add_col('sip_password', "sip_password VARCHAR(191) NULL COMMENT 'Senha SIP do ramal (secreta)'");

DROP PROCEDURE IF EXISTS user_add_col;
