-- Migration 034: Detalhes dos canais do Buffer
-- Execute manualmente no MySQL
-- Observação: a API do Buffer não expõe contagem de seguidores; o campo followers
-- fica disponível para preenchimento manual/futuro, mas não é populado pela sincronização.

USE helpdesk_on;

ALTER TABLE buffer_channels
    ADD COLUMN username VARCHAR(150) NULL AFTER name,
    ADD COLUMN followers INT NULL AFTER service,
    ADD COLUMN external_link VARCHAR(500) NULL AFTER avatar,
    ADD COLUMN channel_type VARCHAR(50) NULL AFTER external_link,
    ADD COLUMN is_disconnected TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_type,
    ADD COLUMN is_queue_paused TINYINT(1) NOT NULL DEFAULT 0 AFTER is_disconnected;
