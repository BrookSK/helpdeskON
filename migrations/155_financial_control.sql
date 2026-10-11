-- Migration 155: Controle Financeiro (aba restrita a super_admin).
-- Lançamentos manuais (ganhos, gastos, salários, empréstimos, transferências),
-- cartões de crédito com parcelamentos e importação das cobranças das contas
-- Asaas já cadastradas em finance_accounts (multi-conta).

-- Cartões de crédito da empresa (faturas, limites, fechamento/vencimento).
CREATE TABLE IF NOT EXISTS fin_credit_cards (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(120) NOT NULL COMMENT 'Rótulo do cartão (ex.: Nubank PJ)',
    `limit`      DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Limite do cartão',
    closing_day  TINYINT NULL COMMENT 'Dia do fechamento da fatura',
    due_day      TINYINT NULL COMMENT 'Dia do vencimento da fatura',
    active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by   INT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fcard_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lançamentos financeiros. kind = natureza; direction = entra (in) / sai (out).
-- Compras parceladas no cartão geram N linhas em fin_installments.
-- source='asaas' = importado da integração (dedupe por asaas_payment_id).
CREATE TABLE IF NOT EXISTS fin_transactions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    kind             ENUM('ganho','gasto','salario','emprestimo','transferencia') NOT NULL,
    direction        ENUM('in','out') NOT NULL,
    category         VARCHAR(80) NULL COMMENT 'Categoria livre (ex.: Infra, Ferramentas, Vendas)',
    description      VARCHAR(255) NULL,
    amount           DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Valor total (nas parceladas, o total da compra)',
    installments     INT NOT NULL DEFAULT 1 COMMENT 'Nº de parcelas (1 = à vista)',
    status           ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    due_date         DATE NULL,
    paid_at          DATETIME NULL,
    card_id          INT NULL COMMENT 'Cartão usado (quando parcelado/no crédito)',
    asaas_account_id INT NULL COMMENT 'Conta Asaas de origem (quando importado)',
    asaas_payment_id VARCHAR(100) NULL COMMENT 'ID da cobrança no Asaas (dedupe)',
    source           ENUM('manual','asaas') NOT NULL DEFAULT 'manual',
    created_by       INT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fin_asaas (asaas_payment_id),
    KEY idx_fint_kind (kind),
    KEY idx_fint_status (status),
    KEY idx_fint_due (due_date),
    KEY idx_fint_card (card_id),
    FOREIGN KEY (card_id) REFERENCES fin_credit_cards(id) ON DELETE SET NULL,
    FOREIGN KEY (asaas_account_id) REFERENCES finance_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcelas das compras parceladas (uma linha por parcela).
CREATE TABLE IF NOT EXISTS fin_installments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id  INT NOT NULL,
    installment_no  INT NOT NULL,
    installment_total INT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date        DATE NULL COMMENT 'Vencimento da parcela (mensal a partir da 1ª)',
    status          ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fins_trans (transaction_id),
    KEY idx_fins_status (status),
    FOREIGN KEY (transaction_id) REFERENCES fin_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;