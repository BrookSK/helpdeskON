-- Migration 024: Marcação "Em recuperação" para leads que retornaram por agendamento
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE crm_cards ADD COLUMN in_recovery TINYINT(1) NOT NULL DEFAULT 0 AFTER follow_up_column_id;
