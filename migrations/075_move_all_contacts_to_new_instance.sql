-- =============================================================================
-- Move TODOS os contatos e mensagens do WhatsApp para a nova instância (id 4,
-- "helpdesk_xiomi"), que é a única que envia com sucesso.
--
-- As instâncias antigas (ex.: 2 = helpdesk-lrv, 3 = atendimento_comercial_on)
-- estão com o socket travado (Connection Closed). Ao apontar todos os contatos
-- para a instância 4, o chat volta a enviar.
--
-- Trata a UNIQUE KEY (instance_id, remote_jid): se um mesmo número já existir
-- na instância 4, funde o histórico no contato existente e remove o duplicado.
--
-- >>> AJUSTE @NOVA_INSTANCIA se o id da instância nova for diferente de 4 <<<
-- Seguro para rodar mais de uma vez.
-- =============================================================================

SET @NOVA_INSTANCIA := 4;

-- ---------------------------------------------------------------------------
-- 1) Resolver DUPLICATAS: contatos cujo remote_jid já existe na instância nova.
--    Reaponta as mensagens do contato antigo para o contato já existente na
--    instância nova e remove o contato antigo duplicado.
-- ---------------------------------------------------------------------------

-- 1a) Move as mensagens dos contatos duplicados para o contato "vencedor" (o que
--     já está na instância nova).
UPDATE whatsapp_messages m
JOIN whatsapp_contacts old_c ON m.contact_id = old_c.id
JOIN whatsapp_contacts new_c
     ON new_c.remote_jid = old_c.remote_jid
    AND new_c.instance_id = @NOVA_INSTANCIA
SET m.contact_id = new_c.id,
    m.instance_id = @NOVA_INSTANCIA
WHERE old_c.instance_id <> @NOVA_INSTANCIA;

-- 1b) Remove os contatos antigos que ficaram duplicados (já existe o mesmo
--     remote_jid na instância nova).
DELETE old_c
FROM whatsapp_contacts old_c
JOIN whatsapp_contacts new_c
     ON new_c.remote_jid = old_c.remote_jid
    AND new_c.instance_id = @NOVA_INSTANCIA
WHERE old_c.instance_id <> @NOVA_INSTANCIA;

-- ---------------------------------------------------------------------------
-- 2) Mover TODOS os contatos restantes para a instância nova.
--    (inclui contatos órfãos com instance_id NULL, se a migration 074 já rodou)
-- ---------------------------------------------------------------------------
UPDATE whatsapp_contacts
SET instance_id = @NOVA_INSTANCIA
WHERE instance_id <> @NOVA_INSTANCIA OR instance_id IS NULL;

-- ---------------------------------------------------------------------------
-- 3) Mover TODAS as mensagens restantes para a instância nova.
-- ---------------------------------------------------------------------------
UPDATE whatsapp_messages
SET instance_id = @NOVA_INSTANCIA
WHERE instance_id <> @NOVA_INSTANCIA OR instance_id IS NULL;

-- ---------------------------------------------------------------------------
-- 4) (Opcional) Conferência: quantos contatos e mensagens ficaram na nova.
-- ---------------------------------------------------------------------------
-- SELECT
--   (SELECT COUNT(*) FROM whatsapp_contacts WHERE instance_id = @NOVA_INSTANCIA) AS contatos_na_nova,
--   (SELECT COUNT(*) FROM whatsapp_messages  WHERE instance_id = @NOVA_INSTANCIA) AS mensagens_na_nova;
