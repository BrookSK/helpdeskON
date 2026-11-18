-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v12
-- Ajustes da reunião com o cliente, sobre o estado atual (com as
-- alterações manuais já feitas; até o #570 nada muda além do #418):
--   1. #418 "7 — Comissão Individualizada" vai junto com o #410 "1.2 —
--      Página Comissões" (mesmo período, 21–27/11).
--   2. Cards 10, 11, 12 e 13 (#421, #422, #423, #424) vão para antes do
--      #414 "3 — Dash Técnico"; o restante vai para frente, na mesma ordem.
--   3. #419 "8 — Integração com Bitrix" passa a se chamar "8 — Ver na reunião".
--   4. #420 "9 — Gestão à Vista V2" é duplicado do #517 "OS V2 + Gestão à
--      Vista Novo": o #420 é ARQUIVADO (reversível) e fica o #517.
-- Recesso 21/12 a 04/01 não muda. Datas em dias corridos; folga 1.
-- Rodar de uma vez no phpMyAdmin (produção). Em transação: se algum
-- comando falhar, nada é gravado. Pode rodar de novo (grava por id).
-- =====================================================================

START TRANSACTION;

-- 1. 7 — Comissão Individualizada junto com o 1.2 Página Comissões
UPDATE planning_cards SET predecessor_id=409, predecessor_lag_days=1,
  start_date='2026-11-21 09:00:00', end_date='2026-11-27 18:00:00', due_date='2026-11-27 18:00:00', client_start_date='2026-11-21', client_end_date='2026-11-27' WHERE id=418 AND company_id=2;

-- 2. Depois do #570 (termina 16/01): cards 10, 11, 12 e 13
UPDATE planning_cards SET predecessor_id=570, predecessor_lag_days=1,
  start_date='2027-01-17 09:00:00', end_date='2027-01-23 18:00:00', due_date='2027-01-23 18:00:00', client_start_date='2027-01-17', client_end_date='2027-01-23' WHERE id=421 AND company_id=2;
UPDATE planning_cards SET predecessor_id=421, predecessor_lag_days=1,
  start_date='2027-01-24 09:00:00', end_date='2027-01-30 18:00:00', due_date='2027-01-30 18:00:00', client_start_date='2027-01-24', client_end_date='2027-01-30' WHERE id=422 AND company_id=2;
UPDATE planning_cards SET predecessor_id=422, predecessor_lag_days=1,
  start_date='2027-01-31 09:00:00', end_date='2027-02-06 18:00:00', due_date='2027-02-06 18:00:00', client_start_date='2027-01-31', client_end_date='2027-02-06' WHERE id=423 AND company_id=2;
UPDATE planning_cards SET predecessor_id=423, predecessor_lag_days=1,
  start_date='2027-02-07 09:00:00', end_date='2027-02-13 18:00:00', due_date='2027-02-13 18:00:00', client_start_date='2027-02-07', client_end_date='2027-02-13' WHERE id=424 AND company_id=2;

-- O restante, na mesma ordem
-- 3 — Dash Técnico
UPDATE planning_cards SET predecessor_id=424, predecessor_lag_days=1,
  start_date='2027-02-14 09:00:00', end_date='2027-02-20 18:00:00', due_date='2027-02-20 18:00:00', client_start_date='2027-02-14', client_end_date='2027-02-20' WHERE id=414 AND company_id=2;
-- 4 — Agenda Técnica
UPDATE planning_cards SET predecessor_id=414, predecessor_lag_days=1,
  start_date='2027-02-21 09:00:00', end_date='2027-02-27 18:00:00', due_date='2027-02-27 18:00:00', client_start_date='2027-02-21', client_end_date='2027-02-27' WHERE id=415 AND company_id=2;
-- 5 — Módulo Assistência
UPDATE planning_cards SET predecessor_id=415, predecessor_lag_days=1,
  start_date='2027-02-28 09:00:00', end_date='2027-03-06 18:00:00', due_date='2027-03-06 18:00:00', client_start_date='2027-02-28', client_end_date='2027-03-06' WHERE id=416 AND company_id=2;
-- 2.1 — Pedido Quatri / Produção
UPDATE planning_cards SET predecessor_id=416, predecessor_lag_days=1,
  start_date='2027-03-07 09:00:00', end_date='2027-03-13 18:00:00', due_date='2027-03-13 18:00:00', client_start_date='2027-03-07', client_end_date='2027-03-13' WHERE id=413 AND company_id=2;
-- 2.2 — Créditos por devolução
UPDATE planning_cards SET predecessor_id=413, predecessor_lag_days=1,
  start_date='2027-03-14 09:00:00', end_date='2027-03-20 18:00:00', due_date='2027-03-20 18:00:00', client_start_date='2027-03-14', client_end_date='2027-03-20' WHERE id=460 AND company_id=2;
-- 2 — Dash Expedição
UPDATE planning_cards SET predecessor_id=460, predecessor_lag_days=1,
  start_date='2027-03-21 09:00:00', end_date='2027-03-27 18:00:00', due_date='2027-03-27 18:00:00', client_start_date='2027-03-21', client_end_date='2027-03-27' WHERE id=412 AND company_id=2;
-- 14 — Nova Proposta Comercial (o #418 saiu daqui e foi para novembro)
UPDATE planning_cards SET predecessor_id=412, predecessor_lag_days=1,
  start_date='2027-03-28 09:00:00', end_date='2027-04-03 18:00:00', due_date='2027-04-03 18:00:00', client_start_date='2027-03-28', client_end_date='2027-04-03' WHERE id=425 AND company_id=2;

-- 3. 8 — Integração com Bitrix vira "8 — Ver na reunião"
UPDATE planning_cards SET title='8 — Ver na reunião', predecessor_id=425, predecessor_lag_days=1,
  start_date='2027-04-04 09:00:00', end_date='2027-04-10 18:00:00', due_date='2027-04-10 18:00:00', client_start_date='2027-04-04', client_end_date='2027-04-10' WHERE id=419 AND company_id=2;

-- 4. 9 — Gestão à Vista V2 duplicado do #517: arquivado
UPDATE planning_cards SET status='archived', predecessor_id=NULL WHERE id=420 AND company_id=2;

-- 6 — Relatório de Fechamento de Obra (dependia do #420)
UPDATE planning_cards SET predecessor_id=419, predecessor_lag_days=1,
  start_date='2027-04-11 09:00:00', end_date='2027-04-17 18:00:00', due_date='2027-04-17 18:00:00', client_start_date='2027-04-11', client_end_date='2027-04-17' WHERE id=417 AND company_id=2;
-- OS V2 + Gestão à Vista Novo e Filtro das Obras, juntos
UPDATE planning_cards SET predecessor_id=417, predecessor_lag_days=1,
  start_date='2027-04-18 09:00:00', end_date='2027-04-24 18:00:00', due_date='2027-04-24 18:00:00', client_start_date='2027-04-18', client_end_date='2027-04-24' WHERE id IN (517, 568) AND company_id=2;

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência: cards em aberto da Quatri na ordem das datas (+ o #420)
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status,
       DATE_FORMAT(pc.start_date, '%d/%m/%Y') AS inicio, DATE_FORMAT(pc.end_date, '%d/%m/%Y') AS fim,
       DATEDIFF(pc.end_date, pc.start_date) + 1 AS dias,
       pc.predecessor_id AS depende_de, pc.predecessor_lag_days AS folga, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON u.id = pc.assigned_to
WHERE pc.company_id = 2 AND (pc.status NOT IN ('completed','archived','denied') OR pc.id = 420)
ORDER BY COALESCE(pc.start_date, pc.due_date, '2099-12-31'), pc.id;
