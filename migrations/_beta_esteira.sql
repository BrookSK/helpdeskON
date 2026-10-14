-- ============================================================================
-- CONSOLIDADO PARA O BETA (helpdesk_on_beta) — migrations 152 a 159
-- ============================================================================
-- Por que este arquivo existe:
--   As migrations originais (152..159) trazem "USE helpdesk_on;" fixo no topo.
--   No beta o banco se chama helpdesk_on_beta e o usuário NÃO tem acesso a
--   'helpdesk_on' -> isso causa "#1044 Access denied ... to database 'helpdesk_on'".
--   Aqui NÃO há "USE": as verificações usam DATABASE() (o banco atualmente
--   selecionado). Então basta SELECIONAR o banco helpdesk_on_beta no phpMyAdmin
--   (barra lateral) e rodar este script inteiro.
--
-- É IDEMPOTENTE: pode rodar mais de uma vez sem erro (verifica antes de criar).
-- Cobre: 152 (RDO prazo/bloqueio + ticket escopo/homologacao), 153/154/155/156
--   (RDO reviews/history/campos/missing), 157 (entrega/garantia de projeto),
--   158 (repo git no provisionamento) e 159 (prestador: proposta/assinatura/
--   revisao de valor + doc_type).
-- ============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- 152a) daily_reports: prazo / bloqueio / revisao
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'submitted_at');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN submitted_at TIMESTAMP NULL COMMENT 'Momento do primeiro preenchimento' AFTER updated_at", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'is_locked');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = bloqueado por prazo ou manual' AFTER submitted_at", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'lock_reason');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN lock_reason ENUM('deadline','manual') NULL COMMENT 'Motivo do bloqueio' AFTER is_locked", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'review_status');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN review_status ENUM('none','pending_review') NOT NULL DEFAULT 'none' COMMENT 'Pendencia de revisao para o administrador' AFTER lock_reason", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND INDEX_NAME = 'idx_review_status');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD KEY idx_review_status (review_status)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND INDEX_NAME = 'idx_is_locked');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD KEY idx_is_locked (is_locked)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 155) daily_reports: campos extras do formulario (pending_tasks / next_day_plan)
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'pending_tasks');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN pending_tasks LONGTEXT NULL COMMENT 'O que ficou em andamento / pendente' AFTER occurrences", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_reports' AND COLUMN_NAME = 'next_day_plan');
SET @s := IF(@c = 0, "ALTER TABLE daily_reports ADD COLUMN next_day_plan LONGTEXT NULL COMMENT 'Plano / prioridades para o proximo dia util' AFTER pending_tasks", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 153) daily_report_reviews
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_report_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    report_id     INT NOT NULL,
    type          ENUM('late_fill','post_deadline','edit_request','unlock_request') NOT NULL,
    requested_by  INT NOT NULL,
    requested_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by   INT NULL,
    reviewed_at   TIMESTAMP NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    payload_json  LONGTEXT NULL,
    notes         TEXT NULL,
    KEY idx_report  (report_id),
    KEY idx_status  (status),
    KEY idx_type    (type),
    FOREIGN KEY (report_id)    REFERENCES daily_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 154) daily_report_history
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_report_history (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    report_id     INT NOT NULL,
    changed_by    INT NOT NULL,
    changed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action        ENUM('created','updated','submitted','approved','rejected','locked','unlocked') NOT NULL,
    snapshot_json LONGTEXT NULL,
    review_id     INT NULL,
    notes         TEXT NULL,
    KEY idx_report    (report_id),
    KEY idx_action    (action),
    FOREIGN KEY (report_id)  REFERENCES daily_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (review_id)  REFERENCES daily_report_reviews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 156) daily_report_missing_notifications
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS daily_report_missing_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    report_date DATE NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, report_date),
    KEY idx_report_date (report_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 152b) tickets: escopo tecnico + regua de homologacao
