-- =====================================================================
-- Remove o prefixo "[CRONO] " do título dos cards do cronograma.
-- ---------------------------------------------------------------------
-- Rode no phpMyAdmin (aba SQL), com o banco "helpdesk_on" selecionado.
--
-- ATENÇÃO (ordem): rode este script por ÚLTIMO. Depois de tirar o prefixo,
-- os cards NÃO serão mais encontrados por "title LIKE '[CRONO] %'", então
-- os outros scripts do cronograma (empresa, reaplicar seed) deixam de
-- localizá-los. Se ainda precisa vincular à empresa, rode
-- _update_planning_cronograma_empresa.sql ANTES deste.
--
-- Idempotente: só altera títulos que ainda começam com "[CRONO] ".
-- =====================================================================

UPDATE planning_cards
   SET title = TRIM(SUBSTRING(title, CHAR_LENGTH('[CRONO] ') + 1))
 WHERE title LIKE '[CRONO] %';

-- Conferência (rode junto): lista os títulos já sem o prefixo.
SELECT id, title, assigned_to, company_id, start_date, due_date
  FROM planning_cards
 WHERE title IN (
    'Dash Compras',
    'Dash Expedição',
    'Dash Técnico',
    'Agenda Técnica',
    'Módulo Assistência',
    'Nova Proposta Comercial'
 )
    OR title LIKE '1 — %' OR title LIKE '1.%' OR title LIKE '2%' OR title LIKE '3 —%'
 ORDER BY start_date;
