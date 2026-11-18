-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v11
-- Nova ordem combinada com o cliente (prints da tela de prioridades).
-- O recesso CONTINUA 21/12/2026 a 04/01/2027 (global, não é alterado):
--   #547 termina na sexta 18/12 e o #548 começa depois do recesso, 05/01.
-- Não mexe: #499, #520, #566, #563, #569 (concluídos/aguardando cliente),
--   #516 (já em homologação), #542 (fica em 02–05/12, fora da fila).
-- #578 Cartão corporativo entrou depois da reunião: vai junto com o #530.
-- Nomes e responsáveis não mudam. Datas em dias corridos; o card seguinte
-- começa no dia depois do anterior (folga 1).
-- Rodar de uma vez no phpMyAdmin (produção). Em transação: se algum
-- comando falhar, nada é gravado. Pode rodar de novo (grava por id).
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- Antes do recesso
-- ---------------------------------------------------------------------
-- 1.1 Página Clube: logo depois do #510 (concluído em 13/11)
UPDATE planning_cards SET predecessor_id=510, predecessor_lag_days=1,
  start_date='2026-11-14 09:00:00', end_date='2026-11-20 18:00:00', due_date='2026-11-20 18:00:00', client_start_date='2026-11-14', client_end_date='2026-11-20' WHERE id=409 AND company_id=2;
-- 1.2 Página Comissões
UPDATE planning_cards SET predecessor_id=409, predecessor_lag_days=1,
  start_date='2026-11-21 09:00:00', end_date='2026-11-27 18:00:00', due_date='2026-11-27 18:00:00', client_start_date='2026-11-21', client_end_date='2026-11-27' WHERE id=410 AND company_id=2;
-- 1.3 Campos de Lucro
UPDATE planning_cards SET predecessor_id=410, predecessor_lag_days=1,
  start_date='2026-11-28 09:00:00', end_date='2026-12-04 18:00:00', due_date='2026-12-04 18:00:00', client_start_date='2026-11-28', client_end_date='2026-12-04' WHERE id=411 AND company_id=2;
-- Checklist de fechamento de vendas
UPDATE planning_cards SET predecessor_id=411, predecessor_lag_days=1,
  start_date='2026-12-05 09:00:00', end_date='2026-12-07 18:00:00', due_date='2026-12-07 18:00:00', client_start_date='2026-12-05', client_end_date='2026-12-07' WHERE id=565 AND company_id=2;
-- Compras: NF e cartão — data combinada com o cliente (11/12): folga 4 depois do #565
UPDATE planning_cards SET predecessor_id=565, predecessor_lag_days=4,
  start_date='2026-12-11 09:00:00', end_date='2026-12-15 18:00:00', due_date='2026-12-15 18:00:00', client_start_date='2026-12-11', client_end_date='2026-12-15' WHERE id=530 AND company_id=2;
-- Cartão corporativo: mesmo período do #530
UPDATE planning_cards SET predecessor_id=565, predecessor_lag_days=4,
  start_date='2026-12-11 09:00:00', end_date='2026-12-15 18:00:00', due_date='2026-12-15 18:00:00', client_start_date='2026-12-11', client_end_date='2026-12-15' WHERE id=578 AND company_id=2;
-- Permissão para alterar o valor + histórico (termina sexta 18/12)
UPDATE planning_cards SET predecessor_id=530, predecessor_lag_days=1,
  start_date='2026-12-16 09:00:00', end_date='2026-12-18 18:00:00', due_date='2026-12-18 18:00:00', client_start_date='2026-12-16', client_end_date='2026-12-18' WHERE id=547 AND company_id=2;

-- ---------------------------------------------------------------------
-- Recesso 21/12 a 04/01 (não muda). Depois dele:
-- ---------------------------------------------------------------------
-- Relatórios: valor original, juros — folga 18 (18/12 -> 05/01, pula o recesso)
UPDATE planning_cards SET predecessor_id=547, predecessor_lag_days=18,
  start_date='2027-01-05 09:00:00', end_date='2027-01-07 18:00:00', due_date='2027-01-07 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-07' WHERE id=548 AND company_id=2;
-- Financeiro: Tela de Contratos
UPDATE planning_cards SET predecessor_id=548, predecessor_lag_days=1,
  start_date='2027-01-08 09:00:00', end_date='2027-01-11 18:00:00', due_date='2027-01-11 18:00:00', client_start_date='2027-01-08', client_end_date='2027-01-11' WHERE id=549 AND company_id=2;
-- Contas a Receber: baixas, lote e tarifas
UPDATE planning_cards SET predecessor_id=549, predecessor_lag_days=1,
  start_date='2027-01-12 09:00:00', end_date='2027-01-16 18:00:00', due_date='2027-01-16 18:00:00', client_start_date='2027-01-12', client_end_date='2027-01-16' WHERE id=570 AND company_id=2;
-- 3 Dash Técnico
UPDATE planning_cards SET predecessor_id=570, predecessor_lag_days=1,
  start_date='2027-01-17 09:00:00', end_date='2027-01-23 18:00:00', due_date='2027-01-23 18:00:00', client_start_date='2027-01-17', client_end_date='2027-01-23' WHERE id=414 AND company_id=2;
-- 4 Agenda Técnica
UPDATE planning_cards SET predecessor_id=414, predecessor_lag_days=1,
  start_date='2027-01-24 09:00:00', end_date='2027-01-30 18:00:00', due_date='2027-01-30 18:00:00', client_start_date='2027-01-24', client_end_date='2027-01-30' WHERE id=415 AND company_id=2;
