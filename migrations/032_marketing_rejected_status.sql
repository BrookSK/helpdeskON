-- Migration 032: Status "rejeitado" para itens de marketing
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE marketing_items
MODIFY COLUMN status ENUM('ideia','em_producao','aguardando_aprovacao','aprovado','agendado','publicado','rejeitado') NOT NULL DEFAULT 'ideia';
