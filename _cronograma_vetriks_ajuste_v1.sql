-- =====================================================================
-- AJUSTE PONTUAL DO CRONOGRAMA VETRIKS (company_id = 13) — v1 (03/11/2026)
-- Rodar no phpMyAdmin (produção), aba SQL, de uma vez. Script de DADOS.
-- Só UPDATE por id. Cards não listados aqui ficam como estão.
-- Hoje = terça 03/11 | amanhã = quarta 04/11 | sexta = 06/11
-- =====================================================================

-- 470 — Vetriks - Ajustes e 502 — Filtro na listagem de pedidos:
-- início 30/10, desenvolvimento até 05/11 (prazo acompanha o fim do dev)
UPDATE planning_cards SET
  start_date='2026-10-30 09:00:00', end_date='2026-11-05 18:00:00', due_date='2026-11-05 18:00:00'
WHERE id IN (470, 502) AND company_id = 13;

-- 490 — Mapear dados a limpar e 491 — Limpar banco Vetriks:
-- prazo para amanhã (04/11) e prioridade medium -> high
UPDATE planning_cards SET
  due_date='2026-11-04 18:00:00', priority='high'
WHERE id IN (490, 491) AND company_id = 13;

-- 387 — Ajustes na tela de utilitários e 444 — Orçamento via link + PDF no WhatsApp:
-- entre hoje e amanhã (03–04/11), status continua em revisão interna
UPDATE planning_cards SET
  start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00'
WHERE id IN (387, 444) AND company_id = 13;

-- 514 — Regra de aprovação de transferência (estoque): entre hoje e amanhã (03–04/11),
-- status continua em homologação
UPDATE planning_cards SET
  start_date='2026-11-03 09:00:00', end_date='2026-11-04 18:00:00', due_date='2026-11-04 18:00:00'
WHERE id = 514 AND company_id = 13;

-- 519 — PDF com itens dos pedidos para vendedores: sexta-feira (06/11)
UPDATE planning_cards SET
  start_date='2026-11-06 09:00:00', end_date='2026-11-06 18:00:00', due_date='2026-11-06 18:00:00'
WHERE id = 519 AND company_id = 13;

-- ---------------------------------------------------------------------
-- CONFERÊNCIA
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date, pc.due_date
FROM planning_cards pc
WHERE pc.company_id = 13 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date,'2000-01-01'), pc.due_date, pc.id;
