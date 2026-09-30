-- =====================================================================
-- 140_email_reply_tracking.sql
-- ---------------------------------------------------------------------
-- Rastreabilidade MINUCIOSA de resposta por e-mail na prospecção.
--
-- Problema: a deteccao de resposta casava apenas por REMETENTE + data, entao
-- qualquer e-mail vindo do endereco do lead (auto-resposta, "obrigado",
-- newsletter, assunto diferente) era tratado como resposta e disparava a
-- triagem por IA indevidamente. A caixa recebe muitos e-mails o tempo todo.
--
-- Solucao: carimbar cada envio com um Message-ID unico (embutindo o track_token)
-- e casar a resposta pelos cabecalhos padrao de e-mail (In-Reply-To / References
-- / Reply-To com +tag) contra o envio original. So conta como resposta quando ha
-- casamento real. Persistimos tambem a resposta como linha inbound e guardamos um
-- cursor de UID por conta para nao varrer/reprocessar a caixa inteira.
--
-- Colunas ja existentes em email_messages (migration 065), reaproveitadas:
--   message_id, in_reply_to, thread_key, track_token, replied_at,
--   direction ENUM('outbound','inbound'), status ENUM(...,'received').
--
-- Idempotente (IF NOT EXISTS) para reexecucao segura.
-- =====================================================================

-- Header References do e-mail (cadeia de Message-IDs da thread).
ALTER TABLE email_messages
    ADD COLUMN IF NOT EXISTS references_header TEXT DEFAULT NULL
        COMMENT 'header References da mensagem (cadeia de Message-IDs da thread)'
        AFTER in_reply_to;

-- Trecho de texto da resposta recebida (alimenta a triagem por IA sem reler IMAP).
ALTER TABLE email_messages
    ADD COLUMN IF NOT EXISTS reply_snippet TEXT DEFAULT NULL
        COMMENT 'trecho de texto da resposta do lead (para a triagem por IA)'
        AFTER references_header;

-- UID IMAP da mensagem recebida (dedupe: nao processar a mesma resposta 2x).
ALTER TABLE email_messages
    ADD COLUMN IF NOT EXISTS imap_uid INT DEFAULT NULL
        COMMENT 'UID IMAP da mensagem inbound (dedupe de resposta)'
        AFTER reply_snippet;

-- Data em que a mensagem inbound foi recebida (linha direction=inbound).
ALTER TABLE email_messages
    ADD COLUMN IF NOT EXISTS received_at DATETIME DEFAULT NULL
        COMMENT 'quando a resposta inbound foi recebida (linha direction=inbound)'
        AFTER replied_at;

-- Indice para casar a resposta: In-Reply-To da inbound -> message_id da outbound.
ALTER TABLE email_messages
    ADD INDEX IF NOT EXISTS idx_in_reply_to (in_reply_to);

-- Dedupe por conta + UID (evita gravar a mesma resposta duas vezes).
ALTER TABLE email_messages
    ADD INDEX IF NOT EXISTS idx_account_uid (email_account_id, imap_uid);

-- Cursor de leitura IMAP por conta: maior UID ja processado. Evita varrer a
-- caixa inteira a cada execucao e reprocessar mensagens antigas.
ALTER TABLE email_accounts
    ADD COLUMN IF NOT EXISTS last_imap_uid INT NOT NULL DEFAULT 0
        COMMENT 'maior UID IMAP ja processado na deteccao de respostas'
        AFTER imap_encryption;
