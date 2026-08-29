-- =====================================================================
-- Cria o CARD do contato de teste no board "Prospecção Automática"
-- (coluna "Novo"), caso ele ainda não tenha nenhum card.
-- Idempotente: reexecutar não duplica.
-- Executar APÓS 075 (board) e 076 (contato de teste).
-- =====================================================================

SET @contact_id := (SELECT id FROM whatsapp_contacts WHERE lead_email = 'lucas@lrvweb.com.br' ORDER BY id ASC LIMIT 1);

SET @col_novo := (
    SELECT col.id FROM crm_columns col
    JOIN crm_boards b ON col.board_id = b.id
    WHERE b.name = 'Prospecção Automática' AND col.name = 'Novo'
    ORDER BY col.position ASC LIMIT 1
);

INSERT INTO crm_cards (column_id, contact_id, title, created_by, assigned_to, position)
SELECT @col_novo, @contact_id, 'Lucas Vacari (TESTE)',
       (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1),
       (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1),
       0
WHERE @col_novo IS NOT NULL AND @contact_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM crm_cards WHERE contact_id = @contact_id);
