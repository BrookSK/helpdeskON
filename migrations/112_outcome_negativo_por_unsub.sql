-- =====================================================================
-- 112_outcome_negativo_por_unsub.sql
-- ---------------------------------------------------------------------
-- Corrige a classificação de desfecho: marca como NEGATIVO (interest='negative',
-- stage='lost') os participantes cujo LEAD foi descadastrado (unsubscribed=1) ou
-- cujo card está na coluna "Perdido" — casos em que o lead disse que NÃO quer.
--
-- Antes, a Performance mostrava 0 negativas mesmo com recusas, porque o desfecho
-- negativo ficava só no participante de triagem (ou não era gravado no backfill).
-- Este SQL consolida o negativo para TODOS os participantes desses leads.
--
-- Idempotente. Requer as tabelas da 106.
-- =====================================================================

-- 1) Negativo por lead descadastrado (opt-out explícito)
UPDATE prospecting_lead_outcome o
JOIN whatsapp_contacts wc ON wc.id = o.contact_id
SET o.interest = 'negative',
    o.interest_at = COALESCE(o.interest_at, NOW()),
    o.stage = CASE WHEN o.scheduled_at IS NOT NULL THEN o.stage ELSE 'lost' END,
    o.lost_reason = COALESCE(o.lost_reason, 'sem interesse (descadastrado)')
WHERE COALESCE(wc.unsubscribed,0) = 1
  AND o.interest <> 'negative';

-- 2) Negativo por card na coluna "Perdido"
UPDATE prospecting_lead_outcome o
JOIN crm_cards cc ON cc.contact_id = o.contact_id
JOIN crm_columns col ON col.id = cc.column_id
SET o.interest = 'negative',
    o.interest_at = COALESCE(o.interest_at, NOW()),
    o.stage = CASE WHEN o.scheduled_at IS NOT NULL THEN o.stage ELSE 'lost' END,
    o.lost_reason = COALESCE(o.lost_reason, 'sem interesse (Perdido)')
WHERE col.name = 'Perdido'
  AND o.interest <> 'negative';
