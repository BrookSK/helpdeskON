-- =====================================================================
-- AJUSTE DO CRONOGRAMA — DEMAIS EMPRESAS (exceto Quatri e Vetriks) — v1 (03/11/2026)
-- Rodar no phpMyAdmin (produção), aba SQL, de uma vez. Script de DADOS.
-- Hoje = ter 03/11 | qua 04/11 | qui 05/11 | sex 06/11 | seg 09/11 | ter 10/11
-- Responsáveis e empresas resolvidos pelo NOME (rode a conferência no início
-- para ver se todos os ids vieram preenchidos antes de seguir).
-- =====================================================================

SET @vacari  := (SELECT id FROM users WHERE name LIKE 'Lucas Vacari%'   ORDER BY id LIMIT 1);
SET @julia   := (SELECT id FROM users WHERE name LIKE 'Julia Bertolucci%' ORDER BY id LIMIT 1);
SET @narciso := (SELECT id FROM users WHERE name LIKE 'Lucas Narciso%'  ORDER BY id LIMIT 1);
SET @felipe  := (SELECT id FROM users WHERE name LIKE 'Felipe%'         ORDER BY id LIMIT 1);
SET @author  := COALESCE(@vacari, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));

SET @co_multilife := (SELECT id FROM companies WHERE name LIKE '%MultiLife%'  ORDER BY id LIMIT 1);
SET @co_helpdesk  := (SELECT id FROM companies WHERE name LIKE '%HelpDesk%'   ORDER BY id LIMIT 1);
SET @co_centralrz := (SELECT id FROM companies WHERE name LIKE '%Central RZ%' ORDER BY id LIMIT 1);

-- Conferência dos ids (todos devem vir preenchidos; se algum vier NULL, me avise)
SELECT @vacari AS vacari, @julia AS julia, @narciso AS narciso, @felipe AS felipe,
       @co_multilife AS multilife, @co_helpdesk AS helpdesk, @co_centralrz AS central_rz;

-- =====================================================================
-- My Academy
-- =====================================================================
-- 481 — Adaptar sistema ao do Felipe: passa para a Júlia, 09–10/11, descrição nova
UPDATE planning_cards SET
  assigned_to=@julia,
  start_date='2026-11-09 09:00:00', end_date='2026-11-10 18:00:00', due_date='2026-11-10 18:00:00',
  description=CONCAT(
    '<p><strong>Objetivo:</strong> integrar ao My Academy o sistema de criação de vídeos com IA feito pelo Felipe, como uma função cobrada à parte.</p>',
    '<p><strong>Passo a passo:</strong></p>',
    '<ol>',
    '<li>Pegar o sistema de IA que o Felipe fez (arquivo enviado no grupo e anexado nesta demanda).</li>',
    '<li>Perguntar ao Lucas Mendes ou ao Lucas Vacari qual é o repositório do My Academy, clonar e abrir no Kiro.</li>',
    '<li>Pedir ao Kiro para implementar a função de <strong>criação de aulas com IA</strong>.</li>',
    '</ol>',
    '<p><strong>Como deve funcionar:</strong></p>',
    '<ul>',
    '<li>Uma página onde o usuário escreve o prompt e o que quer no vídeo.</li>',
    '<li>A página fala via API com o sistema do Felipe, que gera o vídeo (personagem a partir do prompt).</li>',
    '<li>Enquanto gera, a tela mostra o andamento de forma clara para o usuário; ao terminar, mostra o vídeo.</li>',
    '<li>O usuário precisa <strong>aprovar</strong> o vídeo. Aprovado, pode criar um curso com ele ou colocá-lo em uma aula.</li>',
    '</ul>',
    '<p><strong>Cobrança (as duas opções prontas e testadas):</strong></p>',
    '<ul>',
    '<li>Incluída no plano — o super admin ativa/desativa a função por plano.</li>',
    '<li>Cobrada separadamente, por geração de vídeo.</li>',
    '</ul>',
    '<p>O super admin escolhe qual modelo usar.</p>'
  )
WHERE id=481;

-- 18 — Migrar My Academy: sexta 06/11 (mantém horário)
UPDATE planning_cards SET due_date='2026-11-06 10:00:00' WHERE id=18;

-- 257 — Metas e pagamento via Pix: passa para a Júlia, mantém 13/11, nota na descrição
UPDATE planning_cards SET
  assigned_to=@julia,
  description=CONCAT(COALESCE(description,''), '<p><strong>Antes de começar:</strong> mandar mensagem para o Lucas Vacari ou o Lucas Narciso perguntando sobre esta demanda.</p>')
WHERE id=257;

-- =====================================================================
-- LumiClinic
-- =====================================================================
-- 456 — Reunião de onboarding (até quinta): 04–05/11, mantém Júlia
UPDATE planning_cards SET start_date='2026-11-04 09:00:00', end_date='2026-11-05 18:00:00', due_date='2026-11-05 18:00:00' WHERE id=456;

-- 427 — Processo comercial: 03–04/11, mantém Júlia
UPDATE planning_cards SET start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00' WHERE id=427;

-- 455 — Captar primeiros usuários candidatos: 04–05/11
UPDATE planning_cards SET start_date='2026-11-04 09:00:00', end_date='2026-11-05 18:00:00', due_date='2026-11-05 18:00:00' WHERE id=455;

-- =====================================================================
-- Braziliana
-- =====================================================================
-- 9 — Ajustes financeiro: 06–09/11, mantém Vacari
UPDATE planning_cards SET start_date='2026-11-06 09:00:00', end_date='2026-11-09 18:00:00', due_date='2026-11-09 18:00:00' WHERE id=9;

