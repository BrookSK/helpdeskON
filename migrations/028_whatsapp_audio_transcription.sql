-- Migration 028: Transcrição de áudios recebidos no WhatsApp
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE whatsapp_messages ADD COLUMN transcription TEXT NULL AFTER message_text;
