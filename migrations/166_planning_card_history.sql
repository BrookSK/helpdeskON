-- Migration 166: Histórico de alterações dos cards de Planejamento.
--
-- Registra, por campo, cada mudança feita em um card (data, cronograma, status,
-- responsável, predecessor, folga, prioridade, título, campos CX Hub). Alimenta
-- a aba "Histórico" dentro do modal de detalhe do card.
--
-- Guarda valor ANTES e DEPOIS, quem alterou e quando. 'source' distingue a
-- origem: edição manual ('manual') ou recálculo automático em cascata/recesso
-- ('cascade'), para o histórico explicar por que uma data mudou sozinha.
--
-- IDEMPOTENTE: só cria a tabela/índice se não existirem. Sem "USE": rode com o
-- banco alvo já selecionado. Depois, recrie o banco de teste com
-- tests/setup_test_db.php.

CREATE TABLE IF NOT EXISTS planning_card_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    user_id INT NULL COMMENT 'Quem fez a alteração (NULL = sistema/cascata)',
    field VARCHAR(64) NOT NULL COMMENT 'Campo alterado (ex.: due_date, status)',
    old_value TEXT NULL COMMENT 'Valor anterior (string legível)',
    new_value TEXT NULL COMMENT 'Novo valor (string legível)',
    source VARCHAR(16) NOT NULL DEFAULT 'manual' COMMENT 'manual | cascade',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pch_card FOREIGN KEY (card_id) REFERENCES planning_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_pch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice para listar o histórico de um card em ordem cronológica.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_card_history' AND INDEX_NAME = 'idx_pch_card');
SET @s := IF(@i = 0,
    'ALTER TABLE planning_card_history ADD INDEX idx_pch_card (card_id, created_at)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
