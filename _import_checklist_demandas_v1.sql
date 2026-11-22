-- =====================================================================
-- IMPORTAÇÃO — Checklist de prioridades (print de 18/11/2026 às 16:39)
-- 77 demandas (o item 8 "Importar prestadores com notas fiscais" NÃO entra:
-- foi unificado no item 7, importação dos funcionários).
-- Cada atividade vira uma demanda (tickets) + o card no Planejamento, como na
-- "Nova demanda" da equipe: título, categoria, empresa, atendente, início,
-- término e previsão = término. Itens 58 e 63 entram como concluídos.
-- A demanda fica no nome do próprio responsável (cliente = responsável), então
-- nenhum cliente das empresas vê essas demandas. O card não tem datas de
-- cronograma do cliente, então não aparece no cronograma do portal.
-- Rodar de uma vez no phpMyAdmin (produção). Em transação. Pode rodar de novo:
-- atividade já importada não é duplicada.
-- =====================================================================
SET NAMES utf8mb4;

START TRANSACTION;

SET @narciso := (SELECT u.id FROM users u WHERE u.id IN (1, 13) AND u.is_active = 1
  ORDER BY (SELECT COUNT(*) FROM ticket_attendants ta WHERE ta.user_id = u.id)
         + (SELECT COUNT(*) FROM planning_cards pc WHERE pc.assigned_to = u.id) DESC, u.id LIMIT 1);

SELECT @narciso AS narciso_id, (SELECT email FROM users WHERE id = @narciso) AS narciso_email;

-- #1
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Revisar PDF da reunião e organizar as pendências', 'Revisar PDF da reunião e organizar as pendências

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 1.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-18', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar PDF da reunião e organizar as pendências' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar PDF da reunião e organizar as pendências' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Revisar PDF da reunião e organizar as pendências<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 1.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar PDF da reunião e organizar as pendências' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #2
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Conferir cronograma e atualizações do painel Vetriks', 'Conferir cronograma e atualizações do painel Vetriks

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 2.', 'Vetriks', 'medium', 'open',
  '2026-11-18', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir cronograma e atualizações do painel Vetriks' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir cronograma e atualizações do painel Vetriks' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Conferir cronograma e atualizações do painel Vetriks<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 2.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir cronograma e atualizações do painel Vetriks' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #3
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Pegar devolutiva com Felipe Thalacker', 'Pegar devolutiva com Felipe Thalacker

Categoria: Administrativo e contratos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 3.', 'Administrativo e contratos', 'medium', 'open',
  '2026-11-18', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Pegar devolutiva com Felipe Thalacker' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Pegar devolutiva com Felipe Thalacker' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Pegar devolutiva com Felipe Thalacker<br />
<br />
Categoria: Administrativo e contratos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 3.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Pegar devolutiva com Felipe Thalacker' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #4
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Resolver armários (Oswaldo)', 'Resolver armários (Oswaldo)

Categoria: Pessoal · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 4.', 'Pessoal', 'medium', 'open',
  '2026-11-18', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver armários (Oswaldo)' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver armários (Oswaldo)' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Resolver armários (Oswaldo)<br />
<br />
Categoria: Pessoal · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 4.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver armários (Oswaldo)' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #5
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Falar com Ana Roupa do Chef', 'Falar com Ana Roupa do Chef

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 5.', 'ON Solutions', 'medium', 'open',
  '2026-11-18', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Ana Roupa do Chef' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Ana Roupa do Chef' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Falar com Ana Roupa do Chef<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 5.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Ana Roupa do Chef' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #6
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Passar itens de uso para o Claude funcionar para o Isaac', 'Passar itens de uso para o Claude funcionar para o Isaac

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 6.', 'ON Solutions', 'medium', 'open',
  '2026-11-18', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Passar itens de uso para o Claude funcionar para o Isaac' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Passar itens de uso para o Claude funcionar para o Isaac' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Passar itens de uso para o Claude funcionar para o Isaac<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 6.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Passar itens de uso para o Claude funcionar para o Isaac' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #7
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 12, 12, 'Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.', 'Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.

Prazo: até o dia 18 (mês não informado, confirmar) · Alinhamento 13
Inclui também a importação dos prestadores com notas fiscais (item 8 do checklist, unificado nesta demanda).

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 7.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-18', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 12), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 12 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.<br />
<br />
Prazo: até o dia 18 (mês não informado, confirmar) · Alinhamento 13<br />
Inclui também a importação dos prestadores com notas fiscais (item 8 do checklist, unificado nesta demanda).<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 7.</p>', 16, 12, 12, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = 12 AND t.title = 'Realizar a importação dos funcionários até o dia 18, contemplando suas respectivas classificações.' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #9
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 11, 11, 'Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios', 'Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 9.', 'Vetriks', 'medium', 'open',
  '2026-11-18', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 11), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 11 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 9.</p>', 13, 11, 11, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = 11 AND t.title = 'Criar card de verificação da parte de funções: gerenciar EPI não pode mais travar por conta de RH; incluir permissões de visão de relatórios' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #10
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Desenvolver/revisar painel financeiro da Brooks no site', 'Desenvolver/revisar painel financeiro da Brooks no site

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 10.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-18', '2026-11-22', '2026-11-22',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver/revisar painel financeiro da Brooks no site' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver/revisar painel financeiro da Brooks no site' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Desenvolver/revisar painel financeiro da Brooks no site<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 10.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-22 18:00:00', '2026-11-22 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver/revisar painel financeiro da Brooks no site' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #11
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.', 'Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.

