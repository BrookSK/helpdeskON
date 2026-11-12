-- =====================================================================
-- Seed/Ajuste: cronograma Quatri — card "2.2" + encadeamento por predecessor
-- ---------------------------------------------------------------------
-- NÃO é migration de schema. É script de DADOS. Rode manualmente no
-- phpMyAdmin (produção: banco "helpdesk_on") na aba SQL, de uma vez só.
--
-- O QUE ESTE SCRIPT FAZ:
--   1) Insere um card novo "2.2 — Revisar regra de geração de créditos..."
--      entre o "2.1 — Expedição Quatri/Produção" (id 413) e o
--      "3 — Dash Técnico" (id 414), com a descrição completa da demanda.
--   2) Reescreve as datas (start/end/client/due) dos cards de 3 a 14 para
--      acomodar a inserção do 2.2 (+1 semana), PULANDO o recesso de fim de
--      ano (21/12/2026 a 04/01/2027 — ninguém trabalha nessa janela).
--   3) Encadeia TODOS os cards do cronograma por predecessor_id, em cadeia
--      linear (cada card depende do imediatamente anterior), folga 0.
--
-- IDEMPOTENTE:
--   - O card 2.2 é identificado pelo título exato (âncora). Antes de inserir,
--     apaga qualquer card com esse título (evita duplicar ao re-rodar).
--   - Os demais cards são referenciados por ID fixo (não mudam).
--   - Sem START TRANSACTION manual: cada statement é autocommit.
--
-- IMPORTANTE (recálculo automático): a regra de "pular recesso" NÃO existe
-- no código — ela foi aplicada à mão aqui, nas datas gravadas. Se, no futuro,
-- um card atrasar e o sistema recalcular a cascata sozinho, ele empurra dias
-- corridos e pode cair dentro do recesso. Para tornar isso automático seria
-- preciso alterar o PHP (PlanningRules), fora do escopo deste script.
-- =====================================================================

-- Autor do card novo: created_by é NOT NULL. Primeiro super_admin ativo.
SET @author := (SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 ORDER BY id ASC LIMIT 1);
SET @author := COALESCE(@author, (SELECT id FROM users ORDER BY id ASC LIMIT 1));

-- Responsável e empresa: mesmos dos demais cards do cronograma Quatri.
SET @assignee := 11;   -- assigned_to usado em todo o cronograma
SET @company  := 2;    -- company_id usado em todo o cronograma

-- Título-âncora do card novo (usado para idempotência). Mantenha idêntico.
SET @title22 := '2.2 — Revisar regra de geração de créditos de clientes por devolução e lançamentos financeiros';

-- ------------------------------------------------------------------
-- (1) Limpeza idempotente + inserção do card 2.2.
--     Datas: desenvolvimento 07–10/12, homologação 11–12/12, correção 13/12.
--     start_date = início do dev; end_date = fim da correção; due = entrega.
-- ------------------------------------------------------------------
DELETE FROM planning_cards WHERE title = @title22;

