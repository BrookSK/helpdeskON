-- =====================================================================
-- 078_apollo_campaign_lead_source.sql
-- ---------------------------------------------------------------------
-- Campanhas de Prospecção Automática: escolha da ORIGEM dos leads.
--
--   * lead_source = 'apollo'   → busca/revela na Apollo (fluxo atual, aprimorado)
--   * lead_source = 'my_leads' → usa leads já existentes no CRM (sem reveal)
--
-- Também adiciona:
--   * my_leads_filters   → filtros aplicados quando a origem é "Meus Leads"
--   * global_dedupe      → não re-prospectar quem já passou por QUALQUER campanha
--   * colunas de estado/contadores para acompanhar a captação continuada
-- Todas as colunas são idempotentes (IF NOT EXISTS) para reexecução segura.
-- =====================================================================

-- Origem dos leads da campanha
ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS lead_source ENUM('apollo','my_leads') NOT NULL DEFAULT 'apollo'
        COMMENT 'origem dos leads inscritos: apollo (busca/reveal) ou my_leads (CRM existente)'
        AFTER is_active;

-- Filtros para a origem "Meus Leads" (temperatura, fonte, responsável, etc.)
ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS my_leads_filters JSON DEFAULT NULL
        COMMENT 'filtros aplicados quando lead_source=my_leads'
        AFTER icp_rules;

-- Regra global de deduplicação (não re-prospectar entre campanhas)
ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS global_dedupe TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = nunca prospectar de novo quem já foi captado por qualquer campanha automática';

-- Estado / contadores da captação continuada (para exibição rápida na campanha)
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_analyzed   INT NOT NULL DEFAULT 0 COMMENT 'contatos analisados na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_discarded  INT NOT NULL DEFAULT 0 COMMENT 'descartados por ICP/score na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_duplicated INT NOT NULL DEFAULT 0 COMMENT 'duplicados/já conhecidos na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_revealed   INT NOT NULL DEFAULT 0 COMMENT 'revelados (Apollo) na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_imported   INT NOT NULL DEFAULT 0 COMMENT 'criados/atualizados em Meus Leads na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS stat_enrolled   INT NOT NULL DEFAULT 0 COMMENT 'inscritos na sequência na última execução';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS last_run_at     DATETIME DEFAULT NULL COMMENT 'última execução da campanha';
ALTER TABLE apollo_campaigns ADD COLUMN IF NOT EXISTS last_error      TEXT DEFAULT NULL COMMENT 'último erro encontrado na execução';

-- Índice para consultas de dedup por origem
ALTER TABLE apollo_campaigns ADD INDEX IF NOT EXISTS idx_lead_source (lead_source);
