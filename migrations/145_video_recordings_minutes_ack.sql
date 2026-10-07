-- Migration 145: Reconhecimento/assinatura da minuta pelo cliente
-- Adiciona em video_recordings o estado do ACEITE da ata pelo cliente:
--   minutes_ack_status     -> pending/acknowledged/contested (default pending)
--   minutes_ack_by         -> users.id de quem reconheceu (via PIN do cliente)
--   minutes_ack_at         -> quando reconheceu
--   minutes_ack_ip         -> IP de origem do reconhecimento (prova)
--   minutes_contest_reason -> motivo informado ao discordar
-- Idempotente (padrão information_schema + PREPARE, igual às migrations 143/144).

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_ack_status');
SET @s := IF(@c=0,"ALTER TABLE video_recordings ADD COLUMN minutes_ack_status ENUM('pending','acknowledged','contested') NOT NULL DEFAULT 'pending' AFTER minutes_sent_at",'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_ack_by');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_ack_by INT NULL AFTER minutes_ack_status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_ack_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_ack_at DATETIME NULL AFTER minutes_ack_by','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_ack_ip');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_ack_ip VARCHAR(45) NULL AFTER minutes_ack_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_contest_reason');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_contest_reason VARCHAR(1000) NULL AFTER minutes_ack_ip','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
