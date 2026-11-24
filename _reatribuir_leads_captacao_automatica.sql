-- =====================================================================
-- Reatribui os leads que a CAPTAÇÃO AUTOMÁTICA já criou em nome do Super Admin
-- para o Responsável da campanha (ou, se a campanha não tiver Responsável,
-- para quem criou a campanha).
--
-- Só mexe em leads que:
--   * foram criados como NOVOS por uma campanha (log "imported ... (novo → Super Admin)");
--   * continuam com o Super Admin como responsável (ninguém reatribuiu à mão).
-- Os cards desses leads no CRM seguem a mesma regra.
--
-- Rode no phpMyAdmin do banco de produção. Rode primeiro só o SELECT de
-- conferência (bloco 1) se quiser ver o que vai mudar.
-- =====================================================================

-- Super Admin usado pela regra antiga (primeiro super_admin ativo).
SET @sa := (SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 ORDER BY id ASC LIMIT 1);

-- 1) Conferência: quantos leads vão para cada pessoa.
SELECT ac.name AS campanha,
       u.name  AS novo_responsavel,
       COUNT(DISTINCT wc.id) AS leads
FROM apollo_prospecting_log l
JOIN apollo_campaigns ac ON ac.id = l.campaign_id
JOIN whatsapp_contacts wc ON wc.id = l.contact_id
JOIN users u ON u.id = COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0))
WHERE l.action = 'imported'
  AND l.detail LIKE '%(novo%Super Admin)%'
  AND wc.assigned_to = @sa
  AND COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0)) <> @sa
GROUP BY ac.name, u.name;

-- 2) Cards do CRM desses leads.
UPDATE crm_cards c
JOIN apollo_prospecting_log l ON l.contact_id = c.contact_id AND l.action = 'imported' AND l.detail LIKE '%(novo%Super Admin)%'
JOIN apollo_campaigns ac ON ac.id = l.campaign_id
JOIN whatsapp_contacts wc ON wc.id = c.contact_id
SET c.assigned_to = COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0))
WHERE wc.assigned_to = @sa
  AND (c.assigned_to = @sa OR c.assigned_to IS NULL)
  AND COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0)) IS NOT NULL;

-- 3) Leads (Meus Leads / WhatsApp).
UPDATE whatsapp_contacts wc
JOIN apollo_prospecting_log l ON l.contact_id = wc.id AND l.action = 'imported' AND l.detail LIKE '%(novo%Super Admin)%'
JOIN apollo_campaigns ac ON ac.id = l.campaign_id
SET wc.assigned_to = COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0))
WHERE wc.assigned_to = @sa
  AND COALESCE(NULLIF(ac.assigned_to, 0), NULLIF(ac.created_by, 0)) IS NOT NULL;

-- 4) Conferência final: leads da captação automática por responsável.
SELECT u.name AS responsavel, COUNT(DISTINCT wc.id) AS leads
FROM apollo_prospecting_log l
JOIN whatsapp_contacts wc ON wc.id = l.contact_id
LEFT JOIN users u ON u.id = wc.assigned_to
WHERE l.action = 'imported'
GROUP BY u.name;
