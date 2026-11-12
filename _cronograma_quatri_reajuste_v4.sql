-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v4
-- Novo card de 5 dias (Compras: lançamento financeiro + cartão) entre
-- 515/516 (fim 20/11) e 409 Página Clube. Demais cards andam +5 dias,
-- respeitando o recesso de 21/12/2026 a 04/01/2027.
-- Rodar de uma vez no phpMyAdmin (produção). Script de DADOS.
-- =====================================================================

SET @campagna := (SELECT id FROM users WHERE name LIKE 'Lucas Campagna%' ORDER BY id LIMIT 1);
SET @author   := COALESCE(@campagna, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));
SELECT @campagna AS campagna, @author AS autor;

-- ---------------------------------------------------------------------
-- Card novo: 21/11 a 25/11 (5 dias), depois do 515
-- ---------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id=NULL
 WHERE predecessor_id IN (SELECT id FROM (SELECT id FROM planning_cards
   WHERE title='Compras — Lançamento financeiro (NF, pagamento) e compras no cartão de crédito' COLLATE utf8mb4_unicode_ci) t);
DELETE FROM planning_cards
 WHERE title='Compras — Lançamento financeiro (NF, pagamento) e compras no cartão de crédito' COLLATE utf8mb4_unicode_ci;

INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
VALUES ('Compras — Lançamento financeiro (NF, pagamento) e compras no cartão de crédito',
  CONCAT(
  '<p><strong>Objetivo:</strong> o pessoal de Compras lança os dados financeiros do pedido e isso popula automaticamente o Contas a Pagar do financeiro, que só confere.</p>',
  '<p><strong>Bloco de lançamento (Compras):</strong></p>',
  '<ul>',
  '<li>Data do pedido, nota fiscal e data de pagamento.</li>',
  '<li>Condição de pagamento: parcelas (ex.: 3x) e prazos de faturamento (30/40/50, 40/50/60 etc.).</li>',
  '<li>Forma de pagamento: boleto ou não, onde pagou, se usou cartão de crédito ou não.</li>',
  '</ul>',
  '<p><strong>Integração:</strong> ao salvar, os dados geram automaticamente os lançamentos no Contas a Pagar, já organizados.</p>',
  '<p><strong>Permissões (obrigatório):</strong></p>',
  '<ul>',
  '<li>Compras acessa <strong>somente</strong> os próprios lançamentos.</li>',
  '<li>Compras <strong>não pode</strong>, em hipótese alguma, acessar Contas a Pagar nem Contas a Receber.</li>',
  '<li>Compras <strong>não pode apagar</strong>: uma vez lançado e gerado, não pode mais ser excluído.</li>',
  '</ul>',
  '<p><strong>Ajustes relacionados ao cartão:</strong> quando Compras informar que a compra foi no cartão de crédito, o lançamento deve ficar vinculado ao cadastro de cartão de crédito existente, para vincular à fatura, fechar a fatura etc.</p>',
  '<p>Verificar o que já existe (parte disso já pode estar pronta) e completar o restante.</p>',
  '<p><strong>Reunião de referência:</strong> <a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY</a></p>'
  ),
  2, @campagna, @author, 'high', 'open', 0,
  '2026-11-21 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00', '2026-11-21', '2026-11-25', 515, 1);
SET @novo := LAST_INSERT_ID();

-- ---------------------------------------------------------------------
-- Cadeia até o recesso
-- ---------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id=@novo, start_date='2026-11-26 09:00:00', end_date='2026-12-02 18:00:00', due_date='2026-12-02 18:00:00', client_start_date='2026-11-26', client_end_date='2026-12-02' WHERE id=409;
UPDATE planning_cards SET start_date='2026-12-03 09:00:00', end_date='2026-12-09 18:00:00', due_date='2026-12-09 18:00:00', client_start_date='2026-12-03', client_end_date='2026-12-09' WHERE id=410;
UPDATE planning_cards SET start_date='2026-12-10 09:00:00', end_date='2026-12-16 18:00:00', due_date='2026-12-16 18:00:00', client_start_date='2026-12-10', client_end_date='2026-12-16' WHERE id=411;
-- 412 Dash Expedição: 4 dias antes do recesso
UPDATE planning_cards SET start_date='2026-12-17 09:00:00', end_date='2026-12-20 18:00:00', due_date='2026-12-20 18:00:00', client_start_date='2026-12-17', client_end_date='2026-12-20' WHERE id=412;

