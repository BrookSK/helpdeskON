-- Migration 003: Adicionar campo de transcrição original no ticket
-- Execute manualmente no MySQL

USE helpdesk_on;

ALTER TABLE tickets ADD COLUMN transcription TEXT NULL AFTER description;
