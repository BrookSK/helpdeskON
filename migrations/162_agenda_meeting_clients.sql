-- =====================================================================
-- 162_agenda_meeting_clients.sql
-- ---------------------------------------------------------------------
-- Múltiplos clientes/contatos por reunião (comercial).
--
-- Até aqui a reunião comercial guardava UM único cliente nos campos
-- escalares de agenda_meetings (contact_id + client_name/phone/email).
-- Esta migration cria a tabela de clientes da reunião (M:N análoga a
-- agenda_meeting_participants), permitindo N clientes por reunião. O
-- PRIMEIRO cliente continua refletido nos campos escalares da reunião
-- (retrocompatibilidade: Kanban, briefing, conversão, evento Google).
--
-- Cada cliente guarda um snapshot (nome/telefone/e-mail) e, quando veio
-- do CRM, o contact_id (whatsapp_contacts). O snapshot garante que a
-- notificação funciona mesmo sem vínculo de CRM (origem Empresa).
-- =====================================================================

CREATE TABLE IF NOT EXISTS agenda_meeting_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    contact_id INT NULL,                 -- lead do CRM (whatsapp_contacts), quando houver
    client_name VARCHAR(255) NULL,
    client_phone VARCHAR(50) NULL,
    client_email VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 0,     -- ordem; posição 0 = cliente principal
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES agenda_meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
    KEY idx_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
