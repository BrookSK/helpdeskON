-- Migration 030: Anexo opcional nas respostas rápidas do WhatsApp
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE whatsapp_quick_replies
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER message,
    ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path,
    ADD COLUMN attachment_mime VARCHAR(120) NULL AFTER attachment_name;
