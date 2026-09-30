-- =====================================================================
-- 140_backfill_unsub_duplicados.sql
-- ---------------------------------------------------------------------
-- Propaga o bloqueio de reenvio (unsubscribed=1) entre CONTATOS DUPLICADOS
-- (a mesma pessoa em fichas diferentes de whatsapp_contacts).
--
-- Contexto: um lead marcado como "Sem Interesse"/"Sem Resposta" (ou
-- descadastrado) passa a ter unsubscribed=1. Mas se a MESMA pessoa existe em
-- outra ficha (duplicada), essa outra ficha continua com unsubscribed=0 e
-- poderia receber mensagens de novas campanhas. Este backfill fecha o buraco
-- para os duplicados JÁ existentes.
--
-- Chaves fortes de identidade (as mesmas do LeadResolver e de CrmRules::isSameLead):
--   - e-mail (lead_email) igual
--   - LinkedIn (linkedin_url) igual
-- Telefone é DELIBERADAMENTE excluído (evita falso positivo por "últimos 8 dígitos").
--
-- Idempotente: só afeta quem está unsubscribed=0 e tem um irmão unsubscribed=1.
-- Ignora grupos. Seguro para rodar mais de uma vez.
-- =====================================================================

-- 1) Propaga por E-MAIL: se existe um irmão (mesmo lead_email, não-grupo) já
--    bloqueado, bloqueia este contato também.
UPDATE whatsapp_contacts wc
SET wc.unsubscribed = 1
WHERE COALESCE(wc.unsubscribed, 0) = 0
  AND COALESCE(wc.is_group, 0) = 0
  AND wc.lead_email IS NOT NULL AND TRIM(wc.lead_email) <> ''
  AND EXISTS (
      SELECT 1 FROM (SELECT * FROM whatsapp_contacts) sib
      WHERE sib.id <> wc.id
        AND COALESCE(sib.is_group, 0) = 0
        AND COALESCE(sib.unsubscribed, 0) = 1
        AND LOWER(TRIM(sib.lead_email)) = LOWER(TRIM(wc.lead_email))
  );

-- 2) Propaga por LINKEDIN_URL: idem, quando a coluna existir (migration 080).
--    Executado condicionalmente para não quebrar bancos sem a coluna.
SET @has_linkedin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'linkedin_url'
);

SET @sql := IF(@has_linkedin > 0,
    'UPDATE whatsapp_contacts wc
     SET wc.unsubscribed = 1
     WHERE COALESCE(wc.unsubscribed, 0) = 0
       AND COALESCE(wc.is_group, 0) = 0
       AND wc.linkedin_url IS NOT NULL AND TRIM(wc.linkedin_url) <> ''
       AND EXISTS (
           SELECT 1 FROM (SELECT * FROM whatsapp_contacts) sib
           WHERE sib.id <> wc.id
             AND COALESCE(sib.is_group, 0) = 0
             AND COALESCE(sib.unsubscribed, 0) = 1
             AND LOWER(TRIM(sib.linkedin_url)) = LOWER(TRIM(wc.linkedin_url))
       )',
    'SELECT 1');
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;
