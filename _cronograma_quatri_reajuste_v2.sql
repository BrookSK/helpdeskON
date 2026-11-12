-- =====================================================================
-- REAJUSTE GERAL DO CRONOGRAMA QUATRI (company_id = 2) — v2 (03/11/2026)
-- Rodar no phpMyAdmin (produção), aba SQL, de uma vez. Script de DADOS.
-- Só UPDATE por id (sem comparar título => sem problema de collation).
-- Recesso respeitado: 21/12/2026 a 04/01/2027.
-- Padrão: início 09:00, fim 18:00, blocos de 7 dias corridos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Em andamento: 0.1 e 1 vão até amanhã (04/11)
-- ---------------------------------------------------------------------
-- 0.1 — Ajuste OS iluminação x automação: 26/10 -> 04/11
UPDATE planning_cards SET
  start_date='2026-10-26 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-10-26', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=483;

-- 1 — Dash Compras: 29/10 -> 04/11
UPDATE planning_cards SET
  start_date='2026-10-29 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-10-29', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=408;

-- ---------------------------------------------------------------------
-- 2) Os 4 em homologação: hoje (03/11)
--    507 Modal de Parcelas | 513 Comissão profissional | 505 Proposta Victor | 506 Contrato Caio
-- ---------------------------------------------------------------------
UPDATE planning_cards SET
  start_date='2026-11-03 09:00:00', end_date='2026-11-03 18:00:00', due_date='2026-11-03 18:00:00',
  client_start_date='2026-11-03', client_end_date='2026-11-03'
WHERE id IN (507, 513, 505, 506);

-- ---------------------------------------------------------------------
-- 3) Hoje -> amanhã (03/11 a 04/11)
-- ---------------------------------------------------------------------
-- 520 — Ajuste da proposta Vitor: EM ANDAMENTO + URGENTE
UPDATE planning_cards SET
  status='in_progress', priority='urgent',
  start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-11-03', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=520;

-- 499 — Aprimorar cadastro de operadoras de cartão (taxas por bandeira)
UPDATE planning_cards SET
  start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-11-03', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=499;

-- ---------------------------------------------------------------------
-- 4) Nova fila a partir de 05/11 (encadeada por predecessor, folga 1 dia)
-- ---------------------------------------------------------------------
-- 510 — Remover 2 passos da criação do orçamento: 05–11/11 (depois do 520)
UPDATE planning_cards SET
  start_date='2026-11-05 09:00:00', end_date='2026-11-11 18:00:00', due_date='2026-11-11 18:00:00',
  client_start_date='2026-11-05', client_end_date='2026-11-11',
  predecessor_id=520, predecessor_lag_days=1
WHERE id=510;

-- 515 — Relatório Gerencial Financeiro: 12–18/11 (depois do 510)
-- 516 — Mais filtros na tela de Orçamento: 12–18/11 (em PARALELO, também depois do 510)
UPDATE planning_cards SET
  start_date='2026-11-12 09:00:00', end_date='2026-11-18 18:00:00', due_date='2026-11-18 18:00:00',
  client_start_date='2026-11-12', client_end_date='2026-11-18',
  predecessor_id=510, predecessor_lag_days=1
WHERE id IN (515, 516);

-- 1.1 — Página Clube: 19–25/11 (depois do 515)
UPDATE planning_cards SET start_date='2026-11-19 09:00:00', end_date='2026-11-25 18:00:00', due_date='2026-11-25 18:00:00',
  client_start_date='2026-11-19', client_end_date='2026-11-25', predecessor_id=515, predecessor_lag_days=1 WHERE id=409;

-- 1.2 — Página Comissões: 26/11–02/12
UPDATE planning_cards SET start_date='2026-11-26 09:00:00', end_date='2026-12-02 18:00:00', due_date='2026-12-02 18:00:00',
  client_start_date='2026-11-26', client_end_date='2026-12-02', predecessor_id=409, predecessor_lag_days=1 WHERE id=410;

-- 1.3 — Campos de Lucro: 03–09/12
UPDATE planning_cards SET start_date='2026-12-03 09:00:00', end_date='2026-12-09 18:00:00', due_date='2026-12-09 18:00:00',
  client_start_date='2026-12-03', client_end_date='2026-12-09', predecessor_id=410, predecessor_lag_days=1 WHERE id=411;

-- 2 — Dash Expedição: 10–16/12
UPDATE planning_cards SET start_date='2026-12-10 09:00:00', end_date='2026-12-16 18:00:00', due_date='2026-12-16 18:00:00',
  client_start_date='2026-12-10', client_end_date='2026-12-16', predecessor_id=411, predecessor_lag_days=1 WHERE id=412;

-- 2.1 — Expedição Pedido Quatri / Produção: 17–20/12 (parte antes do recesso)
UPDATE planning_cards SET start_date='2026-12-17 09:00:00', end_date='2026-12-20 18:00:00', due_date='2026-12-20 18:00:00',
  client_start_date='2026-12-17', client_end_date='2026-12-20', predecessor_id=412, predecessor_lag_days=1 WHERE id=413;

-- O card 518 (antes "3.1 — Dash Técnico (continuação)") vira a continuação do 2.1,
-- já que agora quem cai no recesso é o 2.1 e o Dash Técnico fica com semana cheia.
-- 2.1 (continuação): 05–07/01 (completa os dias que o recesso interrompeu)
UPDATE planning_cards SET
  title='2.1 — Expedição — Pedido Quatri / Produção (continuação)',
  description='<p>Continuação do <strong>2.1 — Expedição (Pedido Quatri / Produção)</strong> após o recesso de fim de ano. A primeira parte roda em 17–20/12; esta continuação retoma em 05/01.</p>',
  start_date='2027-01-05 09:00:00', end_date='2027-01-07 18:00:00', due_date='2027-01-07 18:00:00',
  client_start_date='2027-01-05', client_end_date='2027-01-07', predecessor_id=413, predecessor_lag_days=1
