-- =====================================================================
-- AJUSTE DO CRONOGRAMA QUATRI (company_id = 2) — rodar no phpMyAdmin (produção)
-- Script de DADOS (não é migration de schema). Rode de uma vez, na aba SQL.
-- Idempotente onde possível (cards novos identificados por título-âncora).
--
-- O QUE FAZ:
--  (A) Desvincula "1 — Dash Compras" (408) do predecessor "0.1" (483), para
--      parar de ser arrastado pelo atraso do 0.1. Datas mantidas.
--  (B) Cria 2 cards novos NO COMEÇO, juntos (05–11/11), FORA da cadeia
--      (predecessor_id = NULL): Relatório Gerencial Financeiro e Filtros de
--      Orçamento. Não empurram o cronograma (paralelos ao 1.1).
--  (C) Cria 1 card novo em JANEIRO, em paralelo com "5 — Módulo Assistência"
--      (16–22/01 após o reencadeamento), FORA da cadeia: OS V2 + Gestão à Vista.
--  (D) DIVIDE "3 — Dash Técnico" (414) fora do recesso (21/12/2026–04/01/2027):
--      414 fica 17–20/12 e um card novo "3.1 — Dash Técnico (continuação)" cobre
--      05–08/01; depois REENCADEIA 4..14 (415..425) SEM SOBREPOSIÇÃO, 1 semana
--      por card, começando o 4 em 09/01 (empurra jan–mar ~4 dias).
--
-- NOTA TÉCNICA: os títulos são comparados com COLLATE utf8mb4_unicode_ci
-- (mesma da coluna) para evitar erro 1267 "illegal mix of collations".
-- Os ids dos cards novos são obtidos via LAST_INSERT_ID() logo após o INSERT.
--
-- OBS recesso: assume apenas o recesso de fim de ano (21/12–04/01). De 09/01 em
-- diante não há recesso de fim de ano; se houver OUTRO recesso cadastrado em
-- jan–mar, avisar para reposicionar.
-- =====================================================================
SET @company  := 2;
SET @assignee := 11;
SET @author   := (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1);
SET @author   := COALESCE(@author, (SELECT id FROM users ORDER BY id ASC LIMIT 1));

-- =====================================================================
-- (A) Desvincular o card 1 (408) do 0.1 (483).
-- =====================================================================
UPDATE planning_cards
SET predecessor_id = NULL, predecessor_lag_days = 0
WHERE id = 408;

-- =====================================================================
-- (B) Cards novos do COMEÇO (paralelos, fora da cadeia). Janela 05–11/11.
-- =====================================================================

-- B1) Relatório Gerencial Financeiro.
DELETE FROM planning_cards
WHERE company_id = @company
  AND title = 'Relatório Gerencial Financeiro — coluna e filtro de Data de Criação do Contrato' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, predecessor_id, predecessor_lag_days,
     start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
('Relatório Gerencial Financeiro — coluna e filtro de Data de Criação do Contrato',
 CONCAT(
   '<p><strong>Ajustar o Relatório Gerencial dentro do Financeiro</strong> para adicionar uma coluna de <strong>Data de Criação do Contrato</strong> e um filtro por essa data, permitindo filtrar melhor os contratos.</p>',
   '<p>Ajustar também os <strong>demais relatórios do sistema</strong> que envolvem contrato para considerar a <strong>data de criação do contrato</strong> (e não apenas a data de assinatura).</p>',
   '<p><strong>Contexto:</strong> em demanda anterior, os eventos do sistema relacionados a contrato passaram a ser disparados na <strong>criação</strong> do contrato (não mais na assinatura). Por isso relatórios e filtros precisam refletir a data de criação, para manter o controle correto.</p>'
 ),
 @company, @assignee, @author, 'high', 'open',
 1, NULL, 0,
 '2026-11-05 09:00:00', '2026-11-11 18:00:00', '2026-11-05', '2026-11-11', '2026-11-11 18:00:00');

-- B2) Filtros na tela de Orçamento. Mesma janela (juntos).
DELETE FROM planning_cards
WHERE company_id = @company
  AND title = 'Adicionar mais filtros na tela de Orçamento (filtragem por N formas)' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, predecessor_id, predecessor_lag_days,
     start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
