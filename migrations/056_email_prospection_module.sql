-- Migration 056: Módulo de Prospecção por E-mail
-- Tabelas para contas SMTP, vinculação com usuários e histórico de envios.

USE helpdesk_on;

-- ============================================
-- Contas de e-mail cadastradas (SMTP)
-- ============================================
CREATE TABLE IF NOT EXISTS email_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    display_name VARCHAR(150) NULL COMMENT 'Nome de exibição no remetente',
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(500) NOT NULL COMMENT 'Senha criptografada',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vinculação: quais contas cada usuário pode usar
-- ============================================
CREATE TABLE IF NOT EXISTS email_account_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_account_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_account_user (email_account_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Histórico de e-mails enviados (prospecção)
-- ============================================
CREATE TABLE IF NOT EXISTS email_prospections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Quem enviou',
    email_account_id INT NOT NULL COMMENT 'Conta usada para envio',
    contact_id INT NULL COMMENT 'Lead/contato do CRM vinculado',
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(150) NULL,
    cc VARCHAR(500) NULL,
    bcc VARCHAR(500) NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL COMMENT 'Corpo HTML do e-mail',
    attachments_json TEXT NULL COMMENT 'JSON com paths dos anexos',
    status ENUM('sent','failed','draft') NOT NULL DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_email_prospections_user ON email_prospections(user_id, sent_at);
CREATE INDEX idx_email_prospections_contact ON email_prospections(contact_id);
CREATE INDEX idx_email_prospections_account ON email_prospections(email_account_id);
