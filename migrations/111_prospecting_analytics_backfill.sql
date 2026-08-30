-- =====================================================================
-- 111_prospecting_analytics_backfill.sql
-- ---------------------------------------------------------------------
-- BACKFILL da Camada 1 (Performance): as tabelas de analytics só passaram a ser
-- gravadas depois de criadas (106). Envios ANTERIORES não apareciam na aba
-- Performance. Este SQL preenche o histórico já existente:
--
--   1) prospecting_message_log  <- e-mails (email_messages) e WhatsApp
--      (whatsapp_messages from_me=1) já enviados por participantes de sequência.
--   2) prospecting_lead_outcome <- um registro por participante, com o estágio
--      do funil inferido do estado atual (respondeu/qualificado/agendou).
--
-- Idempotente: usa INSERT ... WHERE NOT EXISTS / ON DUPLICATE para não duplicar.
-- Requer as tabelas da migration 106.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1a) Mensagens de E-MAIL enviadas por sequência
-- ---------------------------------------------------------------------
INSERT INTO prospecting_message_log
    (contact_id, sequence_id, participant_id, node_id, channel, ab_variant, subject, body, len_chars, sent_at, created_at)
SELECT
    m.contact_id,
    sp.sequence_id,
    m.sequence_participant_id,
    m.node_id,
    'email',
    m.ab_variant,
    m.subject,
    m.body,
    CHAR_LENGTH(COALESCE(m.subject,'')) + CHAR_LENGTH(COALESCE(m.body,'')),
    COALESCE(m.sent_at, m.created_at),
    NOW()
FROM email_messages m
JOIN sequence_participants sp ON sp.id = m.sequence_participant_id
WHERE m.direction = 'outbound'
  AND m.sequence_participant_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM prospecting_message_log l
      WHERE l.participant_id = m.sequence_participant_id
        AND l.channel = 'email'
        AND l.node_id <=> m.node_id
        AND l.sent_at <=> COALESCE(m.sent_at, m.created_at)
  );

-- ---------------------------------------------------------------------
-- 1b) Mensagens de WHATSAPP enviadas (from_me=1) de leads que estão/estiveram
--     em alguma sequência. Vincula ao participante mais recente do contato.
-- ---------------------------------------------------------------------
INSERT INTO prospecting_message_log
    (contact_id, sequence_id, participant_id, node_id, channel, ab_variant, subject, body, len_chars, sent_at, created_at)
SELECT
    w.contact_id,
    sp.sequence_id,
    sp.id,
    NULL,
    'whatsapp',
    sp.ab_variant,
    NULL,
    w.message_text,
    CHAR_LENGTH(COALESCE(w.message_text,'')),
    w.timestamp,
    NOW()
FROM whatsapp_messages w
JOIN sequence_participants sp
     ON sp.id = (SELECT sp2.id FROM sequence_participants sp2 WHERE sp2.contact_id = w.contact_id ORDER BY sp2.id DESC LIMIT 1)
WHERE w.from_me = 1
  AND w.message_text IS NOT NULL AND w.message_text <> ''
  AND w.sender_name IN ('Prospecção','')  -- envios da sequência
  AND NOT EXISTS (
      SELECT 1 FROM prospecting_message_log l
      WHERE l.contact_id = w.contact_id
        AND l.channel = 'whatsapp'
        AND l.body <=> w.message_text
        AND l.sent_at <=> w.timestamp
  );

-- ---------------------------------------------------------------------
-- 2) Desfecho por participante (funil). Um registro por participante.
--    Estágio inferido do estado atual + histórico de respostas.
-- ---------------------------------------------------------------------
INSERT INTO prospecting_lead_outcome
    (contact_id, sequence_id, participant_id, ab_variant, sent_at, replied_at, interest, interest_at,
     scheduled_at, stage, created_at)
SELECT
    sp.contact_id,
    sp.sequence_id,
    sp.id,
    sp.ab_variant,
    sp.started_at,
    -- respondeu? (maior data entre: e-mail com replied_at e whatsapp recebido).
    -- A sentinela '1000-01-01' vira NULL quando não houve nenhuma resposta.
    NULLIF(GREATEST(
        COALESCE((SELECT MAX(em.replied_at) FROM email_messages em WHERE em.contact_id = sp.contact_id AND em.replied_at IS NOT NULL), '1000-01-01 00:00:00'),
        COALESCE((SELECT MAX(wm.timestamp) FROM whatsapp_messages wm WHERE wm.contact_id = sp.contact_id AND wm.from_me = 0), '1000-01-01 00:00:00')
    ), '1000-01-01 00:00:00') AS replied_at,
    -- interesse: positivo se agendou ou card em Qualificado; negativo se descadastrado/Perdido
    CASE
        WHEN am.id IS NOT NULL THEN 'positive'
        WHEN COALESCE(wc.unsubscribed,0) = 1 THEN 'negative'
        ELSE 'unknown'
    END AS interest,
    NULL,
    am.created_at AS scheduled_at,
    CASE
        WHEN am.id IS NOT NULL THEN 'scheduled'
        WHEN COALESCE(wc.unsubscribed,0) = 1 THEN 'lost'
        WHEN EXISTS (SELECT 1 FROM email_messages em WHERE em.contact_id = sp.contact_id AND em.replied_at IS NOT NULL)
          OR EXISTS (SELECT 1 FROM whatsapp_messages wm WHERE wm.contact_id = sp.contact_id AND wm.from_me = 0)
            THEN 'replied'
        ELSE 'sent'
    END AS stage,
    NOW()
FROM sequence_participants sp
JOIN email_sequences s ON s.id = sp.sequence_id
LEFT JOIN whatsapp_contacts wc ON wc.id = sp.contact_id
LEFT JOIN agenda_meetings am ON am.id = (
    SELECT a2.id FROM agenda_meetings a2 WHERE a2.contact_id = sp.contact_id ORDER BY a2.id DESC LIMIT 1
)
WHERE NOT EXISTS (
    SELECT 1 FROM prospecting_lead_outcome o WHERE o.participant_id = sp.id
);
