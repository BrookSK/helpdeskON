-- Migration 069: limite diário de créditos Apollo por usuário comercial
-- e registro de consumo diário (reset automático no dia seguinte).

-- Limite diário de créditos Apollo que o usuário pode consumir (0 = sem limite).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'apollo_daily_credits');
SET @s := IF(@c = 0, 'ALTER TABLE users ADD COLUMN apollo_daily_credits INT NOT NULL DEFAULT 0 COMMENT ''0 = sem limite'' AFTER commission_closing_percent', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Consumo diário de créditos Apollo por usuário (1 linha por usuário/dia).
CREATE TABLE IF NOT EXISTS apollo_credit_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    credits_used INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_day (user_id, usage_date),
    KEY idx_date (usage_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