Alinhamento 3

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 11.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-18', '2026-11-22', '2026-11-22',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.<br />
<br />
Alinhamento 3<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 11.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-22 18:00:00', '2026-11-22 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implementar o custo previsto semanal, calculado com base no custo previsto total de cada categoria.' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #12
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Estruturar marketing utilizando Claude', 'Estruturar marketing utilizando Claude

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 12.', 'ON Solutions', 'medium', 'open',
  '2026-11-18', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Estruturar marketing utilizando Claude' AND t.data_inicio = '2026-11-18');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Estruturar marketing utilizando Claude' AND t.data_inicio = '2026-11-18';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Estruturar marketing utilizando Claude<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 12.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Estruturar marketing utilizando Claude' AND t.data_inicio = '2026-11-18'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #13
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 12, 12, 'Cancelar Contabo', 'Cancelar Contabo

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 13.', 'ON Solutions', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 12), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Cancelar Contabo' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 12 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Cancelar Contabo' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Cancelar Contabo<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 13.</p>', 4, 12, 12, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = 12 AND t.title = 'Cancelar Contabo' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #14
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 12, 12, 'Definir dias fixos para a reunião do financeiro', 'Definir dias fixos para a reunião do financeiro

SE REPETE: Toda Quinta feira 19h
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Reuniões · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 14.', 'Reuniões', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 12), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião do financeiro' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 12 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião do financeiro' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Definir dias fixos para a reunião do financeiro<br />
<br />
SE REPETE: Toda Quinta feira 19h<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Reuniões · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 14.</p>', 4, 12, 12, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião do financeiro' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #15
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Rever exames', 'Rever exames

Categoria: Pessoal · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 15.', 'Pessoal', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Rever exames' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Rever exames' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Rever exames<br />
<br />
Categoria: Pessoal · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 15.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Rever exames' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #16
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Placas de obra', 'Placas de obra

Conselho 13/11

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 16.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Placas de obra' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Placas de obra' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Placas de obra<br />
<br />
Conselho 13/11<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 16.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Placas de obra' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #17
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Conferir relatórios dos prestadores', 'Conferir relatórios dos prestadores

SE REPETE: Diário
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 17.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir relatórios dos prestadores' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir relatórios dos prestadores' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Conferir relatórios dos prestadores<br />
<br />
SE REPETE: Diário<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 17.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Conferir relatórios dos prestadores' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #18
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.', 'Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.

Prazo: até 19/11/2026 · Alinhamento 19

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 18.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-19', '2026-11-19', '2026-11-19',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.<br />
<br />
Prazo: até 19/11/2026 · Alinhamento 19<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 18.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-19 18:00:00', '2026-11-19 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 19/11, todas as placas das obras deverão estar devidamente implementadas e instaladas.' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #19
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Revisar processos e responsabilidades internas', 'Revisar processos e responsabilidades internas

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 19.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-19', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar processos e responsabilidades internas' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar processos e responsabilidades internas' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Revisar processos e responsabilidades internas<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 19.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar processos e responsabilidades internas' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #20
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Ver pintura', 'Ver pintura

Categoria: Estúdio · Empresa: A Firma
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 20.', 'Estúdio', 'medium', 'open',
  '2026-11-19', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver pintura' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver pintura' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Ver pintura<br />
<br />
Categoria: Estúdio · Empresa: A Firma<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 20.</p>', 6, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver pintura' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #21
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Iniciar reuniões semanais do Project', 'Iniciar reuniões semanais do Project

Conselho 13/11 · A partir de 19/11/2026, semanal

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 21.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-19', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Iniciar reuniões semanais do Project' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Iniciar reuniões semanais do Project' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Iniciar reuniões semanais do Project<br />
<br />
Conselho 13/11 · A partir de 19/11/2026, semanal<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 21.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Iniciar reuniões semanais do Project' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #22
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Implantar registro de RDO nas reuniões do Project', 'Implantar registro de RDO nas reuniões do Project

Conselho 13/11 · Entrega junto com a 1ª reunião semanal do Project

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 22.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-19', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implantar registro de RDO nas reuniões do Project' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implantar registro de RDO nas reuniões do Project' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Implantar registro de RDO nas reuniões do Project<br />
<br />
Conselho 13/11 · Entrega junto com a 1ª reunião semanal do Project<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 22.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Implantar registro de RDO nas reuniões do Project' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #23
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Elaborar relatórios das visitas semanais', 'Elaborar relatórios das visitas semanais

Conselho 13/11 · SE REPETE: Toda Quinta Feira - Relatório de visitas, para enviar para Lucas consolidar na sexta · Lucas passa o relatório; sem data na anotação
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 23.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-19', '2026-12-03', '2026-12-03',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar relatórios das visitas semanais' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar relatórios das visitas semanais' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Elaborar relatórios das visitas semanais<br />
<br />
Conselho 13/11 · SE REPETE: Toda Quinta Feira - Relatório de visitas, para enviar para Lucas consolidar na sexta · Lucas passa o relatório; sem data na anotação<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 23.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-12-03 18:00:00', '2026-12-03 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar relatórios das visitas semanais' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #24
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.', 'Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.

