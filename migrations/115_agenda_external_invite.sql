-- =====================================================================
-- 115_agenda_external_invite.sql
-- ---------------------------------------------------------------------
-- Demanda #210 — Convite externo + Registrar agendamento no Google Agenda.
--
-- 1) Adiciona o tipo de reunião "externo" (convite para pessoas que NÃO
--    fazem parte do sistema). Mantém intactos os fluxos "comercial" e
--    "operacional" existentes.
-- 2) Armazena os dados dos convidados externos (nome/e-mail/telefone) em
--    JSON, permitindo N convidados por reunião.
-- 3) Guarda a preferência "Registrar agendamento" e o link gerado para o
--    cliente adicionar o evento ao próprio Google Agenda.
-- =====================================================================

-- 1) Amplia o ENUM meeting_type para incluir "externo" (idempotente).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='meeting_type');
SET @s := IF(@c=1,
    "ALTER TABLE agenda_meetings MODIFY COLUMN meeting_type ENUM('comercial','operacional','externo') NOT NULL DEFAULT 'comercial'",
    "ALTER TABLE agenda_meetings ADD COLUMN meeting_type ENUM('comercial','operacional','externo') NOT NULL DEFAULT 'comercial' AFTER title");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Convidados externos (JSON: [{"name":"","email":"","phone":""}, ...]).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='external_guests');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN external_guests LONGTEXT NULL AFTER notes','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3) Preferência "Registrar agendamento" (registrar no Google Agenda).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='register_google');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN register_google TINYINT(1) NOT NULL DEFAULT 0 AFTER external_guests','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Link "Adicionar ao Google Agenda" enviado no convite ao cliente.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_meetings' AND COLUMN_NAME='google_calendar_link');
SET @s := IF(@c=0,'ALTER TABLE agenda_meetings ADD COLUMN google_calendar_link VARCHAR(1000) NULL AFTER register_google','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
