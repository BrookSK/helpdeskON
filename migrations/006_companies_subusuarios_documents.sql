-- Migration 006: Empresas, sub-usuários e documentos compartilhados
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Tabela de empresas/contas
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    document VARCHAR(30) NULL COMMENT 'CNPJ ou CPF',
    phone VARCHAR(20) NULL,
    email VARCHAR(191) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campos na tabela users para vincular empresa e hierarquia
ALTER TABLE users ADD COLUMN company_id INT NULL AFTER role;
ALTER TABLE users ADD COLUMN parent_user_id INT NULL AFTER company_id;
ALTER TABLE users ADD COLUMN is_company_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_user_id;

ALTER TABLE users ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;
ALTER TABLE users ADD FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Tabela de documentos compartilhados (aba de documentos)
CREATE TABLE IF NOT EXISTS shared_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    user_id INT NOT NULL COMMENT 'Quem fez upload',
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT NULL,
    visibility ENUM('company', 'team', 'all') NOT NULL DEFAULT 'all' COMMENT 'company=só empresa, team=só equipe interna, all=todos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
