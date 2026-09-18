-- =====================================================================
-- 117_video_rooms.sql
-- ---------------------------------------------------------------------
-- Videochamada em GRUPO nativa (WebRTC mesh, sem API externa).
--
-- - Salas com LINK PÚBLICO por token (entrar sem login) — útil inclusive
--   para convidados externos e para ferramentas como o Fathom entrarem
--   pelo link e gravarem do lado deles.
-- - Sinalização (signaling) WebRTC via HTTP long-polling persistido em
--   MySQL, no mesmo padrão descrito na especificação de origem.
-- - Gravação feita no navegador (MediaRecorder) e enviada ao servidor;
--   o arquivo fica salvo em /public/uploads/recordings e o registro
--   guarda o caminho para gerar o link de acesso depois.
--
-- Rode este arquivo no banco (não há runner automático de migrations).
-- =====================================================================

-- ---------------------------------------------------------------------
-- Salas de videochamada em grupo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_rooms (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    token            VARCHAR(64) NOT NULL COMMENT 'Identificador público da sala (link)',
    title            VARCHAR(255) NULL COMMENT 'Título/assunto da chamada',
    created_by       INT NULL COMMENT 'Usuário (users.id) que criou a sala',
    company_id       INT NULL COMMENT 'Empresa/contexto do criador',
    meeting_id       INT NULL COMMENT 'Reunião da Agenda vinculada (agenda_meetings.id)',
    max_participants SMALLINT UNSIGNED NOT NULL DEFAULT 8 COMMENT 'Teto de segurança (mesh degrada acima de ~4-6)',
    allow_recording  TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Permite gravar a chamada nesta sala',
    status           ENUM('active','ended') NOT NULL DEFAULT 'active',
    expires_at       DATETIME NULL COMMENT 'Após esta data a sala não aceita novas entradas',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at         DATETIME NULL,
    UNIQUE KEY uk_video_token (token),
    KEY idx_video_created_by (created_by),
    KEY idx_video_meeting (meeting_id),
    KEY idx_video_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Participantes (presença): quem está/esteve em cada sala.
-- peer_id é gerado no navegador (identifica a aba/dispositivo), pois a
-- sala é pública e um mesmo usuário pode não estar logado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_room_participants (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id      INT NOT NULL,
    peer_id      VARCHAR(64) NOT NULL COMMENT 'ID do participante nesta sessão (gerado no cliente)',
    user_id      INT NULL COMMENT 'users.id quando o participante estiver logado',
    display_name VARCHAR(120) NULL,
    role         ENUM('host','participant') NOT NULL DEFAULT 'participant',
    joined_at    DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL COMMENT 'Atualizado a cada heartbeat/poll; define quem saiu',
    left_at      DATETIME NULL,
    UNIQUE KEY uk_room_peer (room_id, peer_id),
    KEY idx_room_seen (room_id, last_seen_at),
    KEY idx_room_left (room_id, left_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fila de sinais WebRTC (signaling) roteados por par de participantes.
-- to_peer_id NULL = broadcast para a sala (ex.: entrou/saiu/estado de mídia).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_room_signals (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id      INT NOT NULL,
    from_peer_id VARCHAR(64) NOT NULL,
    to_peer_id   VARCHAR(64) NULL COMMENT 'NULL = broadcast para toda a sala',
    kind         ENUM('offer','answer','ice','join','leave','media','screen','end') NOT NULL,
    payload_json LONGTEXT NULL,
    created_at   DATETIME NOT NULL,
    delivered_at DATETIME NULL,
    KEY idx_sig_room_to (room_id, to_peer_id, delivered_at),
    KEY idx_sig_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Gravações da sala (arquivo salvo no servidor + metadados).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_recordings (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id      INT NOT NULL,
    token        VARCHAR(64) NOT NULL COMMENT 'Token público para acessar/baixar a gravação',
    file_path    VARCHAR(500) NOT NULL COMMENT 'Caminho relativo dentro de /public/uploads',
    file_size    BIGINT UNSIGNED NULL,
    mime_type    VARCHAR(80) NULL,
    duration_sec INT NULL,
    recorded_by  INT NULL COMMENT 'users.id de quem gravou (se logado)',
    recorded_by_name VARCHAR(120) NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rec_token (token),
    KEY idx_rec_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
