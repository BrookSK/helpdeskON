-- =====================================================================
-- 120_video_rooms_reactions_hand.sql
-- ---------------------------------------------------------------------
-- Reações (emojis) e "levantar a mão" na videochamada em grupo.
--
-- São sinais transitórios em tempo real (broadcast), então basta permitir
-- os novos valores no ENUM video_room_signals.kind:
--   'reaction' -> chuva de emoji para todos
--   'hand'     -> levantar/baixar a mão (mantém ordem por horário no cliente)
--
-- Idempotente. Rode no banco após a 117, 118 e 119.
-- =====================================================================

SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'reaction'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny','reaction','hand') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
