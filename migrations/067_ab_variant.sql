-- Migration 067: Teste A/B nas sequências.
-- Registra qual variante (A/B/C...) foi enviada em cada mensagem, para medir conversão.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_messages' AND COLUMN_NAME='ab_variant');
SET @s := IF(@c=0,'ALTER TABLE email_messages ADD COLUMN ab_variant VARCHAR(8) DEFAULT NULL AFTER node_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
