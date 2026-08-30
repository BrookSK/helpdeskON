-- =====================================================================
-- 090_agenda_booking_links.sql
-- ---------------------------------------------------------------------
-- Suporte ao bloco "Agendamento" das sequências: gera um link PÚBLICO
-- (sem login) para o lead escolher data/horário de uma reunião online.
-- Ao confirmar, cria a reunião na Agenda, gera o link do Google Meet e
-- notifica por e-mail e WhatsApp.
--
-- Cada token vincula o lead (contact_id) ao responsável (assigned_to) e,
-- opcionalmente, ao participante da sequência que originou o convite.
-- =====================================================================

CREATE TABLE IF NOT EXISTS agenda_booking_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL,
    contact_id INT NULL COMMENT 'Lead do CRM (whatsapp_contacts)',
    assigned_to INT NULL COMMENT 'Responsável que conduzirá a reunião',
    sequence_participant_id INT NULL COMMENT 'Origem: participante da sequência',
    title VARCHAR(255) NULL COMMENT 'Título sugerido da reunião',
    duration_min INT NOT NULL DEFAULT 45,
    status ENUM('pending','booked','expired','canceled') NOT NULL DEFAULT 'pending',
    meeting_id INT NULL COMMENT 'Reunião criada ao confirmar',
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token (token),
    KEY idx_contact (contact_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garante a coluna client_email em agenda_meetings (usada nas notificações).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='client_email');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN client_email VARCHAR(180) NULL AFTER client_phone','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Vincula a reunião ao link de agendamento (para rastreio/idempotência).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='booking_token');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN booking_token VARCHAR(64) NULL AFTER meet_link','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Marca quando o lembrete (X horas antes) foi enviado — evita reenvio.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='reminder_sent_at');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN reminder_sent_at DATETIME NULL AFTER booking_token','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