SE REPETE: Se repete a cada 15 dias para apresentar para Lucas consolidar · Alinhamento 17
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 24.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-19', '2026-12-03', '2026-12-03',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.' AND t.data_inicio = '2026-11-19');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.' AND t.data_inicio = '2026-11-19';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.<br />
<br />
SE REPETE: Se repete a cada 15 dias para apresentar para Lucas consolidar · Alinhamento 17<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 24.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-19 09:00:00', '2026-12-03 18:00:00', '2026-12-03 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alex, Andressa e Érico deverão apresentar relatórios quinzenais de percepção e leitura das obras.' AND t.data_inicio = '2026-11-19'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #25
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 12, 12, 'Definir dias fixos para a reunião comercial', 'Definir dias fixos para a reunião comercial

SE REPETE: Toda Sexta feira 8h
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Reuniões · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 25.', 'Reuniões', 'medium', 'open',
  '2026-11-20', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 12), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião comercial' AND t.data_inicio = '2026-11-20');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 12 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião comercial' AND t.data_inicio = '2026-11-20';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Definir dias fixos para a reunião comercial<br />
<br />
SE REPETE: Toda Sexta feira 8h<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Reuniões · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 25.</p>', 4, 12, 12, 'medium', 'open', 0,
  '2026-11-20 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = 12 AND t.title = 'Definir dias fixos para a reunião comercial' AND t.data_inicio = '2026-11-20'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #26
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar contrato do comercial', 'Criar contrato do comercial

Categoria: Administrativo e contratos · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 26.', 'Administrativo e contratos', 'medium', 'open',
  '2026-11-20', '2026-11-20', '2026-11-20',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar contrato do comercial' AND t.data_inicio = '2026-11-20');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar contrato do comercial' AND t.data_inicio = '2026-11-20';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar contrato do comercial<br />
<br />
Categoria: Administrativo e contratos · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 26.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-20 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar contrato do comercial' AND t.data_inicio = '2026-11-20'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #27
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Finalizar contrato de rescisão Amanda', 'Finalizar contrato de rescisão Amanda

Categoria: Administrativo e contratos · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 27.', 'Administrativo e contratos', 'medium', 'open',
  '2026-11-20', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar contrato de rescisão Amanda' AND t.data_inicio = '2026-11-20');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar contrato de rescisão Amanda' AND t.data_inicio = '2026-11-20';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Finalizar contrato de rescisão Amanda<br />
<br />
Categoria: Administrativo e contratos · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 27.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-20 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar contrato de rescisão Amanda' AND t.data_inicio = '2026-11-20'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #28
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Finalizar painel financeiro da Brooks', 'Finalizar painel financeiro da Brooks

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 28.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar painel financeiro da Brooks' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar painel financeiro da Brooks' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Finalizar painel financeiro da Brooks<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 28.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Finalizar painel financeiro da Brooks' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #29
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Verificar pendência da Agenda Microsoft', 'Verificar pendência da Agenda Microsoft

Conselho 13/11 · Data anterior: verificar

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 29.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Verificar pendência da Agenda Microsoft' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Verificar pendência da Agenda Microsoft' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Verificar pendência da Agenda Microsoft<br />
<br />
Conselho 13/11 · Data anterior: verificar<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 29.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Verificar pendência da Agenda Microsoft' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #30
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.', 'Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.

Alinhamento 8

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 30.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.<br />
<br />
Alinhamento 8<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 30.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar um processo formal de reembolso, estabelecendo que não haverá reembolso com prazo inferior a 20 dias.' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #31
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Organizar visitas semanais às obras', 'Organizar visitas semanais às obras

Conselho 13/11 · Semanais, sem dia definido

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 31.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Organizar visitas semanais às obras' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Organizar visitas semanais às obras' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Organizar visitas semanais às obras<br />
<br />
Conselho 13/11 · Semanais, sem dia definido<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 31.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Organizar visitas semanais às obras' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #32
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Falar com a Euro Fitness', 'Falar com a Euro Fitness

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 32.', 'ON Solutions', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com a Euro Fitness' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com a Euro Fitness' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Falar com a Euro Fitness<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 32.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com a Euro Fitness' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #33
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.', 'Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.

SE REPETE: Se repete a cada 15 dias · Alinhamento 5
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 33.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.<br />
<br />
SE REPETE: Se repete a cada 15 dias · Alinhamento 5<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 33.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatórios quinzenais de logística, contemplando alterações realizadas, compras não planejadas e demais movimentações.' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #34
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.', 'Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.

Alinhamento 9

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 34.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.<br />
<br />
Alinhamento 9<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 34.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar a regra de reembolso tanto nos contratos quanto nos processos internos.' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #35
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.', 'Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.

