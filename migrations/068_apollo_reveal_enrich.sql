-- Migration 068: separa "Liberar dados" (reveal e-mail/telefone) de "Enriquecer"
-- (perfil completo pessoa + organização) na Captação de Leads via Apollo.
--
-- Telefone é revelado de forma ASSÍNCRONA pela Apollo via webhook. Guardamos o
-- request_id e o status para correlacionar o retorno e atualizar o lead.

-- phone_status: NULL = não solicitado, 'pending' = aguardando webhook, 'received' = recebido
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'phone_status');
SET @s := IF(@c = 0, 'ALTER TABLE apollo_leads ADD COLUMN phone_status VARCHAR(20) NULL AFTER phone', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- request_id retornado pela Apollo ao solicitar a revelação do telefone
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'phone_request_id');
SET @s := IF(@c = 0, 'ALTER TABLE apollo_leads ADD COLUMN phone_request_id VARCHAR(120) NULL AFTER phone_status', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- marca se o perfil completo (pessoa + organização) já foi enriquecido
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND COLUMN_NAME = 'is_full_enriched');
SET @s := IF(@c = 0, 'ALTER TABLE apollo_leads ADD COLUMN is_full_enriched TINYINT(1) NOT NULL DEFAULT 0 AFTER is_enriched', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- índice para localizar o lead pelo request_id no webhook
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'apollo_leads' AND INDEX_NAME = 'idx_phone_request');
SET @s := IF(@c = 0, 'ALTER TABLE apollo_leads ADD INDEX idx_phone_request (phone_request_id)', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- token de segurança do webhook do Apollo (valida quem chama o endpoint público)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('apollo_webhook_token', '');
