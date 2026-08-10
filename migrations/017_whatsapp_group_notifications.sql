-- Migration 017: Notificações via grupo de WhatsApp (conexão do chat existente)
-- - Vincula um grupo de WhatsApp a cada empresa
-- - Grupo padrão (empresa dona do helpdesk) para todas as atualizações de status
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Grupo de WhatsApp vinculado à empresa (remote_jid do grupo, ex: 123456789@g.us)
ALTER TABLE companies ADD COLUMN whatsapp_group_jid VARCHAR(100) NULL AFTER email;

-- Configurações do grupo padrão de notificações
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('whatsapp_default_group_jid', ''),
('whatsapp_group_notify_enabled', '0');
