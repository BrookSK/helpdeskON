-- =====================================================================
-- REAJUSTE DO CRONOGRAMA — QUATRI (company_id 2) — v8
-- Substitui o v7: o card C (Financeiro — Tela de Contratos) vai até 01/12.
-- Funciona tanto se o v7 já foi rodado quanto se não foi: os cards novos
-- só são criados se não existirem e todas as datas são gravadas por id.
--   A) Permissão para alterar o valor + histórico  22–24/11
--   B) Relatórios: original, juros, total, descrição 25–27/11
--   C) Financeiro: tela de Contratos                 28/11–01/12
-- O restante anda 2 dias em relação ao v7. O 1.3 Campos de Lucro passa
-- a cair no recesso (21/12/2026 a 04/01/2027): fica dividido em 16–20/12
-- + "(continuação)" 05–06/01, como nos ajustes anteriores.
-- Rodar de uma vez no phpMyAdmin (produção). Em transação: se algum
-- comando falhar, nada é gravado. Pode rodar de novo sem duplicar cards.
-- =====================================================================

SET @campagna := (SELECT id FROM users WHERE name LIKE 'Lucas Campagna%' ORDER BY id LIMIT 1);
SET @author   := COALESCE(@campagna, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));
SELECT @campagna AS campagna, @author AS autor;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- A) Permissão para alterar o valor + histórico (depois do 530)
-- ---------------------------------------------------------------------
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT 'Contas a Pagar — Permissão para alterar o valor e histórico de alterações',
  CONCAT(
  '<p><strong>Contexto (pedido do Victor):</strong> o pessoal de Compras e o Financeiro não podem alterar o valor de uma conta já cadastrada. Se a conta atrasa e alguém muda o valor, perde-se a informação do valor original, inclusive para contabilizar os juros.</p>',
  '<p><strong>Permissão separada para o valor:</strong></p>',
  '<ul>',
  '<li>Hoje existe a permissão de editar o registro. Criar uma permissão específica para editar o <strong>valor</strong> dentro do registro.</li>',
  '<li>Assim dá para liberar a edição do registro em geral (descrição e demais campos) sem liberar a alteração do valor.</li>',
  '<li>Compras e Financeiro ficam sem a permissão de alterar o valor.</li>',
  '<li>Verificar se a mesma regra vale para o Contas a Receber.</li>',
  '</ul>',
  '<p><strong>Histórico de alterações (obrigatório):</strong> toda alteração no registro fica registrada com o que foi editado (campo, valor anterior e valor novo), quem editou, dia e horário. O histórico fica visível no registro.</p>',
  '<p><strong>Dependência:</strong> só depois do card de Compras (lançamento financeiro pelo pessoal de Compras), que é quando Compras passa a lançar registros financeiros.</p>'
  ),
  2, @campagna, @author, 'high', 'open', 0,
  '2026-11-22 09:00:00', '2026-11-24 18:00:00', '2026-11-24 18:00:00', '2026-11-22', '2026-11-24', 530, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM planning_cards WHERE company_id=2
  AND title='Contas a Pagar — Permissão para alterar o valor e histórico de alterações' COLLATE utf8mb4_unicode_ci);
SET @cardA := (SELECT id FROM planning_cards WHERE company_id=2
  AND title='Contas a Pagar — Permissão para alterar o valor e histórico de alterações' COLLATE utf8mb4_unicode_ci ORDER BY id LIMIT 1);

-- ---------------------------------------------------------------------
-- B) Relatórios: valor original, juros, total e descrição (depois do A)
-- ---------------------------------------------------------------------
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT 'Relatórios financeiros — Valor original, juros, valor total e descrição da conta',
  CONCAT(
  '<p><strong>Contexto (pedido do Victor):</strong> os relatórios mostram o custo, mas não mostram o que é a conta nem quanto dela é juros.</p>',
  '<ul>',
  '<li>Em todo relatório que trata de contas, mostrar o <strong>valor original</strong>, os <strong>juros</strong> (quando houver) e o <strong>valor total</strong>.</li>',
  '<li>Incluir a <strong>descrição/comentário</strong> da conta no relatório. Ex.: um pagamento para "Maria Aparecida" hoje aparece só com o valor, sem dizer do que se trata (ex.: referente a plantão).</li>',
  '<li>Verificar se o lançamento já tem onde registrar os juros; se não tiver, incluir.</li>',
  '</ul>',
  '<p><strong>Dependência:</strong> depois do card de permissão do valor e histórico, que preserva o valor original da conta.</p>'
  ),
  2, @campagna, @author, 'high', 'open', 0,
  '2026-11-25 09:00:00', '2026-11-27 18:00:00', '2026-11-27 18:00:00', '2026-11-25', '2026-11-27', @cardA, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM planning_cards WHERE company_id=2
  AND title='Relatórios financeiros — Valor original, juros, valor total e descrição da conta' COLLATE utf8mb4_unicode_ci);
