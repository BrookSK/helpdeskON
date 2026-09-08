-- =====================================================================
-- 106_prospecting_analytics.sql
-- ---------------------------------------------------------------------
-- CAMADA 1 (medição) da prospecção autorregulada. Registra:
--   * prospecting_message_log : cada mensagem REAL enviada ao lead (texto +
--     atributos do texto + variante A/B + canal + bloco), o "estímulo".
--   * prospecting_lead_outcome : o desfecho de cada lead no funil (respondeu?
--     interessado? agendou? objeção?), o "placar".
--
-- Com os dois ligados (por contato/sequência/variante) responde-se:
--   "qual mensagem gerou quais respostas positivas e por que converteu melhor".
--
-- Tabelas novas + idempotente. Não altera nada existente.
-- =====================================================================

-- --------- Log de cada mensagem enviada (o estímulo) ---------
CREATE TABLE IF NOT EXISTS prospecting_message_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    sequence_id INT DEFAULT NULL,
    participant_id INT DEFAULT NULL,
    node_id VARCHAR(120) DEFAULT NULL,           -- bloco do grafo (ou lista de blocos agrupados)
    channel ENUM('email','whatsapp') NOT NULL,
    ab_variant CHAR(1) DEFAULT NULL,             -- A / B / NULL
    subject VARCHAR(255) DEFAULT NULL,
    body MEDIUMTEXT DEFAULT NULL,                -- texto EXATO enviado
    -- Atributos do texto (o "DNA" — permite entender POR QUE converteu)
    len_chars INT DEFAULT 0,
    has_number TINYINT(1) DEFAULT 0,             -- cita número/estatística
    has_question TINYINT(1) DEFAULT 0,           -- faz pergunta
    has_link TINYINT(1) DEFAULT 0,               -- contém link
    has_social_proof TINYINT(1) DEFAULT 0,       -- prova social / caso de cliente
    cta_type VARCHAR(30) DEFAULT NULL,           -- meeting | question | material | none
    tone VARCHAR(20) DEFAULT NULL,               -- formal | informal
    attributes_json JSON DEFAULT NULL,           -- espaço p/ atributos extras
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact (contact_id),
    KEY idx_sequence (sequence_id),
    KEY idx_variant (sequence_id, node_id, ab_variant),
    KEY idx_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------- Desfecho por lead (o placar / funil) ---------
CREATE TABLE IF NOT EXISTS prospecting_lead_outcome (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    sequence_id INT DEFAULT NULL,
    participant_id INT DEFAULT NULL,
    ab_variant CHAR(1) DEFAULT NULL,             -- variante que o lead recebeu (para atribuição)
    -- Contexto do lead (permite comparar mensagens DENTRO do mesmo perfil)
    lead_title VARCHAR(160) DEFAULT NULL,        -- cargo
    lead_industry VARCHAR(160) DEFAULT NULL,     -- setor
    lead_company_size VARCHAR(60) DEFAULT NULL,  -- porte
    lead_region VARCHAR(120) DEFAULT NULL,
    first_channel ENUM('email','whatsapp') DEFAULT NULL,
    -- Estágios do funil (marcos, com data)
    sent_at DATETIME DEFAULT NULL,
    opened_at DATETIME DEFAULT NULL,
    replied_at DATETIME DEFAULT NULL,
    reply_channel ENUM('email','whatsapp') DEFAULT NULL,
    reply_text MEDIUMTEXT DEFAULT NULL,          -- primeira resposta relevante do lead
    interest ENUM('unknown','positive','negative') NOT NULL DEFAULT 'unknown',
    interest_at DATETIME DEFAULT NULL,
    objection VARCHAR(255) DEFAULT NULL,         -- motivo da recusa (preço, timing, etc.)
    scheduled_at DATETIME DEFAULT NULL,          -- agendou reunião
    attended_at DATETIME DEFAULT NULL,           -- compareceu
    won_at DATETIME DEFAULT NULL,                -- fechou
    stage VARCHAR(30) NOT NULL DEFAULT 'sent',   -- sent|opened|replied|interested|scheduled|attended|won|lost
    lost_reason VARCHAR(120) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant (participant_id),
    KEY idx_contact (contact_id),
    KEY idx_sequence (sequence_id),
    KEY idx_stage (stage),
    KEY idx_variant (sequence_id, ab_variant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
