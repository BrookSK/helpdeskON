-- Migration 167: Controle Financeiro v3.
-- Adiciona ao Controle Financeiro (super_admin):
--   1) Contas bancárias (fin_bank_accounts) com SALDO INICIAL. O saldo atual é
--      calculado: saldo_inicial + entradas pagas - saídas pagas vinculadas.
--   2) Vínculo de CARTÃO a uma conta bancária (fin_credit_cards.account_id).
--   3) Vínculo de LANÇAMENTO a uma conta bancária (fin_transactions.account_id).
--   4) Categorias cadastráveis (fin_categories) e vínculo do lançamento a uma
--      categoria (fin_transactions.category_id). O campo de texto `category`
--      continua existindo (origem Asaas/legado), mas a UI passa a usar o select.
-- Obs.: roda após a 155/156 (fin_* já devem existir).

-- 1) Contas bancárias da empresa (saldo inicial manual; saldo atual derivado).
CREATE TABLE IF NOT EXISTS fin_bank_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL COMMENT 'Rótulo da conta (ex.: Nubank PJ, Inter)',
    bank            VARCHAR(120) NULL COMMENT 'Banco/instituição (opcional)',
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Saldo inicial informado',
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fbank_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Categorias cadastráveis (ganho/gasto/ambos). Fonte única para o select.
CREATE TABLE IF NOT EXISTS fin_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL COMMENT 'Nome da categoria (ex.: Infra, Vendas)',
    kind        ENUM('ganho','gasto','ambos') NOT NULL DEFAULT 'ambos' COMMENT 'Em que tipos de lançamento aparece',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fcat_name_kind (name, kind),
    KEY idx_fcat_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Cartão -> conta bancária (de onde a fatura é paga).
ALTER TABLE fin_credit_cards
    ADD COLUMN account_id INT NULL COMMENT 'Conta bancária vinculada ao cartão' AFTER due_day,
    ADD KEY idx_fcard_account (account_id),
    ADD CONSTRAINT fk_fcard_account FOREIGN KEY (account_id)
        REFERENCES fin_bank_accounts(id) ON DELETE SET NULL;

-- 4) Lançamento -> conta bancária + categoria cadastrada.
ALTER TABLE fin_transactions
    ADD COLUMN account_id  INT NULL COMMENT 'Conta bancária que movimenta (entra/sai)' AFTER card_id,
    ADD COLUMN category_id INT NULL COMMENT 'Categoria cadastrada (select)' AFTER category,
    ADD KEY idx_fint_account (account_id),
    ADD KEY idx_fint_category (category_id),
    ADD CONSTRAINT fk_fint_account FOREIGN KEY (account_id)
        REFERENCES fin_bank_accounts(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_fint_category FOREIGN KEY (category_id)
        REFERENCES fin_categories(id) ON DELETE SET NULL;

-- 5) Semeia as categorias a partir das categorias de texto já existentes nos
--    lançamentos (ex.: "Infra", "Asaas"). Idempotente: INSERT IGNORE pela
--    UNIQUE (name, kind). Normaliza para kind='ambos'.
INSERT IGNORE INTO fin_categories (name, kind, active)
SELECT DISTINCT TRIM(category) AS name, 'ambos' AS kind, 1 AS active
FROM fin_transactions
WHERE category IS NOT NULL AND TRIM(category) <> '';

-- 6) Liga os lançamentos existentes à categoria recém-criada (quando o texto
--    casa com o nome). Mantém o texto original em `category` como histórico.
UPDATE fin_transactions t
JOIN fin_categories c ON c.name = TRIM(t.category) AND c.kind = 'ambos'
SET t.category_id = c.id
WHERE t.category_id IS NULL AND t.category IS NOT NULL AND TRIM(t.category) <> '';
