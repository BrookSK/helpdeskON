-- =====================================================================
-- 079_apollo_campaign_my_leads_ids.sql
-- ---------------------------------------------------------------------
-- Seleção manual de leads específicos (opcional) para campanhas com
-- origem "Meus Leads".
--
-- Quando preenchido, tem PRIORIDADE sobre my_leads_filters: a campanha
-- inscreve apenas os contatos escolhidos no modal de multiseleção.
-- Coluna idempotente (IF NOT EXISTS) para reexecução segura.
-- =====================================================================

ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS my_leads_ids JSON DEFAULT NULL
        COMMENT 'IDs de whatsapp_contacts selecionados manualmente (prioridade sobre my_leads_filters)'
        AFTER my_leads_filters;
