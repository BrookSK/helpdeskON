-- Migration 141: Adiciona o tipo 'interno' ao ENUM meeting_type em agenda_meetings.
-- Reuniões 'interno' cobrem eventos de uso interno da empresa (treinamentos, reuniões
-- de empresa, eventos externos em que a empresa participa como convidada, etc.).
-- Comportamento igual ao 'operacional': sem cliente CRM, usa participantes internos.
-- Idempotente: usa SET/IF para não falhar se já executada.

SET @col := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'agenda_meetings'
      AND COLUMN_NAME  = 'meeting_type'
);

SET @needs_update := IF(
    @col IS NOT NULL AND LOCATE("'interno'", @col) = 0,
    "ALTER TABLE agenda_meetings MODIFY COLUMN meeting_type ENUM('comercial','operacional','externo','interno') NOT NULL DEFAULT 'comercial'",
    "SELECT 'meeting_type already has interno or column not found' AS info"
);

PREPARE _stmt FROM @needs_update;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
