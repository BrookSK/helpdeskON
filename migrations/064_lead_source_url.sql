-- Migration 064: URL de origem do lead (ex.: link do projeto no 99Freelas).
-- Preenchida ao converter uma oportunidade em lead; exibida em Meus Leads.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'whatsapp_contacts'
      AND COLUMN_NAME = 'lead_source_url'
);
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE whatsapp_contacts ADD COLUMN lead_source_url VARCHAR(500) NULL AFTER lead_email',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
