-- =====================================================================
-- 174_crm_lead_profile_and_card_origin.sql
-- ---------------------------------------------------------------------
-- Dados do lead visíveis no CRM + origem do card.
--
-- Colunas novas:
--   whatsapp_contacts.job_title     cargo do lead (antes só ia no texto
--       das observações do briefing: "Cargo: X | Empresa: Y | LinkedIn: Z").
--   whatsapp_contacts.company_name  empresa do lead.
--   crm_cards.origin         de onde o card veio:
--       'prospeccao_auto'   captação automática (campanha Apollo)
--       'prospeccao_manual' Captação de Leads (Apollo) > Enviar p/ Meus Leads
--       'planilha'          importação de planilha
--       NULL                criado à mão / outras origens
--   crm_cards.origin_detail  nome da campanha ou do arquivo importado.
--
-- Backfill (só preenche o que está vazio):
--   * cargo/empresa a partir do staging do Apollo (apollo_leads);
--   * origem dos cards de leads captados pela automação (log 'imported') e
--     dos enviados manualmente pela Captação de Leads (apollo_leads).
--
-- Idempotente (checa information_schema) para reexecução segura.
-- Aplique no banco correto pela CONEXÃO (não força USE).
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_contacts' AND COLUMN_NAME = 'job_title');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_contacts ADD COLUMN job_title VARCHAR(190) DEFAULT NULL COMMENT 'cargo do lead' AFTER linkedin_url",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_contacts' AND COLUMN_NAME = 'company_name');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_contacts ADD COLUMN company_name VARCHAR(190) DEFAULT NULL COMMENT 'empresa do lead' AFTER job_title",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'origin');
SET @s := IF(@c = 0,
    "ALTER TABLE crm_cards ADD COLUMN origin VARCHAR(30) DEFAULT NULL COMMENT 'prospeccao_auto | prospeccao_manual | planilha' AFTER contact_id",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crm_cards' AND COLUMN_NAME = 'origin_detail');
SET @s := IF(@c = 0,
    "ALTER TABLE crm_cards ADD COLUMN origin_detail VARCHAR(190) DEFAULT NULL COMMENT 'campanha ou arquivo de origem' AFTER origin",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---- Backfill: cargo/empresa a partir do staging do Apollo ----
UPDATE whatsapp_contacts wc
JOIN apollo_leads al ON al.contact_id = wc.id
SET wc.job_title = al.title
WHERE (wc.job_title IS NULL OR wc.job_title = '') AND al.title IS NOT NULL AND al.title <> '';

UPDATE whatsapp_contacts wc
JOIN apollo_leads al ON al.contact_id = wc.id
SET wc.company_name = al.organization_name
WHERE (wc.company_name IS NULL OR wc.company_name = '') AND al.organization_name IS NOT NULL AND al.organization_name <> '';

-- ---- Backfill: origem dos cards ----
-- Captação automática: o contato aparece no log 'imported' de uma campanha.
UPDATE crm_cards c
JOIN (
    SELECT l.contact_id, MIN(l.campaign_id) AS campaign_id
    FROM apollo_prospecting_log l
    WHERE l.action = 'imported' AND l.contact_id IS NOT NULL AND l.campaign_id IS NOT NULL
    GROUP BY l.contact_id
) x ON x.contact_id = c.contact_id
LEFT JOIN apollo_campaigns ac ON ac.id = x.campaign_id
SET c.origin = 'prospeccao_auto', c.origin_detail = ac.name
WHERE c.origin IS NULL;

-- Captação manual: contato enviado pela tela Captação de Leads (staging importado).
UPDATE crm_cards c
JOIN (SELECT DISTINCT contact_id FROM apollo_leads WHERE contact_id IS NOT NULL) al ON al.contact_id = c.contact_id
SET c.origin = 'prospeccao_manual'
WHERE c.origin IS NULL;

-- Conferência
SELECT origin, COUNT(*) AS cards FROM crm_cards GROUP BY origin;
