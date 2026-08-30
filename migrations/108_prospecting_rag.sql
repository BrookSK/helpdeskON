-- =====================================================================
-- 108_prospecting_rag.sql
-- ---------------------------------------------------------------------
-- CAMADA 3 (RAG / memória) da prospecção autorregulada.
--
--   * prospecting_rag_episode : "fichas" de episódios de prospecção — mensagem
--     enviada, resposta do lead, perfil e desfecho — com um embedding (vetor)
--     do texto, para busca por similaridade.
--
-- Na hora de gerar copy (Camada 2) ou responder um lead (triagem), recuperamos
-- os episódios mais parecidos que DERAM CERTO e os injetamos no prompt.
--
-- O embedding é gerado pela API da OpenAI (text-embedding-3-small) e guardado
-- como JSON (array de floats). A similaridade é calculada em PHP (cosseno).
--
-- Config:
--   * rag_enabled = 1
--   * rag_embed_model = text-embedding-3-small
--
-- Tabela nova + settings. Idempotente.
-- =====================================================================

CREATE TABLE IF NOT EXISTS prospecting_rag_episode (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT DEFAULT NULL,
    sequence_id INT DEFAULT NULL,
    participant_id INT DEFAULT NULL,
    channel ENUM('email','whatsapp') DEFAULT NULL,
    -- Contexto do episódio (o que compõe a "ficha")
    lead_title VARCHAR(160) DEFAULT NULL,
    lead_industry VARCHAR(160) DEFAULT NULL,
    lead_company_size VARCHAR(60) DEFAULT NULL,
    message_text MEDIUMTEXT DEFAULT NULL,        -- mensagem que provocou a reação
    reply_text MEDIUMTEXT DEFAULT NULL,          -- resposta do lead
    outcome VARCHAR(30) DEFAULT NULL,            -- scheduled | interested | lost
    success TINYINT(1) DEFAULT 0,                -- 1 = converteu (agendou/interessou)
    summary MEDIUMTEXT DEFAULT NULL,             -- texto usado para o embedding
    embedding MEDIUMTEXT DEFAULT NULL,           -- vetor JSON (array de floats)
    embed_model VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant (participant_id),
    KEY idx_success (success),
    KEY idx_sequence (sequence_id),
    KEY idx_outcome (outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('rag_enabled', '1');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('rag_embed_model', 'text-embedding-3-small');
