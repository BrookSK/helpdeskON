-- =====================================================================
-- Seed: Recesso de fim de ano 2026/2027 (período em que ninguém trabalha).
-- ---------------------------------------------------------------------
-- NÃO é migration de schema. É script de DADOS. Rode no phpMyAdmin
-- (produção: banco "helpdesk_on") na aba SQL. Requer a tabela criada pela
-- migration 165_recess_periods.sql.
--
-- Após rodar, o calendário do Planejamento pinta os dias 21/12/2026 a
-- 04/01/2027 como recesso (fundo listrado) e a cascata de atividade
-- predecessora passa a deslocar automaticamente cards que cairiam nesse
-- período. Escopo GLOBAL (company_id NULL = vale para todas as empresas).
--
-- IDEMPOTENTE: apaga antes um recesso com este mesmo título/intervalo e
-- recria, então pode rodar de novo sem duplicar.
-- =====================================================================

SET @author := (SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 ORDER BY id ASC LIMIT 1);
SET @author := COALESCE(@author, (SELECT id FROM users ORDER BY id ASC LIMIT 1));

SET @title := 'Recesso de fim de ano';

-- Limpeza idempotente: remove o mesmo recesso (por título + intervalo) antes.
DELETE FROM recess_periods
 WHERE title = @title AND start_date = '2026-12-21' AND end_date = '2027-01-04';

INSERT INTO recess_periods (title, start_date, end_date, company_id, created_by)
VALUES (@title, '2026-12-21', '2027-01-04', NULL, @author);

-- Conferência (rode junto): deve listar o recesso recém-criado.
SELECT id, title, start_date, end_date, company_id FROM recess_periods
 WHERE title = @title ORDER BY start_date;