SET @cardB := (SELECT id FROM planning_cards WHERE company_id=2
  AND title='Relatórios financeiros — Valor original, juros, valor total e descrição da conta' COLLATE utf8mb4_unicode_ci ORDER BY id LIMIT 1);

-- ---------------------------------------------------------------------
-- C) Tela de Contratos no Financeiro (depois do B)
-- ---------------------------------------------------------------------
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT 'Financeiro — Tela de Contratos',
  CONCAT(
  '<p><strong>Contexto (pedido do Victor):</strong> hoje, para ver os contratos, é preciso entrar em Orçamento &gt; Ganho.</p>',
  '<ul>',
  '<li>Criar dentro do Financeiro uma tela que lista <strong>somente os contratos</strong>.</li>',
  '<li>A partir dessa lista, abrir o contrato sem precisar passar pelo Orçamento.</li>',
  '</ul>'
  ),
  2, @campagna, @author, 'medium', 'open', 0,
  '2026-11-28 09:00:00', '2026-12-01 18:00:00', '2026-12-01 18:00:00', '2026-11-28', '2026-12-01', @cardB, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM planning_cards WHERE company_id=2
  AND title='Financeiro — Tela de Contratos' COLLATE utf8mb4_unicode_ci);
SET @cardC := (SELECT id FROM planning_cards WHERE company_id=2
  AND title='Financeiro — Tela de Contratos' COLLATE utf8mb4_unicode_ci ORDER BY id LIMIT 1);

-- Datas e dependências de A, B e C gravadas por id (vale também se o v7 já rodou).
UPDATE planning_cards SET predecessor_id=530, predecessor_lag_days=1,
  start_date='2026-11-22 09:00:00', end_date='2026-11-24 18:00:00', due_date='2026-11-24 18:00:00', client_start_date='2026-11-22', client_end_date='2026-11-24' WHERE id=@cardA AND company_id=2;
UPDATE planning_cards SET predecessor_id=@cardA, predecessor_lag_days=1,
  start_date='2026-11-25 09:00:00', end_date='2026-11-27 18:00:00', due_date='2026-11-27 18:00:00', client_start_date='2026-11-25', client_end_date='2026-11-27' WHERE id=@cardB AND company_id=2;
UPDATE planning_cards SET predecessor_id=@cardB, predecessor_lag_days=1,
  start_date='2026-11-28 09:00:00', end_date='2026-12-01 18:00:00', due_date='2026-12-01 18:00:00', client_start_date='2026-11-28', client_end_date='2026-12-01' WHERE id=@cardC AND company_id=2;

-- ---------------------------------------------------------------------
-- Sequência até o recesso; 409 depende do C
-- ---------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id=@cardC, predecessor_lag_days=1,
  start_date='2026-12-02 09:00:00', end_date='2026-12-08 18:00:00', due_date='2026-12-08 18:00:00', client_start_date='2026-12-02', client_end_date='2026-12-08' WHERE id=409 AND company_id=2;
UPDATE planning_cards SET start_date='2026-12-09 09:00:00', end_date='2026-12-15 18:00:00', due_date='2026-12-15 18:00:00', client_start_date='2026-12-09', client_end_date='2026-12-15' WHERE id=410 AND company_id=2;
-- 1.3 Campos de Lucro: 5 dias antes do recesso
UPDATE planning_cards SET start_date='2026-12-16 09:00:00', end_date='2026-12-20 18:00:00', due_date='2026-12-20 18:00:00', client_start_date='2026-12-16', client_end_date='2026-12-20' WHERE id=411 AND company_id=2;

-- ---------------------------------------------------------------------
-- Depois do recesso
-- 1.3 (continuação): 2 dias restantes, 05–06/01
-- ---------------------------------------------------------------------
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT '1.3 — Campos de Lucro — João (continuação)',
  '<p>Continuação do card "1.3 — Campos de Lucro — João" depois do recesso de fim de ano. A descrição completa (inclusive o ponto de atenção sobre custo bancário) está no card original.</p>',
  2, @campagna, @author, 'medium', 'open', 0,
  '2027-01-05 09:00:00', '2027-01-06 18:00:00', '2027-01-06 18:00:00', '2027-01-05', '2027-01-06', 411, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM planning_cards WHERE company_id=2
  AND title='1.3 — Campos de Lucro — João (continuação)' COLLATE utf8mb4_unicode_ci);
