-- Migration 130: Origem do envio das mensagens de WhatsApp (sistema vs externo).
--
-- Contexto: o webhook passou a salvar TAMBÉM as mensagens fromMe enviadas pelo
-- próprio número por fora do sistema (celular/WhatsApp Web), para aparecerem no
-- chat. Isso, porém, faria as métricas de produtividade contarem como "enviadas
-- pelo responsável" mensagens que não saíram pelo painel.
--
-- Esta coluna marca a ORIGEM do envio das mensagens de saída (from_me = 1):
--   'system'   → enviada pelo painel/sequência do sistema
--   'external' → enviada por fora (celular/WhatsApp Web), capturada pelo webhook
-- Para mensagens recebidas (from_me = 0) o valor é irrelevante (fica 'system'
-- por default, mas as métricas de "enviado" só olham from_me = 1).
--
-- Execute manualmente no MySQL.

USE helpdesk_on;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_messages' AND COLUMN_NAME = 'sent_via');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_messages ADD COLUMN sent_via ENUM('system','external') NOT NULL DEFAULT 'system' AFTER from_me",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