Alinhamento 16

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 35.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.<br />
<br />
Alinhamento 16<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 35.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Considerar a possibilidade de realização da Matterport em três fases da obra: início, meio e fim.' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #36
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar relatório de acidente técnico em campo para preenchimento online', 'Criar relatório de acidente técnico em campo para preenchimento online

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 36.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatório de acidente técnico em campo para preenchimento online' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatório de acidente técnico em campo para preenchimento online' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar relatório de acidente técnico em campo para preenchimento online<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 36.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar relatório de acidente técnico em campo para preenchimento online' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #37
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel', 'Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 37.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 37.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Colocar categorias nos documentos dentro do painel do cliente (igual ao de projetos) e permitir upload de documentos do lado do cliente no painel' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #38
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)', 'Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 38.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 38.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar o layout para ficar em lista, como feito em projetos (os quadrados ficam feios)' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #39
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar tipo de configuração em projeto como obra noturna', 'Criar tipo de configuração em projeto como obra noturna

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 39.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar tipo de configuração em projeto como obra noturna' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar tipo de configuração em projeto como obra noturna' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar tipo de configuração em projeto como obra noturna<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 39.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar tipo de configuração em projeto como obra noturna' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #40
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO', 'Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 40.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 40.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regras de empresa, quando a obra for noturna, os períodos de preenchimento permitidos, para não bloquear o RDO' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #41
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Em Solicitação de compras, adicionar a possibilidade de Reembolso', 'Em Solicitação de compras, adicionar a possibilidade de Reembolso

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 41.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Em Solicitação de compras, adicionar a possibilidade de Reembolso' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Em Solicitação de compras, adicionar a possibilidade de Reembolso' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Em Solicitação de compras, adicionar a possibilidade de Reembolso<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 41.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Em Solicitação de compras, adicionar a possibilidade de Reembolso' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #42
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Adicionar o status "Paralisada" nas atividades previstas em obras no cronograma, abrindo campo de justificativa', 'Adicionar o status "Paralisada" nas atividades previstas em obras no cronograma, abrindo campo de justificativa

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 42.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Adicionar o status "Paralisada" nas atividades previstas em obras no cronograma, abrindo campo de justificativa' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Adicionar o status "Paralisada" nas atividades previstas em obras no cronograma, abrindo campo de justificativa' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Adicionar o status &quot;Paralisada&quot; nas atividades previstas em obras no cronograma, abrindo campo de justificativa<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 42.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Adicionar o status "Paralisada" nas atividades previstas em obras no cronograma, abrindo campo de justificativa' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #43
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)', 'Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 43.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-23', '2026-11-23',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 43.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-23 18:00:00', '2026-11-23 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Alterar o status dentro do card da atividade em aderência de RDO (sugestão do Eduardo)' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #44
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Desenvolver estratégia de marketing e geração de leads', 'Desenvolver estratégia de marketing e geração de leads

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 44.', 'ON Solutions', 'medium', 'open',
  '2026-11-23', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver estratégia de marketing e geração de leads' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver estratégia de marketing e geração de leads' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Desenvolver estratégia de marketing e geração de leads<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 44.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Desenvolver estratégia de marketing e geração de leads' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #45
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não', 'Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 45.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 45.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de criar automaticamente um projeto aprovado ou não' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #46
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados', 'Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 46.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 46.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar em regra de empresa a opção de permitir ou não apagar orçamentos aprovados' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #47
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso', 'Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 47.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 47.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Permitir apagar orçamentos já aprovados e refletir nos relatórios financeiros; criar permissão para isso' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #48
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Ajustar para que o saldo do relatório seja separado em orçado x aprovado', 'Ajustar para que o saldo do relatório seja separado em orçado x aprovado

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 48.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar para que o saldo do relatório seja separado em orçado x aprovado' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar para que o saldo do relatório seja separado em orçado x aprovado' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Ajustar para que o saldo do relatório seja separado em orçado x aprovado<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 48.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ajustar para que o saldo do relatório seja separado em orçado x aprovado' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #49
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 11, 11, 'Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório', 'Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 49.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-27', '2026-11-27',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 11), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 11 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 49.</p>', 13, 11, 11, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-27 18:00:00', '2026-11-27 18:00:00'
FROM tickets t WHERE t.client_id = 11 AND t.title = 'Cadastro único de prestadores de EPI e de materiais, com registro permanente; entre obras, só o CA de cada equipamento passa a ser obrigatório' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #50
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 11, 11, 'Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)', 'Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 50.', 'Vetriks', 'medium', 'open',
  '2026-11-23', '2026-11-27', '2026-11-27',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 11), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)' AND t.data_inicio = '2026-11-23');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 11 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)' AND t.data_inicio = '2026-11-23';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 50.</p>', 13, 11, 11, 'medium', 'open', 0,
  '2026-11-23 09:00:00', '2026-11-27 18:00:00', '2026-11-27 18:00:00'
FROM tickets t WHERE t.client_id = 11 AND t.title = 'Adicionar opção de visualização por blocos, como era antes, em orçamentos e projetos, para escolha do cliente (no iPad os nomes ficam cortados na horizontal)' AND t.data_inicio = '2026-11-23'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #51
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.', 'Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.

