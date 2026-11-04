-- =====================================================================
-- AJUSTE DO CRONOGRAMA — DEMAIS EMPRESAS — v2 (continuação da v1)
-- A v1 parou no card 479 (erro #1582: o REGEXP_REPLACE do MariaDB aceita
-- só 3 parâmetros). Tudo ANTES do 479 já foi aplicado pela v1.
-- Este arquivo roda do 479 em diante. Rodar de uma vez no phpMyAdmin.
-- =====================================================================

SET @vacari  := (SELECT id FROM users WHERE name LIKE 'Lucas Vacari%'     ORDER BY id LIMIT 1);
SET @julia   := (SELECT id FROM users WHERE name LIKE 'Julia Bertolucci%' ORDER BY id LIMIT 1);
SET @narciso := (SELECT id FROM users WHERE name LIKE 'Lucas Narciso%'    ORDER BY id LIMIT 1);
SET @felipe  := (SELECT id FROM users WHERE name LIKE 'Felipe%'           ORDER BY id LIMIT 1);
SET @author  := COALESCE(@vacari, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));

SET @co_multilife := (SELECT id FROM companies WHERE name LIKE '%MultiLife%'  ORDER BY id LIMIT 1);
SET @co_helpdesk  := (SELECT id FROM companies WHERE name LIKE '%HelpDesk%'   ORDER BY id LIMIT 1);
SET @co_centralrz := (SELECT id FROM companies WHERE name LIKE '%Central RZ%' ORDER BY id LIMIT 1);

SELECT @vacari AS vacari, @julia AS julia, @narciso AS narciso, @felipe AS felipe,
       @co_multilife AS multilife, @co_helpdesk AS helpdesk, @co_centralrz AS central_rz;

-- 479 — em andamento, 04/11, remove o trecho "pedir pro Vacari colocar em prod"
--       (MariaDB: REGEXP_REPLACE com 3 parâmetros; (?i) = sem diferenciar maiúsculas)
UPDATE planning_cards SET
  status='in_progress', due_date='2026-11-04 18:00:00',
  description=REGEXP_REPLACE(COALESCE(description,''),
      '(?i)<(p|li)>[^<]*Vacari[^<]*prod[^<]*</(p|li)>', '')
WHERE id=479;

-- 480 — Reunião com Amanda: quinta 05/11
UPDATE planning_cards SET due_date='2026-11-05 18:00:00' WHERE id=480;

-- 465 — Recebimento dos uniformes: atendente Lucas Narciso
UPDATE planning_cards SET assigned_to=@narciso WHERE id=465;

-- =====================================================================
-- CARDS NOVOS (apaga o de mesmo título antes de inserir)
-- =====================================================================
DELETE FROM planning_cards WHERE title='Estressar sistema da MultiLife Care' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Estressar sistema da MultiLife Care',
  '<p>Rodar testes de estresse/carga no sistema da MultiLife Care e registrar os pontos de falha ou lentidão encontrados.</p>',
  @co_multilife, @vacari, @author, 'high', 'open', 0,
  '2026-11-06 09:00:00', '2026-11-10 18:00:00', '2026-11-10 18:00:00');

DELETE FROM planning_cards WHERE title='Subir ajustes do Felipe do HelpDesk para produção' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Subir ajustes do Felipe do HelpDesk para produção',
  '<p>Publicar em produção os ajustes do HelpDesk feitos pelo Felipe.</p>',
  @co_helpdesk, @felipe, @author, 'high', 'open', 0,
  '2026-11-04 09:00:00', '2026-11-04 18:00:00', '2026-11-04 18:00:00');

DELETE FROM planning_cards WHERE title='Testar processo do HelpDesk (ajustes do Felipe)' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Testar processo do HelpDesk (ajustes do Felipe)',
  '<p>Testar o processo do HelpDesk com os ajustes feitos pelo Felipe. Dúvidas: tirar com o Felipe.</p>',
  @co_helpdesk, @julia, @author, 'high', 'open', 0,
  '2026-11-03 09:00:00', '2026-11-04 18:00:00', '2026-11-04 18:00:00');

DELETE FROM planning_cards WHERE title='Finalizar sistema do Renato' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Finalizar sistema do Renato',
  '<p>Finalizar o sistema do Renato (projeto Central RZ).</p>',
  @co_centralrz, @vacari, @author, 'high', 'open', 0,
  '2026-11-03 07:30:00', '2026-11-04 14:00:00', '2026-11-04 14:00:00');

-- =====================================================================
-- CONFERÊNCIA FINAL
-- =====================================================================
SELECT COALESCE(co.name,'(sem empresa)') AS empresa, pc.id, pc.title, pc.status, pc.priority,
       pc.start_date, pc.end_date, pc.due_date, u.name AS atendente
FROM planning_cards pc
LEFT JOIN companies co ON pc.company_id = co.id
LEFT JOIN users u ON pc.assigned_to = u.id
WHERE (pc.company_id IS NULL OR pc.company_id NOT IN (2, 13))
  AND pc.status NOT IN ('completed','archived','denied')
ORDER BY empresa, COALESCE(pc.start_date, pc.due_date, '2000-01-01'), pc.id;
