-- =====================================================================
-- 172_whatsapp_group_mentions.sql
-- ---------------------------------------------------------------------
-- Menções (@) nos grupos de WhatsApp, no estilo do app oficial:
--   * o atendente marca participantes com @ ao escrever no grupo;
--   * quando alguém do grupo marca o NOSSO número (a instância conectada),
--     a conversa ganha o indicador "@" na lista e sai um aviso (toast).
--
-- Colunas:
--   whatsapp_instances.owner_lid   ID anônimo (@lid) do nosso número. Os
--       grupos novos do WhatsApp identificam os membros por LID, então a
--       menção ao nosso número chega como "<lid>@lid", não como telefone.
--       É descoberto sozinho (lista de participantes do grupo) e guardado.
--       (owner_phone já existe desde a 013 e passa a ser preenchido.)
--   whatsapp_contacts.mention_count  menções ao nosso número ainda não
--       vistas naquela conversa. Zera ao abrir a conversa (igual unread_count).
--   whatsapp_messages.mentions_me    1 = a mensagem marcou o nosso número.
--
-- SEM backfill: vale para as mensagens que chegarem a partir de agora.
-- Idempotente (checa information_schema) para reexecução segura.
-- Aplique no banco correto pela CONEXÃO (não força USE).
-- =====================================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_instances'
      AND COLUMN_NAME = 'owner_lid');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_instances ADD COLUMN owner_lid VARCHAR(60) DEFAULT NULL COMMENT 'LID do numero conectado (ex.: 1560...@lid)' AFTER owner_phone",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'mention_count');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_contacts ADD COLUMN mention_count INT NOT NULL DEFAULT 0 COMMENT 'mencoes ao nosso numero ainda nao vistas' AFTER unread_count",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_messages'
      AND COLUMN_NAME = 'mentions_me');
SET @s := IF(@c = 0,
    "ALTER TABLE whatsapp_messages ADD COLUMN mentions_me TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = mensagem marcou o nosso numero' AFTER participant_jid",
    'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
