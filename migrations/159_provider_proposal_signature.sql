-- Migration 159: Proposta por link + assinatura ClickSign + revisão de valor do
-- prestador, e tipo de documento (CLT vs PJ).
--
-- Complementa a Fase 8 (migration 150) para o prestador seguir o MESMO fluxo do
-- cliente: proposta enviada por link (aceitar/recusar com motivo), geração de
-- contrato e assinatura via ClickSign, e revisão de valor com aprovação do gestor.
--
-- Idempotente (PREPARE/EXECUTE, padrão das migrations 151/152/157/158). Execute
-- manualmente no MySQL com o BANCO ALVO JÁ SELECIONADO (local: helpdesk_on;
-- beta: helpdesk_on_beta). As verificações usam DATABASE() (o banco atual), por
-- isso NÃO há "USE" fixo aqui: assim a mesma migration roda em qualquer ambiente
-- sem erro de permissão (ex.: usuário do beta não acessa 'helpdesk_on').

-- ── providers: campos de proposta/assinatura + prazo e forma de pagamento ────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'public_token');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN public_token VARCHAR(64) NULL COMMENT 'Token do link publico da proposta do prestador'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND INDEX_NAME = 'uq_provider_token');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD UNIQUE KEY uq_provider_token (public_token)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'pay_term');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN pay_term VARCHAR(160) NULL COMMENT 'Prazo/condicoes (ex.: 30 dias, por entrega)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'payment_method');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN payment_method VARCHAR(60) NULL COMMENT 'Forma de pagamento (pix, transferencia, boleto...)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'availability');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN availability VARCHAR(160) NULL COMMENT 'Disponibilidade (ex.: imediata, 15 dias)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_sent_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_sent_at DATETIME NULL COMMENT 'Quando a proposta foi enviada ao prestador'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_responded_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_responded_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_reject_reason');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_reject_reason TEXT NULL COMMENT 'Motivo da recusa da proposta pelo prestador'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_accepted_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_accepted_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'clicksign_doc_key');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN clicksign_doc_key VARCHAR(120) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'clicksign_request_key');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN clicksign_request_key VARCHAR(120) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'signed_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN signed_at DATETIME NULL COMMENT 'Quando o contrato do prestador foi assinado'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND INDEX_NAME = 'idx_provider_cskey');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD KEY idx_provider_cskey (clicksign_doc_key)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── provider_documents: tipo de documento (vinculo CLT/PJ e checklist) ───────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_documents' AND COLUMN_NAME = 'doc_type');
SET @s := IF(@c = 0, "ALTER TABLE provider_documents ADD COLUMN doc_type VARCHAR(60) NULL COMMENT 'Chave do documento no checklist (ex.: rg_cpf, cnpj, contrato_social)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ── provider_revisions: revisao de valor com aprovacao do gestor ─────────────
CREATE TABLE IF NOT EXISTS provider_revisions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    old_pay_type    ENUM('mensal','hora','projeto') NULL,
    old_pay_amount  DECIMAL(12,2) NULL,
    new_pay_type    ENUM('mensal','hora','projeto') NULL,
    new_pay_amount  DECIMAL(12,2) NULL,
    reason          TEXT NULL COMMENT 'Justificativa do ajuste',
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_by    INT NULL,
    reviewed_by     INT NULL COMMENT 'Gestor que aprovou/recusou',
    reviewed_at     DATETIME NULL,
    review_notes    TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prev_provider (provider_id),
    KEY idx_prev_status (status),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
