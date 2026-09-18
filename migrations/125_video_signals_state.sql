-- =====================================================================
-- 125_video_signals_state.sql
-- ---------------------------------------------------------------------
-- Sinal de ESTADO consolidado do participante (mic/câmera/mão/nome),
-- reafirmado periodicamente para autocorrigir ícones que se perdiam com
-- muita gente na chamada (mic mutado grudado, mão levantada dessincronizada,
-- nome caindo para "Convidado").
--
-- Adiciona o valor 'state' ao ENUM video_room_signals.kind.
-- Idempotente. Rode no banco após a 117..124.
-- =====================================================================

SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'state'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny','reaction','hand','perm','rec','state') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
