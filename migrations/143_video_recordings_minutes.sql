-- Migration 143: Minuta/ata automática da reunião (Fase 2 — esteira comercial/reuniões)
-- Adiciona em video_recordings os campos da ATA gerada a partir da transcrição:
--   minutes               -> conteúdo da ata (Markdown), editável pela equipe
--   minutes_status        -> none/processing/done/error
--   minutes_generated_at  -> quando a ata foi gerada
-- Idempotente (padrão information_schema + PREPARE, igual às migrations 122/142).

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes LONGTEXT NULL COMMENT ''Ata/minuta da reuniao (Markdown, editavel)'' AFTER summary','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_status');
SET @s := IF(@c=0,"ALTER TABLE video_recordings ADD COLUMN minutes_status ENUM('none','processing','done','error') NOT NULL DEFAULT 'none' AFTER minutes",'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_generated_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_generated_at DATETIME NULL AFTER minutes_status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