WHERE id=518;

-- 2.2 — Regra de créditos por devolução: 08–14/01
UPDATE planning_cards SET start_date='2027-01-08 09:00:00', end_date='2027-01-14 18:00:00', due_date='2027-01-14 18:00:00',
  client_start_date='2027-01-08', client_end_date='2027-01-14', predecessor_id=518, predecessor_lag_days=1 WHERE id=460;

-- 3 — Dash Técnico: 15–21/01 (semana cheia, fora do recesso)
UPDATE planning_cards SET start_date='2027-01-15 09:00:00', end_date='2027-01-21 18:00:00', due_date='2027-01-21 18:00:00',
  client_start_date='2027-01-15', client_end_date='2027-01-21', predecessor_id=460, predecessor_lag_days=1 WHERE id=414;

-- 4 — Agenda Técnica: 22–28/01
UPDATE planning_cards SET start_date='2027-01-22 09:00:00', end_date='2027-01-28 18:00:00', due_date='2027-01-28 18:00:00',
  client_start_date='2027-01-22', client_end_date='2027-01-28', predecessor_id=414, predecessor_lag_days=1 WHERE id=415;

-- 5 — Módulo Assistência: 29/01–04/02
UPDATE planning_cards SET start_date='2027-01-29 09:00:00', end_date='2027-02-04 18:00:00', due_date='2027-02-04 18:00:00',
  client_start_date='2027-01-29', client_end_date='2027-02-04', predecessor_id=415, predecessor_lag_days=1 WHERE id=416;

-- OS V2 + Gestão à Vista: em PARALELO com o 5 (29/01–04/02), também depois do 4
UPDATE planning_cards SET start_date='2027-01-29 09:00:00', end_date='2027-02-04 18:00:00', due_date='2027-02-04 18:00:00',
  client_start_date='2027-01-29', client_end_date='2027-02-04', predecessor_id=415, predecessor_lag_days=1 WHERE id=517;

-- 6 — Relatório de Fechamento de Obra: 05–11/02
UPDATE planning_cards SET start_date='2027-02-05 09:00:00', end_date='2027-02-11 18:00:00', due_date='2027-02-11 18:00:00',
  client_start_date='2027-02-05', client_end_date='2027-02-11', predecessor_id=416, predecessor_lag_days=1 WHERE id=417;

-- 7 — Comissão Individualizada: 12–18/02
UPDATE planning_cards SET start_date='2027-02-12 09:00:00', end_date='2027-02-18 18:00:00', due_date='2027-02-18 18:00:00',
  client_start_date='2027-02-12', client_end_date='2027-02-18', predecessor_id=417, predecessor_lag_days=1 WHERE id=418;

-- 8 — Integração Bitrix: 19–25/02
UPDATE planning_cards SET start_date='2027-02-19 09:00:00', end_date='2027-02-25 18:00:00', due_date='2027-02-25 18:00:00',
  client_start_date='2027-02-19', client_end_date='2027-02-25', predecessor_id=418, predecessor_lag_days=1 WHERE id=419;

-- 9 — Gestão à Vista V2: 26/02–04/03
UPDATE planning_cards SET start_date='2027-02-26 09:00:00', end_date='2027-03-04 18:00:00', due_date='2027-03-04 18:00:00',
  client_start_date='2027-02-26', client_end_date='2027-03-04', predecessor_id=419, predecessor_lag_days=1 WHERE id=420;

-- 10 — Emissão de Boleto V2: 05–11/03
UPDATE planning_cards SET start_date='2027-03-05 09:00:00', end_date='2027-03-11 18:00:00', due_date='2027-03-11 18:00:00',
  client_start_date='2027-03-05', client_end_date='2027-03-11', predecessor_id=420, predecessor_lag_days=1 WHERE id=421;

-- 11 — Lembrete de Pagamento V2: 12–18/03
UPDATE planning_cards SET start_date='2027-03-12 09:00:00', end_date='2027-03-18 18:00:00', due_date='2027-03-18 18:00:00',
  client_start_date='2027-03-12', client_end_date='2027-03-18', predecessor_id=421, predecessor_lag_days=1 WHERE id=422;

-- 12 — Conciliação V2: 19–25/03
UPDATE planning_cards SET start_date='2027-03-19 09:00:00', end_date='2027-03-25 18:00:00', due_date='2027-03-25 18:00:00',
  client_start_date='2027-03-19', client_end_date='2027-03-25', predecessor_id=422, predecessor_lag_days=1 WHERE id=423;

-- 13 — Nota Fiscal: 26/03–01/04
UPDATE planning_cards SET start_date='2027-03-26 09:00:00', end_date='2027-04-01 18:00:00', due_date='2027-04-01 18:00:00',
  client_start_date='2027-03-26', client_end_date='2027-04-01', predecessor_id=423, predecessor_lag_days=1 WHERE id=424;

-- 14 — Nova Proposta Comercial: 02–08/04
UPDATE planning_cards SET start_date='2027-04-02 09:00:00', end_date='2027-04-08 18:00:00', due_date='2027-04-08 18:00:00',
  client_start_date='2027-04-02', client_end_date='2027-04-08', predecessor_id=424, predecessor_lag_days=1 WHERE id=425;

-- ---------------------------------------------------------------------
-- CONFERÊNCIA
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date,
       pc.predecessor_id, pred.title AS predecessor_titulo, pc.predecessor_lag_days
FROM planning_cards pc
LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date,'2000-01-01'), pc.id;
