-- Migration 152: Controle de prazo do RDO (Relatório Diário).
--
-- Acrescenta à tabela daily_reports:
--   submitted_at  → momento exato do primeiro preenchimento (detecta tardio).
--   is_locked     → 1 = bloqueado por prazo vencido ou manualmente.
--   lock_reason   → motivo do bloqueio ('deadline' | 'manual').
--   review_status → sinaliza se há pendência de revisão para o admin.
--
-- Insere também a configuração rdo_deadline_time na tabela settings (padrão 19:00:00).
-- O valor é lido por RdoRules::getDeadline() e pode ser alterado na tela de Settings.
--
-- Execute manualmente no MySQL após 137_daily_reports_company_comment.sql.

USE helpdesk_on;

ALTER TABLE daily_reports
    ADD COLUMN submitted_at TIMESTAMP NULL COMMENT 'Momento do primeiro preenchimento'
        AFTER updated_at,
    ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = bloqueado por prazo ou manual'
        AFTER submitted_at,
    ADD COLUMN lock_reason ENUM('deadline','manual') NULL COMMENT 'Motivo do bloqueio'
        AFTER is_locked,
    ADD COLUMN review_status ENUM('none','pending_review') NOT NULL DEFAULT 'none'
        COMMENT 'Pendência de revisão para o administrador'
        AFTER lock_reason;

ALTER TABLE daily_reports
    ADD KEY idx_review_status (review_status),
    ADD KEY idx_is_locked (is_locked);

-- Configuração do horário limite (padrão 19:00:00, sobrescrito via Settings).
INSERT IGNORE INTO settings (setting_key, setting_value)
VALUES ('rdo_deadline_time', '19:00:00');
