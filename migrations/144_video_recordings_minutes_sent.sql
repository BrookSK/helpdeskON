-- Migration 144: Controle de envio automático da minuta (Fase 2 — tarefa #7)
-- Marca quando a minuta foi enviada (link público) por WhatsApp/e-mail, para
-- não reenviar em cada regeração. Idempotente (information_schema + PREPARE).

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_sent_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_sent_at DATETIME NULL COMMENT ''Quando o link da minuta foi enviado (WhatsApp/e-mail)'' AFTER minutes_generated_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
