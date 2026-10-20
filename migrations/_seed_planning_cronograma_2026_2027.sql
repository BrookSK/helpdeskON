-- =====================================================================
-- Seed: cards de Planejamento a partir do "Cronograma atualizado".
-- ---------------------------------------------------------------------
-- NÃO é uma migration de schema — é um script de DADOS (seed). Rode
-- manualmente (phpMyAdmin > aba SQL) para popular o board de Planejamento.
--
-- COMO RODAR NO phpMyAdmin (produção ou local):
--   1) Selecione o banco "helpdesk_on" na árvore da esquerda.
--   2) Abra a aba "SQL", cole TUDO abaixo e clique em "Executar/Go".
--      (Rode o arquivo inteiro de uma vez — não execute linha a linha.)
--
-- É idempotente: apaga antes os cards com título prefixado por "[CRONO] "
-- e recria, então pode rodar de novo sem duplicar. Sem START TRANSACTION
-- manual de propósito — cada statement é autocommit, evitando transação
-- aberta sem COMMIT (causa de "nada foi gravado").
--
-- Mapeamento de datas por card:
--   start_date        = início do DESENVOLVIMENTO
--   end_date          = fim da CORREÇÃO (fim real da demanda)
--   client_start_date = início do cronograma exibido ao cliente (= start_date)
--   client_end_date   = fim do cronograma exibido ao cliente   (= end_date)
--   due_date          = ENTREGA (último dia da correção)
--
-- Responsável (assigned_to) = Lucas Campagna, resolvido pelo e-mail.
-- created_by = primeiro super_admin ativo (coluna NOT NULL, FK users).
-- =====================================================================

-- NÃO use "USE helpdesk_on;" aqui: em alguns hosts de produção o nome do
-- schema pode diferir. Selecione o banco pela árvore do phpMyAdmin antes.

-- Autor dos cards: primeiro super_admin ativo (fallback: primeiro usuário).
SET @author := (SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 ORDER BY id ASC LIMIT 1);
SET @author := COALESCE(@author, (SELECT id FROM users ORDER BY id ASC LIMIT 1));

-- Responsável pelos cards: Lucas Campagna (pelo e-mail). Fallback: @author.
SET @assignee := (SELECT id FROM users WHERE email = 'lucas.campagna@lrvweb.com.br' LIMIT 1);
SET @assignee := COALESCE(@assignee, @author);

-- Limpeza idempotente: remove os cards deste seed antes de recriar.
DELETE FROM planning_cards WHERE title LIKE '[CRONO] %';

-- ------------------------------------------------------------------
-- Inserção dos cards (um único INSERT com todas as linhas).
-- status 'open' e priority conforme urgência indicada no texto.
-- ------------------------------------------------------------------
INSERT INTO planning_cards
    (title, description, created_by, assigned_to, priority, status, position,
     start_date, end_date, client_start_date, client_end_date, due_date)
VALUES
('[CRONO] 1 — Dash Compras',
 '<p>Desenvolvimento: 26–29/10 • Homologação: 30–31/10 • Correção: 01/11</p>',
 @author, @assignee, 'medium', 'open', 0,
 '2026-10-26 09:00:00', '2026-11-01 18:00:00', '2026-10-26', '2026-11-01', '2026-11-01'),
('[CRONO] 1.1 — Página Clube — Ajustes, multisseleção e relatório',
 '<p>Desenvolvimento: 02–05/11 • Homologação: 06–07/11 • Correção: 08/11</p>',
 @author, @assignee, 'medium', 'open', 1,
 '2026-11-02 09:00:00', '2026-11-08 18:00:00', '2026-11-02', '2026-11-08', '2026-11-08'),
('[CRONO] 1.2 — Página Comissões — RT e funcionários',
 '<p>Desenvolvimento: 09–12/11 • Homologação: 13–14/11 • Correção: 15/11</p>',
 @author, @assignee, 'medium', 'open', 2,
 '2026-11-09 09:00:00', '2026-11-15 18:00:00', '2026-11-09', '2026-11-15', '2026-11-15'),
('[CRONO] 1.3 — Campos de Lucro — João',
 '<p>Desenvolvimento: 16–19/11 • Homologação: 20–21/11 • Correção: 22/11</p>',
 @author, @assignee, 'medium', 'open', 3,
 '2026-11-16 09:00:00', '2026-11-22 18:00:00', '2026-11-16', '2026-11-22', '2026-11-22'),
('[CRONO] 2 — Dash Expedição',
 '<p>Desenvolvimento: 23–26/11 • Homologação: 27–28/11 • Correção: 29/11</p>',
 @author, @assignee, 'medium', 'open', 4,
 '2026-11-23 09:00:00', '2026-11-29 18:00:00', '2026-11-23', '2026-11-29', '2026-11-29'),
