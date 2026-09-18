-- =====================================================================
-- 122_video_recordings_transcript.sql
-- ---------------------------------------------------------------------
-- Transcrição e resumo (IA) das gravações da videochamada.
--
-- - video_recordings.transcript: texto transcrito da gravação (Whisper).
-- - video_recordings.summary: resumo gerado por IA a partir da transcrição.
-- - video_recordings.transcribe_status: controle do processamento
--   ('none' | 'processing' | 'done' | 'error').
-- - video_recordings.transcribed_at: quando concluiu.
--
-- Usa a integração OpenAI já existente no sistema (settings.openai_api_key).
-- Idempotente. Rode no banco após a 117..121.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcript');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN transcript LONGTEXT NULL AFTER duration_sec','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='summary');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN summary LONGTEXT NULL AFTER transcript','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcribe_status');
SET @s := IF(@c=0,"ALTER TABLE video_recordings ADD COLUMN transcribe_status ENUM('none','processing','done','error') NOT NULL DEFAULT 'none' AFTER summary",'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcribed_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN transcribed_at DATETIME NULL AFTER transcribe_status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
