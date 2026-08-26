-- Migration 062: Filtros de exibição das oportunidades (por fonte).
-- Definem o que aparece na tela de Oportunidades:
--  - max_proposals: só mostra projetos com nº de propostas <= X (0 = sem limite)
--  - min_budget: só mostra projetos com orçamento mínimo >= X (0 = sem limite)
--  - max_age_days: só mostra projetos publicados/vistos nos últimos X dias (0 = sem limite)

ALTER TABLE source_settings
    ADD COLUMN max_proposals INT NOT NULL DEFAULT 0 AFTER collect_general,
    ADD COLUMN min_budget DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER max_proposals,
    ADD COLUMN max_age_days INT NOT NULL DEFAULT 0 AFTER min_budget;
