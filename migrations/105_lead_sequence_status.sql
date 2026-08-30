-- =====================================================================
-- 105_lead_sequence_status.sql
-- ---------------------------------------------------------------------
-- Coluna dedicada de status do lead para prospecção/sequências:
--   sequence_status ENUM('active','inactive') DEFAULT 'active'
--
-- Serve como fonte da verdade do botão Ativo/Inativo no seletor de leads.
-- Fica sincronizada com unsubscribed:
--   inativo  -> unsubscribed = 1 (bloqueia envios automáticos)
--   ativo    -> unsubscribed = 0
--
-- Backfill: marca como 'inactive' quem já está descadastrado. Idempotente.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='whatsapp_contacts' AND COLUMN_NAME='sequence_status');
SET @s := IF(@c=0,"ALTER TABLE whatsapp_contacts ADD COLUMN sequence_status ENUM('active','inactive') NOT NULL DEFAULT 'active'",'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Backfill inicial: descadastrados = inativos.
UPDATE whatsapp_contacts SET sequence_status = 'inactive' WHERE COALESCE(unsubscribed,0) = 1;