-- 469 — Telefone WhatsApp: quinta 05/11, prioridade medium -> high
UPDATE planning_cards SET due_date='2026-11-05 18:00:00', priority='high' WHERE id=469;

-- =====================================================================
-- Brooks Construtora
-- =====================================================================
-- 467 — Pendências de recebimento Brooks/Vetriks: amanhã 04/11, high -> urgent, mantém Narciso
UPDATE planning_cards SET due_date='2026-11-04 18:00:00', priority='urgent' WHERE id=467;

-- 508 — Migrar e-mails/agenda/Teams + reunião: amanhã 04/11, high -> urgent, mantém Narciso
UPDATE planning_cards SET due_date='2026-11-04 18:00:00', priority='urgent' WHERE id=508;

-- 492 — Cronograma de treinamentos: mantém 05/11, prioridade medium -> high
UPDATE planning_cards SET priority='high' WHERE id=492;

-- 440 — Data book prévia (modelo Dev): mantém data, nota na descrição
UPDATE planning_cards SET
  description=CONCAT(COALESCE(description,''), '<p><strong>Antes de começar:</strong> verificar com o Lucas Narciso o que é para ser feito.</p>')
WHERE id=440;

-- =====================================================================
-- Central RZ
-- =====================================================================
-- 489 — Reunião com Renato (Copilot): mesmo dia/horário da entrega V3/V4 (04/11 10:00)
UPDATE planning_cards SET due_date='2026-11-04 10:00:00' WHERE id=489;

-- =====================================================================
-- Fideliza AI
-- =====================================================================
-- 19 — Migrar Fideliza: sexta 06/11 (mantém horário)
UPDATE planning_cards SET due_date='2026-11-06 11:00:00' WHERE id=19;

-- =====================================================================
-- HelpDesk
-- =====================================================================
-- 488, 487, 486, 482 — em homologação: entre hoje e amanhã (03–04/11)
UPDATE planning_cards SET start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00'
WHERE id IN (488, 487, 486, 482);

-- 509 — Criação das LPs: 03–04/11, prioridade medium -> high
UPDATE planning_cards SET start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00', priority='high' WHERE id=509;

-- =====================================================================
-- Mafedo Engenharia
-- =====================================================================
-- 372 — Devolutiva do site: sexta 06/11, passa para a Júlia
UPDATE planning_cards SET due_date='2026-11-06 18:00:00', assigned_to=@julia WHERE id=372;

-- =====================================================================
-- ON Solutions Brasil
-- =====================================================================
-- 493 — Reunião Bololo: amanhã 04/11, medium -> high, mantém Narciso
UPDATE planning_cards SET due_date='2026-11-04 14:00:00', priority='high' WHERE id=493;

-- 472 — Modelos dos contratos ON e LRV: quinta 05/11, mantém Narciso
UPDATE planning_cards SET due_date='2026-11-05 18:00:00' WHERE id=472;

-- 479 — Finalização site casamentos: em andamento, amanhã 04/11, tira da descrição
--       o trecho "pedir pro Vacari colocar em prod" (já foi feito)
UPDATE planning_cards SET
  status='in_progress', due_date='2026-11-04 18:00:00',
  description=REGEXP_REPLACE(COALESCE(description,''),
      '<(p|li)>[^<]*Vacari[^<]*prod[^<]*</(p|li)>', '', 1, 0, 'i')
WHERE id=479;

-- 480 — Reunião com Amanda (sites institucionais): quinta 05/11, mantém Narciso
UPDATE planning_cards SET due_date='2026-11-05 18:00:00' WHERE id=480;

-- 465 — Recebimento dos uniformes: atendente Lucas Narciso, mantém 10/11
UPDATE planning_cards SET assigned_to=@narciso WHERE id=465;

-- =====================================================================
-- CARDS NOVOS (idempotentes: apaga o de mesmo título antes de inserir)
-- =====================================================================

-- N1) Estressar sistema da MultiLife Care — Vacari, alta, 06–10/11
DELETE FROM planning_cards WHERE title='Estressar sistema da MultiLife Care' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Estressar sistema da MultiLife Care',
  '<p>Rodar testes de estresse/carga no sistema da MultiLife Care e registrar os pontos de falha ou lentidão encontrados.</p>',
  @co_multilife, @vacari, @author, 'high', 'open', 0,
  '2026-11-06 09:00:00', '2026-11-10 18:00:00', '2026-11-10 18:00:00');

-- N2) Subir ajustes do Felipe do HelpDesk para produção — Felipe, amanhã 04/11
DELETE FROM planning_cards WHERE title='Subir ajustes do Felipe do HelpDesk para produção' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Subir ajustes do Felipe do HelpDesk para produção',
  '<p>Publicar em produção os ajustes do HelpDesk feitos pelo Felipe.</p>',
  @co_helpdesk, @felipe, @author, 'high', 'open', 0,
  '2026-11-04 09:00:00', '2026-11-04 18:00:00', '2026-11-04 18:00:00');

-- N3) Testar processo do HelpDesk (ajustes do Felipe) — Júlia, 03–04/11
DELETE FROM planning_cards WHERE title='Testar processo do HelpDesk (ajustes do Felipe)' COLLATE utf8mb4_unicode_ci;
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date)
VALUES ('Testar processo do HelpDesk (ajustes do Felipe)',
  '<p>Testar o processo do HelpDesk com os ajustes feitos pelo Felipe. Dúvidas: tirar com o Felipe.</p>',
  @co_helpdesk, @julia, @author, 'high', 'open', 0,
  '2026-11-03 09:00:00', '2026-11-04 18:00:00', '2026-11-04 18:00:00');

-- N4) Finalizar sistema do Renato — Central RZ, Vacari, hoje 07:30 até amanhã 14:00
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
