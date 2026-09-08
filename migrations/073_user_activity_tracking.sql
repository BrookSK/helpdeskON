-- =============================================================================
-- Rastreamento de acesso e ações dos usuários.
--
-- Permite ao super_admin, a partir do perfil de um usuário (users/edit/ID),
-- visualizar:
--   1) Todos os logins realizados (data, IP, navegador).
--   2) Todas as ações executadas na plataforma (rota acessada, método HTTP, IP).
--
-- As duas tabelas são preenchidas automaticamente pelo ActivityLogger.
-- Seguro para rodar mais de uma vez.
-- =============================================================================

-- Histórico de logins (inclui impersonação feita por super_admin).
CREATE TABLE IF NOT EXISTS user_login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(512) DEFAULT NULL,
    login_type VARCHAR(20) NOT NULL DEFAULT 'password', -- password | impersonation
    impersonated_by INT DEFAULT NULL,                    -- id do super_admin, quando login_type = impersonation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ulh_user (user_id, created_at),
    CONSTRAINT fk_ulh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registro de ações (uma linha por requisição relevante).
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    controller VARCHAR(64) NOT NULL,
    action VARCHAR(64) NOT NULL,
    params VARCHAR(255) DEFAULT NULL,
    http_method VARCHAR(10) NOT NULL DEFAULT 'GET',
    path VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ual_user (user_id, created_at),
    CONSTRAINT fk_ual_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
