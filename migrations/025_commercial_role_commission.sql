-- Migration 025: Papel "Comercial" e percentual de comissão
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Adicionar papel 'comercial'
ALTER TABLE users
MODIFY COLUMN role ENUM('super_admin', 'attendant', 'client', 'whatsapp_agent', 'developer', 'analyst', 'comercial') NOT NULL DEFAULT 'client';

-- Percentual de comissão do usuário comercial (ex.: 10.00 = 10%)
ALTER TABLE users ADD COLUMN commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER see_all_companies;
