-- =============================================================================
-- Desacopla os contatos e mensagens do WhatsApp da instância.
--
-- ANTES: whatsapp_contacts.instance_id e whatsapp_messages.instance_id eram
-- NOT NULL com FK ON DELETE CASCADE. Apagar uma instância apagava contatos e
-- conversas junto.
--
-- DEPOIS: o contato/mensagem pertence à PLATAFORMA. A instância apenas "consome"
-- (envia/recebe por) o contato. Ao apagar a instância, o vínculo vira NULL e as
-- conversas e números são preservados, podendo ser reatribuídos a outra instância.
--
-- Seguro para rodar mais de uma vez (verifica antes de alterar).
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1) whatsapp_contacts.instance_id -> nullable + FK ON DELETE SET NULL
-- ---------------------------------------------------------------------------

-- Remove a FK antiga (o nome pode variar; buscamos dinamicamente).
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'instance_id' AND REFERENCED_TABLE_NAME = 'whatsapp_instances'
    LIMIT 1);
SET @s := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE whatsapp_contacts DROP FOREIGN KEY ', @fk), 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Torna a coluna nullable.
ALTER TABLE whatsapp_contacts MODIFY COLUMN instance_id INT NULL;

-- Recria a FK com SET NULL.
ALTER TABLE whatsapp_contacts
    ADD CONSTRAINT fk_wc_instance FOREIGN KEY (instance_id)
    REFERENCES whatsapp_instances(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 2) whatsapp_messages.instance_id -> nullable + FK ON DELETE SET NULL
-- ---------------------------------------------------------------------------

SET @fk2 := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_messages'
      AND COLUMN_NAME = 'instance_id' AND REFERENCED_TABLE_NAME = 'whatsapp_instances'
    LIMIT 1);
SET @s2 := IF(@fk2 IS NOT NULL, CONCAT('ALTER TABLE whatsapp_messages DROP FOREIGN KEY ', @fk2), 'SELECT 1');
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;

ALTER TABLE whatsapp_messages MODIFY COLUMN instance_id INT NULL;

ALTER TABLE whatsapp_messages
    ADD CONSTRAINT fk_wm_instance FOREIGN KEY (instance_id)
    REFERENCES whatsapp_instances(id) ON DELETE SET NULL;
