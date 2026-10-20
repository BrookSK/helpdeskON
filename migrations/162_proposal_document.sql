-- Migration 162: documento da proposta comercial (PDF).
-- Separa o texto fixo (quem somos, o que fazemos, clientes, como fazemos,
-- condições, notas) do que varia por proposta (entendimento do negócio,
-- serviços, valores, cronograma, projeção e retorno).
-- Idempotente. Sem "USE": rode com o banco alvo já selecionado.

CREATE TABLE IF NOT EXISTS proposal_blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    block_key   VARCHAR(40) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    body        MEDIUMTEXT NULL,
    position    INT NOT NULL DEFAULT 0 COMMENT 'Ordem no PDF (fixa por block_key)',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_proposal_block_key (block_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Período geral de desenvolvimento e cópia do texto fixo no momento do save,
-- para o link do cliente não mudar se o texto padrão for editado depois.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'dev_start');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN dev_start DATE NULL COMMENT ''Início do período de desenvolvimento'' AFTER validity_date',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'dev_end');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN dev_end DATE NULL COMMENT ''Fim do período de desenvolvimento'' AFTER dev_start',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'fixed_snapshot');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN fixed_snapshot LONGTEXT NULL COMMENT ''JSON dos blocos fixos no último save'' AFTER observations',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Seções que mudam por cliente (entendimento do negócio, retorno, renovação,
-- resumo, condição de pagamento). JSON, ver ProposalRules::CONTENT_FIELDS.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'content');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN content LONGTEXT NULL COMMENT ''JSON das seções variáveis'' AFTER observations',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Cobrança pontual ou mensal (base da projeção de 12 meses).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'billing');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN billing VARCHAR(10) NOT NULL DEFAULT ''once'' COMMENT ''once = pontual; monthly = mensal'' AFTER amount',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'billing');
SET @s := IF(@c = 0,
    'ALTER TABLE service_catalog ADD COLUMN billing VARCHAR(10) NOT NULL DEFAULT ''once'' COMMENT ''once = pontual; monthly = mensal'' AFTER hourly_rate',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Quantidade e janela do serviço no cronograma.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'quantity');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 1 AFTER scope',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'schedule_start');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN schedule_start DATE NULL COMMENT ''Início deste serviço no cronograma'' AFTER is_hosting',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'schedule_end');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN schedule_end DATE NULL COMMENT ''Fim deste serviço no cronograma'' AFTER schedule_start',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
