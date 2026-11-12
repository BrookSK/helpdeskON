-- =====================================================================
-- Ajuste do cronograma Quatri — 2026-10-26 (arquivo NOVO; não sobrescreve
-- o anterior). Cards 0 / 0.1 + datas em dias inteiros + folga de 1 dia na
-- cadeia, PULANDO o recesso de fim de ano (21/12/2026 a 04/01/2027).
-- ---------------------------------------------------------------------
-- NÃO é migration de schema. É script de DADOS. Rode manualmente no
-- phpMyAdmin (produção: banco "helpdesk_on") na aba SQL, de uma vez só.
-- Exporte a tabela planning_cards antes (rollback).
--
-- CONTEXTO: a cascata automática (PlanningRules) reescreveu as datas em
-- horas quebradas e jogou cards DENTRO do recesso, além de dessincronizar
-- start/end de client_start/client_end. Este script regrava tudo à mão,
-- em dias inteiros, com gap real de 1 dia entre cards e nada no recesso.
--
-- O QUE FAZ:
--   1) Card "0 - Ajuste proposta (Vitor)" (id 477): termina 28/10, início
--      da cadeia (sem predecessor).
--   2) Cria "0.1 — Ajuste OS iluminação x automação (tipo de OS e serviço)"
--      (26->28/10), paralelo ao 0 (predecessor = 0, folga 0).
--   3) Card "1 — Dash Compras" (408): predecessor = 0.1, começa 29/10.
--   4) Datas de 1..14 em dias inteiros, folga de 1 dia, pulando o recesso
--      (3 trunca em 20/12; 4 recomeça em 05/01). Ressincroniza client_*.
--   5) predecessor_lag_days = 1 em toda a cadeia (exceção: 0.1 -> 0 = 0).
--
-- IDEMPOTENTE: o 0.1 é ancorado pelo título; os demais por ID fixo.
--
-- OBS: a regra de "pular recesso" ainda NÃO está no código nesta data; as
-- datas abaixo são calculadas à mão. A feature que automatiza isso vem na
-- migration de schema planning_recess_periods + alteração do PlanningRules.
-- =====================================================================

SET @author := (SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 ORDER BY id ASC LIMIT 1);
SET @author := COALESCE(@author, (SELECT id FROM users ORDER BY id ASC LIMIT 1));
SET @assignee := 11;
SET @company  := 2;

SET @title01 := '0.1 — Ajuste OS iluminação x automação (tipo de OS e serviço)';

-- (1) Card 0 (id 477): termina 28/10, início da cadeia.
UPDATE planning_cards SET
    start_date = '2026-10-26 15:24:00', end_date = '2026-10-28 18:00:00',
    client_start_date = '2026-10-26', client_end_date = '2026-10-28',
    due_date = '2026-10-28 18:00:00',
    predecessor_id = NULL, predecessor_lag_days = 0
WHERE id = 477;

-- (2) Card 0.1 (novo): 26->28/10, paralelo ao 0. Idempotente pelo título.
DELETE FROM planning_cards WHERE title = @title01;

INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, start_date, end_date, client_start_date, client_end_date, due_date,
     predecessor_id, predecessor_lag_days)
VALUES
(@title01,
 CONCAT(
   '<p>Na criação da OS, quem é de <strong>iluminação</strong> está puxando o orçamento de <strong>automação</strong> — e não pode.</p>',
   '<p>Ajustar também o <strong>tipo da OS</strong> e o <strong>tipo de serviço</strong> da OS.</p>'
 ),
 @company, @assignee, @author, 'high', 'open',
 0, '2026-10-26 09:00:00', '2026-10-28 18:00:00', '2026-10-26', '2026-10-28', '2026-10-28 18:00:00',
 477, 0);

SET @id01 := (SELECT id FROM planning_cards WHERE title = @title01 ORDER BY id DESC LIMIT 1);

