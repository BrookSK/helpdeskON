-- Migration 057: Adicionar campos IMAP nas contas de e-mail para leitura de inbox
-- Permite conectar via IMAP para buscar e-mails recebidos.

USE helpdesk_on;

ALTER TABLE email_accounts
    ADD COLUMN imap_host VARCHAR(255) NULL COMMENT 'Servidor IMAP' AFTER smtp_password,
    ADD COLUMN imap_port INT NOT NULL DEFAULT 993 AFTER imap_host,
    ADD COLUMN imap_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl' AFTER imap_port;

-- Defaults do IMAP também salvos nas settings (como o SMTP)
INSERT INTO settings (setting_key, setting_value) VALUES
    ('prospection_imap_host', ''),
    ('prospection_imap_port', '993'),
    ('prospection_imap_encryption', 'ssl')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
