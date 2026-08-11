-- Migration: Adicionar campo is_deleted nas mensagens do WhatsApp
ALTER TABLE whatsapp_messages 
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER is_read;
