-- =====================================================================
-- 121_video_rooms_presentation_permission.sql
-- ---------------------------------------------------------------------
-- Permissão de "apresentar" (compartilhar tela) na sala, controlada pelo
-- administrador — como no Google Meet.
--
-- - video_rooms.allow_presentation: 1 = qualquer participante pode
--   compartilhar a tela; 0 = apenas administradores podem. Padrão 1.
--   O admin pode alternar isso em tempo real durante a chamada.
--
-- Observação: a restrição de GRAVAÇÃO (em sala privada, só admin grava) e a
-- de VER a lista de mãos (qualquer um) são tratadas na aplicação/cliente e
-- não exigem coluna nova.
--
-- Idempotente. Rode no banco após a 117..120.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_rooms' AND COLUMN_NAME='allow_presentation');
SET @s := IF(@c=0,
    "ALTER TABLE video_rooms ADD COLUMN allow_presentation TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_recording",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Novo sinal para propagar em tempo real a mudança dessa permissão.
SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'perm'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny','reaction','hand','perm') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
