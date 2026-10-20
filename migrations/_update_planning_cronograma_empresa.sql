-- =====================================================================
-- Vincula os cards do cronograma ("[CRONO] ...") à empresa Quatri Automação.
-- ---------------------------------------------------------------------
-- Rode no phpMyAdmin (aba SQL), com o banco "helpdesk_on" selecionado.
-- Resolve o id da empresa pelo nome (não usa id fixo). Idempotente:
-- pode rodar quantas vezes quiser; só atualiza os cards "[CRONO] ".
-- =====================================================================

-- Id da empresa Quatri Automação (ajuste o nome se estiver diferente no banco).
SET @company := (SELECT id FROM companies WHERE name = 'Quatri Automação' LIMIT 1);
-- Fallback tolerante a variações de acento/caixa (ex.: "Quatri Automacao").
SET @company := COALESCE(@company, (SELECT id FROM companies WHERE name LIKE 'Quatri Automa%' LIMIT 1));

UPDATE planning_cards
   SET company_id = @company
 WHERE title LIKE '[CRONO] %';

-- Conferência (rode junto): deve mostrar o id da empresa e 18 cards.
SELECT @company AS company_id,
       (SELECT name FROM companies WHERE id = @company) AS company_name,
       COUNT(*) AS cards_atualizados
  FROM planning_cards
 WHERE title LIKE '[CRONO] %' AND company_id = @company;
