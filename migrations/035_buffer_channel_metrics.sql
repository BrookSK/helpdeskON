-- Migration 035: Métricas agregadas por canal do Buffer (últimos N dias)
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS buffer_channel_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id VARCHAR(64) NOT NULL,
    metric_type VARCHAR(40) NOT NULL COMMENT 'reactions, comments, impressions, reach, views, engagementRate, saves, follows, postCount...',
    metric_name VARCHAR(80) NULL,
    metric_value DOUBLE NOT NULL DEFAULT 0,
    metric_unit VARCHAR(20) NULL,
    period_days INT NOT NULL DEFAULT 30,
    metrics_updated_at DATETIME NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_channel_metric (channel_id, metric_type, period_days),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
