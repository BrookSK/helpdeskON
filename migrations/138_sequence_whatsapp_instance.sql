-- =====================================================================
-- 138_sequence_whatsapp_instance.sql
-- ---------------------------------------------------------------------
-- "Conta de envio" de WhatsApp por sequência.
--
-- Assim como cada sequência pode declarar a conta de e-mail usada no envio
-- (email_account_id -> email_accounts), agora ela também pode declarar a
-- INSTÂNCIA de WhatsApp usada no envio (whatsapp_instance_id -> whatsapp_instances).
--
-- Semântica (espelha email_account_id):
--   * NULL  -> usa a instância PADRÃO (whatsapp_instances.is_default = 1),
--              mantendo o comportamento anterior.
--   * valor -> usa a instância escolhida na configuração da sequência.
--
-- ON DELETE SET NULL: se a instância for removida, a sequência volta ao
-- comportamento padrão em vez de quebrar.
--
-- Idempotente (IF NOT EXISTS) para reexecução segura.
-- =====================================================================

ALTER TABLE email_sequences
    ADD COLUMN IF NOT EXISTS whatsapp_instance_id INT DEFAULT NULL
        COMMENT 'instancia de WhatsApp usada no envio (padrao/is_default se null)'
        AFTER email_account_id;

ALTER TABLE email_sequences
    ADD INDEX IF NOT EXISTS idx_whatsapp_instance (whatsapp_instance_id);

-- FK idempotente: só cria se ainda não existir (ADD CONSTRAINT não aceita IF NOT EXISTS).
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'email_sequences'
      AND CONSTRAINT_NAME = 'fk_seq_whatsapp_instance'
);
SET @fk_sql := IF(@fk_exists = 0,
    'ALTER TABLE email_sequences ADD CONSTRAINT fk_seq_whatsapp_instance FOREIGN KEY (whatsapp_instance_id) REFERENCES whatsapp_instances(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
