-- Migration 142: Escopo técnico aprovável + status de aprovação de escopo +
-- régua de homologação (48h) + previsão de publicação.
--
-- Fluxo da Nova Demanda do cliente:
--   open -> in_progress (análise/estimativa) -> aguardando_aprovacao_escopo
--   -> (cliente aprova) in_progress ... em_homologacao -> aprovado_producao -> completed
--   -> (cliente recusa o escopo) volta para in_progress com motivo registrado
--
-- O status 'aguardando_aprovacao_escopo' é novo e precisa ser adicionado ao ENUM
-- de tickets E planning_cards (a coluna status é ENUM no MySQL; ver migration 012).
--
-- Idempotente: segue o padrão PREPARE/EXECUTE da migration 126 (sem DELIMITER/
-- procedure, para funcionar em qualquer forma de aplicação — mysql client ou PDO).

-- ── 1) Novo status no ENUM (tickets) ────────────────────────────────────────
ALTER TABLE tickets MODIFY COLUMN status ENUM(
    'open', 'in_progress', 'aguardando_aprovacao_escopo', 'em_revisao_interna',
    'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed',
    'denied', 'archived'
) NOT NULL DEFAULT 'open';

-- ── 2) Novo status no ENUM (planning_cards) ──────────────────────────────────
ALTER TABLE planning_cards MODIFY COLUMN status ENUM(
    'open', 'in_progress', 'aguardando_aprovacao_escopo', 'em_revisao_interna',
    'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed',
    'denied', 'archived'
) NOT NULL DEFAULT 'open';

-- ── 3) Campos de escopo técnico / homologação (idempotentes) ─────────────────
-- Escopo técnico: o que será feito, o que não será, como será executado, estimativa.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_incluido');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_incluido TEXT NULL COMMENT 'O que SERÁ desenvolvido (dentro do escopo)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_excluido');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_excluido TEXT NULL COMMENT 'O que NÃO será desenvolvido (fora do escopo)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_execucao');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_execucao TEXT NULL COMMENT 'Como a demanda será executada'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'estimativa_dias');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN estimativa_dias INT NULL COMMENT 'Estimativa de prazo em dias (escopo técnico)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_submitted_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_submitted_at TIMESTAMP NULL COMMENT 'Quando o escopo foi enviado ao cliente para aprovação'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_approved_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_approved_at TIMESTAMP NULL COMMENT 'Quando o cliente aprovou o escopo'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_rejected_reason');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_rejected_reason TEXT NULL COMMENT 'Motivo da recusa do escopo pelo cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Previsão de publicação em produção.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'previsao_publicacao');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN previsao_publicacao DATE NULL COMMENT 'Previsão de publicação em produção'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Régua de homologação (48h): início da janela + instantes dos 3 contatos +
-- liberação automática + motivo de recusa.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_started_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_started_at TIMESTAMP NULL COMMENT 'Quando a demanda entrou em homologação (início da janela de 48h)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact1_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact1_at TIMESTAMP NULL COMMENT '1o contato: entrega disponivel para homologacao'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact2_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact2_at TIMESTAMP NULL COMMENT '2o contato: 24h sem retorno'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact3_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact3_at TIMESTAMP NULL COMMENT '3o contato: ~42h, publicacao em 6h'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_auto_released_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_auto_released_at TIMESTAMP NULL COMMENT 'Liberacao automatica para producao apos 48h sem manifestacao'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_denied_reason');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_denied_reason TEXT NULL COMMENT 'Motivo da recusa na homologacao pelo cliente'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
