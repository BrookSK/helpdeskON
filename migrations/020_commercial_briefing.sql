-- Migration 020: Briefing comercial do lead (vinculado ao contato de WhatsApp)
-- e faixa de investimento exibida no CRM.
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS commercial_briefings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    need TEXT NULL COMMENT 'Necessidade do lead',
    main_pain TEXT NULL COMMENT 'Principal dor/problema',
    current_solution TEXT NULL COMMENT 'Solução atual utilizada',
    expected_goal TEXT NULL COMMENT 'Objetivo esperado',
    urgency VARCHAR(255) NULL COMMENT 'Urgência/prazo',
    investment_range VARCHAR(100) NULL COMMENT 'Faixa de investimento',
    decision_level VARCHAR(100) NULL COMMENT 'Nível de decisão do contato',
    lead_temperature ENUM('frio','morno','quente') NULL COMMENT 'Temperatura do lead',
    main_objection TEXT NULL COMMENT 'Principal objeção',
    next_step TEXT NULL COMMENT 'Próximo passo combinado',
    next_contact_date DATE NULL COMMENT 'Data do próximo contato',
    notes TEXT NULL COMMENT 'Observações importantes',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contact (contact_id),
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
