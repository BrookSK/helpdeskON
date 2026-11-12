-- Migration 167b: aplica a 167 de forma IDEMPOTENTE (segura para rodar quando
-- parte já foi aplicada, ex.: produção onde as tabelas já existem mas faltam
-- as colunas account_id/category_id). Pode rodar no phpMyAdmin (aba SQL).
--
-- Não usa ALTER ... ADD COLUMN direto (que falha se a coluna já existe).
-- Em vez disso, usa procedures temporárias que só adicionam quando falta.
-- Ao final, as procedures são removidas.

-- 1) Tabelas (CREATE IF NOT EXISTS já é idempotente).
CREATE TABLE IF NOT EXISTS fin_bank_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    bank            VARCHAR(120) NULL,
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fbank_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    kind        ENUM('ganho','gasto','ambos') NOT NULL DEFAULT 'ambos',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fcat_name_kind (name, kind),
    KEY idx_fcat_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Colunas + FKs via procedure (só adiciona o que falta).
DROP PROCEDURE IF EXISTS _fin_v3_patch;
DELIMITER //
CREATE PROCEDURE _fin_v3_patch()
BEGIN
    DECLARE db VARCHAR(64);
    SET db = DATABASE();

    -- fin_credit_cards.account_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_credit_cards' AND COLUMN_NAME = 'account_id') THEN
        ALTER TABLE fin_credit_cards ADD COLUMN account_id INT NULL AFTER due_day;
        ALTER TABLE fin_credit_cards ADD KEY idx_fcard_account (account_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_credit_cards' AND CONSTRAINT_NAME = 'fk_fcard_account') THEN
        ALTER TABLE fin_credit_cards ADD CONSTRAINT fk_fcard_account
            FOREIGN KEY (account_id) REFERENCES fin_bank_accounts(id) ON DELETE SET NULL;
    END IF;

    -- fin_transactions.account_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_transactions' AND COLUMN_NAME = 'account_id') THEN
        ALTER TABLE fin_transactions ADD COLUMN account_id INT NULL AFTER card_id;
        ALTER TABLE fin_transactions ADD KEY idx_fint_account (account_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_transactions' AND CONSTRAINT_NAME = 'fk_fint_account') THEN
        ALTER TABLE fin_transactions ADD CONSTRAINT fk_fint_account
            FOREIGN KEY (account_id) REFERENCES fin_bank_accounts(id) ON DELETE SET NULL;
    END IF;

    -- fin_transactions.category_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_transactions' AND COLUMN_NAME = 'category_id') THEN
        ALTER TABLE fin_transactions ADD COLUMN category_id INT NULL AFTER category;
        ALTER TABLE fin_transactions ADD KEY idx_fint_category (category_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = db AND TABLE_NAME = 'fin_transactions' AND CONSTRAINT_NAME = 'fk_fint_category') THEN
        ALTER TABLE fin_transactions ADD CONSTRAINT fk_fint_category
            FOREIGN KEY (category_id) REFERENCES fin_categories(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;

CALL _fin_v3_patch();
DROP PROCEDURE IF EXISTS _fin_v3_patch;

-- 3) Semeia categorias a partir dos textos existentes (idempotente) e liga.
INSERT IGNORE INTO fin_categories (name, kind, active)
SELECT DISTINCT TRIM(category), 'ambos', 1
FROM fin_transactions
WHERE category IS NOT NULL AND TRIM(category) <> '';

UPDATE fin_transactions t
JOIN fin_categories c ON c.name = TRIM(t.category) AND c.kind = 'ambos'
SET t.category_id = c.id
WHERE t.category_id IS NULL AND t.category IS NOT NULL AND TRIM(t.category) <> '';

-- Conferência:
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_credit_cards' AND COLUMN_NAME='account_id')  AS cc_account_id,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_transactions' AND COLUMN_NAME='account_id')  AS tx_account_id,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_transactions' AND COLUMN_NAME='category_id') AS tx_category_id;
