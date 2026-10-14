-- Migration 153: Fila de pendências de revisão do RDO.
--
-- daily_report_reviews registra toda ocorrência que precisa de atenção do admin:
--   late_fill     → relatório preenchido após o horário limite do mesmo dia.
--   post_deadline → relatório preenchido em data posterior ao dia trabalhado.
--   edit_request  → usuário solicita alterar relatório de dia anterior (aguarda aprovação).
--   unlock_request→ usuário solicita desbloqueio para preencher relatório bloqueado por prazo.
--
-- O campo payload_json armazena o snapshot dos novos valores (para edit_request) ou
-- contexto adicional. Usar JSON em vez de tabela de campos evita uma JOIN extra.
--
-- Execute manualmente no MySQL após 152_daily_reports_deadline.sql.

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS daily_report_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    report_id     INT NOT NULL                COMMENT 'Relatório em questão',
    type          ENUM('late_fill','post_deadline','edit_request','unlock_request')
                  NOT NULL                    COMMENT 'Motivo da pendência',
    requested_by  INT NOT NULL                COMMENT 'Usuário que gerou a pendência',
    requested_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by   INT NULL                    COMMENT 'Admin que avaliou',
    reviewed_at   TIMESTAMP NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    payload_json  LONGTEXT NULL               COMMENT 'Snapshot dos novos dados (edit_request) ou contexto',
    notes         TEXT NULL                   COMMENT 'Observação do admin ao aprovar/recusar',
    KEY idx_report  (report_id),
    KEY idx_status  (status),
    KEY idx_type    (type),
    FOREIGN KEY (report_id)    REFERENCES daily_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
