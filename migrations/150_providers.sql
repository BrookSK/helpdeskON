-- Migration 150: Contratação de prestadores (Fase 8)
-- Reaproveita a lógica da esteira comercial para o fluxo INTERNO de contratar
-- prestadores: cadastro, tipo de contratação (CLT/PJ/freelancer), valores,
-- jornada/modelo, escopo e o que NÃO faz parte, estados do ciclo (prospecto ->
-- proposta -> contrato -> ativo -> encerrado), documentos, acessos concedidos e
-- o checklist de REVOGAÇÃO de acessos no encerramento (mesmo dia).
-- Tabelas novas (CREATE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS providers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    email           VARCHAR(191) NULL,
    phone           VARCHAR(40) NULL,
    document        VARCHAR(40) NULL COMMENT 'CPF/CNPJ',
    role_title      VARCHAR(160) NULL COMMENT 'Função (ex.: Dev PHP pleno)',
    engagement_type ENUM('clt','pj','freelancer','estagio','outro') NOT NULL DEFAULT 'pj',
    work_model      ENUM('remoto','hibrido','presencial') NULL,
    pay_type        ENUM('mensal','hora','projeto') NULL,
    pay_amount      DECIMAL(12,2) NULL,
    workload        VARCHAR(120) NULL COMMENT 'Jornada (ex.: 40h/semana)',
    start_date      DATE NULL,
    end_date        DATE NULL,
    scope           TEXT NULL COMMENT 'O que faz parte',
    out_of_scope    TEXT NULL COMMENT 'O que NÃO faz parte',
    status          ENUM('prospect','proposal','contract','active','terminated','cancelled') NOT NULL DEFAULT 'prospect',
    user_id         INT NULL COMMENT 'users.id criado para o prestador (acesso)',
    termination_reason TEXT NULL,
    terminated_at   DATETIME NULL,
    notes           TEXT NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acessos concedidos ao prestador (GitHub, Helpdesk, e-mail, servidor, etc.).
-- revoked_at marca a revogação; o encerramento exige revogar todos.
CREATE TABLE IF NOT EXISTS provider_accesses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    access_label    VARCHAR(160) NOT NULL COMMENT 'GitHub, Helpdesk, E-mail, Servidor...',
    details         VARCHAR(300) NULL,
    granted_at      DATETIME NULL,
    revoked_at      DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_paccess_provider (provider_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos do prestador (contrato CLT/PJ, NDA, etc.).
CREATE TABLE IF NOT EXISTS provider_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    doc_label       VARCHAR(160) NOT NULL,
    file_path       VARCHAR(500) NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pdoc_provider (provider_id),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico do prestador.
CREATE TABLE IF NOT EXISTS provider_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT NOT NULL,
    user_id         INT NULL,
    event_type      VARCHAR(50) NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pevent_provider (provider_id, created_at),
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
