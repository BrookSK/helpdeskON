-- Migration 136: Lembrete de vencimento dos cards de Planejamento.
--
-- Feature: quando faltar MENOS de 24h para o prazo (due_date) de um card, o
-- responsável (assigned_to) recebe um lembrete no WhatsApp. O disparo é feito
-- pelo cron (CronController::sendCardDueReminders), chamado tanto pelo endpoint
-- dedicado GET /cron/cardDueReminders quanto de dentro do runProspecting.
--
-- Para o lembrete sair UMA ÚNICA VEZ por prazo (e não a cada execução do cron),
-- registramos o instante do envio nesta coluna. Ela é zerada pela aplicação
-- (PlanningController::update) sempre que o due_date do card muda, tornando o
-- novo prazo elegível a um novo lembrete.
--
-- IDEMPOTENTE: pode ser executada mais de uma vez sem erro. Só cria a coluna e
-- o índice se ainda não existirem (evita "Duplicate column"/"Duplicate key"),
-- para ser segura em qualquer ambiente (local, beta, produção).
--
-- Execute manualmente no MySQL.

-- 1) Coluna due_reminder_sent_at (só cria se não existir).
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'planning_cards'
      AND COLUMN_NAME = 'due_reminder_sent_at');
SET @sql := IF(@exists = 0,
    "ALTER TABLE planning_cards ADD COLUMN due_reminder_sent_at DATETIME NULL DEFAULT NULL COMMENT 'Quando o lembrete de <24h do vencimento foi enviado ao responsável' AFTER due_date",
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Índice que acelera a query do cron (cards com prazo próximo ainda não lembrados).
SET @exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'planning_cards'
      AND INDEX_NAME = 'idx_planning_cards_due_reminder');
SET @sql := IF(@exists = 0,
    "CREATE INDEX idx_planning_cards_due_reminder ON planning_cards(due_date, due_reminder_sent_at)",
    'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