Alinhamento 14

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 51.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-24', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.' AND t.data_inicio = '2026-11-24');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.' AND t.data_inicio = '2026-11-24';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.<br />
<br />
Alinhamento 14<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 51.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-24 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o processo de onboarding, estabelecendo uma periodicidade de atualização a cada 15 dias.' AND t.data_inicio = '2026-11-24'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #52
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.', 'Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.

Alinhamento 15

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 52.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-24', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.' AND t.data_inicio = '2026-11-24');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.' AND t.data_inicio = '2026-11-24';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.<br />
<br />
Alinhamento 15<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 52.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-24 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Atualizar o texto de onboarding, esclarecendo que a Matterport será realizada quando necessário, conforme a possibilidade e a necessidade de cada obra.' AND t.data_inicio = '2026-11-24'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #53
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Planejar tráfego pago', 'Planejar tráfego pago

Categoria: ON Solutions · Empresa: ON Solutions Brasil
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 53.', 'ON Solutions', 'medium', 'open',
  '2026-11-24', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Planejar tráfego pago' AND t.data_inicio = '2026-11-24');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Planejar tráfego pago' AND t.data_inicio = '2026-11-24';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Planejar tráfego pago<br />
<br />
Categoria: ON Solutions · Empresa: ON Solutions Brasil<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 53.</p>', 4, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-24 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Planejar tráfego pago' AND t.data_inicio = '2026-11-24'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #54
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Criar a cultura de elaboração de relatórios semanais de gastos por categorias.', 'Criar a cultura de elaboração de relatórios semanais de gastos por categorias.

Alinhamento 1

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 54.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-24', '2026-11-27', '2026-11-27',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar a cultura de elaboração de relatórios semanais de gastos por categorias.' AND t.data_inicio = '2026-11-24');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar a cultura de elaboração de relatórios semanais de gastos por categorias.' AND t.data_inicio = '2026-11-24';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Criar a cultura de elaboração de relatórios semanais de gastos por categorias.<br />
<br />
Alinhamento 1<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 54.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-24 09:00:00', '2026-11-27 18:00:00', '2026-11-27 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Criar a cultura de elaboração de relatórios semanais de gastos por categorias.' AND t.data_inicio = '2026-11-24'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #55
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Definir dias fixos para a reunião do Dophi', 'Definir dias fixos para a reunião do Dophi

SE REPETE: Toda Quarta Feira 19h
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Reuniões · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 55.', 'Reuniões', 'medium', 'open',
  '2026-11-25', '2026-11-25', '2026-11-25',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir dias fixos para a reunião do Dophi' AND t.data_inicio = '2026-11-25');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir dias fixos para a reunião do Dophi' AND t.data_inicio = '2026-11-25';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Definir dias fixos para a reunião do Dophi<br />
<br />
SE REPETE: Toda Quarta Feira 19h<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Reuniões · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 55.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-25 09:00:00', '2026-11-25 18:00:00', '2026-11-25 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir dias fixos para a reunião do Dophi' AND t.data_inicio = '2026-11-25'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #56
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Revisar contratos da Brooks', 'Revisar contratos da Brooks

Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 56.', 'Brooks: gestão e financeiro', 'medium', 'open',
  '2026-11-25', '2026-11-27', '2026-11-27',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar contratos da Brooks' AND t.data_inicio = '2026-11-25');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar contratos da Brooks' AND t.data_inicio = '2026-11-25';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Revisar contratos da Brooks<br />
<br />
Categoria: Brooks: gestão e financeiro · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 56.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-25 09:00:00', '2026-11-27 18:00:00', '2026-11-27 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar contratos da Brooks' AND t.data_inicio = '2026-11-25'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #57
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Falar com Camila Bololo', 'Falar com Camila Bololo

Categoria: ON Solutions · Empresa: Fideliza AI
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 57.', 'ON Solutions', 'medium', 'open',
  '2026-11-25', '2026-11-30', '2026-11-30',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Camila Bololo' AND t.data_inicio = '2026-11-25');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Camila Bololo' AND t.data_inicio = '2026-11-25';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Falar com Camila Bololo<br />
<br />
Categoria: ON Solutions · Empresa: Fideliza AI<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 57.</p>', 15, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-25 09:00:00', '2026-11-30 18:00:00', '2026-11-30 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Falar com Camila Bololo' AND t.data_inicio = '2026-11-25'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #58
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Revisar calendário de eventos da Brooks e da Vetriks', 'Revisar calendário de eventos da Brooks e da Vetriks

Responsável não informado no checklist.

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 58.', 'Brooks: reuniões e eventos', 'medium', 'completed',
  '2026-11-26', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), '2026-11-26 18:00:00'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Brooks e da Vetriks' AND t.data_inicio = '2026-11-26');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Brooks e da Vetriks' AND t.data_inicio = '2026-11-26';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Revisar calendário de eventos da Brooks e da Vetriks<br />
<br />
Responsável não informado no checklist.<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 58.</p>', 16, @narciso, @narciso, 'medium', 'completed', 0,
  '2026-11-26 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Brooks e da Vetriks' AND t.data_inicio = '2026-11-26'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #59
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Reciclagem', 'Reciclagem

