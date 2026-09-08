-- Migration 071: Marketing — aprovador, histórico de ajustes e status "rascunho".

-- Novo status "rascunho" (marketing salva sem imagem) + garante os demais status.
ALTER TABLE marketing_items
    MODIFY COLUMN status ENUM('rascunho','ideia','em_producao','aguardando_aprovacao','aprovado','agendado','publicado','rejeitado')
    NOT NULL DEFAULT 'ideia';

-- Aprovador responsável por revisar a demanda (recebe notificação de ajustes).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_items' AND COLUMN_NAME = 'approver_id');
SET @s := IF(@c = 0, 'ALTER TABLE marketing_items ADD COLUMN approver_id INT NULL AFTER assigned_to', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Histórico de eventos/ajustes da demanda.
CREATE TABLE IF NOT EXISTS marketing_item_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NULL COMMENT 'quem executou a ação',
    action VARCHAR(40) NOT NULL COMMENT 'created, updated, submitted, changes_requested, approved, rejected, adjusted',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_item (item_id),
    FOREIGN KEY (item_id) REFERENCES marketing_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
