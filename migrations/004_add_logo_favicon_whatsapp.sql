-- Migration 004: Adicionar configurações de logo, favicon e WhatsApp
-- Execute manualmente no MySQL

USE helpdesk_on;

INSERT INTO settings (setting_key, setting_value) VALUES
('app_logo', ''),
('app_favicon', ''),
('whatsapp_number', ''),
('whatsapp_message', 'Olá! Preciso de ajuda.'),
('whatsapp_enabled', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
