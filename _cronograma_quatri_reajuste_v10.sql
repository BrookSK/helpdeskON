-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v10
-- Encaixa 3 cards que estavam sem responsável e sem data:
--   570 Contas a Receber | Baixas, edição em lote e tarifas de cartão
--       -> logo depois do 549 Tela de Contratos, antes do 1.1   02–06/12
--   565 Solicitação de melhoria – Checklist de fechamento de vendas
--       -> logo depois do 1.3 Campos de Lucro, antes do 2       12–14/01
--   568 Ajuste do Filtro das Obras em Andamento
--       -> junto com o 517 OS V2 + Gestão à Vista (mesmo período) 19–25/02
-- Os três ficam com o Lucas Campagna.
-- Com o 570 entrando, o 1.2 Comissões termina em 20/12 e o 1.3 Campos de
-- Lucro vai INTEIRO para depois do recesso (21/12/2026 a 04/01/2027):
-- 05–11/01. O card "1.3 (continuação)" (550) fica sem uso e é ARQUIVADO
-- (reversível). Do 2 Dash Expedição em diante tudo anda 8 dias.
-- Rodar de uma vez no phpMyAdmin (produção). Em transação: se algum
-- comando falhar, nada é gravado. Pode rodar de novo (grava por id).
-- =====================================================================

SET @campagna := (SELECT id FROM users WHERE name LIKE 'Lucas Campagna%' ORDER BY id LIMIT 1);
SELECT @campagna AS campagna;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 570 Contas a Receber — 5 dias, depois do 549 Tela de Contratos
-- ---------------------------------------------------------------------
UPDATE planning_cards SET assigned_to=@campagna, predecessor_id=549, predecessor_lag_days=1,
  start_date='2026-12-02 09:00:00', end_date='2026-12-06 18:00:00', due_date='2026-12-06 18:00:00',
  client_start_date='2026-12-02', client_end_date='2026-12-06'
 WHERE id=570 AND company_id=2;

-- 1.1 e 1.2 passam a vir depois do 570 (+5 dias)
UPDATE planning_cards SET predecessor_id=570, predecessor_lag_days=1,
  start_date='2026-12-07 09:00:00', end_date='2026-12-13 18:00:00', due_date='2026-12-13 18:00:00', client_start_date='2026-12-07', client_end_date='2026-12-13' WHERE id=409 AND company_id=2;
UPDATE planning_cards SET start_date='2026-12-14 09:00:00', end_date='2026-12-20 18:00:00', due_date='2026-12-20 18:00:00', client_start_date='2026-12-14', client_end_date='2026-12-20' WHERE id=410 AND company_id=2;

-- ---------------------------------------------------------------------
-- Depois do recesso: 1.3 inteiro (7 dias); continuação arquivada
-- ---------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id=410, predecessor_lag_days=1,
  start_date='2027-01-05 09:00:00', end_date='2027-01-11 18:00:00', due_date='2027-01-11 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-11' WHERE id=411 AND company_id=2;
UPDATE planning_cards SET status='archived', predecessor_id=NULL WHERE id=550 AND company_id=2;

-- 565 Checklist de fechamento de vendas — 3 dias, depois do 1.3
UPDATE planning_cards SET assigned_to=@campagna, predecessor_id=411, predecessor_lag_days=1,
  start_date='2027-01-12 09:00:00', end_date='2027-01-14 18:00:00', due_date='2027-01-14 18:00:00',
  client_start_date='2027-01-12', client_end_date='2027-01-14'
 WHERE id=565 AND company_id=2;

-- 2 Dash Expedição passa a vir depois do 565; daqui em diante +8 dias
UPDATE planning_cards SET predecessor_id=565, predecessor_lag_days=1,
  start_date='2027-01-15 09:00:00', end_date='2027-01-21 18:00:00', due_date='2027-01-21 18:00:00', client_start_date='2027-01-15', client_end_date='2027-01-21' WHERE id=412 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-22 09:00:00', end_date='2027-01-28 18:00:00', due_date='2027-01-28 18:00:00', client_start_date='2027-01-22', client_end_date='2027-01-28' WHERE id=413 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-29 09:00:00', end_date='2027-02-04 18:00:00', due_date='2027-02-04 18:00:00', client_start_date='2027-01-29', client_end_date='2027-02-04' WHERE id=460 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-05 09:00:00', end_date='2027-02-11 18:00:00', due_date='2027-02-11 18:00:00', client_start_date='2027-02-05', client_end_date='2027-02-11' WHERE id=414 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-12 09:00:00', end_date='2027-02-18 18:00:00', due_date='2027-02-18 18:00:00', client_start_date='2027-02-12', client_end_date='2027-02-18' WHERE id=415 AND company_id=2;

-- 416 Assistência e 517 OS V2 em paralelo; 568 Filtro de Obras junto com o 517
UPDATE planning_cards SET start_date='2027-02-19 09:00:00', end_date='2027-02-25 18:00:00', due_date='2027-02-25 18:00:00', client_start_date='2027-02-19', client_end_date='2027-02-25' WHERE id IN (416, 517) AND company_id=2;
UPDATE planning_cards SET assigned_to=@campagna, predecessor_id=415, predecessor_lag_days=1,
  start_date='2027-02-19 09:00:00', end_date='2027-02-25 18:00:00', due_date='2027-02-25 18:00:00',
  client_start_date='2027-02-19', client_end_date='2027-02-25'
 WHERE id=568 AND company_id=2;

UPDATE planning_cards SET start_date='2027-02-26 09:00:00', end_date='2027-03-04 18:00:00', due_date='2027-03-04 18:00:00', client_start_date='2027-02-26', client_end_date='2027-03-04' WHERE id=417 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-05 09:00:00', end_date='2027-03-11 18:00:00', due_date='2027-03-11 18:00:00', client_start_date='2027-03-05', client_end_date='2027-03-11' WHERE id=418 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-12 09:00:00', end_date='2027-03-18 18:00:00', due_date='2027-03-18 18:00:00', client_start_date='2027-03-12', client_end_date='2027-03-18' WHERE id=419 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-19 09:00:00', end_date='2027-03-25 18:00:00', due_date='2027-03-25 18:00:00', client_start_date='2027-03-19', client_end_date='2027-03-25' WHERE id=420 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-26 09:00:00', end_date='2027-04-01 18:00:00', due_date='2027-04-01 18:00:00', client_start_date='2027-03-26', client_end_date='2027-04-01' WHERE id=421 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-02 09:00:00', end_date='2027-04-08 18:00:00', due_date='2027-04-08 18:00:00', client_start_date='2027-04-02', client_end_date='2027-04-08' WHERE id=422 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-09 09:00:00', end_date='2027-04-15 18:00:00', due_date='2027-04-15 18:00:00', client_start_date='2027-04-09', client_end_date='2027-04-15' WHERE id=423 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-16 09:00:00', end_date='2027-04-22 18:00:00', due_date='2027-04-22 18:00:00', client_start_date='2027-04-16', client_end_date='2027-04-22' WHERE id=424 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-23 09:00:00', end_date='2027-04-29 18:00:00', due_date='2027-04-29 18:00:00', client_start_date='2027-04-23', client_end_date='2027-04-29' WHERE id=425 AND company_id=2;

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date, pc.predecessor_id, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON u.id = pc.assigned_to
WHERE pc.company_id = 2 AND (pc.status NOT IN ('completed','archived','denied') OR pc.id = 550)
ORDER BY COALESCE(pc.start_date, pc.due_date, '2099-12-31'), pc.id;
