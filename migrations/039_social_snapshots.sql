-- Migration 039: Snapshots históricos de métricas (Buffer + Meta/LinkedIn) para comparação
-- Salvos automaticamente ao sincronizar canais / atualizar métricas. Sem botão exclusivo.
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS social_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(20) NOT NULL COMMENT 'buffer ou direct (meta/linkedin)',
    provider VARCHAR(40) NULL COMMENT 'instagram, facebook, linkedin, tiktok...',
    entity_key VARCHAR(120) NOT NULL COMMENT 'channel_id (Buffer) ou external_id (direct)',
    account_label VARCHAR(150) NULL COMMENT 'Nome do canal/conta para leitura',
    snapshot_date DATE NOT NULL,
    followers INT NULL,
    reach BIGINT NULL,
    impressions BIGINT NULL,
    views BIGINT NULL,
    likes BIGINT NULL,
    comments BIGINT NULL,
    shares BIGINT NULL,
    saves BIGINT NULL,
    posts_count INT NULL,
    engagement_rate DECIMAL(8,2) NULL,
    extra_json LONGTEXT NULL COMMENT 'Demais métricas para comparação futura',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_snapshot (source, entity_key, snapshot_date),
    INDEX idx_entity (entity_key),
    INDEX idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
