-- Migration 041: Fonte do lead no briefing + email do cliente + integração Google na agenda
-- Execute manualmente no MySQL

USE helpdesk_on;

-- Campo "Fonte do lead" no briefing comercial
ALTER TABLE commercial_briefings
    ADD COLUMN lead_source VARCHAR(30) NULL COMMENT 'telefonema, email, whatsapp, linkedin, instagram, facebook' AFTER lead_temperature;

-- Email do cliente e dados do evento Google na reunião da agenda
ALTER TABLE agenda_meetings
    ADD COLUMN client_email VARCHAR(190) NULL AFTER client_phone,
    ADD COLUMN google_event_id VARCHAR(190) NULL AFTER notes,
    ADD COLUMN meet_link VARCHAR(500) NULL AFTER google_event_id;