-- ---------------------------------------------------------------------
-- Depois do recesso
-- 518 vira a continuação do Dash Expedição (3 dias restantes);
-- 413 (2.1) volta a ser inteiro, 7 dias, depois do 518.
-- ---------------------------------------------------------------------
UPDATE planning_cards SET title='2 — Dash Expedição (continuação)', predecessor_id=412, predecessor_lag_days=1,
  start_date='2027-01-05 09:00:00', end_date='2027-01-07 18:00:00', due_date='2027-01-07 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-07' WHERE id=518;
UPDATE planning_cards SET predecessor_id=518, predecessor_lag_days=1,
  start_date='2027-01-08 09:00:00', end_date='2027-01-14 18:00:00', due_date='2027-01-14 18:00:00', client_start_date='2027-01-08', client_end_date='2027-01-14' WHERE id=413;
UPDATE planning_cards SET predecessor_id=413, predecessor_lag_days=1,
  start_date='2027-01-15 09:00:00', end_date='2027-01-21 18:00:00', due_date='2027-01-21 18:00:00', client_start_date='2027-01-15', client_end_date='2027-01-21' WHERE id=460;
UPDATE planning_cards SET start_date='2027-01-22 09:00:00', end_date='2027-01-28 18:00:00', due_date='2027-01-28 18:00:00', client_start_date='2027-01-22', client_end_date='2027-01-28' WHERE id=414;
UPDATE planning_cards SET start_date='2027-01-29 09:00:00', end_date='2027-02-04 18:00:00', due_date='2027-02-04 18:00:00', client_start_date='2027-01-29', client_end_date='2027-02-04' WHERE id=415;
UPDATE planning_cards SET start_date='2027-02-05 09:00:00', end_date='2027-02-11 18:00:00', due_date='2027-02-11 18:00:00', client_start_date='2027-02-05', client_end_date='2027-02-11' WHERE id IN (416, 517);
UPDATE planning_cards SET start_date='2027-02-12 09:00:00', end_date='2027-02-18 18:00:00', due_date='2027-02-18 18:00:00', client_start_date='2027-02-12', client_end_date='2027-02-18' WHERE id=417;
UPDATE planning_cards SET start_date='2027-02-19 09:00:00', end_date='2027-02-25 18:00:00', due_date='2027-02-25 18:00:00', client_start_date='2027-02-19', client_end_date='2027-02-25' WHERE id=418;
UPDATE planning_cards SET start_date='2027-02-26 09:00:00', end_date='2027-03-04 18:00:00', due_date='2027-03-04 18:00:00', client_start_date='2027-02-26', client_end_date='2027-03-04' WHERE id=419;
UPDATE planning_cards SET start_date='2027-03-05 09:00:00', end_date='2027-03-11 18:00:00', due_date='2027-03-11 18:00:00', client_start_date='2027-03-05', client_end_date='2027-03-11' WHERE id=420;
UPDATE planning_cards SET start_date='2027-03-12 09:00:00', end_date='2027-03-18 18:00:00', due_date='2027-03-18 18:00:00', client_start_date='2027-03-12', client_end_date='2027-03-18' WHERE id=421;
UPDATE planning_cards SET start_date='2027-03-19 09:00:00', end_date='2027-03-25 18:00:00', due_date='2027-03-25 18:00:00', client_start_date='2027-03-19', client_end_date='2027-03-25' WHERE id=422;
UPDATE planning_cards SET start_date='2027-03-26 09:00:00', end_date='2027-04-01 18:00:00', due_date='2027-04-01 18:00:00', client_start_date='2027-03-26', client_end_date='2027-04-01' WHERE id=423;
UPDATE planning_cards SET start_date='2027-04-02 09:00:00', end_date='2027-04-08 18:00:00', due_date='2027-04-08 18:00:00', client_start_date='2027-04-02', client_end_date='2027-04-08' WHERE id=424;
UPDATE planning_cards SET start_date='2027-04-09 09:00:00', end_date='2027-04-15 18:00:00', due_date='2027-04-15 18:00:00', client_start_date='2027-04-09', client_end_date='2027-04-15' WHERE id=425;

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.start_date, pc.end_date, pc.predecessor_id, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON pc.assigned_to = u.id
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date, pc.due_date, '2000-01-01'), pc.id;
