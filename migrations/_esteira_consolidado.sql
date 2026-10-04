-- =============================================================================
-- CONSOLIDADO da esteira comercial + reuniões (migrations 142 a 154).
-- Para aplicar num banco que ainda não tem as tabelas novas (ex.: beta/produção).
--
-- COMO USAR (phpMyAdmin do Plesk):
--   1. Selecione o banco (ex.: helpdesk_on_beta) na barra lateral.
--   2. Aba "Importar" -> escolha este arquivo -> Executar.
--      (ou aba "SQL" -> cole todo o conteúdo -> Executar.)
--
-- É SEGURO rodar mais de uma vez: tudo é idempotente (CREATE TABLE IF NOT EXISTS
-- e ALTER com checagem no information_schema). A ORDEM importa por causa das
-- chaves estrangeiras — não reordene os blocos.
-- =============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- 142: video_rooms.auto_record
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_rooms' AND COLUMN_NAME = 'auto_record');
SET @s := IF(@c = 0,
    'ALTER TABLE video_rooms ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Inicia a gravacao automaticamente ao entrar na sala'' AFTER allow_recording',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 143: video_recordings minutes/minutes_status/minutes_generated_at
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes LONGTEXT NULL COMMENT ''Ata/minuta da reuniao (Markdown, editavel)'' AFTER summary','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_status');
SET @s := IF(@c=0,"ALTER TABLE video_recordings ADD COLUMN minutes_status ENUM('none','processing','done','error') NOT NULL DEFAULT 'none' AFTER minutes",'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_generated_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_generated_at DATETIME NULL AFTER minutes_status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 144: video_recordings.minutes_sent_at
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='video_recordings' AND COLUMN_NAME='minutes_sent_at');
SET @s := IF(@c=0,'ALTER TABLE video_recordings ADD COLUMN minutes_sent_at DATETIME NULL COMMENT ''Quando o link da minuta foi enviado'' AFTER minutes_generated_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 145: Catálogo de serviços + Propostas
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_catalog (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    description   TEXT NULL,
    est_hours     DECIMAL(10,2) NULL,
    hourly_rate   DECIMAL(10,2) NULL,
    is_hosting    TINYINT(1) NOT NULL DEFAULT 0,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_by    INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_service_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    public_token   VARCHAR(64) NOT NULL,
    title          VARCHAR(200) NOT NULL,
    contact_id     INT NULL,
    card_id        INT NULL,
    company_id     INT NULL,
    client_name    VARCHAR(200) NULL,
    client_email   VARCHAR(191) NULL,
    client_phone   VARCHAR(40) NULL,
    status         ENUM('draft','ready','sent','awaiting','accepted','rejected','cancelled') NOT NULL DEFAULT 'draft',
    contract_type  ENUM('dev_zero','dev_manutencao','suporte','dev_suporte','outro') NULL,
    observations   TEXT NULL,
    validity_date  DATE NULL,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    reject_reason  TEXT NULL,
    sent_at        DATETIME NULL,
    responded_at   DATETIME NULL,
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_proposal_token (public_token),
    KEY idx_proposal_status (status),
    KEY idx_proposal_contact (contact_id),
    KEY idx_proposal_card (card_id),
    KEY idx_proposal_company (company_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (card_id) REFERENCES crm_cards(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id   INT NOT NULL,
    service_id    INT NULL,
    description   VARCHAR(255) NOT NULL,
    scope         TEXT NULL,
    hours         DECIMAL(10,2) NULL,
    hourly_rate   DECIMAL(10,2) NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_hosting    TINYINT(1) NOT NULL DEFAULT 0,
    position      INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_item_proposal (proposal_id, position),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES service_catalog(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id   INT NOT NULL,
    user_id       INT NULL,
    event_type    VARCHAR(40) NOT NULL,
    description   TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_proposal (proposal_id, created_at),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 146: Modelos de contrato + Contratos
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contract_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    body        LONGTEXT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ctpl_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    public_token       VARCHAR(64) NOT NULL,
    title              VARCHAR(200) NOT NULL,
    proposal_id        INT NULL,
    template_id        INT NULL,
    contact_id         INT NULL,
    company_id         INT NULL,
    client_name        VARCHAR(200) NULL,
    client_email       VARCHAR(191) NULL,
    client_phone       VARCHAR(40) NULL,
    body               LONGTEXT NULL,
    status             ENUM('draft','client_review','approved','awaiting_signature','signed','client_rejected','cancelled') NOT NULL DEFAULT 'draft',
    reject_reason      TEXT NULL,
    clicksign_doc_key  VARCHAR(100) NULL,
    clicksign_signer_key VARCHAR(100) NULL,
    clicksign_request_key VARCHAR(100) NULL,
    signed_at          DATETIME NULL,
    sent_signature_at  DATETIME NULL,
    approved_at        DATETIME NULL,
    created_by         INT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_token (public_token),
    KEY idx_contract_status (status),
    KEY idx_contract_proposal (proposal_id),
    KEY idx_contract_company (company_id),
    KEY idx_contract_cskey (clicksign_doc_key),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_events (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    contract_id  INT NOT NULL,
    user_id      INT NULL,
    event_type   VARCHAR(40) NOT NULL,
    description  TEXT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cevent_contract (contract_id, created_at),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 147: Financeiro (contas Asaas + projetos + cobranças)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS finance_accounts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    purpose     ENUM('parcela','recorrente','outra') NOT NULL DEFAULT 'outra',
    asaas_token VARCHAR(255) NULL,
    sandbox     TINYINT(1) NOT NULL DEFAULT 1,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_facc_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_projects (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    contract_id   INT NULL,
    proposal_id   INT NULL,
    company_id    INT NULL,
    contact_id    INT NULL,
    title         VARCHAR(200) NOT NULL,
    total_value   DECIMAL(12,2) NOT NULL DEFAULT 0,
    status        ENUM('open','entry_paid','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
    entry_paid_at DATETIME NULL,
    created_by    INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fproj_status (status),
    KEY idx_fproj_contract (contract_id),
    KEY idx_fproj_company (company_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_charges (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NOT NULL,
    account_id     INT NULL,
    kind           ENUM('entry','installment','recurring') NOT NULL DEFAULT 'installment',
    description    VARCHAR(200) NULL,
    amount         DECIMAL(12,2) NOT NULL DEFAULT 0,
    installment_no INT NULL,
    installment_total INT NULL,
    due_date       DATE NULL,
    method         ENUM('pix','boleto','cartao') NULL,
    recurring_cycle ENUM('monthly','yearly') NULL,
    recurring_start DATE NULL,
    status         ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
    asaas_charge_id VARCHAR(100) NULL,
    paid_at        DATETIME NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fchg_project (project_id),
    KEY idx_fchg_status (status),
    KEY idx_fchg_asaas (asaas_charge_id),
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 148: Onboarding + Pontos focais + Credenciais
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS onboardings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NULL,
    contract_id    INT NULL,
    company_id     INT NULL,
    contact_id     INT NULL,
    title          VARCHAR(200) NOT NULL,
    project_type   ENUM('zero','esteira','manutencao','outro') NULL,
    pipeline_mode  ENUM('esteira_cx','fora_esteira') NULL,
    tech_responsible_id INT NULL,
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

CREATE TABLE IF NOT EXISTS onboarding_steps (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id  INT NOT NULL,
    step_key       VARCHAR(60) NOT NULL,
    title          VARCHAR(200) NOT NULL,
    status         ENUM('pending','in_progress','done','blocked') NOT NULL DEFAULT 'pending',
    responsible_id INT NULL,
    required       TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS client_contacts (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NULL,
    onboarding_id  INT NULL,
    name           VARCHAR(200) NOT NULL,
    role           VARCHAR(120) NULL,
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

CREATE TABLE IF NOT EXISTS client_credentials (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    company_id     INT NULL,
    service_label  VARCHAR(160) NOT NULL,
    username       VARCHAR(255) NULL,
    secret_encrypted LONGTEXT NULL,
    url            VARCHAR(500) NULL,
    notes          TEXT NULL,
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cred_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 149: Provisionamento
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS provisionings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id   INT NULL,
    project_id      INT NULL,
    company_id      INT NULL,
    pipeline_mode   ENUM('esteira_cx','fora_esteira') NOT NULL DEFAULT 'fora_esteira',
    status          ENUM('pending','in_progress','blocked','done','cancelled') NOT NULL DEFAULT 'pending',
    lrv_client_id   VARCHAR(64) NULL,
    lrv_vps_id      VARCHAR(64) NULL,
    lrv_app_id      VARCHAR(64) NULL,
    git_repo        VARCHAR(300) NULL,
    git_branch      VARCHAR(120) NULL,
    staging_url     VARCHAR(300) NULL,
    notes           TEXT NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_prov_status (status),
    KEY idx_prov_onb (onboarding_id),
    KEY idx_prov_company (company_id),
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_steps (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provisioning_id INT NOT NULL,
    step_key        VARCHAR(60) NOT NULL,
    title           VARCHAR(200) NOT NULL,
    mode            ENUM('auto','manual') NOT NULL DEFAULT 'manual' COMMENT 'auto=API disponivel / manual=pendencia (gap de API)',
    status          ENUM('pending','in_progress','done','blocked','skipped') NOT NULL DEFAULT 'pending',
    required        TINYINT(1) NOT NULL DEFAULT 1,
    responsible_id  INT NULL,
    external_ref    VARCHAR(120) NULL,
    blocked_reason  TEXT NULL,
    notes           TEXT NULL,
    position        INT NOT NULL DEFAULT 0,
    done_at         DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pstep_prov (provisioning_id, position),
    FOREIGN KEY (provisioning_id) REFERENCES provisionings(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provisioning_id INT NOT NULL,
    user_id         INT NULL,
    event_type      VARCHAR(50) NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pevent_prov (provisioning_id, created_at),
    FOREIGN KEY (provisioning_id) REFERENCES provisionings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 150: Prestadores
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS providers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    email           VARCHAR(191) NULL,
    phone           VARCHAR(40) NULL,
    document        VARCHAR(40) NULL,
    role_title      VARCHAR(160) NULL,
    engagement_type ENUM('clt','pj','freelancer','estagio','outro') NOT NULL DEFAULT 'pj',
    work_model      ENUM('remoto','hibrido','presencial') NULL,
    pay_type        ENUM('mensal','hora','projeto') NULL,
    pay_amount      DECIMAL(12,2) NULL,
    workload        VARCHAR(120) NULL,
    start_date      DATE NULL,
    end_date        DATE NULL,
    scope           TEXT NULL,
    out_of_scope    TEXT NULL,
    status          ENUM('prospect','proposal','contract','active','terminated','cancelled') NOT NULL DEFAULT 'prospect',
    user_id         INT NULL,
    termination_reason TEXT NULL,
    terminated_at   DATETIME NULL,
    notes           TEXT NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_accesses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    access_label    VARCHAR(160) NOT NULL,
    details         VARCHAR(300) NULL,
    granted_at      DATETIME NULL,
    revoked_at      DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_paccess_provider (provider_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    doc_label       VARCHAR(160) NOT NULL,
    file_path       VARCHAR(500) NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pdoc_provider (provider_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    user_id         INT NULL,
    event_type      VARCHAR(50) NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pevent_provider (provider_id, created_at),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 151: Ponte lead->cliente + Projetos/Garantia + PIN do cliente
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lead_company_links (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    contact_id      INT NULL,
    company_id      INT NULL,
    user_id         INT NULL,
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
    support_contract TINYINT(1) NOT NULL DEFAULT 0,
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

-- users.client_pin (PIN de login do cliente, 6 dígitos, distinto do external_pin de equipe)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'client_pin');
SET @s := IF(@c = 0,
    'ALTER TABLE users ADD COLUMN client_pin VARCHAR(6) NULL UNIQUE COMMENT ''PIN de login simplificado do cliente''',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
