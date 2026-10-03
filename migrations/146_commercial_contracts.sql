-- Migration 146: Modelos de contrato + Contratos + assinatura ClickSign (Fase 4)
-- Fluxo: proposta aceita -> contrato (a partir de um modelo, editável) -> cliente
-- APROVA pela página pública (ou pede ajuste com motivo) -> envia para assinatura
-- na ClickSign -> webhook confirma a assinatura -> status 'signed'.
-- Tabelas novas (CREATE IF NOT EXISTS, idempotente).

-- Modelos de contrato reutilizáveis (corpo HTML/Markdown editável).
CREATE TABLE IF NOT EXISTS contract_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    body        LONGTEXT NULL COMMENT 'Corpo do modelo (placeholders permitidos)',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ctpl_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contratos. Nascem de uma proposta aceita; corpo editável por cliente.
-- Estados: draft -> client_review -> approved -> awaiting_signature -> signed
--          (rejeições: client_rejected volta p/ draft; cancelled encerra).
CREATE TABLE IF NOT EXISTS contracts (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    public_token       VARCHAR(64) NOT NULL COMMENT 'Link público ao cliente',
    title              VARCHAR(200) NOT NULL,
    proposal_id        INT NULL COMMENT 'Proposta que originou',
    template_id        INT NULL COMMENT 'Modelo usado',
    contact_id         INT NULL COMMENT 'Lead do CRM (whatsapp_contacts)',
    company_id         INT NULL COMMENT 'Empresa (cliente)',
    client_name        VARCHAR(200) NULL,
    client_email       VARCHAR(191) NULL,
    client_phone       VARCHAR(40) NULL,
    body               LONGTEXT NULL COMMENT 'Corpo final do contrato (editável)',
    status             ENUM('draft','client_review','approved','awaiting_signature','signed','client_rejected','cancelled') NOT NULL DEFAULT 'draft',
    reject_reason      TEXT NULL COMMENT 'Motivo do cliente ao pedir ajuste',
    -- ClickSign (preenchidos ao enviar para assinatura / pelo webhook):
    clicksign_doc_key  VARCHAR(100) NULL COMMENT 'Chave do documento na ClickSign',
    clicksign_signer_key VARCHAR(100) NULL COMMENT 'Chave do signatário',
    clicksign_request_key VARCHAR(100) NULL COMMENT 'request_signature_key (widget)',
    signed_at          DATETIME NULL,
    sent_signature_at  DATETIME NULL,
    approved_at        DATETIME NULL,
    created_by         INT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contract_token (public_token),
    KEY idx_contract_status (status),
    KEY idx_contract_proposal (proposal_id),
    KEY idx_contract_company (company_id),
    KEY idx_contract_cskey (clicksign_doc_key),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico/auditoria do contrato.
CREATE TABLE IF NOT EXISTS contract_events (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    contract_id  INT NOT NULL,
    user_id      INT NULL COMMENT 'NULL = cliente/externo/webhook',
    event_type   VARCHAR(40) NOT NULL,
    description  TEXT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cevent_contract (contract_id, created_at),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
