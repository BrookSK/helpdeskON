-- Migration 026: Registrar quem converteu o lead (para cálculo de comissão)
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE crm_cards ADD COLUMN converted_by INT NULL AFTER outcome_at;
ALTER TABLE crm_cards ADD FOREIGN KEY (converted_by) REFERENCES users(id) ON DELETE SET NULL;
