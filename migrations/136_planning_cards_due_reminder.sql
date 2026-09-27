-- Migration 136: Lembrete de vencimento dos cards de Planejamento.
--
-- Feature: quando faltar MENOS de 24h para o prazo (due_date) de um card, o
-- responsável (assigned_to) recebe um lembrete no WhatsApp. O disparo é feito
-- por um cron periódico (CronController::cardDueReminders).
--
-- Para o lembrete sair UMA ÚNICA VEZ por prazo (e não a cada execução do cron),
-- registramos o instante do envio nesta coluna. Ela é zerada pela aplicação
-- (PlanningController::update) sempre que o due_date do card muda, tornando o
-- novo prazo elegível a um novo lembrete.
--
-- Execute manualmente no MySQL.

ALTER TABLE planning_cards
    ADD COLUMN due_reminder_sent_at DATETIME NULL DEFAULT NULL
        COMMENT 'Quando o lembrete de <24h do vencimento foi enviado ao responsável' AFTER due_date;

-- Ajuda a query do cron (cards com prazo próximo ainda não lembrados).
CREATE INDEX idx_planning_cards_due_reminder ON planning_cards(due_date, due_reminder_sent_at);
