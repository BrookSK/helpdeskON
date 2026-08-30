-- =====================================================================
-- 110_cadencias_janela_full_teste.sql
-- ---------------------------------------------------------------------
-- FINS DE TESTE: permite disparar as cadências a QUALQUER hora e sem barrar por
-- limite diário. É isto que causava o reagendamento para "+1 hora" ou para o
-- próximo horário comercial ao clicar em executar:
--   * fora da janela (08:30–17:00) => reagenda para o próximo window_start;
--   * daily_limit atingido         => reagenda +1 hora.
--
-- Abre a janela 00:00–23:59, liga fins de semana e sobe o limite diário.
-- Aplica às 3 cadências ON Solu e à Triagem IA. Migration nova (não edita SQLs).
-- Para produção, reverter com um novo SQL que restaure 08:30–17:00 e o limite.
-- =====================================================================

UPDATE email_sequences
SET window_start = '00:00:00',
    window_end   = '23:59:59',
    send_weekends = 1,
    daily_limit  = 1000
WHERE name IN (
    'ON Solu · Fluxo 1 (Só E-mail)',
    'ON Solu · Fluxo 2 (E-mail + WhatsApp)',
    'ON Solu · Fluxo 3 (Só WhatsApp)',
    'ON Solu · Triagem IA (pós-resposta)'
);