Conselho 13/11

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 59.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-26', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reciclagem' AND t.data_inicio = '2026-11-26');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reciclagem' AND t.data_inicio = '2026-11-26';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Reciclagem<br />
<br />
Conselho 13/11<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 59.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-26 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reciclagem' AND t.data_inicio = '2026-11-26'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #60
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Acompanhar Project', 'Acompanhar Project

Conselho 13/11 · SE REPETE: Diariamente
(Demanda única: a repetição não é criada automaticamente.)

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 60.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-11-26', '2026-11-26', '2026-11-26',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar Project' AND t.data_inicio = '2026-11-26');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar Project' AND t.data_inicio = '2026-11-26';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Acompanhar Project<br />
<br />
Conselho 13/11 · SE REPETE: Diariamente<br />
(Demanda única: a repetição não é criada automaticamente.)<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 60.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-26 09:00:00', '2026-11-26 18:00:00', '2026-11-26 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar Project' AND t.data_inicio = '2026-11-26'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #61
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Instalar uma câmera no estoque para acompanhamento das movimentações.', 'Instalar uma câmera no estoque para acompanhamento das movimentações.

Alinhamento 11

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 61.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-27', '2026-11-30', '2026-11-30',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma câmera no estoque para acompanhamento das movimentações.' AND t.data_inicio = '2026-11-27');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma câmera no estoque para acompanhamento das movimentações.' AND t.data_inicio = '2026-11-27';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Instalar uma câmera no estoque para acompanhamento das movimentações.<br />
<br />
Alinhamento 11<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 61.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-27 09:00:00', '2026-11-30 18:00:00', '2026-11-30 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma câmera no estoque para acompanhamento das movimentações.' AND t.data_inicio = '2026-11-27'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #62
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.', 'Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.

Alinhamento 12

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 62.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-11-27', '2026-11-30', '2026-11-30',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.' AND t.data_inicio = '2026-11-27');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.' AND t.data_inicio = '2026-11-27';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.<br />
<br />
Alinhamento 12<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 62.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-11-27 09:00:00', '2026-11-30 18:00:00', '2026-11-30 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Instalar uma tela no estoque para que a equipe consiga realizar solicitações e registrar retiradas de materiais.' AND t.data_inicio = '2026-11-27'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #63
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Revisar calendário de eventos da Vetriks', 'Revisar calendário de eventos da Vetriks

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 63.', 'Vetriks', 'medium', 'completed',
  '2026-11-30', '2026-12-01', '2026-12-01',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), '2026-12-01 18:00:00'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Vetriks' AND t.data_inicio = '2026-11-30');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Vetriks' AND t.data_inicio = '2026-11-30';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Revisar calendário de eventos da Vetriks<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 63.</p>', 13, @narciso, @narciso, 'medium', 'completed', 0,
  '2026-11-30 09:00:00', '2026-12-01 18:00:00', '2026-12-01 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Revisar calendário de eventos da Vetriks' AND t.data_inicio = '2026-11-30'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #64
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Evento em Sorocaba', 'Evento em Sorocaba

Dia 01: confirmar mês e ano

Categoria: Visitas e eventos · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 64.', 'Visitas e eventos', 'medium', 'open',
  '2026-12-01', '2026-12-01', '2026-12-01',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Evento em Sorocaba' AND t.data_inicio = '2026-12-01');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Evento em Sorocaba' AND t.data_inicio = '2026-12-01';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Evento em Sorocaba<br />
<br />
Dia 01: confirmar mês e ano<br />
<br />
Categoria: Visitas e eventos · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 64.</p>', 13, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-01 09:00:00', '2026-12-01 18:00:00', '2026-12-01 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Evento em Sorocaba' AND t.data_inicio = '2026-12-01'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #65
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Resolver MEI', 'Resolver MEI

Conselho 13/11

Categoria: Pessoal · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 65.', 'Pessoal', 'medium', 'open',
  '2026-12-02', '2026-12-10', '2026-12-10',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver MEI' AND t.data_inicio = '2026-12-02');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver MEI' AND t.data_inicio = '2026-12-02';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Resolver MEI<br />
<br />
Conselho 13/11<br />
<br />
Categoria: Pessoal · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 65.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-02 09:00:00', '2026-12-10 18:00:00', '2026-12-10 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Resolver MEI' AND t.data_inicio = '2026-12-02'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #66
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Acompanhar máquina facial', 'Acompanhar máquina facial

Conselho 13/11

Categoria: Pessoal · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 66.', 'Pessoal', 'medium', 'open',
  '2026-12-02', '2026-12-10', '2026-12-10',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar máquina facial' AND t.data_inicio = '2026-12-02');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar máquina facial' AND t.data_inicio = '2026-12-02';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Acompanhar máquina facial<br />
<br />
Conselho 13/11<br />
<br />
Categoria: Pessoal · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 66.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-02 09:00:00', '2026-12-10 18:00:00', '2026-12-10 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Acompanhar máquina facial' AND t.data_inicio = '2026-12-02'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #67
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Visitar obra em Campos', 'Visitar obra em Campos

Categoria: Visitas e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 67.', 'Visitas e eventos', 'medium', 'open',
  '2026-12-03', '2026-12-06', '2026-12-06',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar obra em Campos' AND t.data_inicio = '2026-12-03');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar obra em Campos' AND t.data_inicio = '2026-12-03';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Visitar obra em Campos<br />
