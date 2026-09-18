-- =====================================================================
-- 123_video_signals_rec_state.sql
-- ---------------------------------------------------------------------
-- Indicador global de gravação: propaga em tempo real para todos que a
-- reunião está sendo gravada / pausada / parada.
--
-- Adiciona o valor 'rec' ao ENUM video_room_signals.kind. O payload do
-- sinal carrega { state: 'start'|'pause'|'resume'|'stop', by: nome }.
--
-- Idempotente. Rode no banco após a 117..122.
-- =====================================================================

SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'rec'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny','reaction','hand','perm','rec') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
