-- Migration 147: Financeiro (Fase 5 — esteira comercial)
-- Depois do contrato ASSINADO, o financeiro entra: define o valor do projeto,
-- a entrada e as parcelas, a recorrência (assinatura), e cria o "projeto".
-- Integração Asaas com 3 CONTAS selecionáveis por cobrança (parcelas / recorrente
-- / outra parcela). A chamada HTTP real é validação manual; aqui ficam o schema
-- e os campos. Tabelas novas (CREATE IF NOT EXISTS).

-- Contas Asaas disponíveis (as 3 contas usadas pela empresa). Selecionável por
-- cobrança. token guardado como está (ambiente de dev não envia de verdade).
CREATE TABLE IF NOT EXISTS finance_accounts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL COMMENT 'Rótulo da conta (ex.: Parcelas, Recorrente, Entradas)',
    purpose     ENUM('parcela','recorrente','outra') NOT NULL DEFAULT 'outra' COMMENT 'Finalidade padrão',
    asaas_token VARCHAR(255) NULL COMMENT 'API key da conta Asaas',
    sandbox     TINYINT(1) NOT NULL DEFAULT 1,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_facc_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projeto financeiro: nasce do contrato assinado. Guarda o valor total e o
-- controle de "entrada paga" (que destrava o onboarding).
CREATE TABLE IF NOT EXISTS finance_projects (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    contract_id   INT NULL,
    proposal_id   INT NULL,
    company_id    INT NULL,
    contact_id    INT NULL,
    title         VARCHAR(200) NOT NULL,
    total_value   DECIMAL(12,2) NOT NULL DEFAULT 0,
    status        ENUM('open','entry_paid','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
    entry_paid_at DATETIME NULL COMMENT 'Quando a entrada foi confirmada (destrava onboarding)',
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

-- Cobranças (entrada, parcelas, recorrente). Cada uma aponta para a conta Asaas
-- escolhida. kind='entry' é a entrada que destrava o onboarding ao ser paga.
CREATE TABLE IF NOT EXISTS finance_charges (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NOT NULL,
    account_id     INT NULL COMMENT 'Conta Asaas usada nesta cobrança',
    kind           ENUM('entry','installment','recurring') NOT NULL DEFAULT 'installment',
    description    VARCHAR(200) NULL,
    amount         DECIMAL(12,2) NOT NULL DEFAULT 0,
    installment_no INT NULL COMMENT 'Nº da parcela (quando installment)',
    installment_total INT NULL COMMENT 'Total de parcelas',
    due_date       DATE NULL,
    method         ENUM('pix','boleto','cartao') NULL,
    recurring_cycle ENUM('monthly','yearly') NULL COMMENT 'Ciclo quando recurring',
    recurring_start DATE NULL COMMENT 'Início da recorrência',
    status         ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
    asaas_charge_id VARCHAR(100) NULL COMMENT 'ID da cobrança no Asaas',
    paid_at        DATETIME NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fchg_project (project_id),
    KEY idx_fchg_status (status),
    KEY idx_fchg_asaas (asaas_charge_id),
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
