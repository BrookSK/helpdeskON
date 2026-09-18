-- =====================================================================
-- 126_video_signals_forcemute.sql
-- ---------------------------------------------------------------------
-- Administrador pode silenciar o microfone de um participante.
-- Adiciona o valor 'forcemute' ao ENUM video_room_signals.kind:
--   sinal direcionado (to_peer_id) que pede ao participante para mutar o
--   próprio microfone. Ele recebe um aviso e pode desmutar depois.
--
-- Idempotente. Rode no banco após a 117..125.
-- =====================================================================

SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'forcemute'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny','reaction','hand','perm','rec','state','forcemute') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
