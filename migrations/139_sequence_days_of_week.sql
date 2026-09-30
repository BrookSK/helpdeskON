-- =====================================================================
-- 139_sequence_days_of_week.sql
-- ---------------------------------------------------------------------
-- Dias da semana permitidos para ENVIO de uma sequência.
--
-- Motivação (bug de produção): a janela de envio da sequência só considerava
-- horário (window_start/window_end) e um flag de fim de semana (send_weekends).
-- Faltava o mesmo controle de DIAS DA SEMANA que a campanha de prospecção
-- (apollo_campaigns.days_of_week) já tem. Sem isso, o envio de WhatsApp escapava
-- do que estava configurado na campanha (ex.: mandava sábado/domingo/feriado).
--
-- Formato: CSV ISO-8601 "1,2,3,4,5" (1=segunda ... 7=domingo). Igual a
-- apollo_campaigns.days_of_week. NULL/vazio = todos os dias (comportamento
-- anterior preservado; o send_weekends continua valendo como fallback).
--
-- Idempotente (IF NOT EXISTS) para reexecução segura.
-- =====================================================================

ALTER TABLE email_sequences
    ADD COLUMN IF NOT EXISTS days_of_week VARCHAR(20) DEFAULT NULL
        COMMENT 'dias permitidos p/ envio, CSV ISO-8601 "1,2,3,4,5" (1=seg..7=dom); null=todos'
        AFTER send_weekends;