INSERT INTO planning_cards
    (title, description, company_id, assigned_to, created_by, priority, status,
     position, start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
(@title22,
 CONCAT(
   '<p><strong>Revisar e alterar a regra de geração de créditos de clientes</strong> para que os valores sejam gerados a partir de operações financeiras válidas, como devoluções vinculadas à respectiva Nota Fiscal ou lançamentos manuais no Contas a Receber, quando houver pagamento superior ao valor devido.</p>',
   '<p>Objetivo: evitar que a simples retirada de produtos de um contrato ou aditivo gere automaticamente um crédito disponível para o cliente, sem que exista uma devolução ou outra operação financeira que justifique esse saldo.</p>',
   '<p><strong>Como funciona hoje:</strong> a retirada de 54 produtos de um aditivo gerou um crédito de R$ 144.503,53. O sistema considera os preços vigentes dos produtos no cálculo, em vez dos valores registrados originalmente no contrato, e disponibiliza o crédito sem uma movimentação financeira de saída que o justifique.</p>',
   '<p><strong>Como deve funcionar:</strong></p>',
   '<p>1. <strong>Crédito por devolução</strong> — gerar crédito a partir de devolução registrada, vinculado à Nota Fiscal de devolução e aos registros financeiros; o valor deve corresponder ao efetivamente reconhecido na devolução, sem recalcular pelos preços atuais do cadastro.</p>',
   '<p>2. <strong>Crédito por lançamento financeiro manual</strong> — permitir gerar crédito via lançamento manual no Contas a Receber em situações justificadas (ex.: pagamento superior ao devido), registrando origem, valor, cliente e rastreio.</p>',
   '<p>3. <strong>Impedir geração automática indevida</strong> — revisar a retirada de produtos de contratos/aditivos para que a simples exclusão/retirada não gere crédito sem operação financeira válida.</p>',
   '<p>4. <strong>Rastreabilidade</strong> — créditos permanecem vinculados à origem (motivo, documento/lançamento, valor original e movimentações), preservando os fluxos existentes de utilização em contratos, aditivos, orçamentos e financeiro.</p>',
   '<p><strong>Impacto:</strong> Alto. <strong>Urgência:</strong> Alta. A regularização do saldo de R$ 144.503,53 já identificado é tratada separadamente (exclusão manual no banco); esta demanda corrige a regra do sistema.</p>'
 ),
 @company, @assignee, @author, 'medium', 'open',
 25, '2026-12-07 09:00:00', '2026-12-13 18:00:00', '2026-12-07', '2026-12-13', '2026-12-13 00:00:00');

-- Guarda o ID recém-criado do 2.2 para o encadeamento.
SET @id22 := (SELECT id FROM planning_cards WHERE title = @title22 ORDER BY id DESC LIMIT 1);

-- ------------------------------------------------------------------
-- (2) Reescrita das datas dos cards 3..14 (+1 semana), pulando o recesso
--     de fim de ano (21/12/2026 a 04/01/2027). Padrão de 1 semana por card.
--     Formato das datas igual ao já existente na tabela.
-- ------------------------------------------------------------------

-- 3 — Dash Técnico (414): era 07–13/12 -> 14–20/12 (ainda antes do recesso).
UPDATE planning_cards SET
    start_date = '2026-12-14 09:00:00', end_date = '2026-12-20 18:00:00',
    client_start_date = '2026-12-14', client_end_date = '2026-12-20',
    due_date = '2026-12-20 00:00:00'
WHERE id = 414;

-- 4 — Agenda Técnica (415): cairia em 21–27/12 (recesso). Salta para 05–11/01.
UPDATE planning_cards SET
    start_date = '2027-01-05 09:00:00', end_date = '2027-01-11 18:00:00',
    client_start_date = '2027-01-05', client_end_date = '2027-01-11',
    due_date = '2027-01-11 00:00:00'
WHERE id = 415;

-- 5 — Módulo Assistência (416): 12–18/01.
UPDATE planning_cards SET
    start_date = '2027-01-12 09:00:00', end_date = '2027-01-18 18:00:00',
    client_start_date = '2027-01-12', client_end_date = '2027-01-18',
    due_date = '2027-01-18 00:00:00'
WHERE id = 416;

-- 6 — Relatório de Fechamento de Obra (417): 19–25/01.
UPDATE planning_cards SET
    start_date = '2027-01-19 09:00:00', end_date = '2027-01-25 18:00:00',
    client_start_date = '2027-01-19', client_end_date = '2027-01-25',
    due_date = '2027-01-25 00:00:00'
WHERE id = 417;

-- 7 — Comissão Individualizada (418): 26/01–01/02.
UPDATE planning_cards SET
    start_date = '2027-01-26 09:00:00', end_date = '2027-02-01 18:00:00',
    client_start_date = '2027-01-26', client_end_date = '2027-02-01',
    due_date = '2027-02-01 00:00:00'
WHERE id = 418;

-- 8 — Integração Bitrix (419): 02–08/02.
UPDATE planning_cards SET
    start_date = '2027-02-02 09:00:00', end_date = '2027-02-08 18:00:00',
    client_start_date = '2027-02-02', client_end_date = '2027-02-08',
    due_date = '2027-02-08 00:00:00'
WHERE id = 419;

-- 9 — Gestão à Vista V2 (420): 09–15/02 (carnaval tratado como semana normal).
UPDATE planning_cards SET
    start_date = '2027-02-09 09:00:00', end_date = '2027-02-15 18:00:00',
    client_start_date = '2027-02-09', client_end_date = '2027-02-15',
    due_date = '2027-02-15 00:00:00'
WHERE id = 420;

-- 10 — Emissão de Boleto V2 (421): 16–22/02.
UPDATE planning_cards SET
    start_date = '2027-02-16 09:00:00', end_date = '2027-02-22 18:00:00',
    client_start_date = '2027-02-16', client_end_date = '2027-02-22',
    due_date = '2027-02-22 00:00:00'
WHERE id = 421;

-- 11 — Lembrete de Pagamento V2 (422): 23/02–01/03.
UPDATE planning_cards SET
    start_date = '2027-02-23 09:00:00', end_date = '2027-03-01 18:00:00',
    client_start_date = '2027-02-23', client_end_date = '2027-03-01',
    due_date = '2027-03-01 00:00:00'
WHERE id = 422;

-- 12 — Conciliação V2 (423): 02–08/03.
UPDATE planning_cards SET
    start_date = '2027-03-02 09:00:00', end_date = '2027-03-08 18:00:00',
    client_start_date = '2027-03-02', client_end_date = '2027-03-08',
    due_date = '2027-03-08 00:00:00'
WHERE id = 423;

-- 13 — Nota Fiscal (424): 09–15/03.
UPDATE planning_cards SET
    start_date = '2027-03-09 09:00:00', end_date = '2027-03-15 18:00:00',
    client_start_date = '2027-03-09', client_end_date = '2027-03-15',
    due_date = '2027-03-15 00:00:00'
WHERE id = 424;

-- 14 — Nova Proposta Comercial (425): 16–22/03.
UPDATE planning_cards SET
    start_date = '2027-03-16 09:00:00', end_date = '2027-03-22 18:00:00',
    client_start_date = '2027-03-16', client_end_date = '2027-03-22',
    due_date = '2027-03-22 00:00:00'
WHERE id = 425;

-- ------------------------------------------------------------------
-- (3) Encadeamento por predecessor (cadeia linear, folga 0).
--     Ordem: 408 -> 409 -> 410 -> 411 -> 412 -> 413 -> [2.2] -> 414
--            -> 415 -> 416 -> 417 -> 418 -> 419 -> 420 -> 421 -> 422
--            -> 423 -> 424 -> 425
--     O primeiro (408) não tem predecessor (início da cadeia).
-- ------------------------------------------------------------------
UPDATE planning_cards SET predecessor_id = NULL,  predecessor_lag_days = 0 WHERE id = 408; -- 1 (início)
UPDATE planning_cards SET predecessor_id = 408,   predecessor_lag_days = 0 WHERE id = 409; -- 1.1
UPDATE planning_cards SET predecessor_id = 409,   predecessor_lag_days = 0 WHERE id = 410; -- 1.2
UPDATE planning_cards SET predecessor_id = 410,   predecessor_lag_days = 0 WHERE id = 411; -- 1.3
UPDATE planning_cards SET predecessor_id = 411,   predecessor_lag_days = 0 WHERE id = 412; -- 2
UPDATE planning_cards SET predecessor_id = 412,   predecessor_lag_days = 0 WHERE id = 413; -- 2.1
UPDATE planning_cards SET predecessor_id = 413,   predecessor_lag_days = 0 WHERE id = @id22; -- 2.2 (novo)
UPDATE planning_cards SET predecessor_id = @id22, predecessor_lag_days = 0 WHERE id = 414; -- 3
UPDATE planning_cards SET predecessor_id = 414,   predecessor_lag_days = 0 WHERE id = 415; -- 4
UPDATE planning_cards SET predecessor_id = 415,   predecessor_lag_days = 0 WHERE id = 416; -- 5
UPDATE planning_cards SET predecessor_id = 416,   predecessor_lag_days = 0 WHERE id = 417; -- 6
UPDATE planning_cards SET predecessor_id = 417,   predecessor_lag_days = 0 WHERE id = 418; -- 7
UPDATE planning_cards SET predecessor_id = 418,   predecessor_lag_days = 0 WHERE id = 419; -- 8
UPDATE planning_cards SET predecessor_id = 419,   predecessor_lag_days = 0 WHERE id = 420; -- 9
UPDATE planning_cards SET predecessor_id = 420,   predecessor_lag_days = 0 WHERE id = 421; -- 10
UPDATE planning_cards SET predecessor_id = 421,   predecessor_lag_days = 0 WHERE id = 422; -- 11
UPDATE planning_cards SET predecessor_id = 422,   predecessor_lag_days = 0 WHERE id = 423; -- 12
UPDATE planning_cards SET predecessor_id = 423,   predecessor_lag_days = 0 WHERE id = 424; -- 13
UPDATE planning_cards SET predecessor_id = 424,   predecessor_lag_days = 0 WHERE id = 425; -- 14

-- ------------------------------------------------------------------
-- Conferência (rode junto). Deve listar a cadeia completa, 19 linhas,
-- do 1 ao 14 com o 2.2 entre o 2.1 e o 3, datas e predecessor corretos.
-- ------------------------------------------------------------------
SELECT pc.id, pc.title, pc.start_date, pc.end_date, pc.due_date,
       pc.predecessor_id, pred.title AS predecessor_title, pc.predecessor_lag_days
FROM planning_cards pc
LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
WHERE pc.id IN (408,409,410,411,412,413,@id22,414,415,416,417,418,419,420,421,422,423,424,425)
ORDER BY COALESCE(pc.start_date, '2000-01-01'), pc.id;
