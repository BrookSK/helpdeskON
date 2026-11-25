-- =====================================================================
-- 175_sequence_whatsapp_send_interval.sql
-- ---------------------------------------------------------------------
-- Intervalo variável entre os WhatsApps disparados pelas sequências
-- (proteção contra bloqueio por spam).
--
-- Colunas novas em email_sequences:
--   wa_gap_min_minutes  menor intervalo entre dois WhatsApps da mesma conta
--   wa_gap_max_minutes  maior intervalo
-- A cada mensagem o sistema sorteia um intervalo entre esses dois valores,
-- então os envios não seguem um ritmo fixo. 0 e 0 = sem intervalo.
-- Padrão para todas as sequências (inclusive as que já existem): 2 a 10 min.
--
-- Idempotente (checa information_schema) para reexecução segura.
-- Aplique no banco correto pela CONEXÃO (não força USE).
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_sequences' AND COLUMN_NAME = 'wa_gap_min_minutes');
SET @s := IF(@c = 0,
    "ALTER TABLE email_sequences ADD COLUMN wa_gap_min_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'WhatsApp: menor intervalo entre envios (min)'",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_sequences' AND COLUMN_NAME = 'wa_gap_max_minutes');
SET @s := IF(@c = 0,
    "ALTER TABLE email_sequences ADD COLUMN wa_gap_max_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'WhatsApp: maior intervalo entre envios (min)'",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Conferência
SELECT id, name, wa_gap_min_minutes, wa_gap_max_minutes FROM email_sequences ORDER BY id;