--  (o ENUM de status so e alterado se o novo valor ainda nao existir)
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%aguardando_aprovacao_escopo%');
SET @s := IF(@c = 0, "ALTER TABLE tickets MODIFY COLUMN status ENUM('open','in_progress','aguardando_aprovacao_escopo','em_revisao_interna','waiting_client','em_homologacao','aprovado_producao','completed','denied','archived') NOT NULL DEFAULT 'open'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_cards' AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%aguardando_aprovacao_escopo%');
SET @s := IF(@c = 0, "ALTER TABLE planning_cards MODIFY COLUMN status ENUM('open','in_progress','aguardando_aprovacao_escopo','em_revisao_interna','waiting_client','em_homologacao','aprovado_producao','completed','denied','archived') NOT NULL DEFAULT 'open'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_incluido');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_incluido TEXT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_excluido');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_excluido TEXT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'escopo_execucao');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN escopo_execucao TEXT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'estimativa_dias');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN estimativa_dias INT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_submitted_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_submitted_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_approved_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_approved_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'scope_rejected_reason');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN scope_rejected_reason TEXT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'previsao_publicacao');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN previsao_publicacao DATE NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_started_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_started_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact1_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact1_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact2_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact2_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_contact3_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_contact3_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_auto_released_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_auto_released_at TIMESTAMP NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'homolog_denied_reason');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN homolog_denied_reason TEXT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 157) projects: entrega / documentacao / aceite / garantia
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'published_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN published_at DATETIME NULL AFTER delivered_at", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'manual_url');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN manual_url VARCHAR(500) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'documentation_delivered_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN documentation_delivered_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'delivery_meeting_id');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN delivery_meeting_id INT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'delivery_meeting_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN delivery_meeting_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'acceptance_token');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN acceptance_token VARCHAR(64) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'uq_project_acceptance_token');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD UNIQUE KEY uq_project_acceptance_token (acceptance_token)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_accepted_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN client_accepted_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_accepted_by');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN client_accepted_by INT NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'warranty_warn_sent_at');
SET @s := IF(@c = 0, "ALTER TABLE projects ADD COLUMN warranty_warn_sent_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 158) provisionings: repositorio Git
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisionings' AND COLUMN_NAME = 'lrv_repo_id');
SET @s := IF(@c = 0, "ALTER TABLE provisionings ADD COLUMN lrv_repo_id VARCHAR(64) NULL AFTER lrv_client_id", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provisionings' AND COLUMN_NAME = 'repo_url');
SET @s := IF(@c = 0, "ALTER TABLE provisionings ADD COLUMN repo_url VARCHAR(300) NULL AFTER git_repo", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 159) providers: proposta por link / assinatura / prazo-forma + doc_type + revisoes
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'public_token');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN public_token VARCHAR(64) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND INDEX_NAME = 'uq_provider_token');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD UNIQUE KEY uq_provider_token (public_token)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'pay_term');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN pay_term VARCHAR(160) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'payment_method');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN payment_method VARCHAR(60) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'availability');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN availability VARCHAR(160) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_sent_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_sent_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_responded_at');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_responded_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'proposal_reject_reason');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN proposal_reject_reason TEXT NULL", 'SELECT 1');
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
SET @s := IF(@c = 0, "ALTER TABLE providers ADD COLUMN signed_at DATETIME NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND INDEX_NAME = 'idx_provider_cskey');
SET @s := IF(@c = 0, "ALTER TABLE providers ADD KEY idx_provider_cskey (clicksign_doc_key)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_documents' AND COLUMN_NAME = 'doc_type');
SET @s := IF(@c = 0, "ALTER TABLE provider_documents ADD COLUMN doc_type VARCHAR(60) NULL", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS provider_revisions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    old_pay_type    ENUM('mensal','hora','projeto') NULL,
    old_pay_amount  DECIMAL(12,2) NULL,
    new_pay_type    ENUM('mensal','hora','projeto') NULL,
    new_pay_amount  DECIMAL(12,2) NULL,
    reason          TEXT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    requested_by    INT NULL,
    reviewed_by     INT NULL,
    reviewed_at     DATETIME NULL,
    review_notes    TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prev_provider (provider_id),
    KEY idx_prev_status (status),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FIM. Rode com helpdesk_on_beta SELECIONADO no phpMyAdmin.


-- ─────────────────────────────────────────────────────────────────────────────
-- 142) agenda_meetings / video_rooms: gravação automática (trabalho da Julia)
-- ─────────────────────────────────────────────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agenda_meetings' AND COLUMN_NAME = 'auto_record');
SET @s := IF(@c = 0, "ALTER TABLE agenda_meetings ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Gravar automaticamente ao iniciar' AFTER notes", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video_rooms' AND COLUMN_NAME = 'auto_record');
SET @s := IF(@c = 0, "ALTER TABLE video_rooms ADD COLUMN auto_record TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Gravar automaticamente quando o host entra' AFTER allow_recording", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ─────────────────────────────────────────────────────────────────────────────
-- 143) system_accesses: cofre de credenciais / módulo Acessos (trabalho da Julia)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `system_accesses` (
    `id`            INT            NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(120)   NOT NULL,
    `category`      VARCHAR(60)    NOT NULL DEFAULT 'Geral',
    `url`           VARCHAR(255)   NULL     DEFAULT NULL,
    `username`      VARCHAR(150)   NULL     DEFAULT NULL,
    `password_enc`  TEXT           NULL     DEFAULT NULL   COMMENT 'Senha criptografada (AES-256-CBC)',
    `notes`         TEXT           NULL     DEFAULT NULL,
    `company_id`    INT            NULL     DEFAULT NULL,
    `created_by`    INT            NOT NULL,
    `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sa_created_by`  (`created_by`),
    KEY `idx_sa_category`    (`category`),
    KEY `idx_sa_company_id`  (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FIM DO CONSOLIDADO (esteira + trabalho da Julia). Rode com o banco da produção
-- SELECIONADO no phpMyAdmin. É idempotente: pode rodar mais de uma vez.
