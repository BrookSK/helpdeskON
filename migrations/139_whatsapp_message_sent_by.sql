-- =====================================================================
-- 139_whatsapp_message_sent_by.sql
-- ---------------------------------------------------------------------
-- "Dono do envio" das mensagens de WhatsApp (espelha email_messages.sent_by).
--
-- Contexto: no e-mail, cada mensagem carrega o usuário que enviou
-- (email_messages.sent_by / email_prospections.user_id). É por esse dono que
-- o usuário enxerga o que mandou. No WhatsApp, até aqui, a mensagem só tinha
-- sender_name (texto) e a visibilidade do chat era apenas por
-- whatsapp_contacts.assigned_to — então quem disparava por uma instância (ex.:
-- via sequência/prospecção) não necessariamente via a conversa.
--
-- Esta coluna grava o usuário (users.id) DONO do envio das mensagens de saída:
--   * envio manual pelo painel  -> usuário logado;
--   * envio pela sequência       -> dono da instância que enviou;
--   * mensagens recebidas (from_me = 0) ou capturadas externamente -> NULL.
--
-- A visibilidade do chat passa a considerar assigned_to OU sent_by, permitindo
-- que MAIS DE UMA pessoa opere sobre a mesma instância e cada uma veja o que
-- enviou (desacopla "quem envia" de "qual instância").
--
-- SEM backfill: só vale daqui para frente (mensagens antigas ficam sent_by NULL).
--
-- Idempotente (checa information_schema) para reexecução segura.
--
-- Aplique no banco correto pela CONEXÃO (não força USE), para não afetar o
-- database errado em ambientes de teste/beta/produção.
-- =====================================================================

-- Coluna sent_by (após sent_via, mantendo a ordem lógica das colunas de envio)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_messages'
      AND COLUMN_NAME = 'sent_by');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_messages ADD COLUMN sent_by INT DEFAULT NULL COMMENT 'usuario (users.id) dono do envio; NULL = recebida/externa' AFTER sent_via",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Índice para o filtro de visibilidade (EXISTS por sent_by)
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_messages'
      AND INDEX_NAME = 'idx_wm_sent_by');
SET @s := IF(@i = 0,
    'ALTER TABLE whatsapp_messages ADD INDEX idx_wm_sent_by (sent_by)',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- FK idempotente: só cria se ainda não existir (ADD CONSTRAINT não aceita IF NOT EXISTS).
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_messages'
      AND CONSTRAINT_NAME = 'fk_wm_sent_by');
SET @s := IF(@fk = 0,
    'ALTER TABLE whatsapp_messages ADD CONSTRAINT fk_wm_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
