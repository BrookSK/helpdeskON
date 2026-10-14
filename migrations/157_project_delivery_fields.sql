-- Migration 157: Campos do fluxo de Entrega, Finalização e Garantia de Projeto.
--
-- Complementa a tabela projects (migration 151) com os pontos do Guia de
-- Entrega que ainda não eram registrados:
--   published_at              → quando o projeto foi publicado em produção.
--   manual_url                → link/caminho do manual de uso entregue.
--   documentation_delivered_at→ quando a documentação/manual foi entregue.
--   delivery_meeting_id       → FK para a reunião de entrega (agenda_meetings).
--   delivery_meeting_at       → data da reunião de entrega (desnormalizado).
--   acceptance_token          → token do link público de aceite formal.
--   client_accepted_at        → quando o cliente aceitou formalmente.
--   client_accepted_by        → usuário (cliente) que realizou o aceite.
--   warranty_warn_sent_at     → carimbo do aviso de 15 dias (idempotência do cron).
--
-- Tudo idempotente (segue o padrão PREPARE/EXECUTE das migrations 151/152 para
-- funcionar via mysql client ou PDO, sem DELIMITER/procedure).
--
-- Execute manualmente no MySQL após 156_daily_report_missing_notifications.sql.

USE helpdesk_on;

-- published_at
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'published_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN published_at DATETIME NULL COMMENT 'Quando o projeto foi publicado em producao' AFTER delivered_at", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- manual_url
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'manual_url');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN manual_url VARCHAR(500) NULL COMMENT 'Link/caminho do manual de uso entregue ao cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- documentation_delivered_at
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'documentation_delivered_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN documentation_delivered_at DATETIME NULL COMMENT 'Quando o manual/documentacao foi entregue ao cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- delivery_meeting_id
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'delivery_meeting_id');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN delivery_meeting_id INT NULL COMMENT 'FK agenda_meetings.id da reuniao de entrega'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- delivery_meeting_at
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'delivery_meeting_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN delivery_meeting_at DATETIME NULL COMMENT 'Data/hora da reuniao de entrega'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- acceptance_token
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'acceptance_token');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN acceptance_token VARCHAR(64) NULL COMMENT 'Token do link publico de aceite formal do cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice único do token (idempotente)
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'uq_project_acceptance_token');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD UNIQUE KEY uq_project_acceptance_token (acceptance_token)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- client_accepted_at
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_accepted_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN client_accepted_at DATETIME NULL COMMENT 'Quando o cliente aceitou formalmente a entrega'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- client_accepted_by
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_accepted_by');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN client_accepted_by INT NULL COMMENT 'Usuario (cliente) que realizou o aceite'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- warranty_warn_sent_at
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'warranty_warn_sent_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN warranty_warn_sent_at DATETIME NULL COMMENT 'Carimbo do aviso de 15 dias antes do fim da garantia (idempotencia do cron)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
