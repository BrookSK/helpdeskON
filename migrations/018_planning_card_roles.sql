-- Migration 018: Papéis no card de planejamento (atendente, técnico e analista)
-- assigned_to = atendente responsável; technical_responsible_id = técnico (migration 016)
-- analyst_id = analista responsável
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE planning_cards ADD COLUMN analyst_id INT NULL AFTER technical_responsible_id;
ALTER TABLE planning_cards ADD FOREIGN KEY (analyst_id) REFERENCES users(id) ON DELETE SET NULL;
