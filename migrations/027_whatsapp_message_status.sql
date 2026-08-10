-- Migration 027: Status de entrega das mensagens do WhatsApp (checkzinhos)
-- Execute manualmente no MySQL

USE helpdesk_on;

-- status: pending (relógio), sent (1 check), delivered (2 checks), read (2 checks azuis), failed
ALTER TABLE whatsapp_messages ADD COLUMN ack_status ENUM('pending','sent','delivered','read','failed') NULL DEFAULT NULL AFTER is_read;
