-- Migration 066: Templates de mensagem (e-mail e WhatsApp) com variáveis.
-- Usados nas sequências e nos disparos manuais da prospecção.

CREATE TABLE IF NOT EXISTS message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('email','whatsapp') NOT NULL DEFAULT 'email',
    name VARCHAR(150) NOT NULL,
    subject VARCHAR(300) DEFAULT NULL COMMENT 'usado apenas em e-mail',
    body MEDIUMTEXT NOT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_channel (channel),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
