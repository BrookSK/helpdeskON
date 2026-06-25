-- Migration 005: Adicionar numeração sequencial por cliente
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE tickets ADD COLUMN client_ticket_number INT NULL AFTER client_id;

-- Preencher números para tickets já existentes
SET @row_number = 0;
SET @current_client = 0;

UPDATE tickets t
INNER JOIN (
    SELECT id, client_id,
           @row_number := IF(@current_client = client_id, @row_number + 1, 1) AS rn,
           @current_client := client_id
    FROM tickets
    ORDER BY client_id, id
) AS numbered ON t.id = numbered.id
SET t.client_ticket_number = numbered.rn;
