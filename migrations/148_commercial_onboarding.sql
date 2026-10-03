-- Migration 148: Onboarding por etapas + Pontos focais + Credenciais seguras (Fase 6)
-- Depois da ENTRADA PAGA (finance), inicia o onboarding por etapas com checklist,
-- responsável e status por etapa, e bloqueios configuráveis. Pontos focais do
-- cliente e cofre de credenciais (secret criptografado, acesso restrito).
-- Tabelas novas (CREATE IF NOT EXISTS).

-- Onboarding do cliente (um por projeto/contrato).
CREATE TABLE IF NOT EXISTS onboardings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NULL COMMENT 'finance_projects.id',
    contract_id    INT NULL,
    company_id     INT NULL,
    contact_id     INT NULL,
    title          VARCHAR(200) NOT NULL,
    project_type   ENUM('zero','esteira','manutencao','outro') NULL COMMENT 'Tipo do projeto',
    pipeline_mode  ENUM('esteira_cx','fora_esteira') NULL COMMENT 'Entra na esteira (CX) ou automação própria',
    tech_responsible_id INT NULL COMMENT 'Responsável técnico (users.id)',
    status         ENUM('blocked','in_progress','done','cancelled') NOT NULL DEFAULT 'blocked',
    notes          TEXT NULL,
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_onb_status (status),
    KEY idx_onb_project (project_id),
    KEY idx_onb_company (company_id),
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (tech_responsible_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas do onboarding (checklist). step_key identifica a etapa padrão.
-- blocked_reason guarda o porquê quando a etapa está bloqueada por requisito.
CREATE TABLE IF NOT EXISTS onboarding_steps (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id  INT NOT NULL,
    step_key       VARCHAR(60) NOT NULL COMMENT 'ex.: tech_responsible, kickoff, server, storage...',
    title          VARCHAR(200) NOT NULL,
    status         ENUM('pending','in_progress','done','blocked') NOT NULL DEFAULT 'pending',
    responsible_id INT NULL,
    required       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Requisito obrigatório p/ concluir o onboarding',
    blocked_reason TEXT NULL,
    notes          TEXT NULL,
    position       INT NOT NULL DEFAULT 0,
    done_at        DATETIME NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ostep_onb (onboarding_id, position),
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico do onboarding.
CREATE TABLE IF NOT EXISTS onboarding_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    user_id       INT NULL,
    event_type    VARCHAR(40) NOT NULL,
    description   TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_oevent_onb (onboarding_id, created_at),
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pontos focais do cliente (múltiplos; 1 principal).
CREATE TABLE IF NOT EXISTS client_contacts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NULL,
    onboarding_id  INT NULL,
    name           VARCHAR(200) NOT NULL,
    role           VARCHAR(120) NULL COMMENT 'Cargo/função',
    email          VARCHAR(191) NULL,
    phone          VARCHAR(40) NULL,
    responsibility VARCHAR(200) NULL,
    is_primary     TINYINT(1) NOT NULL DEFAULT 0,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    notes          TEXT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cc_company (company_id),
    KEY idx_cc_onb (onboarding_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cofre de credenciais por cliente. O segredo é CRIPTOGRAFADO (nunca texto puro).
-- Acesso restrito (super_admin). secret_encrypted guarda o ciphertext.
CREATE TABLE IF NOT EXISTS client_credentials (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NULL,
    service_label  VARCHAR(160) NOT NULL COMMENT 'Servidor/Hospedagem/Cloud/Domínio/E-mail/API/...',
    username       VARCHAR(255) NULL,
    secret_encrypted LONGTEXT NULL COMMENT 'Senha/token CRIPTOGRAFADA (base64 iv+cipher)',
    url            VARCHAR(500) NULL,
    notes          TEXT NULL,
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cred_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
