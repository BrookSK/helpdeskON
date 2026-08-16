-- ============================================
-- Integração de Telefonia Nvoip (API v3)
-- ============================================
-- Reutiliza a tabela settings (Config::get/set) para as credenciais.
-- Valores conforme documentação oficial Nvoip v3:
--   authBaseUrl, baseUrl, oauthClientId, oauthClientCredential, oauthScopes
-- nvoip_caller: originador AUTORIZADO da conta Nvoip (não presumido pelo sistema;
--   informado pelo administrador conforme a conta/documentação Nvoip).

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('nvoip_auth_base_url', ''),
('nvoip_base_url', ''),
('nvoip_oauth_client_id', ''),
('nvoip_oauth_client_credential', ''),
('nvoip_oauth_scopes', ''),
('nvoip_caller', '');

-- ============================================
-- Registro mínimo de ligações originadas pelo CRM.
-- Relaciona: Lead (whatsapp_contacts) + usuário do CRM + callId + telefone + status.
-- ============================================
CREATE TABLE IF NOT EXISTS nvoip_calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NULL,
    user_id INT NULL,
    call_id VARCHAR(191) NULL COMMENT 'callId retornado pela criação da chamada Nvoip',
    caller VARCHAR(50) NULL,
    called VARCHAR(50) NULL,
    status VARCHAR(100) NULL COMMENT 'situação disponibilizada pela API Nvoip',
    response_json TEXT NULL COMMENT 'corpo da resposta (sem dados sensíveis de auth)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contact (contact_id),
    KEY idx_call_id (call_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
