-- =====================================================================
-- REAJUSTE DO CRONOGRAMA QUATRI (company_id = 2) — v3 (03/11/2026)
-- Substitui a v2 (_cronograma_quatri_reajuste_v2.sql). Rode SÓ esta.
-- Rodar no phpMyAdmin (produção), aba SQL, de uma vez. Script de DADOS.
-- Só UPDATE por id. Recesso respeitado: 21/12/2026 a 04/01/2027.
-- Padrão: início 09:00, fim 18:00, blocos de 7 dias corridos.
-- Mudança em relação à v2: Dash Compras vai de 30/10 a 06/11 e a fila a
-- partir do 510 começa em 07/11 (tudo depois anda 2 dias).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Em andamento
-- ---------------------------------------------------------------------
-- 0.1 — Ajuste OS iluminação x automação: 26/10 -> 04/11
UPDATE planning_cards SET
  start_date='2026-10-26 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-10-26', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=483;

-- 1 — Dash Compras (ainda em correção): 30/10 -> 06/11
UPDATE planning_cards SET
  start_date='2026-10-30 09:00:00', end_date='2026-11-06 18:00:00', due_date='2026-11-06 18:00:00',
  client_start_date='2026-10-30', client_end_date='2026-11-06',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=408;

-- ---------------------------------------------------------------------
-- 2) Os 4 em homologação: hoje (03/11)
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

-- 499 — Aprimorar cadastro de operadoras de cartão
UPDATE planning_cards SET
  start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00',
  client_start_date='2026-11-03', client_end_date='2026-11-04',
  predecessor_id=NULL, predecessor_lag_days=0
WHERE id=499;

-- ---------------------------------------------------------------------
-- 4) Fila a partir de 07/11 (encadeada por predecessor, folga 1 dia)
-- ---------------------------------------------------------------------
-- 510 — Remover 2 passos da criação do orçamento: 07–13/11 (depois do Dash Compras)
UPDATE planning_cards SET
  start_date='2026-11-07 09:00:00', end_date='2026-11-13 18:00:00', due_date='2026-11-13 18:00:00',
  client_start_date='2026-11-07', client_end_date='2026-11-13',
  predecessor_id=408, predecessor_lag_days=1
WHERE id=510;

-- 515 — Relatório Gerencial Financeiro e 516 — Filtros no Orçamento: 14–20/11 (paralelos)
UPDATE planning_cards SET
  start_date='2026-11-14 09:00:00', end_date='2026-11-20 18:00:00', due_date='2026-11-20 18:00:00',
  client_start_date='2026-11-14', client_end_date='2026-11-20',
  predecessor_id=510, predecessor_lag_days=1
WHERE id IN (515, 516);

-- 1.1 — Página Clube: 21–27/11
UPDATE planning_cards SET start_date='2026-11-21 09:00:00', end_date='2026-11-27 18:00:00', due_date='2026-11-27 18:00:00',
  client_start_date='2026-11-21', client_end_date='2026-11-27', predecessor_id=515, predecessor_lag_days=1 WHERE id=409;

-- 1.2 — Página Comissões: 28/11–04/12
UPDATE planning_cards SET start_date='2026-11-28 09:00:00', end_date='2026-12-04 18:00:00', due_date='2026-12-04 18:00:00',
  client_start_date='2026-11-28', client_end_date='2026-12-04', predecessor_id=409, predecessor_lag_days=1 WHERE id=410;

-- 1.3 — Campos de Lucro: 05–11/12
UPDATE planning_cards SET start_date='2026-12-05 09:00:00', end_date='2026-12-11 18:00:00', due_date='2026-12-11 18:00:00',
  client_start_date='2026-12-05', client_end_date='2026-12-11', predecessor_id=410, predecessor_lag_days=1 WHERE id=411;

-- 2 — Dash Expedição: 12–18/12
UPDATE planning_cards SET start_date='2026-12-12 09:00:00', end_date='2026-12-18 18:00:00', due_date='2026-12-18 18:00:00',
  client_start_date='2026-12-12', client_end_date='2026-12-18', predecessor_id=411, predecessor_lag_days=1 WHERE id=412;

-- 2.1 — Expedição Pedido Quatri / Produção: 19–20/12 (parte antes do recesso)
UPDATE planning_cards SET start_date='2026-12-19 09:00:00', end_date='2026-12-20 18:00:00', due_date='2026-12-20 18:00:00',
  client_start_date='2026-12-19', client_end_date='2026-12-20', predecessor_id=412, predecessor_lag_days=1 WHERE id=413;

-- 518 — 2.1 (continuação): 05–09/01 (completa os 7 dias depois do recesso)
UPDATE planning_cards SET
  title='2.1 — Expedição — Pedido Quatri / Produção (continuação)',
  description='<p>Continuação do <strong>2.1 — Expedição (Pedido Quatri / Produção)</strong> após o recesso de fim de ano. A primeira parte roda em 19–20/12; esta continuação retoma em 05/01.</p>',
  start_date='2027-01-05 09:00:00', end_date='2027-01-09 18:00:00', due_date='2027-01-09 18:00:00',
  client_start_date='2027-01-05', client_end_date='2027-01-09', predecessor_id=413, predecessor_lag_days=1
