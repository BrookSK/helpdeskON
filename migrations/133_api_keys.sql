-- API Keys de integração externa (demanda: API v1 de criação de chamados).
--
-- Modelo simplificado: UMA chave por empresa. A chave é gravada inteira (não é
-- hash) para poder ser reexibida na tela de Configurações e copiada quando
-- preciso repassá-la ao sistema do cliente. Trade-off conhecido: por ser uma
-- ferramenta interna (só super_admin), aceitamos guardar a chave em claro em
-- troca da praticidade de reexibição.
--
-- Cada chave pertence a uma empresa (company_id, UNIQUE) e aponta para um
-- "usuário de integração" (role client) dessa empresa. O ticket criado via API
-- grava client_id = integration_user_id e a empresa é derivada por
-- users.company_id, preservando todo o relacionamento atual.
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    integration_user_id INT NOT NULL,
    api_key VARCHAR(80) NOT NULL COMMENT 'Chave completa (hk_live_...), usada no header X-Api-Key',
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_api_key (api_key),
    UNIQUE KEY uq_api_key_company (company_id),
    CONSTRAINT fk_apikey_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_apikey_user FOREIGN KEY (integration_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
