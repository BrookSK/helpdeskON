-- Migration 143: Fluxo de Suporte de Produção.
--
-- Separa o atendimento de problemas que JÁ estão em produção (suporte) do
-- desenvolvimento de novas demandas. O "tipo" da demanda continua sendo expresso
-- pela coluna `category` existente (ex.: 'suporte' vs 'desenvolvimento') — a tag
-- visível na UI. Aqui adicionamos os campos específicos de suporte:
--   - gravidade (Crítico/Alto/Médio/Baixo): define o prazo interno de ANÁLISE;
--   - prazo previsto de RESOLUÇÃO (30 min a 48h), informado ao cliente;
--   - identificação de problema de TERCEIROS (API/causa externa) + acompanhamento.
--
-- A gravidade é independente da prioridade (low/medium/high/urgent), que
-- permanece como está. É NULL para demandas que não são de suporte.
--
-- Idempotente (padrão PREPARE/EXECUTE da migration 126).

-- Gravidade do suporte (NULL quando não é suporte).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'support_severity');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN support_severity ENUM('critico','alto','medio','baixo') NULL COMMENT 'Gravidade do chamado de suporte (define prazo de analise)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Prazo interno de ANÁLISE (deadline calculado a partir da gravidade).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'support_analysis_due_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN support_analysis_due_at TIMESTAMP NULL COMMENT 'Prazo interno de analise do suporte (conforme gravidade)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Prazo previsto de RESOLUÇÃO (definido pela equipe; 30 min a 48h).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'support_resolution_due_at');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN support_resolution_due_at TIMESTAMP NULL COMMENT 'Prazo previsto de resolucao do suporte (informado ao cliente)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Solução temporária aplicada (não é a correção definitiva).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'support_workaround');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN support_workaround TEXT NULL COMMENT 'Solucao temporaria aplicada (nao e a correcao definitiva)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Problema de TERCEIROS (API/causa externa fora do código da empresa).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'is_third_party');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN is_third_party TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Causa externa (API de terceiro, etc.) fora do controle interno'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'third_party_name');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN third_party_name VARCHAR(191) NULL COMMENT 'Terceiro responsavel (nome do servico/empresa externa)'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'third_party_notes');
SET @s := IF(@c = 0, "ALTER TABLE tickets ADD COLUMN third_party_notes TEXT NULL COMMENT 'Andamento/evidencias do acompanhamento com o terceiro'", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice para listar/filtrar suportes por gravidade rapidamente.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets' AND INDEX_NAME = 'idx_tickets_support_severity');
SET @s := IF(@i = 0, "ALTER TABLE tickets ADD INDEX idx_tickets_support_severity (support_severity)", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
