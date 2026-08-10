-- Migration 029: Respostas rápidas do WhatsApp (atalhos "/comando")
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS whatsapp_quick_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shortcut VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_shortcut (shortcut),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
