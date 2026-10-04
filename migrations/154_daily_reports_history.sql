-- Migration 154: Histórico de alterações (audit trail) do RDO.
--
-- daily_report_history registra cada evento relevante sobre um relatório:
--   created   → primeira criação.
--   updated   → edição direta (dentro do prazo).
--   submitted → edição enviada para aprovação (edit_request criado).
--   approved  → aprovação pelo admin (draft aplicado ao relatório).
--   rejected  → rejeição pelo admin (draft descartado).
--   locked    → bloqueio por prazo vencido.
--   unlocked  → liberação pelo admin após unlock_request.
--
-- snapshot_json: estado COMPLETO dos campos editáveis do relatório no momento
--   da ação (title, activities, occurrences, pending_tasks, next_day_plan,
--   status, company_id). Permite reconstruir qualquer versão.
--
-- Execute manualmente no MySQL após 153_daily_reports_reviews.sql.

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS daily_report_history (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    report_id     INT NOT NULL,
    changed_by    INT NOT NULL                COMMENT 'Usuário que realizou a ação',
    changed_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action        ENUM('created','updated','submitted','approved','rejected','locked','unlocked')
                  NOT NULL,
    snapshot_json LONGTEXT NULL               COMMENT 'Estado completo dos campos editáveis no momento da ação',
    review_id     INT NULL                    COMMENT 'FK daily_report_reviews (quando aplicável)',
    notes         TEXT NULL,
    KEY idx_report    (report_id),
    KEY idx_action    (action),
    FOREIGN KEY (report_id)  REFERENCES daily_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (review_id)  REFERENCES daily_report_reviews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
