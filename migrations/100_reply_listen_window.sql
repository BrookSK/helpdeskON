-- =====================================================================
-- 100_reply_listen_window.sql
-- ---------------------------------------------------------------------
-- Suporte à "janela de escuta" ao detectar resposta do lead e ao lock que
-- evita duplicação da triagem:
--
--   * reply_listen_until : enquanto no futuro, o participante está "escutando"
--     (aguardando novas mensagens do lead antes de interpretar). Novas respostas
--     no intervalo NÃO reiniciam a janela nem re-disparam a triagem.
--   * triaged_at : marca que o participante já foi triado. Impede reprocessar a
--     triagem quando o lead manda várias mensagens (ex.: entre execuções do cron).
--
-- Config: sequence_reply_listen_minutes (padrão 2) — tempo da janela de escuta.
-- Idempotente.
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='reply_listen_until');
SET @s := IF(@c=0,'ALTER TABLE sequence_participants ADD COLUMN reply_listen_until DATETIME NULL AFTER next_run_at','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='triaged_at');
SET @s := IF(@c=0,'ALTER TABLE sequence_participants ADD COLUMN triaged_at DATETIME NULL AFTER reply_listen_until','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Tempo (minutos) da janela de escuta ao detectar resposta.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('sequence_reply_listen_minutes', '2');
