-- Migration 161: Webhooks de ENTRADA do WhatsApp.
--
-- Permite que sistemas externos (que NÃO têm conexão direta com a Evolution API)
-- disparem envios de WhatsApp via um endpoint HTTP do helpdeskON. Cada EMPRESA
-- pode ter N webhooks; cada webhook tem uma URL pública única (token) que recebe
-- POST com um JSON contendo telefone(s), nome, e-mail e mensagem. O sistema
-- extrai os campos pelo MAPEAMENTO configurado, monta a mensagem a partir de um
-- TEMPLATE e envia para o(s) telefone(s). Todas as requisições recebidas ficam
-- registradas (log/fila) para visualização ao vivo e reprocessamento.
--
-- Idempotente: cria as tabelas só se não existirem (CREATE TABLE IF NOT EXISTS).

-- ─────────────────────────────────────────────────────────────────────────────
-- Configuração de cada webhook (por empresa)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_webhooks (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    company_id       INT NOT NULL COMMENT 'Empresa dona do webhook',
    instance_id      INT NULL COMMENT 'Instância WhatsApp usada no envio (NULL = instância padrão)',
    name             VARCHAR(150) NOT NULL COMMENT 'Nome amigável (ex.: Webhook Loja X)',
    token            VARCHAR(64) NOT NULL COMMENT 'Token único usado na URL pública',
    -- Mapeamento: caminho do campo no JSON recebido (suporta dot-notation, ex.: data.phone)
    phone_field      VARCHAR(190) NOT NULL DEFAULT 'phone'   COMMENT 'Campo do telefone no payload',
    name_field       VARCHAR(190) NULL                       COMMENT 'Campo do nome no payload',
    email_field      VARCHAR(190) NULL                       COMMENT 'Campo do e-mail no payload',
    message_field    VARCHAR(190) NULL                       COMMENT 'Campo da mensagem no payload (quando não usa template)',
    -- Template da mensagem. Variáveis: {{nome}} {{telefone}} {{email}} {{mensagem}}
    message_template TEXT NULL COMMENT 'Template da mensagem (se vazio, usa o campo message_field)',
    active           TINYINT(1) NOT NULL DEFAULT 1,
    created_by       INT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_webhook_token (token),
    KEY idx_webhook_company (company_id),
    CONSTRAINT fk_wh_company  FOREIGN KEY (company_id)  REFERENCES companies(id)          ON DELETE CASCADE,
    CONSTRAINT fk_wh_instance FOREIGN KEY (instance_id) REFERENCES whatsapp_instances(id) ON DELETE SET NULL,
    CONSTRAINT fk_wh_user     FOREIGN KEY (created_by)   REFERENCES users(id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Requisições recebidas em cada webhook (log + fila de processamento/envio)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_webhook_requests (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id     INT NOT NULL,
    source_ip      VARCHAR(45) NULL COMMENT 'IP de origem da requisição',
    raw_payload    MEDIUMTEXT NULL COMMENT 'Corpo cru recebido (JSON)',
    -- Dados extraídos do payload pelo mapeamento (para exibição/reprocessamento)
    parsed_phones  TEXT NULL COMMENT 'Telefones extraídos (JSON array)',
    parsed_name    VARCHAR(255) NULL,
    parsed_email   VARCHAR(255) NULL,
    parsed_message TEXT NULL COMMENT 'Mensagem final montada (template aplicado)',
    -- received: recém-chegada; queued: na fila de envio; processing: em envio;
    -- sent: enviada a todos; failed: falhou; skipped: sem telefone/mensagem válidos.
    status         ENUM('received','queued','processing','sent','failed','skipped') NOT NULL DEFAULT 'received',
    attempts       INT NOT NULL DEFAULT 0,
    sent_count     INT NOT NULL DEFAULT 0 COMMENT 'Quantos números receberam com sucesso',
    error_message  VARCHAR(500) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at   DATETIME NULL,
    KEY idx_whr_webhook (webhook_id, id),
    KEY idx_whr_status (status, created_at),
    CONSTRAINT fk_whr_webhook FOREIGN KEY (webhook_id) REFERENCES whatsapp_webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
