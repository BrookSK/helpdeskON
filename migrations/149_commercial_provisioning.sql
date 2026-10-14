-- Migration 149: Provisionamento automático (Fase 7)
-- Fora da esteira (CX): após o onboarding, provisiona a infra do cliente no LRV
-- Cloud (conta → VPS → banco → aplicação/deploy via Git → homologação).
-- A API do LRV Cloud ainda NÃO tem alguns endpoints (criar conta, criar VPS,
-- criar app/deploy) — ver api-lrv-cloud-gaps.md. Por isso cada etapa guarda se é
-- 'auto' (API disponível) ou 'manual' (pendência rastreável até a API existir).
-- Tabelas novas (CREATE IF NOT EXISTS).

-- Processo de provisionamento (um por projeto/onboarding).
CREATE TABLE IF NOT EXISTS provisionings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id   INT NULL,
    project_id      INT NULL COMMENT 'finance_projects.id',
    company_id      INT NULL,
    pipeline_mode   ENUM('esteira_cx','fora_esteira') NOT NULL DEFAULT 'fora_esteira',
    status          ENUM('pending','in_progress','blocked','done','cancelled') NOT NULL DEFAULT 'pending',
    -- Referências externas (LRV Cloud / Git), preenchidas conforme avança.
    lrv_client_id   VARCHAR(64) NULL,
    lrv_vps_id      VARCHAR(64) NULL,
    lrv_app_id      VARCHAR(64) NULL,
    git_repo        VARCHAR(300) NULL,
    git_branch      VARCHAR(120) NULL,
    staging_url     VARCHAR(300) NULL,
    notes           TEXT NULL,
    created_by      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_prov_status (status),
    KEY idx_prov_onb (onboarding_id),
    KEY idx_prov_company (company_id),
    FOREIGN KEY (onboarding_id) REFERENCES onboardings(id) ON DELETE SET NULL,
    FOREIGN KEY (project_id) REFERENCES finance_projects(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapas do provisionamento (checklist). mode indica se é automatizável pela API
-- hoje (auto) ou se é pendência manual (manual) por falta de endpoint na API.
CREATE TABLE IF NOT EXISTS provisioning_steps (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provisioning_id INT NOT NULL,
    step_key        VARCHAR(60) NOT NULL COMMENT 'create_client, create_vps, create_database, create_app, deploy, staging, collect_credentials, deliver',
    title           VARCHAR(200) NOT NULL,
    mode            ENUM('auto','manual') NOT NULL DEFAULT 'manual' COMMENT 'auto=API disponivel / manual=pendencia (gap de API)',
    status          ENUM('pending','in_progress','done','blocked','skipped') NOT NULL DEFAULT 'pending',
    required        TINYINT(1) NOT NULL DEFAULT 1,
    responsible_id  INT NULL,
    external_ref    VARCHAR(120) NULL COMMENT 'id/retorno da API quando auto',
    blocked_reason  TEXT NULL,
    notes           TEXT NULL,
    position        INT NOT NULL DEFAULT 0,
    done_at         DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pstep_prov (provisioning_id, position),
    FOREIGN KEY (provisioning_id) REFERENCES provisionings(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico do provisionamento (inclui eventos recebidos por webhook do LRV Cloud).
CREATE TABLE IF NOT EXISTS provisioning_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    provisioning_id INT NOT NULL,
    user_id         INT NULL,
    event_type      VARCHAR(50) NOT NULL,
    description     TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pevent_prov (provisioning_id, created_at),
    FOREIGN KEY (provisioning_id) REFERENCES provisionings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