<br />
Categoria: Visitas e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 67.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-03 09:00:00', '2026-12-06 18:00:00', '2026-12-06 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar obra em Campos' AND t.data_inicio = '2026-12-03'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #68
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Reunião de alinhamento interno do setor de qualidade', 'Reunião de alinhamento interno do setor de qualidade

Categoria: Reuniões · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 68.', 'Reuniões', 'medium', 'open',
  '2026-12-04', '2026-12-04', '2026-12-04',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reunião de alinhamento interno do setor de qualidade' AND t.data_inicio = '2026-12-04');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reunião de alinhamento interno do setor de qualidade' AND t.data_inicio = '2026-12-04';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Reunião de alinhamento interno do setor de qualidade<br />
<br />
Categoria: Reuniões · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 68.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-04 09:00:00', '2026-12-04 18:00:00', '2026-12-04 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Reunião de alinhamento interno do setor de qualidade' AND t.data_inicio = '2026-12-04'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #69
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.', 'Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.

Prazo: até 04/01/2027 · Alinhamento 7

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 69.', 'Alinhamentos e ações', 'medium', 'open',
  '2026-12-04', '2026-12-07', '2026-12-07',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.' AND t.data_inicio = '2026-12-04');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.' AND t.data_inicio = '2026-12-04';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.<br />
<br />
Prazo: até 04/01/2027 · Alinhamento 7<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 69.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-04 09:00:00', '2026-12-07 18:00:00', '2026-12-07 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Até 04/01, todas as placas deverão estar contabilizadas, as solicitações de compras realizadas e as máquinas de identificação compradas e instaladas.' AND t.data_inicio = '2026-12-04'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #70
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Elaborar contrato', 'Elaborar contrato

Categoria: Administrativo e contratos · Empresa: A Firma
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 70.', 'Administrativo e contratos', 'medium', 'open',
  '2026-12-07', '2026-12-08', '2026-12-08',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar contrato' AND t.data_inicio = '2026-12-07');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar contrato' AND t.data_inicio = '2026-12-07';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Elaborar contrato<br />
<br />
Categoria: Administrativo e contratos · Empresa: A Firma<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 70.</p>', 6, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-07 09:00:00', '2026-12-08 18:00:00', '2026-12-08 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Elaborar contrato' AND t.data_inicio = '2026-12-07'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #71
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 11, 11, 'Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard', 'Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard

REVISÃO

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 71.', 'Vetriks', 'medium', 'open',
  '2026-12-07', '2026-12-11', '2026-12-11',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 11), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard' AND t.data_inicio = '2026-12-07');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 11 FROM tickets t WHERE t.client_id = 11 AND t.title = 'Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard' AND t.data_inicio = '2026-12-07';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard<br />
<br />
REVISÃO<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 71.</p>', 13, 11, 11, 'medium', 'open', 0,
  '2026-12-07 09:00:00', '2026-12-11 18:00:00', '2026-12-11 18:00:00'
FROM tickets t WHERE t.client_id = 11 AND t.title = 'Futuramente, integrar e concluir o painel financeiro dentro da Vetriks, colocando em regra de empresa usar integração com Nibo e botão sincronizar com dashboard' AND t.data_inicio = '2026-12-07'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #72
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Formalizar alinhamentos e responsabilidades', 'Formalizar alinhamentos e responsabilidades

Categoria: Administrativo e contratos · Empresa: A Firma
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 72.', 'Administrativo e contratos', 'medium', 'open',
  '2026-12-10', '2026-12-10', '2026-12-10',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar alinhamentos e responsabilidades' AND t.data_inicio = '2026-12-10');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar alinhamentos e responsabilidades' AND t.data_inicio = '2026-12-10';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Formalizar alinhamentos e responsabilidades<br />
<br />
Categoria: Administrativo e contratos · Empresa: A Firma<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 72.</p>', 6, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-10 09:00:00', '2026-12-10 18:00:00', '2026-12-10 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Formalizar alinhamentos e responsabilidades' AND t.data_inicio = '2026-12-10'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #73
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Visitar Quatri', 'Visitar Quatri

Categoria: Visitas e eventos · Empresa: Quatri Automação
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 73.', 'Visitas e eventos', 'medium', 'open',
  '2026-12-10', '2026-12-11', '2026-12-11',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar Quatri' AND t.data_inicio = '2026-12-10');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar Quatri' AND t.data_inicio = '2026-12-10';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Visitar Quatri<br />
<br />
Categoria: Visitas e eventos · Empresa: Quatri Automação<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 73.</p>', 2, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-10 09:00:00', '2026-12-11 18:00:00', '2026-12-11 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Visitar Quatri' AND t.data_inicio = '2026-12-10'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #74
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Ver móveis', 'Ver móveis

Categoria: Estúdio · Empresa: A Firma
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 74.', 'Estúdio', 'medium', 'open',
  '2026-12-10', '2026-12-12', '2026-12-12',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver móveis' AND t.data_inicio = '2026-12-10');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver móveis' AND t.data_inicio = '2026-12-10';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Ver móveis<br />
