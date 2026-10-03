-- =====================================================================
-- 142_agenda_auto_record.sql
-- ---------------------------------------------------------------------
-- Adiciona a opção "gravar automaticamente" ao agendamento de reuniões.
--
-- - agenda_meetings.auto_record: flag que indica se a reunião deve ser
--   gravada automaticamente ao iniciar (host entra na sala).
-- - video_rooms.auto_record: propagado do agendamento; lido pelo cliente
--   JS ao fazer join para disparar toggleRecording() automaticamente.
--
-- Aplica-se apenas a reuniões do tipo 'operacional' e 'interno', mas a
-- coluna aceita qualquer reunião (sem restrição no banco — a lógica fica
-- na view e no controller).
--
-- Idempotente. Rode após as migrations 117 e 141.
-- =====================================================================

-- agenda_meetings.auto_record
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='auto_record');
SET @s := IF(@c=0,
    'ALTER TABLE agenda_meetings ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Gravar automaticamente ao iniciar\' AFTER allow_recording',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- video_rooms.auto_record
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_rooms' AND COLUMN_NAME='auto_record');
SET @s := IF(@c=0,
    'ALTER TABLE video_rooms ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Gravar automaticamente quando o host entra\' AFTER allow_recording',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
