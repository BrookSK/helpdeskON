-- =====================================================================
-- 118_video_rooms_browser_session.sql
-- ---------------------------------------------------------------------
-- Impede SESSÃO DUPLICADA no mesmo navegador na videochamada em grupo.
--
-- - Adiciona video_room_participants.browser_id: identidade persistente do
--   navegador (guardada no localStorage do cliente). Dois dispositivos têm
--   browser_id distinto (podem entrar juntos), mas abrir outra guia no MESMO
--   navegador reusa o browser_id, permitindo detectar a duplicidade.
-- - Adiciona o valor 'kick' ao ENUM de video_room_signals.kind, usado para
--   derrubar a guia antiga quando a nova assume a chamada (takeover).
--
-- Idempotente: só cria o que faltar. Rode no banco após a 117.
-- =====================================================================

-- 1) Coluna browser_id em video_room_participants (+ índice de busca).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_participants' AND COLUMN_NAME='browser_id');
SET @s := IF(@c=0,
    "ALTER TABLE video_room_participants ADD COLUMN browser_id VARCHAR(64) NULL COMMENT 'Identidade persistente do navegador (localStorage)' AFTER peer_id",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice (room_id, browser_id) para localizar sessões do mesmo navegador.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_participants' AND INDEX_NAME='idx_room_browser');
SET @s := IF(@i=0,'ALTER TABLE video_room_participants ADD KEY idx_room_browser (room_id, browser_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Valor 'kick' no ENUM video_room_signals.kind.
SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'kick'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
