-- Migration 143: Cofre de credenciais (Acessos)
-- Cria a tabela system_accesses para armazenar logins/senhas de sistemas externos.
-- As senhas são armazenadas criptografadas (AES-256-CBC via PHP); nunca em texto puro.
-- Isolamento por super_admin: cada registro pertence ao usuário que o criou (created_by).
-- Rode manualmente no banco (não há runner automático).

CREATE TABLE IF NOT EXISTS `system_accesses` (
    `id`            INT            NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(120)   NOT NULL,
    `category`      VARCHAR(60)    NOT NULL DEFAULT 'Geral',
    `url`           VARCHAR(255)   NULL     DEFAULT NULL,
    `username`      VARCHAR(150)   NULL     DEFAULT NULL,
    `password_enc`  TEXT           NULL     DEFAULT NULL   COMMENT 'Senha criptografada (AES-256-CBC)',
    `notes`         TEXT           NULL     DEFAULT NULL,
    `company_id`    INT            NULL     DEFAULT NULL   COMMENT 'Empresa relacionada (opcional)',
    `created_by`    INT            NOT NULL                COMMENT 'FK users.id — define o isolamento entre super_admins',
    `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sa_created_by`  (`created_by`),
    KEY `idx_sa_category`    (`category`),
    KEY `idx_sa_company_id`  (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
