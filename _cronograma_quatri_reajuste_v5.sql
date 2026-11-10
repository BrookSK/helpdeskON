-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v5
--  1) 515 Relatório Gerencial (data de criação do contrato): já feito,
--     06/11 a 08/11, vai para Em homologação e sai da sequência.
--  2) 516 Filtros no Orçamento: mais simples, 14/11 a 16/11.
--  3) 530 Compras começa 17/11 e a sequência anda 4 dias para trás,
--     mantendo a duração de cada card.
--  4) 412 Dash Expedição volta a ser inteiro (7 dias, 13/12 a 19/12):
--     agora cabe antes do recesso (21/12/2026 a 04/01/2027). O card de
--     continuação (518) fica sem uso e é ARQUIVADO (reversível).
--  5) 411 Campos de Lucro: ponto de atenção sobre custo bancário na descrição.
-- Rodar de uma vez no phpMyAdmin (produção). Script de DADOS, em transação:
-- se algum comando falhar, nada é gravado.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) 515 — já feito: 06/11 a 08/11, Em homologação, sem predecessor
-- ---------------------------------------------------------------------
UPDATE planning_cards SET status='em_homologacao', predecessor_id=NULL, predecessor_lag_days=0,
  start_date='2026-11-06 09:00:00', end_date='2026-11-08 18:00:00', due_date='2026-11-08 18:00:00',
  client_start_date='2026-11-06', client_end_date='2026-11-08'
 WHERE id=515 AND company_id=2;

-- ---------------------------------------------------------------------
-- 2) 516 — 3 dias, 14/11 a 16/11 (continua depois do 510)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET
  start_date='2026-11-14 09:00:00', end_date='2026-11-16 18:00:00', due_date='2026-11-16 18:00:00',
  client_start_date='2026-11-14', client_end_date='2026-11-16'
 WHERE id=516 AND company_id=2;

-- ---------------------------------------------------------------------
-- 3) Sequência: 530 passa a depender do 516; todos andam -4 dias
-- ---------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id=516, predecessor_lag_days=1,
  start_date='2026-11-17 09:00:00', end_date='2026-11-21 18:00:00', due_date='2026-11-21 18:00:00', client_start_date='2026-11-17', client_end_date='2026-11-21' WHERE id=530 AND company_id=2;
UPDATE planning_cards SET start_date='2026-11-22 09:00:00', end_date='2026-11-28 18:00:00', due_date='2026-11-28 18:00:00', client_start_date='2026-11-22', client_end_date='2026-11-28' WHERE id=409 AND company_id=2;
UPDATE planning_cards SET start_date='2026-11-29 09:00:00', end_date='2026-12-05 18:00:00', due_date='2026-12-05 18:00:00', client_start_date='2026-11-29', client_end_date='2026-12-05' WHERE id=410 AND company_id=2;
UPDATE planning_cards SET start_date='2026-12-06 09:00:00', end_date='2026-12-12 18:00:00', due_date='2026-12-12 18:00:00', client_start_date='2026-12-06', client_end_date='2026-12-12' WHERE id=411 AND company_id=2;

-- ---------------------------------------------------------------------
-- 4) Dash Expedição inteiro antes do recesso; continuação arquivada
-- ---------------------------------------------------------------------
UPDATE planning_cards SET start_date='2026-12-13 09:00:00', end_date='2026-12-19 18:00:00', due_date='2026-12-19 18:00:00', client_start_date='2026-12-13', client_end_date='2026-12-19' WHERE id=412 AND company_id=2;

-- Quem dependia da continuação (413) passa a depender do 412.
UPDATE planning_cards SET predecessor_id=412, predecessor_lag_days=1 WHERE predecessor_id=518 AND company_id=2;
UPDATE planning_cards SET status='archived', predecessor_id=NULL WHERE id=518 AND company_id=2;

