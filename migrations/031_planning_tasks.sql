-- Migration 031: Tabela de tasks internas dos cards de planejamento
-- Permite criar sub-tarefas dentro de um card com texto, descrição e status de conclusão.

CREATE TABLE IF NOT EXISTS planning_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    completed_by INT DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    position INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES planning_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_planning_tasks_card ON planning_tasks(card_id, position);

-- Tabela de imagens/anexos das tasks
CREATE TABLE IF NOT EXISTS planning_task_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT 0,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES planning_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_planning_task_images_task ON planning_task_images(task_id);
