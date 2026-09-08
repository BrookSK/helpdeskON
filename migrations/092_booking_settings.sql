-- =====================================================================
-- 092_booking_settings.sql
-- ---------------------------------------------------------------------
-- Configurações padrão do Agendamento público (bloco "Agendamento" das
-- sequências), editáveis em Configurações → Agendamento de reuniões.
-- Idempotente: INSERT IGNORE (não sobrescreve valores já definidos).
-- =====================================================================

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('booking_min_advance_days', '1'),
    ('booking_work_start', '09:00'),
    ('booking_work_end', '18:00'),
    ('booking_slot_minutes', '30'),
    ('booking_days_of_week', '1,2,3,4,5'),
    ('booking_duration_min', '45'),
    ('booking_notify_hours_before', '24'),
    ('booking_link_expiry_days', '30');
