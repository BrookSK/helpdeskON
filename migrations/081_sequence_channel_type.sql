-- =====================================================================
-- 081_sequence_channel_type.sql
-- ---------------------------------------------------------------------
-- Segmentação por CANAL na sequência de prospecção.
--
-- Cada sequência passa a declarar o canal que utiliza:
--   * 'email'    → usa e-mail. Só aceita leads COM e-mail.
--   * 'whatsapp' → usa WhatsApp. Só aceita leads COM telefone.
--   * 'mixed'    → usa e-mail e/ou WhatsApp. Aceita leads com e-mail OU telefone
--                  (os blocos cujo canal o lead não possui são pulados).
--
-- Isso permite que leads da Apollo sem e-mail (só telefone/WhatsApp) ainda
-- entrem em campanhas de WhatsApp ou mistas, e que leads só com e-mail entrem
-- em campanhas de e-mail ou mistas.
--
-- Idempotente (IF NOT EXISTS) para reexecução segura.
-- =====================================================================

ALTER TABLE email_sequences
    ADD COLUMN IF NOT EXISTS channel_type ENUM('email','whatsapp','mixed') NOT NULL DEFAULT 'email'
        COMMENT 'canal da sequencia: email (exige e-mail), whatsapp (exige telefone) ou mixed (e-mail e/ou telefone)'
        AFTER description;

ALTER TABLE email_sequences ADD INDEX IF NOT EXISTS idx_channel_type (channel_type);
