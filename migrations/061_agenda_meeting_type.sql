-- Adiciona o tipo de reunião (comercial/operacional) em agenda_meetings.
-- Reuniões comerciais mantêm o fluxo atual (cliente CRM, briefing etc.).
-- Reuniões operacionais usam apenas título, descrição, data/horário e participantes internos.
ALTER TABLE agenda_meetings
    ADD COLUMN meeting_type ENUM('comercial','operacional') NOT NULL DEFAULT 'comercial' AFTER title;

-- Controla se as notificações (WhatsApp + e-mail) já foram enviadas aos participantes,
-- permitindo diferenciar o primeiro envio de um reenvio manual.
ALTER TABLE agenda_meetings
    ADD COLUMN notifications_sent_at DATETIME NULL AFTER meet_link;
