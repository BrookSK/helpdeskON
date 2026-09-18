-- =====================================================================
-- 119_video_rooms_private_moderation.sql
-- ---------------------------------------------------------------------
-- Salas PRIVADAS com moderação (estilo Meet):
--
-- - video_rooms.visibility: 'public' (qualquer um com o link entra) ou
--   'private' (entrada precisa ser aprovada por um administrador da sala).
-- - video_room_admins: usuários da equipe definidos como administradores
--   da sala (podem ver a lista de participantes e aprovar/recusar entradas).
-- - video_room_join_requests: fila de pedidos de entrada de uma sala privada.
-- - Novos valores no ENUM de sinais para a moderação em tempo real:
--   'request'  -> avisa os admins que há um novo pedido de entrada
--   'admit'    -> admin liberou a entrada de um peer que aguardava
--   'deny'     -> admin recusou a entrada
--
-- Idempotente. Rode no banco após a 117 e a 118.
-- =====================================================================

-- 1) Coluna visibility em video_rooms.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_rooms' AND COLUMN_NAME='visibility');
SET @s := IF(@c=0,
    "ALTER TABLE video_rooms ADD COLUMN visibility ENUM('public','private') NOT NULL DEFAULT 'public' AFTER status",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Administradores da sala (equipe). Um mesmo usuário só uma vez por sala.
CREATE TABLE IF NOT EXISTS video_room_admins (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id    INT NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_room_admin (room_id, user_id),
    KEY idx_admin_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Pedidos de entrada em salas privadas.
CREATE TABLE IF NOT EXISTS video_room_join_requests (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id      INT NOT NULL,
    peer_id      VARCHAR(64) NOT NULL COMMENT 'Peer que está aguardando aprovação',
    display_name VARCHAR(120) NULL,
    user_id      INT NULL COMMENT 'users.id se o solicitante estiver logado',
    status       ENUM('pending','admitted','denied') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL,
    decided_at   DATETIME NULL,
    decided_by   INT NULL COMMENT 'users.id do admin que decidiu',
    UNIQUE KEY uk_room_peer_req (room_id, peer_id),
    KEY idx_req_room_status (room_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Novos valores no ENUM video_room_signals.kind (moderação).
SET @ct := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_room_signals' AND COLUMN_NAME='kind');
SET @s := IF(@ct IS NOT NULL AND LOCATE("'request'", @ct)=0,
    "ALTER TABLE video_room_signals MODIFY COLUMN kind ENUM('offer','answer','ice','join','leave','media','screen','end','kick','request','admit','deny') NOT NULL",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
