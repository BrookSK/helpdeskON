-- Migration 070: guarda quem solicitou a revelação do telefone, para debitar
-- os créditos corretos (Apollo cobra +8 créditos quando um celular é retornado)
-- no controle diário quando o webhook entregar o número.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'phone_requested_by');
SET @s := IF(@c = 0, 'ALTER TABLE apollo_leads ADD COLUMN phone_requested_by INT NULL AFTER phone_request_id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
