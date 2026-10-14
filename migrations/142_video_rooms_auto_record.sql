-- Migration 142: Gravação automática da sala de vídeo (Fase 1 — esteira comercial/reuniões)
-- Adiciona a flag auto_record em video_rooms. Quando 1 (padrão), a sala começa a
-- gravar sozinha assim que o primeiro participante entra. É ortogonal a
-- allow_recording (que diz se é PERMITIDO gravar); auto_record diz se COMEÇA sozinho.
-- Idempotente (padrão information_schema + PREPARE, igual às migrations 122/126).

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_rooms' AND COLUMN_NAME = 'auto_record');
SET @s := IF(@c = 0,
    'ALTER TABLE video_rooms ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Inicia a gravação automaticamente ao entrar na sala (Fase 1)'' AFTER allow_recording',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
