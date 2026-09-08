-- =====================================================================
-- 101_stop_participantes_ativos.sql  (limpeza / manutenção)
-- ---------------------------------------------------------------------
-- Encerra TODOS os participantes ATIVOS/PAUSADOS das sequências (para os
-- fluxos que ficaram em looping durante os testes). Não apaga histórico —
-- apenas marca como 'stopped' e zera o agendamento (next_run_at).
--
-- Usa apenas colunas que sempre existem, então funciona mesmo que a
-- migration 100 (reply_listen_until / triaged_at) ainda não tenha sido aplicada.
-- =====================================================================

UPDATE sequence_participants
SET status = 'stopped',
    stop_reason = 'limpeza manual',
    finished_at = NOW(),
    next_run_at = NULL
WHERE status IN ('active', 'paused');

-- Se as colunas da migration 100 existirem, zera também a janela de escuta e o
-- lock de triagem (idempotente e seguro caso ainda não existam).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='reply_listen_until');
SET @s := IF(@c>0, 'UPDATE sequence_participants SET reply_listen_until = NULL WHERE reply_listen_until IS NOT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='triaged_at');
SET @s := IF(@c>0, 'UPDATE sequence_participants SET triaged_at = NULL WHERE triaged_at IS NOT NULL', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
