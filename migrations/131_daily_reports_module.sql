-- Migration 131: Módulo RDO (Relatório Diário).
--
-- Registro e acompanhamento das atividades diárias de cada pessoa da equipe.
-- Visibilidade: cada um vê o seu; super_admin vê de todos; developer vê só o
-- seu (regra de escopo aplicada em RdoRules/DailyReport, não no schema).
--
-- Tabelas:
--   daily_reports              → o relatório em si (data, descrição das
--                                atividades, ocorrências, status, autor).
--   daily_report_attachments   → anexos (áudio/imagem/arquivo) do relatório.
--   daily_report_collaborators → colaboradores/prestadores envolvidos no dia.
--
-- Execute manualmente no MySQL.

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS daily_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Autor do relatório (dono)',
    report_date DATE NOT NULL COMMENT 'Dia a que o relatório se refere',
    title VARCHAR(255) NULL COMMENT 'Título/resumo opcional',
    activities LONGTEXT NULL COMMENT 'Descrição das atividades realizadas no dia',
    occurrences LONGTEXT NULL COMMENT 'Ocorrências/impedimentos, se houver',
    has_occurrence TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = registrou ocorrência',
    status ENUM('em_andamento','finalizado') NOT NULL DEFAULT 'em_andamento',
    transcription LONGTEXT NULL COMMENT 'Transcrição bruta do áudio (se gravado)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_date (user_id, report_date),
    KEY idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_report_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    user_id INT NULL COMMENT 'Quem anexou',
    file_name VARCHAR(255) NOT NULL COMMENT 'Nome original',
    file_path VARCHAR(500) NOT NULL COMMENT 'Caminho relativo em public/',
    file_type VARCHAR(120) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_report (report_id),
    FOREIGN KEY (report_id) REFERENCES daily_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_report_collaborators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    collaborator_name VARCHAR(200) NOT NULL COMMENT 'Nome do colaborador/prestador',
    kind ENUM('colaborador','prestador') NOT NULL DEFAULT 'colaborador',
    notes VARCHAR(255) NULL COMMENT 'Papel/observação (opcional)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_report (report_id),
    FOREIGN KEY (report_id) REFERENCES daily_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
