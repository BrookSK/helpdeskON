-- Migration 128: Corrige AUTO_INCREMENT da PK de planning_cards
-- O schema importado deixou planning_cards.id como INT PRIMARY KEY SEM
-- AUTO_INCREMENT. Isso fez um card ser gravado com id=0, quebrando os links
-- do cronograma do cliente (planning/clientCardDetail/0 => "ID não informado").
--
-- Passos:
--  1) Remapear qualquer card com id=0 para um id livre (próximo do máximo).
--  2) Restaurar AUTO_INCREMENT na coluna id.
--
-- Idempotente o suficiente para rodar uma vez por ambiente. Ajuste os ids se
-- necessário conforme o AUTO_INCREMENT atual do ambiente.
-- Execute manualmente no MySQL.

-- 1) Reatribui o card id=0 (se existir) para MAX(id)+1.
UPDATE planning_cards
SET id = (SELECT next_id FROM (SELECT MAX(id) + 1 AS next_id FROM planning_cards) AS t)
WHERE id = 0;

-- 2) Restaura o AUTO_INCREMENT na PK.
ALTER TABLE planning_cards MODIFY id INT NOT NULL AUTO_INCREMENT;
