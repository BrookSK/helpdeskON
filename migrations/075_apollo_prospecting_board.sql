-- =====================================================================
-- Board dedicado da Prospecção Automática (visível só para admin/super_admin)
-- =====================================================================
-- Cria (idempotente):
--   1) Coluna visibility em crm_boards (admin = só super_admin enxerga).
--   2) O board "Prospecção Automática" com as colunas do funil.
--   3) Aponta a campanha "Captação Apollo · Padrão" para esse board/1ª coluna.
--   4) Atualiza o grafo da sequência para MOVER o card para a coluna "Respondeu"
--      quando o lead responde (em vez de apenas encerrar).
-- Executar APÓS 072/073/074.
-- =====================================================================

-- (0) Etiquetas da prospecção (criadas já no CRM; idempotente por nome)
INSERT INTO whatsapp_labels (name, color)
SELECT 'prospecao apollo - Ativa', '#0d6efd'
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_labels WHERE name = 'prospecao apollo - Ativa');
INSERT INTO whatsapp_labels (name, color)
SELECT 'prospecao apollo - Sem Resposta', '#f5a623'
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_labels WHERE name = 'prospecao apollo - Sem Resposta');
INSERT INTO whatsapp_labels (name, color)
SELECT 'prospecao apollo - Perdida', '#d32f2f'
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_labels WHERE name = 'prospecao apollo - Perdida');

-- (1) Coluna de visibilidade do board
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crm_boards' AND COLUMN_NAME='visibility');
SET @s := IF(@c=0, "ALTER TABLE crm_boards ADD COLUMN visibility ENUM('all','admin') NOT NULL DEFAULT 'all' AFTER is_active", 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- (2) Board da Prospecção Automática (só admin) + colunas do funil
INSERT INTO crm_boards (name, description, is_active, visibility, created_by)
SELECT 'Prospecção Automática', 'Funil da captação automática via Apollo (somente administradores).', 1, 'admin',
       (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM crm_boards WHERE name='Prospecção Automática');

-- Colunas (só cria se o board ainda não tiver nenhuma)
SET @board_id := (SELECT id FROM crm_boards WHERE name='Prospecção Automática' ORDER BY id ASC LIMIT 1);

INSERT INTO crm_columns (board_id, name, color, position)
SELECT @board_id, t.name, t.color, t.position
FROM (
    SELECT 'Novo' AS name, '#607d8b' AS color, 0 AS position
    UNION ALL SELECT 'Em prospecção', '#0d6efd', 1
    UNION ALL SELECT 'Respondeu', '#20c997', 2
    UNION ALL SELECT 'Qualificado', '#6f42c1', 3
    UNION ALL SELECT 'Reunião', '#fd7e14', 4
    UNION ALL SELECT 'Ganho', '#2e7d32', 5
    UNION ALL SELECT 'Sem resposta', '#9e9e9e', 6
    UNION ALL SELECT 'Perdido', '#d32f2f', 7
) t
WHERE @board_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM crm_columns WHERE board_id = @board_id);

-- (3) Aponta a campanha padrão para esse board e a 1ª coluna (Novo)
UPDATE apollo_campaigns
SET board_id = @board_id,
    column_id = (SELECT id FROM crm_columns WHERE board_id = @board_id AND name='Novo' ORDER BY position ASC LIMIT 1)
WHERE name = 'Captação Apollo · Padrão' AND @board_id IS NOT NULL;

-- (4) Atualiza o grafo: ao responder, MOVE o card para "Respondeu" e depois encerra.
--     Insere o nó 'moved' e faz todas as condições apontarem nextYes para ele.
SET @col_respondeu := (SELECT id FROM crm_columns WHERE board_id = @board_id AND name='Respondeu' ORDER BY position ASC LIMIT 1);
SET @col_semresp   := (SELECT id FROM crm_columns WHERE board_id = @board_id AND name='Sem resposta' ORDER BY position ASC LIMIT 1);

UPDATE email_sequences
SET graph = JSON_OBJECT(
    'start', 'tagativa',
    'nodes', JSON_ARRAY(
        -- Marca o lead como "Ativa" ao entrar na sequência (posicionado à esquerda)
        JSON_OBJECT('id','tagativa','type','tag','x',40,'y',40,'next','send1','data', JSON_OBJECT('label','prospecao apollo - Ativa','color','#0d6efd')),
        JSON_OBJECT('id','send1','type','send','x',360,'y',40,'next','wait1','data', JSON_OBJECT(
            'ab_enabled', true,
            'template_id',   (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
            'template_id_b', (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
            'subject',   (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
            'body',      (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
            'subject_b', (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
            'body_b',    (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)')
        )),
        JSON_OBJECT('id','wait1','type','wait','x',360,'y',140,'next','cond1','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','cond1','type','condition','x',360,'y',260,'nextYes','moved','nextNo','send2','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','send2','type','send','x',360,'y',380,'next','wait2','data', JSON_OBJECT(
            'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up 1'),
            'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up 1'),
            'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up 1')
        )),
        JSON_OBJECT('id','wait2','type','wait','x',360,'y',500,'next','cond2','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','cond2','type','condition','x',360,'y',620,'nextYes','moved','nextNo','revealph','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','revealph','type','reveal_phone','x',360,'y',740,'next','waitph','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','waitph','type','wait','x',360,'y',860,'next','wa1','data', JSON_OBJECT('amount',1,'unit','days')),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',980,'next','wait3','data', JSON_OBJECT(
            'template_id', (SELECT id FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato'),
            'body',        (SELECT body FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato')
        )),
        JSON_OBJECT('id','wait3','type','wait','x',360,'y',1100,'next','cond3','data', JSON_OBJECT('amount',2,'unit','days')),
        JSON_OBJECT('id','cond3','type','condition','x',360,'y',1220,'nextYes','moved','nextNo','task1','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','task1','type','tag','x',360,'y',1340,'next','send3','data', JSON_OBJECT('label','prospecao apollo - Sem Resposta','color','#f5a623')),
        JSON_OBJECT('id','send3','type','send','x',360,'y',1460,'next','wait4','data', JSON_OBJECT(
            'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up Final'),
            'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up Final'),
            'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up Final')
        )),
        JSON_OBJECT('id','wait4','type','wait','x',360,'y',1580,'next','cond4','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','cond4','type','condition','x',360,'y',1700,'nextYes','moved','nextNo','coldmove','data', JSON_OBJECT('kind','replied')),
        -- Sem resposta ao fim do fluxo: move para "Sem resposta", marca Perdida e encerra
        JSON_OBJECT('id','coldmove','type','move','x',360,'y',1820,'next','cold','data', JSON_OBJECT('column_id', @col_semresp)),
        JSON_OBJECT('id','cold','type','tag','x',360,'y',1940,'next','done','data', JSON_OBJECT('label','prospecao apollo - Perdida','color','#d32f2f')),
        -- Respondeu: move para a coluna "Respondeu" e encerra a automação
        JSON_OBJECT('id','moved','type','move','x',720,'y',260,'next','done','data', JSON_OBJECT('column_id', @col_respondeu)),
        JSON_OBJECT('id','done','type','end','x',720,'y',2060,'data', JSON_OBJECT())
    )
)
WHERE name = 'Prospecção Apollo · Cold Outbound';
