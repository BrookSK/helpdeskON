-- ============================================================
-- 166_commercial_flow_links.sql
-- Fluxo comercial (aceite até atendimento), change 02:
-- - proposals: versões (root_proposal_id, revision_no, superseded_by) e
--   registro do aceite (nome, IP, navegador e versão aceita).
-- - contracts: CPF/CNPJ e endereço do cliente, origem da assinatura e PDF
--   assinado guardado fora de public/ (com estado do download).
-- - finance_projects: cliente e assinatura no Asaas.
-- - finance_charges: reserva da emissão, links de pagamento, assinatura de
--   origem e asaas_charge_id único (baixa idempotente).
-- - finance_events: histórico do financeiro (emissão, baixa, webhook).
-- - onboarding_credential_links: link seguro para o cliente enviar credenciais.
-- - client_credentials.onboarding_id: credencial enviada pelo link.
-- - projects: contrato e proposta de origem.
-- - tickets: projeto e serviço contratado (item da proposta aceita).
-- Idempotente (information_schema + PREPARE). Propostas existentes ficam com
-- revision_no = 1 e root_proposal_id = id.
-- ============================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='root_proposal_id');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN root_proposal_id INT NULL COMMENT ''Primeira versao (R01) da proposta'' AFTER id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='revision_no');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN revision_no INT NOT NULL DEFAULT 1 COMMENT ''Numero da versao (R01, R02...)'' AFTER root_proposal_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='superseded_by');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN superseded_by INT NULL COMMENT ''Versao que substituiu esta (somente leitura)'' AFTER revision_no','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='accepted_name');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN accepted_name VARCHAR(200) NULL COMMENT ''Nome informado no aceite'' AFTER accepted_plan','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='accepted_ip');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN accepted_ip VARCHAR(45) NULL AFTER accepted_name','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='accepted_user_agent');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN accepted_user_agent VARCHAR(255) NULL AFTER accepted_ip','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND COLUMN_NAME='accepted_revision');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD COLUMN accepted_revision INT NULL COMMENT ''Versao aceita'' AFTER accepted_user_agent','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='client_document');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN client_document VARCHAR(30) NULL COMMENT ''CPF/CNPJ do cliente'' AFTER client_phone','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='client_address');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN client_address VARCHAR(500) NULL COMMENT ''Endereco do cliente'' AFTER client_document','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='signed_via');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN signed_via VARCHAR(20) NULL COMMENT ''clicksign ou upload'' AFTER signed_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='signed_file_path');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN signed_file_path VARCHAR(300) NULL COMMENT ''PDF assinado (fora de public/)'' AFTER signed_via','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='signed_file_status');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN signed_file_status VARCHAR(20) NULL COMMENT ''pending, stored ou failed'' AFTER signed_file_path','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='signed_file_attempts');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN signed_file_attempts INT NOT NULL DEFAULT 0 AFTER signed_file_status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME='signed_file_error');
SET @s := IF(@c=0,'ALTER TABLE contracts ADD COLUMN signed_file_error VARCHAR(500) NULL AFTER signed_file_attempts','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_projects' AND COLUMN_NAME='asaas_customer_id');
SET @s := IF(@c=0,'ALTER TABLE finance_projects ADD COLUMN asaas_customer_id VARCHAR(100) NULL COMMENT ''Cliente no Asaas'' AFTER entry_paid_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_projects' AND COLUMN_NAME='asaas_customer_account_id');
SET @s := IF(@c=0,'ALTER TABLE finance_projects ADD COLUMN asaas_customer_account_id INT NULL COMMENT ''Conta Asaas do cliente acima'' AFTER asaas_customer_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_projects' AND COLUMN_NAME='asaas_subscription_id');
SET @s := IF(@c=0,'ALTER TABLE finance_projects ADD COLUMN asaas_subscription_id VARCHAR(100) NULL COMMENT ''Assinatura mensal no Asaas'' AFTER asaas_customer_account_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='issuing_at');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN issuing_at DATETIME NULL COMMENT ''Reserva da emissao (evita clique duplo)'' AFTER asaas_charge_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='issued_at');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN issued_at DATETIME NULL AFTER issuing_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='issue_error');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN issue_error VARCHAR(500) NULL AFTER issued_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='invoice_url');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN invoice_url VARCHAR(500) NULL COMMENT ''Link de pagamento do Asaas'' AFTER issue_error','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='bank_slip_url');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN bank_slip_url VARCHAR(500) NULL AFTER invoice_url','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND COLUMN_NAME='asaas_subscription_id');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD COLUMN asaas_subscription_id VARCHAR(100) NULL COMMENT ''Assinatura que gerou a cobranca'' AFTER bank_slip_url','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='client_credentials' AND COLUMN_NAME='onboarding_id');
SET @s := IF(@c=0,'ALTER TABLE client_credentials ADD COLUMN onboarding_id INT NULL COMMENT ''Enviada pelo cliente no link do onboarding'' AFTER company_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND COLUMN_NAME='contract_id');
SET @s := IF(@c=0,'ALTER TABLE projects ADD COLUMN contract_id INT NULL AFTER onboarding_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND COLUMN_NAME='proposal_id');
SET @s := IF(@c=0,'ALTER TABLE projects ADD COLUMN proposal_id INT NULL AFTER contract_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tickets' AND COLUMN_NAME='project_id');
SET @s := IF(@c=0,'ALTER TABLE tickets ADD COLUMN project_id INT NULL COMMENT ''Projeto do cliente'' AFTER client_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tickets' AND COLUMN_NAME='proposal_item_id');
SET @s := IF(@c=0,'ALTER TABLE tickets ADD COLUMN proposal_item_id INT NULL COMMENT ''Servico contratado (item da proposta aceita)'' AFTER project_id','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='proposals' AND INDEX_NAME='idx_proposal_root');
SET @s := IF(@c=0,'ALTER TABLE proposals ADD KEY idx_proposal_root (root_proposal_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_charges' AND INDEX_NAME='uk_fchg_asaas');
SET @s := IF(@c=0,'ALTER TABLE finance_charges ADD UNIQUE KEY uk_fchg_asaas (asaas_charge_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='finance_projects' AND INDEX_NAME='idx_fproj_subscription');
SET @s := IF(@c=0,'ALTER TABLE finance_projects ADD KEY idx_fproj_subscription (asaas_subscription_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND INDEX_NAME='idx_project_onboarding');
SET @s := IF(@c=0,'ALTER TABLE projects ADD KEY idx_project_onboarding (onboarding_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tickets' AND INDEX_NAME='idx_ticket_project');
SET @s := IF(@c=0,'ALTER TABLE tickets ADD KEY idx_ticket_project (project_id)','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE proposals SET root_proposal_id = id WHERE root_proposal_id IS NULL;

CREATE TABLE IF NOT EXISTS finance_events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    charge_id   INT NULL,
    user_id     INT NULL COMMENT 'NULL = webhook/sistema',
    event_type  VARCHAR(40) NOT NULL,
    description TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fevent_project (project_id, created_at),
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onboarding_credential_links (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    token_hash    CHAR(64) NOT NULL COMMENT 'SHA-256 do token (o token em claro nao fica no banco)',
    expires_at    DATETIME NOT NULL,
    revoked_at    DATETIME NULL,
    created_by    INT NULL,
    last_used_at  DATETIME NULL,
    last_used_ip  VARCHAR(45) NULL,
    use_count     INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_credlink_hash (token_hash),
    KEY idx_credlink_onboarding (onboarding_id),
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