WHERE id=518;

-- 2.2 — Regra de créditos por devolução: 10–16/01
UPDATE planning_cards SET start_date='2027-01-10 09:00:00', end_date='2027-01-16 18:00:00', due_date='2027-01-16 18:00:00',
  client_start_date='2027-01-10', client_end_date='2027-01-16', predecessor_id=518, predecessor_lag_days=1 WHERE id=460;

-- 3 — Dash Técnico: 17–23/01
UPDATE planning_cards SET start_date='2027-01-17 09:00:00', end_date='2027-01-23 18:00:00', due_date='2027-01-23 18:00:00',
  client_start_date='2027-01-17', client_end_date='2027-01-23', predecessor_id=460, predecessor_lag_days=1 WHERE id=414;

-- 4 — Agenda Técnica: 24–30/01
UPDATE planning_cards SET start_date='2027-01-24 09:00:00', end_date='2027-01-30 18:00:00', due_date='2027-01-30 18:00:00',
  client_start_date='2027-01-24', client_end_date='2027-01-30', predecessor_id=414, predecessor_lag_days=1 WHERE id=415;

-- 5 — Módulo Assistência e 517 — OS V2 + Gestão à Vista (paralelos): 31/01–06/02
UPDATE planning_cards SET start_date='2027-01-31 09:00:00', end_date='2027-02-06 18:00:00', due_date='2027-02-06 18:00:00',
  client_start_date='2027-01-31', client_end_date='2027-02-06', predecessor_id=415, predecessor_lag_days=1 WHERE id IN (416, 517);

-- 6 — Relatório de Fechamento de Obra: 07–13/02
UPDATE planning_cards SET start_date='2027-02-07 09:00:00', end_date='2027-02-13 18:00:00', due_date='2027-02-13 18:00:00',
  client_start_date='2027-02-07', client_end_date='2027-02-13', predecessor_id=416, predecessor_lag_days=1 WHERE id=417;

-- 7 — Comissão Individualizada: 14–20/02
UPDATE planning_cards SET start_date='2027-02-14 09:00:00', end_date='2027-02-20 18:00:00', due_date='2027-02-20 18:00:00',
  client_start_date='2027-02-14', client_end_date='2027-02-20', predecessor_id=417, predecessor_lag_days=1 WHERE id=418;

-- 8 — Integração Bitrix: 21–27/02
UPDATE planning_cards SET start_date='2027-02-21 09:00:00', end_date='2027-02-27 18:00:00', due_date='2027-02-27 18:00:00',
  client_start_date='2027-02-21', client_end_date='2027-02-27', predecessor_id=418, predecessor_lag_days=1 WHERE id=419;

-- 9 — Gestão à Vista V2: 28/02–06/03
UPDATE planning_cards SET start_date='2027-02-28 09:00:00', end_date='2027-03-06 18:00:00', due_date='2027-03-06 18:00:00',
  client_start_date='2027-02-28', client_end_date='2027-03-06', predecessor_id=419, predecessor_lag_days=1 WHERE id=420;

-- 10 — Emissão de Boleto V2: 07–13/03
UPDATE planning_cards SET start_date='2027-03-07 09:00:00', end_date='2027-03-13 18:00:00', due_date='2027-03-13 18:00:00',
  client_start_date='2027-03-07', client_end_date='2027-03-13', predecessor_id=420, predecessor_lag_days=1 WHERE id=421;

-- 11 — Lembrete de Pagamento V2: 14–20/03
UPDATE planning_cards SET start_date='2027-03-14 09:00:00', end_date='2027-03-20 18:00:00', due_date='2027-03-20 18:00:00',
  client_start_date='2027-03-14', client_end_date='2027-03-20', predecessor_id=421, predecessor_lag_days=1 WHERE id=422;

-- 12 — Conciliação V2: 21–27/03
UPDATE planning_cards SET start_date='2027-03-21 09:00:00', end_date='2027-03-27 18:00:00', due_date='2027-03-27 18:00:00',
  client_start_date='2027-03-21', client_end_date='2027-03-27', predecessor_id=422, predecessor_lag_days=1 WHERE id=423;

-- 13 — Nota Fiscal: 28/03–03/04
UPDATE planning_cards SET start_date='2027-03-28 09:00:00', end_date='2027-04-03 18:00:00', due_date='2027-04-03 18:00:00',
  client_start_date='2027-03-28', client_end_date='2027-04-03', predecessor_id=423, predecessor_lag_days=1 WHERE id=424;

-- 14 — Nova Proposta Comercial: 04–10/04
UPDATE planning_cards SET start_date='2027-04-04 09:00:00', end_date='2027-04-10 18:00:00', due_date='2027-04-10 18:00:00',
  client_start_date='2027-04-04', client_end_date='2027-04-10', predecessor_id=424, predecessor_lag_days=1 WHERE id=425;

-- ---------------------------------------------------------------------
-- CONFERÊNCIA
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date,
       pc.predecessor_id, pred.title AS predecessor_titulo, pc.predecessor_lag_days
FROM planning_cards pc
LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date,'2000-01-01'), pc.id;
