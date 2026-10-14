-- Migration 155: Signatários da empresa (lado contratada) para contratos.
-- Permite cadastrar vários representantes/assinantes da própria empresa e, ao
-- enviar o contrato para assinatura na ClickSign, escolher qual(is) assina(m)
-- junto com o cliente. Tabela nova (CREATE IF NOT EXISTS, idempotente).

CREATE TABLE IF NOT EXISTS contract_signers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    email       VARCHAR(191) NOT NULL,
    phone       VARCHAR(40) NULL,
    role_label  VARCHAR(120) NULL COMMENT 'Cargo/representacao (ex.: Socio-administrador)',
    is_default  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pre-selecionado ao enviar p/ assinatura',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_csigner_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