('Adicionar mais filtros na tela de Orçamento (filtragem por N formas)',
 '<p><strong>Adicionar mais filtros na tela de Orçamento</strong>, permitindo filtrar de diversas formas diferentes para facilitar a localização e a análise dos orçamentos.</p>',
 @company, @assignee, @author, 'high', 'open',
 2, NULL, 0,
 '2026-11-05 09:00:00', '2026-11-11 18:00:00', '2026-11-05', '2026-11-11', '2026-11-11 18:00:00');

-- =====================================================================
-- (C) Card novo de JANEIRO (paralelo com "5 — Módulo Assistência" 16–22/01).
-- =====================================================================
DELETE FROM planning_cards
WHERE company_id = @company
  AND title = 'OS V2 + Gestão à Vista Novo (alinhamentos da última reunião)' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, predecessor_id, predecessor_lag_days,
     start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
('OS V2 + Gestão à Vista Novo (alinhamentos da última reunião)',
 CONCAT(
   '<p><strong>Implementar a V2 da Ordem de Serviço (OS)</strong> e os pontos acordados na última <strong>reunião de alinhamento</strong> referentes ao <strong>Gestão à Vista novo</strong>.</p>',
   '<p>Detalhar os itens a partir das anotações da reunião de alinhamento (OS V2 + evoluções do Gestão à Vista).</p>'
 ),
 @company, @assignee, @author, 'medium', 'open',
 3, NULL, 0,
 '2027-01-16 09:00:00', '2027-01-22 18:00:00', '2027-01-16', '2027-01-22', '2027-01-22 18:00:00');

-- =====================================================================
-- (D) Dividir o card "3 — Dash Técnico" (414) fora do recesso.
--     414 fica 17–20/12 (antes do recesso 21/12–04/01).
-- =====================================================================
UPDATE planning_cards SET
    start_date = '2026-12-17 09:00:00', end_date = '2026-12-20 18:00:00',
    client_start_date = '2026-12-17', client_end_date = '2026-12-20',
    due_date = '2026-12-20 18:00:00'
WHERE id = 414;

-- 3.1 — continuação do Dash Técnico (05–08/01), depois do recesso. Depende do 3 (414).
DELETE FROM planning_cards
WHERE company_id = @company
  AND title = '3.1 — Dash Técnico (continuação)' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, predecessor_id, predecessor_lag_days,
     start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
('3.1 — Dash Técnico (continuação)',
 '<p>Continuação do <strong>Dash Técnico</strong> após o recesso de fim de ano. A primeira parte roda em 17–20/12; esta continuação retoma em 05/01, já fora do recesso.</p>',
 @company, @assignee, @author, 'medium', 'open',
 4, 414, 0,
 '2027-01-05 09:00:00', '2027-01-08 18:00:00', '2027-01-05', '2027-01-08', '2027-01-08 18:00:00');

-- Guarda o id do 3.1 recém-inserido (para o encadeamento do card 4).
SET @id31 := LAST_INSERT_ID();

-- =====================================================================
-- (D.2) REENCADEAR 4..14 SEM SOBREPOSIÇÃO (1 semana por card), começando o 4
--       em 09/01. O "4" passa a depender do 3.1; demais mantêm cadeia (lag 0).
-- =====================================================================

-- 4 — Agenda Técnica (415): 09–15/01, predecessor = 3.1
UPDATE planning_cards SET
    predecessor_id = @id31, predecessor_lag_days = 0,
    start_date = '2027-01-09 09:00:00', end_date = '2027-01-15 18:00:00',
    client_start_date = '2027-01-09', client_end_date = '2027-01-15',
    due_date = '2027-01-15 18:00:00'
WHERE id = 415;

-- 5 — Módulo Assistência (416): 16–22/01  (OS V2 roda em paralelo nesta janela)
UPDATE planning_cards SET
    start_date = '2027-01-16 09:00:00', end_date = '2027-01-22 18:00:00',
    client_start_date = '2027-01-16', client_end_date = '2027-01-22',
    due_date = '2027-01-22 18:00:00'
WHERE id = 416;

