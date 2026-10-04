-- Migration 155: Campos extras do formulário do RDO.
--
-- O Guia Operacional define 4 seções distintas no relatório diário:
--   1. Atividades realizadas  → campo 'activities' (já existia).
--   2. Pendências / em andamento → campo 'pending_tasks' (NOVO).
--   3. Impedimentos / bloqueios  → campo 'occurrences' (já existia).
--   4. Plano para o próximo dia  → campo 'next_day_plan' (NOVO).
--
-- Execute manualmente no MySQL após 154_daily_reports_history.sql.

USE helpdesk_on;

ALTER TABLE daily_reports
    ADD COLUMN pending_tasks LONGTEXT NULL
        COMMENT 'O que ficou em andamento / pendente para os próximos dias'
        AFTER occurrences,
    ADD COLUMN next_day_plan LONGTEXT NULL
        COMMENT 'Plano / prioridades para o próximo dia útil'
        AFTER pending_tasks;
