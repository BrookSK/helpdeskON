-- Migration 022: Conversão de leads, perda e retomada de contato no CRM
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Situação do lead no funil (independente da coluna): aberto, convertido ou perdido
ALTER TABLE crm_cards ADD COLUMN lead_outcome ENUM('open','converted','lost') NOT NULL DEFAULT 'open' AFTER status;
ALTER TABLE crm_cards ADD COLUMN outcome_at TIMESTAMP NULL AFTER lead_outcome;

-- Retomada de contato: data em que o card deve voltar para a primeira coluna
ALTER TABLE crm_cards ADD COLUMN follow_up_at DATE NULL AFTER outcome_at;
