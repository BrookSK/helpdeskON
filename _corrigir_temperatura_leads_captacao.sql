-- =====================================================================
-- Corrige a TEMPERATURA dos leads criados pela captação automática.
--
-- Erro: a captação somava o score de PERFIL do lead (0 a 100) no placar de
-- engajamento (e-mail aberto +1, clique +3, resposta +5). Com isso o lead já
-- nascia "quente" sem nunca ter aberto um e-mail.
--
-- O script tira do placar só os pontos lançados pela captação
-- ("— apollo_prospecting" na timeline) e recalcula a temperatura:
--   Frio 0 · Morno 1 a 4 · Quente 5 ou mais.
-- A temperatura do briefing só é trocada se ainda estiver no valor automático;
-- se alguém ajustou à mão, fica como está.
--
-- Rode no phpMyAdmin do banco de produção (MariaDB). O bloco 1 só confere.
-- =====================================================================

DROP TEMPORARY TABLE IF EXISTS tmp_fix_temp;
CREATE TEMPORARY TABLE tmp_fix_temp AS
SELECT t.contact_id,
       SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.meta, '$.delta')) AS SIGNED)) AS apollo_points
FROM lead_timeline t
WHERE t.event_type = 'score'
  AND t.description LIKE '%apollo_prospecting%'
GROUP BY t.contact_id;

-- 1) Conferência: quantos leads e como fica a temperatura.
SELECT
    COUNT(*) AS leads_afetados,
    SUM(CASE WHEN GREATEST(0, ls.score - x.apollo_points) >= 5 THEN 1 ELSE 0 END) AS ficam_quentes,
    SUM(CASE WHEN GREATEST(0, ls.score - x.apollo_points) BETWEEN 1 AND 4 THEN 1 ELSE 0 END) AS ficam_mornos,
    SUM(CASE WHEN GREATEST(0, ls.score - x.apollo_points) = 0 THEN 1 ELSE 0 END) AS ficam_frios
FROM tmp_fix_temp x
JOIN lead_score ls ON ls.contact_id = x.contact_id;

-- 2) Temperatura do briefing (só onde ainda está no valor automático).
UPDATE commercial_briefings b
JOIN tmp_fix_temp x ON x.contact_id = b.contact_id
JOIN lead_score ls ON ls.contact_id = b.contact_id
SET b.lead_temperature = CASE
        WHEN GREATEST(0, ls.score - x.apollo_points) >= 5 THEN 'quente'
        WHEN GREATEST(0, ls.score - x.apollo_points) >= 1 THEN 'morno'
        ELSE 'frio' END
WHERE b.lead_temperature = CASE
        WHEN ls.score >= 5 THEN 'quente'
        WHEN ls.score >= 1 THEN 'morno'
        ELSE 'frio' END;

-- 3) Placar de engajamento.
UPDATE lead_score ls
JOIN tmp_fix_temp x ON x.contact_id = ls.contact_id
SET ls.score = GREATEST(0, ls.score - x.apollo_points),
    ls.classification = CASE
        WHEN GREATEST(0, ls.score - x.apollo_points) >= 5 THEN 'quente'
        WHEN GREATEST(0, ls.score - x.apollo_points) >= 3 THEN 'engajado'
        WHEN GREATEST(0, ls.score - x.apollo_points) >= 1 THEN 'morno'
        ELSE 'frio' END;

DROP TEMPORARY TABLE IF EXISTS tmp_fix_temp;