-- 5 Módulo Assistência
UPDATE planning_cards SET predecessor_id=415, predecessor_lag_days=1,
  start_date='2027-01-31 09:00:00', end_date='2027-02-06 18:00:00', due_date='2027-02-06 18:00:00', client_start_date='2027-01-31', client_end_date='2027-02-06' WHERE id=416 AND company_id=2;
-- 2.1 Pedido Quatri / Produção
UPDATE planning_cards SET predecessor_id=416, predecessor_lag_days=1,
  start_date='2027-02-07 09:00:00', end_date='2027-02-13 18:00:00', due_date='2027-02-13 18:00:00', client_start_date='2027-02-07', client_end_date='2027-02-13' WHERE id=413 AND company_id=2;
-- 2.2 Créditos por devolução
UPDATE planning_cards SET predecessor_id=413, predecessor_lag_days=1,
  start_date='2027-02-14 09:00:00', end_date='2027-02-20 18:00:00', due_date='2027-02-20 18:00:00', client_start_date='2027-02-14', client_end_date='2027-02-20' WHERE id=460 AND company_id=2;
-- 2 Dash Expedição
UPDATE planning_cards SET predecessor_id=460, predecessor_lag_days=1,
  start_date='2027-02-21 09:00:00', end_date='2027-02-27 18:00:00', due_date='2027-02-27 18:00:00', client_start_date='2027-02-21', client_end_date='2027-02-27' WHERE id=412 AND company_id=2;
-- 7 Comissão Individualizada
UPDATE planning_cards SET predecessor_id=412, predecessor_lag_days=1,
  start_date='2027-02-28 09:00:00', end_date='2027-03-06 18:00:00', due_date='2027-03-06 18:00:00', client_start_date='2027-02-28', client_end_date='2027-03-06' WHERE id=418 AND company_id=2;
-- 10 Emissão de Boleto V2
UPDATE planning_cards SET predecessor_id=418, predecessor_lag_days=1,
  start_date='2027-03-07 09:00:00', end_date='2027-03-13 18:00:00', due_date='2027-03-13 18:00:00', client_start_date='2027-03-07', client_end_date='2027-03-13' WHERE id=421 AND company_id=2;
-- 11 Lembrete de Pagamento V2
UPDATE planning_cards SET predecessor_id=421, predecessor_lag_days=1,
  start_date='2027-03-14 09:00:00', end_date='2027-03-20 18:00:00', due_date='2027-03-20 18:00:00', client_start_date='2027-03-14', client_end_date='2027-03-20' WHERE id=422 AND company_id=2;
-- 12 Conciliação V2
UPDATE planning_cards SET predecessor_id=422, predecessor_lag_days=1,
  start_date='2027-03-21 09:00:00', end_date='2027-03-27 18:00:00', due_date='2027-03-27 18:00:00', client_start_date='2027-03-21', client_end_date='2027-03-27' WHERE id=423 AND company_id=2;
-- 13 Nota Fiscal
UPDATE planning_cards SET predecessor_id=423, predecessor_lag_days=1,
  start_date='2027-03-28 09:00:00', end_date='2027-04-03 18:00:00', due_date='2027-04-03 18:00:00', client_start_date='2027-03-28', client_end_date='2027-04-03' WHERE id=424 AND company_id=2;
-- 14 Nova Proposta Comercial
UPDATE planning_cards SET predecessor_id=424, predecessor_lag_days=1,
  start_date='2027-04-04 09:00:00', end_date='2027-04-10 18:00:00', due_date='2027-04-10 18:00:00', client_start_date='2027-04-04', client_end_date='2027-04-10' WHERE id=425 AND company_id=2;
-- 8 Integração Bitrix
UPDATE planning_cards SET predecessor_id=425, predecessor_lag_days=1,
  start_date='2027-04-11 09:00:00', end_date='2027-04-17 18:00:00', due_date='2027-04-17 18:00:00', client_start_date='2027-04-11', client_end_date='2027-04-17' WHERE id=419 AND company_id=2;
-- 9 Gestão à Vista V2
UPDATE planning_cards SET predecessor_id=419, predecessor_lag_days=1,
  start_date='2027-04-18 09:00:00', end_date='2027-04-24 18:00:00', due_date='2027-04-24 18:00:00', client_start_date='2027-04-18', client_end_date='2027-04-24' WHERE id=420 AND company_id=2;
-- 6 Relatório de Fechamento de Obra
UPDATE planning_cards SET predecessor_id=420, predecessor_lag_days=1,
  start_date='2027-04-25 09:00:00', end_date='2027-05-01 18:00:00', due_date='2027-05-01 18:00:00', client_start_date='2027-04-25', client_end_date='2027-05-01' WHERE id=417 AND company_id=2;
-- Filtro das Obras em Andamento + OS V2/Gestão à Vista Novo, juntos
UPDATE planning_cards SET predecessor_id=417, predecessor_lag_days=1,
  start_date='2027-05-02 09:00:00', end_date='2027-05-08 18:00:00', due_date='2027-05-08 18:00:00', client_start_date='2027-05-02', client_end_date='2027-05-08' WHERE id IN (568, 517) AND company_id=2;

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência: cards em aberto da Quatri, na ordem das datas
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status,
       DATE_FORMAT(pc.start_date, '%d/%m/%Y') AS inicio, DATE_FORMAT(pc.end_date, '%d/%m/%Y') AS fim,
       DATEDIFF(pc.end_date, pc.start_date) + 1 AS dias,
       pc.predecessor_id AS depende_de, pc.predecessor_lag_days AS folga, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON u.id = pc.assigned_to
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date, pc.due_date, '2099-12-31'), pc.id;
