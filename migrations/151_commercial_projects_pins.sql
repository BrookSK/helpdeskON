-- Migration 151: Ponte lead->cliente + Projetos/Garantia + PIN do cliente (Fase 9)
-- Fecha a rastreabilidade da esteira (lead -> company/user) sem duplicar
-- cadastro, cria a aba de Projetos com garantia de 90 dias e um PIN de login
-- simplificado DO CLIENTE (distinto do external_pin de EQUIPE da migration 126,
-- que NÃO pode ser quebrado). Tudo aditivo e idempotente.

-- 1) Ponte lead -> cliente. Liga o lead (whatsapp_contacts) à empresa/usuário
--    criado na conversão, preservando a cadeia potencial->cliente.
CREATE TABLE IF NOT EXISTS lead_company_links (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    contact_id      INT NULL COMMENT 'whatsapp_contacts.id (lead)',
    company_id      INT NULL COMMENT 'companies.id (cliente)',
    user_id         INT NULL COMMENT 'users.id dono criado na conversão',
    proposal_id     INT NULL,
    contract_id     INT NULL,
    onboarding_id   INT NULL,
    converted_by    INT NULL,
    converted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes           TEXT NULL,
    UNIQUE KEY uq_lead_company (contact_id, company_id),
    KEY idx_lcl_company (company_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (converted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Projetos entregues + garantia. contract_type zero (sem garantia de 90d) vs
--    manutencao/outro. warranty_ends_at calculado na entrega.
CREATE TABLE IF NOT EXISTS projects (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_id      INT NULL,
    onboarding_id   INT NULL,
    provisioning_id INT NULL,
    name            VARCHAR(200) NOT NULL,
    contract_type   ENUM('zero','manutencao','suporte','outro') NOT NULL DEFAULT 'outro',
    status          ENUM('planning','in_progress','delivered','warranty','closed','cancelled') NOT NULL DEFAULT 'planning',
    delivered_at    DATETIME NULL,
    warranty_days   INT NOT NULL DEFAULT 90,
    warranty_ends_at DATETIME NULL,
    support_contract TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tem contrato de suporte ativo (libera chamados pós-garantia)',
    notes           TEXT NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_project_company (company_id),
    KEY idx_project_status (status),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico do projeto (entrega, garantia, mudança de status).
CREATE TABLE IF NOT EXISTS project_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    user_id         INT NULL,
    event_type      VARCHAR(50) NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_projevent (project_id, created_at),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) PIN do CLIENTE (login simplificado). Coluna separada de external_pin
--    (que é da EQUIPE). Idempotente: só adiciona se não existir.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'client_pin'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN client_pin VARCHAR(6) NULL UNIQUE COMMENT "PIN de login simplificado do cliente (distinto de external_pin de equipe)"',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
