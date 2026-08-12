-- Migration 037: Integração direta com Meta (Instagram/Facebook) e LinkedIn
-- Traz seguidores, analytics de conta e publicações com interações (curtidas, comentários, etc.)
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Contas conectadas diretamente nas redes, com cache de métricas de conta
CREATE TABLE IF NOT EXISTS social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL COMMENT 'meta_instagram, facebook_page, linkedin_org',
    display_name VARCHAR(150) NULL,
    username VARCHAR(150) NULL,
    avatar VARCHAR(500) NULL,
    external_id VARCHAR(120) NOT NULL COMMENT 'IG user id, FB page id ou LinkedIn org id',
    access_token TEXT NULL COMMENT 'Token específico da conta (opcional; senão usa o global)',
    followers INT NULL,
    follows INT NULL COMMENT 'Quem a conta segue (IG)',
    media_count INT NULL,
    reach INT NULL,
    impressions INT NULL,
    profile_views INT NULL,
    total_likes BIGINT NULL,
    total_comments BIGINT NULL,
    total_shares BIGINT NULL,
    engagement_rate DECIMAL(6,2) NULL,
    extra_json LONGTEXT NULL,
    metrics_updated_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_provider_external (provider, external_id),
    INDEX idx_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publicações das contas, com interações
CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    external_post_id VARCHAR(190) NOT NULL,
    post_type VARCHAR(40) NULL COMMENT 'IMAGE, VIDEO, CAROUSEL_ALBUM, REELS, article, ugcPost...',
    caption LONGTEXT NULL,
    permalink VARCHAR(500) NULL,
    thumbnail VARCHAR(500) NULL,
    published_at DATETIME NULL,
    likes INT NULL,
    comments INT NULL,
    shares INT NULL,
    saved INT NULL,
    reach INT NULL,
    impressions INT NULL,
    video_views INT NULL,
    engagement INT NULL,
    extra_json LONGTEXT NULL,
    metrics_updated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_provider_post (provider, external_post_id),
    INDEX idx_account (account_id),
    INDEX idx_published (published_at),
    FOREIGN KEY (account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
