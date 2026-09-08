-- =====================================================================
-- PARA o participante de teste semeado pela 076 (Lucas Vacari — TESTE).
-- =====================================================================
-- Problema: a migration 076 cria o contato "Lucas Vacari (TESTE)" e o
-- INSCREVE automaticamente na sequência "Prospecção Apollo · TESTE (5min)".
-- Esse participante roda sozinho pelo cron, então os e-mails continuam
-- indo para ele mesmo quando o operador seleciona OUTRO lead na campanha.
--
-- Esta migration apenas ENCERRA (finished) esse participante de teste — não
-- apaga o contato nem a sequência. É reversível: reexecutar a 076 reinscreve.
-- =====================================================================

SET @seq_id := (SELECT id FROM email_sequences
                WHERE name = 'Prospecção Apollo · TESTE (5min)'
                ORDER BY id ASC LIMIT 1);

SET @contact_id := (SELECT id FROM whatsapp_contacts
                    WHERE lead_email = 'lucas@lrvweb.com.br'
                    ORDER BY id ASC LIMIT 1);

-- Encerra o participante de teste (para de enviar), preservando o histórico.
UPDATE sequence_participants
SET status = 'finished',
    stop_reason = 'Encerrado pela migration 080 (participante de teste semeado).',
    finished_at = NOW(),
    next_run_at = NULL
WHERE @seq_id IS NOT NULL
  AND @contact_id IS NOT NULL
  AND sequence_id = @seq_id
  AND contact_id = @contact_id
  AND status IN ('active', 'paused');
