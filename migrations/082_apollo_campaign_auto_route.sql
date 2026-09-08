-- =====================================================================
-- 082_apollo_campaign_auto_route.sql
-- ---------------------------------------------------------------------
-- Roteamento automático por CANAL na captação Apollo.
--
-- Quando auto_route = 1, a campanha decide a sequência de cada lead com base
-- nos dados que o Apollo efetivamente encontrou:
--   * e-mail E telefone  → sequence_id_mixed    (fluxo misto)
--   * só e-mail           → sequence_id_email    (fluxo de e-mail)
--   * só telefone         → sequence_id_whatsapp (fluxo de WhatsApp)
--
-- Se algum slot estiver vazio, cai no sequence_id "padrão" da campanha.
-- Idempotente (IF NOT EXISTS).
-- =====================================================================

ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS auto_route TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = escolhe a sequencia por canal conforme os dados encontrados'
        AFTER sequence_id;

ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS sequence_id_email INT DEFAULT NULL
        COMMENT 'sequencia para leads SÓ com e-mail (auto_route)'
        AFTER auto_route;

ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS sequence_id_whatsapp INT DEFAULT NULL
        COMMENT 'sequencia para leads SÓ com telefone (auto_route)'
        AFTER sequence_id_email;

ALTER TABLE apollo_campaigns
    ADD COLUMN IF NOT EXISTS sequence_id_mixed INT DEFAULT NULL
        COMMENT 'sequencia para leads com e-mail E telefone (auto_route)'
        AFTER sequence_id_whatsapp;
