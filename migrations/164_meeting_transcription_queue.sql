-- ============================================================
-- 164_meeting_transcription_queue.sql
-- Fluxo comercial (reunião até proposta):
-- - video_recordings.transcribe_status ganha o valor 'queued' (fila do cron).
-- - video_recordings.transcribe_error: motivo da última falha da transcrição.
-- - video_recordings.is_incomplete: gravação publicada a partir de sessão
--   abandonada (aba fechada sem parar).
-- - video_recordings.minutes_edited_by / minutes_edited_at: revisão interna da
--   minuta. Minuta "rascunho" = minutes_status='done' e minutes_sent_at nulo.
-- - video_recordings.minutes_sent_by: quem enviou a minuta ao cliente.
-- - video_recording_segments: segmentos só de áudio gravados em paralelo,
--   transcritos um a um pelo cron (sem ffmpeg no servidor).
-- - proposals.recording_id: proposta gerada a partir da minuta da gravação.
-- Idempotente (information_schema + PREPARE).
-- ============================================================

SET @t := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcribe_status');
SET @s := IF(@t IS NOT NULL AND @t NOT LIKE '%queued%',
    "ALTER TABLE video_recordings MODIFY COLUMN transcribe_status ENUM('none','queued','processing','done','error') NOT NULL DEFAULT 'none'",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='transcribe_error');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN transcribe_error VARCHAR(500) NULL AFTER transcribed_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='is_incomplete');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN is_incomplete TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_sec','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_edited_by');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_edited_by INT NULL AFTER minutes_generated_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_edited_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_edited_at DATETIME NULL AFTER minutes_edited_by','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_sent_by');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_sent_by INT NULL AFTER minutes_sent_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS video_recording_segments (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recording_id  BIGINT UNSIGNED NOT NULL COMMENT 'video_recordings.id',
    seq           INT NOT NULL COMMENT 'Ordem do segmento na gravação (1-based)',
    start_sec     DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'Início do segmento em relação ao começo da gravação',
    file_path     VARCHAR(500) NOT NULL COMMENT 'Caminho relativo a storage/ (fora de public/)',
    file_size     INT UNSIGNED NULL,
    mime_type     VARCHAR(60) NULL,
    sha256        CHAR(64) NULL,
    status        ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending',
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_at     DATETIME NULL,
    text          LONGTEXT NULL,
    segments_json LONGTEXT NULL COMMENT 'Trechos com tempo relativo ao segmento [{start,end,text}]',
    last_error    VARCHAR(500) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rec_seg (recording_id, seq),
    KEY idx_seg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='recording_id');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN recording_id BIGINT UNSIGNED NULL COMMENT ''Gravação/minuta de origem'' AFTER card_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND INDEX_NAME='idx_proposal_recording');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD KEY idx_proposal_recording (recording_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
