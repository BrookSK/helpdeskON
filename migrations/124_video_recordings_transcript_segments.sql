-- =====================================================================
-- 124_video_recordings_transcript_segments.sql
-- ---------------------------------------------------------------------
-- Segmentos da transcrição com marca de tempo (para clicar e pular no
-- player) e controle de progresso de transcrições longas (cortadas em
-- pedaços para reuniões de várias horas).
--
-- - video_recordings.transcript_json: JSON [{start, end, text, speaker?}, ...].
-- - video_recordings.duration_sec já existe (117); reutilizado.
--
-- Idempotente. Rode no banco após a 117..123.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcript_json');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN transcript_json LONGTEXT NULL AFTER transcript','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
