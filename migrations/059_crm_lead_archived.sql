-- Migration 059: Arquivamento de leads no CRM (independente do arquivamento do chat).
-- O campo is_archived já é usado pela lista do chat do WhatsApp; para arquivar um lead
-- apenas na aba "Meus Leads" do CRM sem escondê-lo do chat, usamos uma coluna dedicada.

ALTER TABLE whatsapp_contacts
    ADD COLUMN crm_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived;
