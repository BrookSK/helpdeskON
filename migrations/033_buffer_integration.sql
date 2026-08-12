-- Migration 033: Integração com Buffer (agendamento e métricas de redes sociais)
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Canais (perfis sociais) conectados no Buffer, cacheados localmente para seleção
CREATE TABLE IF NOT EXISTS buffer_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id VARCHAR(64) NOT NULL COMMENT 'ID do canal no Buffer',
    organization_id VARCHAR(64) NULL,
    name VARCHAR(150) NULL,
    service VARCHAR(50) NULL COMMENT 'instagram, facebook, linkedin, etc',
    avatar VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo entre demandas de marketing e posts criados no Buffer
CREATE TABLE IF NOT EXISTS buffer_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marketing_item_id INT NULL COMMENT 'Demanda de marketing de origem (opcional)',
    buffer_post_id VARCHAR(64) NOT NULL COMMENT 'ID do post no Buffer',
    channel_id VARCHAR(64) NOT NULL,
    service VARCHAR(50) NULL,
    text LONGTEXT NULL,
    status VARCHAR(30) NULL COMMENT 'scheduled, sent, draft, error',
    due_at DATETIME NULL,
    sent_at DATETIME NULL,
    external_link VARCHAR(500) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_buffer_post (buffer_post_id),
    FOREIGN KEY (marketing_item_id) REFERENCES marketing_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métricas coletadas por post (snapshot mais recente por tipo de métrica)
CREATE TABLE IF NOT EXISTS buffer_post_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buffer_post_id VARCHAR(64) NOT NULL,
    metric_type VARCHAR(40) NOT NULL COMMENT 'reactions, comments, impressions, reach, views, engagementRate...',
    metric_name VARCHAR(80) NULL,
    metric_value DOUBLE NOT NULL DEFAULT 0,
    metric_unit VARCHAR(20) NULL COMMENT 'count ou percentage',
    metrics_updated_at DATETIME NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_post_metric (buffer_post_id, metric_type),
    INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
