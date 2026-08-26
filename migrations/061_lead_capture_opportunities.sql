-- Migration 061: Módulo de Captação de Leads · Oportunidades (99Freelas)
-- Coleta via HTTP direto de projetos publicados, normaliza, deduplica e exibe.
-- Fonte única nesta entrega: freelas99.

-- ============================================
-- Oportunidades coletadas
-- ============================================
CREATE TABLE IF NOT EXISTS opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(30) NOT NULL DEFAULT 'freelas99',
    external_id VARCHAR(64) NOT NULL,
    canonical_url VARCHAR(500) NOT NULL,
    title VARCHAR(300) NOT NULL,
    description MEDIUMTEXT DEFAULT NULL,
    category VARCHAR(150) DEFAULT NULL,
    experience_level VARCHAR(60) DEFAULT NULL,
    skills JSON DEFAULT NULL,
    budget_min DECIMAL(12,2) DEFAULT NULL,
    budget_max DECIMAL(12,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT NULL,
    published_at DATETIME DEFAULT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    proposal_count INT DEFAULT NULL,
    interested_count INT DEFAULT NULL,
    client_name VARCHAR(190) DEFAULT NULL,
    client_rating VARCHAR(20) DEFAULT NULL,
    score INT DEFAULT NULL,
    status ENUM('nova','vista','ignorada','convertida') NOT NULL DEFAULT 'nova',
    matched_terms JSON DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    raw_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source_external (source, external_id),
    KEY idx_status_seen (status, first_seen_at DESC),
    KEY idx_score (score DESC),
    KEY idx_first_seen (first_seen_at DESC),
    FOREIGN KEY (lead_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Termos de busca monitorados
-- ============================================
CREATE TABLE IF NOT EXISTS search_terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(150) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_term (term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Configuração por fonte
-- ============================================
CREATE TABLE IF NOT EXISTS source_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(30) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    schedule_minutes INT NOT NULL DEFAULT 60,
    max_pages INT NOT NULL DEFAULT 2,
    collect_general TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Execuções de coleta
-- ============================================
CREATE TABLE IF NOT EXISTS collection_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trigger_type ENUM('manual','scheduled') NOT NULL DEFAULT 'manual',
    status ENUM('running','success','partial','failed') NOT NULL DEFAULT 'running',
    started_at DATETIME NOT NULL,
    finished_at DATETIME DEFAULT NULL,
    duration_ms INT DEFAULT NULL,
    terms_used JSON DEFAULT NULL,
    pages_fetched INT DEFAULT 0,
    cards_detected INT DEFAULT 0,
    projects_parsed INT DEFAULT 0,
    projects_found INT DEFAULT 0,
    projects_new INT DEFAULT 0,
    projects_known INT DEFAULT 0,
    http_errors INT DEFAULT 0,
    parser_errors INT DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_started (started_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Saúde da integração (uma linha por fonte)
-- ============================================
CREATE TABLE IF NOT EXISTS source_health (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(30) NOT NULL,
    last_run_at DATETIME DEFAULT NULL,
    last_success_at DATETIME DEFAULT NULL,
    last_failure_at DATETIME DEFAULT NULL,
    projects_found_last_run INT DEFAULT NULL,
    consecutive_failures INT NOT NULL DEFAULT 0,
    last_duration_ms INT DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    cards_detected_last_run INT DEFAULT NULL,
    projects_parsed_last_run INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Seeds
-- ============================================
INSERT IGNORE INTO source_settings (source, enabled, schedule_minutes, max_pages, collect_general)
VALUES ('freelas99', 1, 60, 2, 0);

INSERT IGNORE INTO search_terms (term, active) VALUES
('Automação', 1),
('Inteligência Artificial', 1),
('IA', 1),
('API', 1),
('SaaS', 1),
('CRM', 1),
('ERP', 1),
('Dashboard', 1),
('WhatsApp', 1),
('Integração', 1),
('Desenvolvimento de Software', 1),
('Desenvolvimento de Sistema', 1);

INSERT IGNORE INTO source_health (source) VALUES ('freelas99');
