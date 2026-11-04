-- Demanda: datas informadas pelo cliente no cadastro da demanda.
-- Três datas obrigatórias no formulário (validadas no backend em tickets/store):
--   data_inicio            -> data de início
--   data_termino           -> data de término
--   data_prevista_entrega  -> data prevista de entrega ("Previsão de término")
-- Guardadas como DATE NULL (consistente com previsao_publicacao e com as colunas
-- de data já usadas em planning_cards). Idempotente (segue o padrão das migrations
-- 152/153): só adiciona a coluna quando ela ainda não existe.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'data_inicio');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN data_inicio DATE NULL COMMENT 'Data de inicio informada pelo cliente no cadastro da demanda'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'data_termino');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN data_termino DATE NULL COMMENT 'Data de termino informada pelo cliente no cadastro da demanda'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'data_prevista_entrega');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN data_prevista_entrega DATE NULL COMMENT 'Data prevista de entrega (previsao de termino) informada pelo cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
