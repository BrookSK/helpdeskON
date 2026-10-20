-- Migration 163: condições comerciais da proposta (descontos, recorrência,
-- renovação e plano aceito).
--
-- Evolui a proposta-documento (migration 162) com:
--  - descontos comerciais estruturados (JSON) aplicáveis a pontual, mensal e total;
--  - recorrência e renovação ESTRUTURADAS por item (não só texto livre);
--  - registro do plano escolhido pelo cliente no aceite.
--
-- Idempotente (checa information_schema antes de cada ALTER). Sem "USE": rode
-- com o banco alvo já selecionado. Retrocompatível: colunas NULL / com default
-- seguro, de modo que propostas antigas continuam válidas.

-- ============================================================
-- proposals: descontos comerciais e plano aceito
-- ============================================================

-- JSON com a lista de descontos comerciais da proposta. Cada desconto:
--   { "scope": "once|monthly|total", "type": "percent|fixed|schedule",
--     "value": <numero>, "label": <texto>,
--     "months": [ { "month": 1, "type": "percent|fixed", "value": <numero> }, ... ] }
-- 'months' só é usado quando type = 'schedule' (desconto por período na mensalidade).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'discounts');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN discounts LONGTEXT NULL COMMENT ''JSON dos descontos comerciais (ver ProposalRules)'' AFTER content',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Plano escolhido pelo cliente no momento do aceite (ex.: plan_3/plan_6/plan_12),
-- quando a proposta apresenta mais de uma opção. NULL quando não se aplica.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals' AND COLUMN_NAME = 'accepted_plan');
SET @s := IF(@c = 0,
    'ALTER TABLE proposals ADD COLUMN accepted_plan VARCHAR(20) NULL COMMENT ''Plano aceito pelo cliente (plan_3/plan_6/plan_12)'' AFTER reject_reason',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- proposal_items: recorrência e renovação estruturadas
-- ============================================================

-- Renovação do item: none (não renova), auto (renovação automática), manual.
-- Default 'none' preserva o comportamento de propostas antigas.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'renewal');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN renewal VARCHAR(10) NOT NULL DEFAULT ''none'' COMMENT ''none|auto|manual'' AFTER billing',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Periodicidade da recorrência (ex.: monthly, yearly). NULL para itens pontuais.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_items' AND COLUMN_NAME = 'recurrence_period');
SET @s := IF(@c = 0,
    'ALTER TABLE proposal_items ADD COLUMN recurrence_period VARCHAR(10) NULL COMMENT ''monthly|yearly (recorrência); NULL = pontual'' AFTER renewal',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ============================================================
-- service_catalog: espelha recorrência/renovação para alimentar os itens
-- ============================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'renewal');
SET @s := IF(@c = 0,
    'ALTER TABLE service_catalog ADD COLUMN renewal VARCHAR(10) NOT NULL DEFAULT ''none'' COMMENT ''none|auto|manual'' AFTER billing',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_catalog' AND COLUMN_NAME = 'recurrence_period');
SET @s := IF(@c = 0,
    'ALTER TABLE service_catalog ADD COLUMN recurrence_period VARCHAR(10) NULL COMMENT ''monthly|yearly; NULL = pontual'' AFTER renewal',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