-- Depois do recesso: tudo anda -3 dias (20/12 é domingo e fica livre)
UPDATE planning_cards SET start_date='2027-01-05 09:00:00', end_date='2027-01-11 18:00:00', due_date='2027-01-11 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-11' WHERE id=413 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-12 09:00:00', end_date='2027-01-18 18:00:00', due_date='2027-01-18 18:00:00', client_start_date='2027-01-12', client_end_date='2027-01-18' WHERE id=460 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-19 09:00:00', end_date='2027-01-25 18:00:00', due_date='2027-01-25 18:00:00', client_start_date='2027-01-19', client_end_date='2027-01-25' WHERE id=414 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-26 09:00:00', end_date='2027-02-01 18:00:00', due_date='2027-02-01 18:00:00', client_start_date='2027-01-26', client_end_date='2027-02-01' WHERE id=415 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-02 09:00:00', end_date='2027-02-08 18:00:00', due_date='2027-02-08 18:00:00', client_start_date='2027-02-02', client_end_date='2027-02-08' WHERE id IN (416, 517) AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-09 09:00:00', end_date='2027-02-15 18:00:00', due_date='2027-02-15 18:00:00', client_start_date='2027-02-09', client_end_date='2027-02-15' WHERE id=417 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-16 09:00:00', end_date='2027-02-22 18:00:00', due_date='2027-02-22 18:00:00', client_start_date='2027-02-16', client_end_date='2027-02-22' WHERE id=418 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-23 09:00:00', end_date='2027-03-01 18:00:00', due_date='2027-03-01 18:00:00', client_start_date='2027-02-23', client_end_date='2027-03-01' WHERE id=419 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-02 09:00:00', end_date='2027-03-08 18:00:00', due_date='2027-03-08 18:00:00', client_start_date='2027-03-02', client_end_date='2027-03-08' WHERE id=420 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-09 09:00:00', end_date='2027-03-15 18:00:00', due_date='2027-03-15 18:00:00', client_start_date='2027-03-09', client_end_date='2027-03-15' WHERE id=421 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-16 09:00:00', end_date='2027-03-22 18:00:00', due_date='2027-03-22 18:00:00', client_start_date='2027-03-16', client_end_date='2027-03-22' WHERE id=422 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-23 09:00:00', end_date='2027-03-29 18:00:00', due_date='2027-03-29 18:00:00', client_start_date='2027-03-23', client_end_date='2027-03-29' WHERE id=423 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-30 09:00:00', end_date='2027-04-05 18:00:00', due_date='2027-04-05 18:00:00', client_start_date='2027-03-30', client_end_date='2027-04-05' WHERE id=424 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-06 09:00:00', end_date='2027-04-12 18:00:00', due_date='2027-04-12 18:00:00', client_start_date='2027-04-06', client_end_date='2027-04-12' WHERE id=425 AND company_id=2;

-- ---------------------------------------------------------------------
-- 5) 411 Campos de Lucro — ponto de atenção (acrescenta ao fim da
--    descrição; não duplica se o script rodar de novo)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
  '<p><strong>Ponto de atenção — custo bancário no cálculo do lucro:</strong></p>',
  '<p>O sistema já tem o cadastro de operadoras de cartão, que cobra as taxas de acordo com a operadora, o cartão/bandeira e o número de parcelas (ver card #499). Os campos de lucro deste card precisam levar isso em conta:</p>',
  '<ul>',
  '<li>Ao informar cartão de crédito como forma de pagamento, permitir selecionar qual é o cartão/operadora.</li>',
  '<li>Com o cartão e o número de parcelas, puxar automaticamente o custo bancário correspondente.</li>',
  '<li>Usar esse custo no cálculo, para a margem de lucro sair correta.</li>',
  '</ul>')
 WHERE id=411 AND company_id=2
   AND (description IS NULL OR description NOT LIKE '%Ponto de atenção — custo bancário%');

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.start_date, pc.end_date, pc.predecessor_id, pc.predecessor_lag_days
FROM planning_cards pc
WHERE pc.company_id = 2 AND (pc.status NOT IN ('completed','archived','denied') OR pc.id = 518)
ORDER BY COALESCE(pc.start_date, pc.due_date, '2000-01-01'), pc.id;

SELECT id, title, description FROM planning_cards WHERE id = 411;
