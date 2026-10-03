-- Migration 145: Catálogo de serviços + Proposta/Orçamento (Fase 3 — esteira comercial)
-- Base do processo comercial: serviços pré-cadastrados e propostas com itens,
-- cálculo automático (horas x valor/hora), status e histórico de eventos.
-- Tabelas novas via CREATE TABLE IF NOT EXISTS (idempotente por natureza).

-- Catálogo de serviços pré-cadastrados (reutilizados ao montar propostas).
CREATE TABLE IF NOT EXISTS service_catalog (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    description   TEXT NULL COMMENT 'Escopo padrão do serviço',
    est_hours     DECIMAL(10,2) NULL COMMENT 'Horas estimadas padrão',
    hourly_rate   DECIMAL(10,2) NULL COMMENT 'Valor/hora padrão',
    is_hosting    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Serviço de hospedagem',
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_by    INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_service_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Propostas/orçamentos. Vinculam-se ao lead (whatsapp_contacts) e, opcionalmente,
-- ao card do CRM e à empresa (quando o lead já virou cliente). O token público
-- permite ao cliente abrir a proposta por link e aceitar/recusar.
CREATE TABLE IF NOT EXISTS proposals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    public_token   VARCHAR(64) NOT NULL COMMENT 'Identificador público (link ao cliente)',
    title          VARCHAR(200) NOT NULL,
    contact_id     INT NULL COMMENT 'Lead do CRM (whatsapp_contacts)',
    card_id        INT NULL COMMENT 'Card do CRM que originou a proposta',
    company_id     INT NULL COMMENT 'Empresa (quando o lead já é cliente)',
    client_name    VARCHAR(200) NULL COMMENT 'Snapshot do nome do cliente',
    client_email   VARCHAR(191) NULL,
    client_phone   VARCHAR(40) NULL,
    status         ENUM('draft','ready','sent','awaiting','accepted','rejected','cancelled') NOT NULL DEFAULT 'draft',
    contract_type  ENUM('dev_zero','dev_manutencao','suporte','dev_suporte','outro') NULL COMMENT 'Tipo de contratação',
    observations   TEXT NULL,
    validity_date  DATE NULL COMMENT 'Validade da proposta',
    total          DECIMAL(12,2) NOT NULL DEFAULT 0,
    reject_reason  TEXT NULL COMMENT 'Motivo informado pelo cliente ao recusar',
    sent_at        DATETIME NULL,
    responded_at   DATETIME NULL COMMENT 'Quando o cliente aceitou/recusou',
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_proposal_token (public_token),
    KEY idx_proposal_status (status),
    KEY idx_proposal_contact (contact_id),
    KEY idx_proposal_card (card_id),
    KEY idx_proposal_company (company_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (card_id) REFERENCES crm_cards(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens da proposta. amount = hours * hourly_rate (ou valor fixo quando sem horas).
CREATE TABLE IF NOT EXISTS proposal_items (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id   INT NOT NULL,
    service_id    INT NULL COMMENT 'Serviço do catálogo que originou o item',
    description   VARCHAR(255) NOT NULL,
    scope         TEXT NULL COMMENT 'Escopo/o que será feito',
    hours         DECIMAL(10,2) NULL,
    hourly_rate   DECIMAL(10,2) NULL,
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_hosting    TINYINT(1) NOT NULL DEFAULT 0,
    position      INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_item_proposal (proposal_id, position),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES service_catalog(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico/auditoria da proposta (mudança de status, envio, aceite/recusa, edição).
CREATE TABLE IF NOT EXISTS proposal_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id   INT NOT NULL,
    user_id       INT NULL COMMENT 'Quem fez (NULL = cliente/externo)',
    event_type    VARCHAR(40) NOT NULL COMMENT 'created/status/sent/accepted/rejected/edited/...',
    description   TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_proposal (proposal_id, created_at),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