('[CRONO] 2.1 — Expedição — Pedido Quatri / Produção',
 '<p>Desenvolvimento: 30/11–03/12 • Homologação: 04–05/12 • Correção: 06/12</p>',
 @author, @assignee, 'medium', 'open', 5,
 '2026-11-30 09:00:00', '2026-12-06 18:00:00', '2026-11-30', '2026-12-06', '2026-12-06'),
('[CRONO] 3 — Dash Técnico',
 '<p>Desenvolvimento: 07–10/12 • Homologação: 11–12/12 • Correção: 13/12</p>',
 @author, @assignee, 'medium', 'open', 6,
 '2026-12-07 09:00:00', '2026-12-13 18:00:00', '2026-12-07', '2026-12-13', '2026-12-13'),
('[CRONO] 4 — Agenda Técnica',
 '<p>Desenvolvimento: 14–17/12 • Homologação: 18–19/12 • Correção: 20/12</p>',
 @author, @assignee, 'medium', 'open', 7,
 '2026-12-14 09:00:00', '2026-12-20 18:00:00', '2026-12-14', '2026-12-20', '2026-12-20'),
('[CRONO] 5 — Módulo Assistência',
 '<p>Desenvolvimento: 05–08/01 • Homologação: 09–10/01 • Correção: 11/01</p>',
 @author, @assignee, 'medium', 'open', 8,
 '2027-01-05 09:00:00', '2027-01-11 18:00:00', '2027-01-05', '2027-01-11', '2027-01-11'),
('[CRONO] 6 — Relatório de Fechamento de Obra',
 '<p>Desenvolvimento: 12–15/01 • Homologação: 16–17/01 • Correção: 18/01</p>',
 @author, @assignee, 'medium', 'open', 9,
 '2027-01-12 09:00:00', '2027-01-18 18:00:00', '2027-01-12', '2027-01-18', '2027-01-18'),
('[CRONO] 7 — Comissão Individualizada de Vendedores',
 '<p>Desenvolvimento: 19–22/01 • Homologação: 23–24/01 • Correção: 25/01</p>',
 @author, @assignee, 'medium', 'open', 10,
 '2027-01-19 09:00:00', '2027-01-25 18:00:00', '2027-01-19', '2027-01-25', '2027-01-25'),
('[CRONO] 8 — Integração com Bitrix — Urgente 2',
 '<p>Desenvolvimento: 26–29/01 • Homologação: 30–31/01 • Correção: 01/02</p>',
 @author, @assignee, 'urgent', 'open', 11,
 '2027-01-26 09:00:00', '2027-02-01 18:00:00', '2027-01-26', '2027-02-01', '2027-02-01'),
('[CRONO] 9 — Gestão à Vista V2',
 '<p>Desenvolvimento: 02–05/02 • Homologação: 06–07/02 • Correção: 11/02 (recesso de carnaval entre a homologação e a correção).</p>',
 @author, @assignee, 'medium', 'open', 12,
 '2027-02-02 09:00:00', '2027-02-11 18:00:00', '2027-02-02', '2027-02-11', '2027-02-11'),
('[CRONO] 10 — Emissão de Boleto V2',
 '<p>Desenvolvimento: 12–15/02 • Homologação: 16–17/02 • Correção: 18/02</p>',
 @author, @assignee, 'medium', 'open', 13,
 '2027-02-12 09:00:00', '2027-02-18 18:00:00', '2027-02-12', '2027-02-18', '2027-02-18'),
('[CRONO] 11 — Lembrete de Pagamento V2',
 '<p>Desenvolvimento: 19–22/02 • Homologação: 23–24/02 • Correção: 25/02</p>',
 @author, @assignee, 'medium', 'open', 14,
 '2027-02-19 09:00:00', '2027-02-25 18:00:00', '2027-02-19', '2027-02-25', '2027-02-25'),
('[CRONO] 12 — Conciliação V2',
 '<p>Desenvolvimento: 26/02–01/03 • Homologação: 02–03/03 • Correção: 04/03</p>',
 @author, @assignee, 'medium', 'open', 15,
 '2027-02-26 09:00:00', '2027-03-04 18:00:00', '2027-02-26', '2027-03-04', '2027-03-04'),
('[CRONO] 13 — Nota Fiscal — Urgente',
 '<p>Desenvolvimento: 05–08/03 • Homologação: 09–10/03 • Correção: 11/03</p>',
 @author, @assignee, 'urgent', 'open', 16,
 '2027-03-05 09:00:00', '2027-03-11 18:00:00', '2027-03-05', '2027-03-11', '2027-03-11'),
('[CRONO] 14 — Nova Proposta Comercial',
 '<p>Desenvolvimento: 12–15/03 • Homologação: 16–17/03 • Correção: 18/03</p>',
 @author, @assignee, 'medium', 'open', 17,
 '2027-03-12 09:00:00', '2027-03-18 18:00:00', '2027-03-12', '2027-03-18', '2027-03-18');

-- Conferência (rode junto): deve retornar 18.
SELECT COUNT(*) AS total_crono FROM planning_cards WHERE title LIKE '[CRONO] %';
