-- Migration 164: atividade predecessora no card de planejamento.
--
-- Permite que um card dependa de outro (predecessor). Quando a data de término
-- (end_date) do predecessor muda/atrasa, as datas do(s) card(s) dependente(s)
-- são recalculadas em cascata — sempre respeitando o prazo final (due_date),
-- que nunca é ultrapassado automaticamente.
--
-- Idempotente (checa information_schema antes de cada ALTER). Sem "USE": rode
-- com o banco alvo já selecionado. Retrocompatível: coluna NULL, de modo que
-- cards existentes continuam sem predecessor.

-- Predecessor: aponta para outro planning_cards.id. ON DELETE SET NULL para que
-- apagar o card predecessor não derrube os dependentes (apenas solta o vínculo).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'predecessor_id');
SET @s := IF(@c = 0,
    'ALTER TABLE planning_cards ADD COLUMN predecessor_id INT NULL COMMENT ''Card predecessor (dependência de cronograma)'' AFTER end_date',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Folga em dias entre o término do predecessor e o início deste card. Default 0
-- (começa logo após o predecessor terminar). Usado no recálculo em cascata.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'predecessor_lag_days');
SET @s := IF(@c = 0,
    'ALTER TABLE planning_cards ADD COLUMN predecessor_lag_days INT NOT NULL DEFAULT 0 COMMENT ''Folga (dias) entre o fim do predecessor e o início deste card'' AFTER predecessor_id',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice para achar dependentes rapidamente (quem aponta para um dado card).
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND INDEX_NAME = 'idx_planning_predecessor');
SET @s := IF(@i = 0,
    'ALTER TABLE planning_cards ADD INDEX idx_planning_predecessor (predecessor_id)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Chave estrangeira auto-referente (ON DELETE SET NULL). Só adiciona se ainda
-- não existir uma FK na coluna predecessor_id.
SET @f := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards'
      AND COLUMN_NAME = 'predecessor_id' AND REFERENCED_TABLE_NAME = 'planning_cards');
SET @s := IF(@f = 0,
    'ALTER TABLE planning_cards ADD CONSTRAINT fk_planning_predecessor FOREIGN KEY (predecessor_id) REFERENCES planning_cards(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