<br />
Categoria: Estúdio · Empresa: A Firma<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 74.</p>', 6, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-10 09:00:00', '2026-12-12 18:00:00', '2026-12-12 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Ver móveis' AND t.data_inicio = '2026-12-10'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #75
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Definir KPI Lucas para 2027', 'Definir KPI Lucas para 2027

Conselho 13/11 · Na anotação só consta 2027, sem dia definido

Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 75.', 'Brooks: reuniões e eventos', 'medium', 'open',
  '2026-12-15', '2026-12-22', '2026-12-22',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir KPI Lucas para 2027' AND t.data_inicio = '2026-12-15');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir KPI Lucas para 2027' AND t.data_inicio = '2026-12-15';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Definir KPI Lucas para 2027<br />
<br />
Conselho 13/11 · Na anotação só consta 2027, sem dia definido<br />
<br />
Categoria: Brooks: reuniões e eventos · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 75.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2026-12-15 09:00:00', '2026-12-22 18:00:00', '2026-12-22 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Definir KPI Lucas para 2027' AND t.data_inicio = '2026-12-15'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #76
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT 12, 12, 'Implementar em produção My Academy e Fideliza', 'Implementar em produção My Academy e Fideliza

REVISÃO · Lançamento em 10/01/2027

Categoria: Vetriks · Empresa: Vetriks
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 76.', 'Vetriks', 'medium', 'open',
  '2027-01-05', '2027-01-10', '2027-01-10',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = 12), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Implementar em produção My Academy e Fideliza' AND t.data_inicio = '2027-01-05');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, 12 FROM tickets t WHERE t.client_id = 12 AND t.title = 'Implementar em produção My Academy e Fideliza' AND t.data_inicio = '2027-01-05';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Implementar em produção My Academy e Fideliza<br />
<br />
REVISÃO · Lançamento em 10/01/2027<br />
<br />
Categoria: Vetriks · Empresa: Vetriks<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 76.</p>', 13, 12, 12, 'medium', 'open', 0,
  '2027-01-05 09:00:00', '2027-01-10 18:00:00', '2027-01-10 18:00:00'
FROM tickets t WHERE t.client_id = 12 AND t.title = 'Implementar em produção My Academy e Fideliza' AND t.data_inicio = '2027-01-05'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #77
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.', 'Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.

Alinhamento 20

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 77.', 'Alinhamentos e ações', 'medium', 'open',
  '2027-01-10', '2027-01-14', '2027-01-14',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.' AND t.data_inicio = '2027-01-10');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.' AND t.data_inicio = '2027-01-10';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.<br />
<br />
Alinhamento 20<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 77.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2027-01-10 09:00:00', '2027-01-14 18:00:00', '2027-01-14 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Lucas deverá realizar visitas às obras aproximadamente a cada dois meses.' AND t.data_inicio = '2027-01-10'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

-- #78
INSERT INTO tickets (client_id, attendant_id, title, description, category, priority, status,
  data_inicio, data_termino, data_prevista_entrega, client_ticket_number, completed_at)
SELECT @narciso, @narciso, 'Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.', 'Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.

Alinhamento 18 · Sem data no checklist: colocada como a última atividade.

Categoria: Alinhamentos e ações · Empresa: Brooks Construtora
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 78.', 'Alinhamentos e ações', 'medium', 'open',
  '2027-01-15', '2027-01-15', '2027-01-15',
  (SELECT COALESCE(MAX(x.client_ticket_number), 0) + 1 FROM tickets x WHERE x.client_id = @narciso), NULL
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.' AND t.data_inicio = '2027-01-15');

INSERT IGNORE INTO ticket_attendants (ticket_id, user_id)
SELECT t.id, @narciso FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.' AND t.data_inicio = '2027-01-15';

INSERT INTO planning_cards (ticket_id, title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
SELECT t.id, t.title, '<p>Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.<br />
<br />
Alinhamento 18 · Sem data no checklist: colocada como a última atividade.<br />
<br />
Categoria: Alinhamentos e ações · Empresa: Brooks Construtora<br />
Importado do Checklist de prioridades (print de 18/11/2026 às 16:39), item 78.</p>', 16, @narciso, @narciso, 'medium', 'open', 0,
  '2027-01-15 09:00:00', '2027-01-15 18:00:00', '2027-01-15 18:00:00'
FROM tickets t WHERE t.client_id = @narciso AND t.title = 'Érico ficará responsável por toda a parte de contratação, INSS, CNO, liberações e registros dos funcionários relacionados à construção.' AND t.data_inicio = '2027-01-15'
  AND NOT EXISTS (SELECT 1 FROM planning_cards pc WHERE pc.ticket_id = t.id);

COMMIT;

SELECT co.name AS empresa, u.name AS responsavel, pc.status, COUNT(*) AS qtd
FROM tickets t
JOIN planning_cards pc ON pc.ticket_id = t.id
LEFT JOIN companies co ON co.id = pc.company_id
LEFT JOIN users u ON u.id = t.attendant_id
WHERE t.description LIKE '%Importado do Checklist de prioridades (print de 18/11/2026 às 16:39)%'
GROUP BY co.name, u.name, pc.status WITH ROLLUP;

