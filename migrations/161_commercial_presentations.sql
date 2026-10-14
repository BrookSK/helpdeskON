-- Migration 161: Apresentação comercial (etapa da esteira anterior à Proposta).
-- Conteúdo editável em seções (JSON em `content`, ver PresentationRules) e link
-- público por token, ativo apenas quando status = 'published'.
-- Tabela nova via CREATE TABLE IF NOT EXISTS (idempotente). Sem "USE": rode com
-- o banco alvo já selecionado (local: helpdesk_on; beta: helpdesk_on_beta).

CREATE TABLE IF NOT EXISTS presentations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    public_token   VARCHAR(64) NOT NULL COMMENT 'Identificador público (link ao cliente)',
    title          VARCHAR(200) NOT NULL,
    contact_id     INT NULL COMMENT 'Lead do CRM (whatsapp_contacts)',
    proposal_id    INT NULL COMMENT 'Proposta gerada a partir desta apresentação',
    client_name    VARCHAR(200) NULL COMMENT 'Snapshot do nome do cliente',
    client_email   VARCHAR(191) NULL,
    client_phone   VARCHAR(40) NULL,
    content        LONGTEXT NULL COMMENT 'JSON {sections:[...]}',
    status         ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at   DATETIME NULL,
    sent_at        DATETIME NULL,
    view_count     INT NOT NULL DEFAULT 0,
    last_viewed_at DATETIME NULL,
    created_by     INT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_presentation_token (public_token),
    KEY idx_presentation_status (status),
    KEY idx_presentation_contact (contact_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
