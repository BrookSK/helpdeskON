-- Migration 023: Retomada de contato com precisão de minutos e coluna de destino
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Alterar follow_up_at de DATE para DATETIME (suporte a minutos/horas)
ALTER TABLE crm_cards MODIFY COLUMN follow_up_at DATETIME NULL;

-- Coluna de destino para onde o card deve ir ao atingir o agendamento
ALTER TABLE crm_cards ADD COLUMN follow_up_column_id INT NULL AFTER follow_up_at;
ALTER TABLE crm_cards ADD FOREIGN KEY (follow_up_column_id) REFERENCES crm_columns(id) ON DELETE SET NULL;