-- 6 — Relatório de Fechamento de Obra (417): 23–29/01
UPDATE planning_cards SET
    start_date = '2027-01-23 09:00:00', end_date = '2027-01-29 18:00:00',
    client_start_date = '2027-01-23', client_end_date = '2027-01-29',
    due_date = '2027-01-29 18:00:00'
WHERE id = 417;

-- 7 — Comissão Individualizada (418): 30/01–05/02
UPDATE planning_cards SET
    start_date = '2027-01-30 09:00:00', end_date = '2027-02-05 18:00:00',
    client_start_date = '2027-01-30', client_end_date = '2027-02-05',
    due_date = '2027-02-05 18:00:00'
WHERE id = 418;

-- 8 — Integração Bitrix (419): 06–12/02
UPDATE planning_cards SET
    start_date = '2027-02-06 09:00:00', end_date = '2027-02-12 18:00:00',
    client_start_date = '2027-02-06', client_end_date = '2027-02-12',
    due_date = '2027-02-12 18:00:00'
WHERE id = 419;

-- 9 — Gestão à Vista V2 (420): 13–19/02
UPDATE planning_cards SET
    start_date = '2027-02-13 09:00:00', end_date = '2027-02-19 18:00:00',
    client_start_date = '2027-02-13', client_end_date = '2027-02-19',
    due_date = '2027-02-19 18:00:00'
WHERE id = 420;

-- 10 — Emissão de Boleto V2 (421): 20–26/02
UPDATE planning_cards SET
    start_date = '2027-02-20 09:00:00', end_date = '2027-02-26 18:00:00',
    client_start_date = '2027-02-20', client_end_date = '2027-02-26',
    due_date = '2027-02-26 18:00:00'
WHERE id = 421;

-- 11 — Lembrete de Pagamento V2 (422): 27/02–05/03
UPDATE planning_cards SET
    start_date = '2027-02-27 09:00:00', end_date = '2027-03-05 18:00:00',
    client_start_date = '2027-02-27', client_end_date = '2027-03-05',
    due_date = '2027-03-05 18:00:00'
WHERE id = 422;

-- 12 — Conciliação V2 (423): 06–12/03
UPDATE planning_cards SET
    start_date = '2027-03-06 09:00:00', end_date = '2027-03-12 18:00:00',
    client_start_date = '2027-03-06', client_end_date = '2027-03-12',
    due_date = '2027-03-12 18:00:00'
WHERE id = 423;

-- 13 — Nota Fiscal (424): 13–19/03
UPDATE planning_cards SET
    start_date = '2027-03-13 09:00:00', end_date = '2027-03-19 18:00:00',
    client_start_date = '2027-03-13', client_end_date = '2027-03-19',
    due_date = '2027-03-19 18:00:00'
WHERE id = 424;

-- 14 — Nova Proposta Comercial (425): 20–26/03
UPDATE planning_cards SET
    start_date = '2027-03-20 09:00:00', end_date = '2027-03-26 18:00:00',
    client_start_date = '2027-03-20', client_end_date = '2027-03-26',
    due_date = '2027-03-26 18:00:00'
WHERE id = 425;

-- =====================================================================
-- CONFERÊNCIA (rode junto): cadeia + cards novos (fora da cadeia).
-- =====================================================================
SELECT pc.id, pc.title, pc.status, pc.start_date, pc.end_date, pc.due_date,
       pc.predecessor_id, pred.title AS predecessor_titulo
FROM planning_cards pc
LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
WHERE pc.company_id = 2
  AND (pc.id IN (408,409,410,411,412,413,460,414,415,416,417,418,419,420,421,422,423,424,425)
       OR pc.title IN (
           'Relatório Gerencial Financeiro — coluna e filtro de Data de Criação do Contrato' COLLATE utf8mb4_unicode_ci,
           'Adicionar mais filtros na tela de Orçamento (filtragem por N formas)' COLLATE utf8mb4_unicode_ci,
           'OS V2 + Gestão à Vista Novo (alinhamentos da última reunião)' COLLATE utf8mb4_unicode_ci,
           '3.1 — Dash Técnico (continuação)' COLLATE utf8mb4_unicode_ci))
ORDER BY COALESCE(pc.start_date, '2000-01-01'), pc.id;