SET @cont411 := (SELECT id FROM planning_cards WHERE company_id=2
  AND title='1.3 — Campos de Lucro — João (continuação)' COLLATE utf8mb4_unicode_ci ORDER BY id LIMIT 1);
UPDATE planning_cards SET predecessor_id=411, predecessor_lag_days=1,
  start_date='2027-01-05 09:00:00', end_date='2027-01-06 18:00:00', due_date='2027-01-06 18:00:00', client_start_date='2027-01-05', client_end_date='2027-01-06' WHERE id=@cont411 AND company_id=2;

-- Dash Expedição inteiro (7 dias) depois da continuação; demais seguem
UPDATE planning_cards SET predecessor_id=@cont411, predecessor_lag_days=1,
  start_date='2027-01-07 09:00:00', end_date='2027-01-13 18:00:00', due_date='2027-01-13 18:00:00', client_start_date='2027-01-07', client_end_date='2027-01-13' WHERE id=412 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-14 09:00:00', end_date='2027-01-20 18:00:00', due_date='2027-01-20 18:00:00', client_start_date='2027-01-14', client_end_date='2027-01-20' WHERE id=413 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-21 09:00:00', end_date='2027-01-27 18:00:00', due_date='2027-01-27 18:00:00', client_start_date='2027-01-21', client_end_date='2027-01-27' WHERE id=460 AND company_id=2;
UPDATE planning_cards SET start_date='2027-01-28 09:00:00', end_date='2027-02-03 18:00:00', due_date='2027-02-03 18:00:00', client_start_date='2027-01-28', client_end_date='2027-02-03' WHERE id=414 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-04 09:00:00', end_date='2027-02-10 18:00:00', due_date='2027-02-10 18:00:00', client_start_date='2027-02-04', client_end_date='2027-02-10' WHERE id=415 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-11 09:00:00', end_date='2027-02-17 18:00:00', due_date='2027-02-17 18:00:00', client_start_date='2027-02-11', client_end_date='2027-02-17' WHERE id IN (416, 517) AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-18 09:00:00', end_date='2027-02-24 18:00:00', due_date='2027-02-24 18:00:00', client_start_date='2027-02-18', client_end_date='2027-02-24' WHERE id=417 AND company_id=2;
UPDATE planning_cards SET start_date='2027-02-25 09:00:00', end_date='2027-03-03 18:00:00', due_date='2027-03-03 18:00:00', client_start_date='2027-02-25', client_end_date='2027-03-03' WHERE id=418 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-04 09:00:00', end_date='2027-03-10 18:00:00', due_date='2027-03-10 18:00:00', client_start_date='2027-03-04', client_end_date='2027-03-10' WHERE id=419 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-11 09:00:00', end_date='2027-03-17 18:00:00', due_date='2027-03-17 18:00:00', client_start_date='2027-03-11', client_end_date='2027-03-17' WHERE id=420 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-18 09:00:00', end_date='2027-03-24 18:00:00', due_date='2027-03-24 18:00:00', client_start_date='2027-03-18', client_end_date='2027-03-24' WHERE id=421 AND company_id=2;
UPDATE planning_cards SET start_date='2027-03-25 09:00:00', end_date='2027-03-31 18:00:00', due_date='2027-03-31 18:00:00', client_start_date='2027-03-25', client_end_date='2027-03-31' WHERE id=422 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-01 09:00:00', end_date='2027-04-07 18:00:00', due_date='2027-04-07 18:00:00', client_start_date='2027-04-01', client_end_date='2027-04-07' WHERE id=423 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-08 09:00:00', end_date='2027-04-14 18:00:00', due_date='2027-04-14 18:00:00', client_start_date='2027-04-08', client_end_date='2027-04-14' WHERE id=424 AND company_id=2;
UPDATE planning_cards SET start_date='2027-04-15 09:00:00', end_date='2027-04-21 18:00:00', due_date='2027-04-21 18:00:00', client_start_date='2027-04-15', client_end_date='2027-04-21' WHERE id=425 AND company_id=2;

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT @cardA AS card_A, @cardB AS card_B, @cardC AS card_C, @cont411 AS continuacao_1_3;

SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date, pc.predecessor_id, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON u.id = pc.assigned_to
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date, pc.due_date, '2000-01-01'), pc.id;
