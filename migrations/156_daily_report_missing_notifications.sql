-- Migration 156: Controle de idempotência para o job de relatórios faltantes.
--
-- daily_report_missing_notifications registra cada vez que o cron
-- checkMissingReports notificou um profissional por não ter preenchido
-- o relatório de uma determinada data.
--
-- O job filtra WHERE NOT EXISTS (...) nesta tabela para não renotificar o
-- mesmo usuário/data em execuções subsequentes do cron no mesmo dia.
--
-- Execute manualmente no MySQL após 155_daily_reports_extra_fields.sql.

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS daily_report_missing_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL          COMMENT 'Profissional que não preencheu',
    report_date DATE NOT NULL         COMMENT 'Data do relatório ausente',
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_date (user_id, report_date),
    KEY idx_report_date (report_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
