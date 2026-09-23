-- API Keys para integração externa (demanda: API v1 de criação de chamados).
--
-- Cada chave pertence a UMA empresa e aponta para um "usuário de integração"
-- (role client) daquela empresa. Assim o ticket criado via API grava
-- client_id = integration_user_id e a empresa é derivada por users.company_id,
-- preservando todo o relacionamento atual (notificações, PlanningCard, filtros
-- por empresa) sem mudar o schema de tickets.
--
-- Segurança: NUNCA guardamos a chave em claro. Persistimos apenas o hash
-- (SHA-256) e um prefixo curto (key_prefix) usado só para identificação na UI.
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    integration_user_id INT NOT NULL,
    name VARCHAR(120) NOT NULL COMMENT 'Rótulo da chave (ex.: ERP ACME)',
    key_prefix VARCHAR(16) NOT NULL COMMENT 'Prefixo exibível para identificação',
    key_hash VARCHAR(255) NOT NULL COMMENT 'Hash SHA-256 da chave (nunca o valor puro)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at TIMESTAMP NULL,
    revoked_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_api_key_hash (key_hash),
    KEY idx_api_keys_company (company_id),
    CONSTRAINT fk_apikey_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_apikey_user FOREIGN KEY (integration_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
