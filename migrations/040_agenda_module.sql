-- Migration 040: Módulo Agenda (reuniões comerciais) — separado do Planejamento
-- Integra com o CRM (contatos/leads) e reaproveita o briefing comercial.
-- Execute manualmente no MySQL

USE helpdesk_on;

CREATE TABLE IF NOT EXISTS agenda_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    contact_id INT NULL COMMENT 'Lead do CRM (whatsapp_contacts)',
    client_name VARCHAR(150) NULL,
    client_phone VARCHAR(40) NULL,
    assigned_to INT NULL COMMENT 'Responsável pela reunião',
    created_by INT NOT NULL,
    urgency ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    temperature ENUM('frio','morno','quente') NULL,
    status ENUM('a_agendar','agendada','confirmada','realizada','remarcada','cancelada') NOT NULL DEFAULT 'a_agendar',
    meeting_at DATETIME NULL COMMENT 'Data e horário da reunião',
    notes LONGTEXT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_agenda_status ON agenda_meetings(status, position);
CREATE INDEX idx_agenda_assigned ON agenda_meetings(assigned_to);
CREATE INDEX idx_agenda_meeting_at ON agenda_meetings(meeting_at);
