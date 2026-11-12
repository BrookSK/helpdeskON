-- =====================================================================
-- QUATRI (company_id 2) — v6
--  1) 517 OS V2 + Gestão à Vista Novo: acrescenta na descrição o PDF da
--     listagem de produtos (aba Visão Geral do card).
--  2) Card NOVO, urgente, para HOJE (10/11/2026), para o Lucas Campagna:
--     "Gestão à Vista — Últimas melhorias".
-- Rodar de uma vez no phpMyAdmin (produção). Em transação: se algum
-- comando falhar, nada é gravado. Pode rodar de novo sem duplicar.
-- =====================================================================

SET @campagna := (SELECT id FROM users WHERE name LIKE 'Lucas Campagna%' ORDER BY id LIMIT 1);
SET @author   := COALESCE(@campagna, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));
SELECT @campagna AS campagna, @author AS autor;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) 517 — PDF da listagem de produtos (acrescenta ao fim da descrição)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
  '<p><strong>PDF da listagem de produtos:</strong></p>',
  '<ul>',
  '<li>No Gestão à Vista, ao entrar em um card, a aba <strong>Visão Geral</strong> mostra a listagem dos produtos.</li>',
  '<li>Colocar um botão para <strong>gerar um PDF</strong> com essa listagem dos produtos e as quantidades, para a equipe poder imprimir a lista.</li>',
  '</ul>')
 WHERE id=517 AND company_id=2
   AND (description IS NULL OR description NOT LIKE '%PDF da listagem de produtos%');

-- ---------------------------------------------------------------------
-- 2) Card novo de hoje (só cria se ainda não existir)
-- ---------------------------------------------------------------------
INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT 'Gestão à Vista — Últimas melhorias',
  CONCAT(
  '<p><strong>Kanban do Gestão à Vista — filtros:</strong></p>',
  '<ul>',
  '<li><strong>Busca:</strong> a opção de busca sumiu dos filtros. Colocar de volta uma busca geral, que encontre o card por tudo: cliente, orçamento, número e as demais informações do card.</li>',
  '<li><strong>Filtro por categoria:</strong> permitir filtrar os cards pela categoria do card.</li>',
  '</ul>',
  '<p><strong>Layout do Kanban em telas menores:</strong> em telas menores o Kanban fica pequeno e só dá para ler um card de cada vez. Aumentar o tamanho da área do Kanban nessas telas. Pode ter rolagem de tela, não tem problema.</p>',
  '<p><strong>Dentro do card — Documentos:</strong> na aba de documentos, adicionar uma opção de documentos para o setor de <strong>Produção</strong>.</p>',
  '<p><strong>Dentro do card — Categoria:</strong> abaixo do segmento fica a categoria, que hoje só permite selecionar uma. Permitir selecionar <strong>mais de uma categoria</strong> no card.</p>'
  ),
  2, @campagna, @author, 'urgent', 'open', 0,
  '2026-11-10 09:00:00', '2026-11-10 18:00:00', '2026-11-10 18:00:00', '2026-11-10', '2026-11-10', NULL, 0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM planning_cards
   WHERE company_id=2 AND title='Gestão à Vista — Últimas melhorias' COLLATE utf8mb4_unicode_ci
);

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date, pc.due_date, u.name AS atendente
FROM planning_cards pc LEFT JOIN users u ON u.id = pc.assigned_to
WHERE pc.company_id=2 AND (pc.id=517 OR pc.title='Gestão à Vista — Últimas melhorias' COLLATE utf8mb4_unicode_ci);

SELECT id, title, description FROM planning_cards
WHERE company_id=2 AND (id=517 OR title='Gestão à Vista — Últimas melhorias' COLLATE utf8mb4_unicode_ci);
