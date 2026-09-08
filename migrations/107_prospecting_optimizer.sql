-- =====================================================================
-- 107_prospecting_optimizer.sql
-- ---------------------------------------------------------------------
-- CAMADA 2 (IA sugere) da prospecção autorregulada.
--
--   * prospecting_copy_suggestion : variantes de copy PROPOSTAS pela IA a partir
--     do que performou melhor + objeções reais. Entram como RASCUNHO (pending) e
--     só vão ao ar após aprovação humana (approved/rejected).
--
-- Config (settings):
--   * optimizer_min_replies : nº de respostas recebidas que dispara uma nova
--     análise. Padrão = 6 (analisa a cada 6 respostas, não a cada 30/50).
--   * optimizer_enabled     : liga/desliga o otimizador (padrão 1).
--
-- Tabela nova + settings. Idempotente.
-- =====================================================================

CREATE TABLE IF NOT EXISTS prospecting_copy_suggestion (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sequence_id INT DEFAULT NULL,
    node_id VARCHAR(120) DEFAULT NULL,           -- bloco alvo (opcional)
    channel ENUM('email','whatsapp') NOT NULL DEFAULT 'email',
    based_on_variant CHAR(1) DEFAULT NULL,       -- variante vencedora que serviu de base
    suggested_subject VARCHAR(255) DEFAULT NULL,
    suggested_body MEDIUMTEXT DEFAULT NULL,      -- a copy proposta
    rationale MEDIUMTEXT DEFAULT NULL,           -- POR QUE a IA propôs isso
    -- Fotografia dos dados que embasaram a sugestão (auditoria)
    sample_size INT DEFAULT 0,                   -- respostas analisadas nesta rodada
    winner_meeting_rate DECIMAL(5,2) DEFAULT NULL,
    top_objections VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_sequence (sequence_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Config: análise a cada 6 respostas recebidas.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('optimizer_min_replies', '6');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('optimizer_enabled', '1');
-- Marcador de quantas respostas já haviam sido contabilizadas na última análise
-- (por sequência). Serve para disparar a cada +6 respostas novas. Guardado como
-- setting dinâmico com a chave optimizer_last_replies_<sequence_id>.