-- (3)+(4) Datas de 1..14 em dias inteiros, folga de 1 dia, pulando recesso.
UPDATE planning_cards SET start_date='2026-10-29 09:00:00', end_date='2026-11-04 18:00:00', client_start_date='2026-10-29', client_end_date='2026-11-04', due_date='2026-11-04 18:00:00', predecessor_id=@id01, predecessor_lag_days=1 WHERE id=408; -- 1
UPDATE planning_cards SET start_date='2026-11-05 09:00:00', end_date='2026-11-11 18:00:00', client_start_date='2026-11-05', client_end_date='2026-11-11', due_date='2026-11-11 18:00:00', predecessor_id=408, predecessor_lag_days=1 WHERE id=409; -- 1.1
UPDATE planning_cards SET start_date='2026-11-12 09:00:00', end_date='2026-11-18 18:00:00', client_start_date='2026-11-12', client_end_date='2026-11-18', due_date='2026-11-18 18:00:00', predecessor_id=409, predecessor_lag_days=1 WHERE id=410; -- 1.2
UPDATE planning_cards SET start_date='2026-11-19 09:00:00', end_date='2026-11-25 18:00:00', client_start_date='2026-11-19', client_end_date='2026-11-25', due_date='2026-11-25 18:00:00', predecessor_id=410, predecessor_lag_days=1 WHERE id=411; -- 1.3
UPDATE planning_cards SET start_date='2026-11-26 09:00:00', end_date='2026-12-02 18:00:00', client_start_date='2026-11-26', client_end_date='2026-12-02', due_date='2026-12-02 18:00:00', predecessor_id=411, predecessor_lag_days=1 WHERE id=412; -- 2
UPDATE planning_cards SET start_date='2026-12-03 09:00:00', end_date='2026-12-09 18:00:00', client_start_date='2026-12-03', client_end_date='2026-12-09', due_date='2026-12-09 18:00:00', predecessor_id=412, predecessor_lag_days=1 WHERE id=413; -- 2.1
UPDATE planning_cards SET start_date='2026-12-10 09:00:00', end_date='2026-12-16 18:00:00', client_start_date='2026-12-10', client_end_date='2026-12-16', due_date='2026-12-16 18:00:00', predecessor_id=413, predecessor_lag_days=1 WHERE id=460; -- 2.2
UPDATE planning_cards SET start_date='2026-12-17 09:00:00', end_date='2026-12-20 18:00:00', client_start_date='2026-12-17', client_end_date='2026-12-20', due_date='2026-12-20 18:00:00', predecessor_id=460, predecessor_lag_days=1 WHERE id=414; -- 3 (trunca no recesso)
UPDATE planning_cards SET start_date='2027-01-05 09:00:00', end_date='2027-01-11 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-11', due_date='2027-01-11 18:00:00', predecessor_id=414, predecessor_lag_days=1 WHERE id=415; -- 4 (pula recesso)
UPDATE planning_cards SET start_date='2027-01-12 09:00:00', end_date='2027-01-18 18:00:00', client_start_date='2027-01-12', client_end_date='2027-01-18', due_date='2027-01-18 18:00:00', predecessor_id=415, predecessor_lag_days=1 WHERE id=416; -- 5
UPDATE planning_cards SET start_date='2027-01-19 09:00:00', end_date='2027-01-25 18:00:00', client_start_date='2027-01-19', client_end_date='2027-01-25', due_date='2027-01-25 18:00:00', predecessor_id=416, predecessor_lag_days=1 WHERE id=417; -- 6
UPDATE planning_cards SET start_date='2027-01-26 09:00:00', end_date='2027-02-01 18:00:00', client_start_date='2027-01-26', client_end_date='2027-02-01', due_date='2027-02-01 18:00:00', predecessor_id=417, predecessor_lag_days=1 WHERE id=418; -- 7
UPDATE planning_cards SET start_date='2027-02-02 09:00:00', end_date='2027-02-08 18:00:00', client_start_date='2027-02-02', client_end_date='2027-02-08', due_date='2027-02-08 18:00:00', predecessor_id=418, predecessor_lag_days=1 WHERE id=419; -- 8
UPDATE planning_cards SET start_date='2027-02-09 09:00:00', end_date='2027-02-15 18:00:00', client_start_date='2027-02-09', client_end_date='2027-02-15', due_date='2027-02-15 18:00:00', predecessor_id=419, predecessor_lag_days=1 WHERE id=420; -- 9
UPDATE planning_cards SET start_date='2027-02-16 09:00:00', end_date='2027-02-22 18:00:00', client_start_date='2027-02-16', client_end_date='2027-02-22', due_date='2027-02-22 18:00:00', predecessor_id=420, predecessor_lag_days=1 WHERE id=421; -- 10
UPDATE planning_cards SET start_date='2027-02-23 09:00:00', end_date='2027-03-01 18:00:00', client_start_date='2027-02-23', client_end_date='2027-03-01', due_date='2027-03-01 18:00:00', predecessor_id=421, predecessor_lag_days=1 WHERE id=422; -- 11
UPDATE planning_cards SET start_date='2027-03-02 09:00:00', end_date='2027-03-08 18:00:00', client_start_date='2027-03-02', client_end_date='2027-03-08', due_date='2027-03-08 18:00:00', predecessor_id=422, predecessor_lag_days=1 WHERE id=423; -- 12
UPDATE planning_cards SET start_date='2027-03-09 09:00:00', end_date='2027-03-15 18:00:00', client_start_date='2027-03-09', client_end_date='2027-03-15', due_date='2027-03-15 18:00:00', predecessor_id=423, predecessor_lag_days=1 WHERE id=424; -- 13
UPDATE planning_cards SET start_date='2027-03-16 09:00:00', end_date='2027-03-22 18:00:00', client_start_date='2027-03-16', client_end_date='2027-03-22', due_date='2027-03-22 18:00:00', predecessor_id=424, predecessor_lag_days=1 WHERE id=425; -- 14

-- Conferência (rode junto): nenhuma linha entre 21/12/2026 e 04/01/2027.
SELECT pc.id, pc.title, pc.start_date, pc.end_date, pc.due_date,
       pc.predecessor_id, pred.title AS predecessor_title, pc.predecessor_lag_days
FROM planning_cards pc
LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
WHERE pc.id IN (477,@id01,408,409,410,411,412,413,460,414,415,416,417,418,419,420,421,422,423,424,425)
ORDER BY pc.start_date, pc.id;
