-- =====================================================================
-- 114_email_account_signature.sql
-- ---------------------------------------------------------------------
-- Assinatura de e-mail POR CONTA/DOMÍNIO. Cada caixa de e-mail (ex.: On Solutions
-- e LRV Web) passa a ter sua própria assinatura: logo, nome, cargo, empresa,
-- site, e-mail de contato, telefone e uma linha final (tagline).
--
-- Quando o e-mail é disparado por uma conta, a camada de envio usa a assinatura
-- daquela conta. Se a conta não tiver assinatura configurada, cai na assinatura
-- padrão do sistema (ON Solutions), preservando o comportamento atual.
--
-- Idempotente (information_schema).
-- =====================================================================

SET @tbl := 'email_accounts';

-- helper: adiciona coluna se não existir
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_enabled');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_enabled TINYINT(1) NOT NULL DEFAULT 1", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_logo');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_logo VARCHAR(255) DEFAULT NULL COMMENT 'caminho da logo da assinatura'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_name');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_name VARCHAR(150) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_role');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_role VARCHAR(150) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_company');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_company VARCHAR(150) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_site');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_site VARCHAR(200) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_email');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_email VARCHAR(200) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_phone');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_phone VARCHAR(80) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_tagline');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_tagline VARCHAR(255) DEFAULT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@tbl AND COLUMN_NAME='signature_color');
SET @s := IF(@c=0, "ALTER TABLE email_accounts ADD COLUMN signature_color VARCHAR(20) DEFAULT '#00997D'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
