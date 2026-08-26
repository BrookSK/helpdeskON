-- Migration 060: Captação de Leads via Apollo.io
-- Cria a chave de configuração da API e a tabela de "staging" dos leads
-- pesquisados no Apollo antes de serem importados para "Meus Leads".

-- Chave da API Apollo (x-api-key). Deixe em branco para desabilitar o módulo.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('apollo_api_key', ''),
('apollo_base_url', 'https://api.apollo.io/api/v1');

-- E-mail do lead no contato do CRM (usado ao importar do Apollo e pela prospecção).
-- Adiciona a coluna somente se ainda não existir.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'lead_email'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE whatsapp_contacts ADD COLUMN lead_email VARCHAR(255) NULL AFTER phone',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Staging de leads capturados no Apollo
-- ============================================
-- Guarda os prospects revelados/enriquecidos para consulta e importação posterior.
-- O vínculo com o lead do CRM (whatsapp_contacts) é feito em contact_id após import.
CREATE TABLE IF NOT EXISTS apollo_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apollo_id VARCHAR(64) NOT NULL COMMENT 'ID do person no Apollo',
    first_name VARCHAR(150) DEFAULT NULL,
    last_name VARCHAR(150) DEFAULT NULL,
    full_name VARCHAR(300) DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    seniority VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    email_status VARCHAR(50) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    linkedin_url VARCHAR(500) DEFAULT NULL,
    organization_name VARCHAR(255) DEFAULT NULL,
    organization_domain VARCHAR(255) DEFAULT NULL,
    organization_website VARCHAR(500) DEFAULT NULL,
    organization_industry VARCHAR(255) DEFAULT NULL,
    city VARCHAR(150) DEFAULT NULL,
    state VARCHAR(150) DEFAULT NULL,
    country VARCHAR(150) DEFAULT NULL,
    is_enriched TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se já revelou email/telefone',
    raw_json MEDIUMTEXT DEFAULT NULL COMMENT 'Payload completo retornado pelo Apollo',
    contact_id INT DEFAULT NULL COMMENT 'whatsapp_contacts.id após importação',
    imported_at TIMESTAMP NULL DEFAULT NULL,
    imported_by INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_apollo_id (apollo_id),
    KEY idx_contact (contact_id),
    KEY idx_imported (imported_at),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
