-- =====================================================================
-- SQL 1 (executar por último) — SEQUÊNCIA + INFRAESTRUTURA DA CAPTAÇÃO APOLLO
-- =====================================================================
-- Cria (idempotente):
--   1) DDL de apoio: coluna ab_variant (A/B persistente), tabela de campanhas
--      (configuração de captação/ICP) e tabela de log/consumo de créditos.
--   2) A sequência de follow-up (email_sequences) com o grafo completo.
--   3) A campanha de captação (apollo_campaigns) apontando para a sequência,
--      board/coluna, ICP, filtros de busca, meta diária e janela.
--
-- Executar APÓS os templates (072 e-mail, 073 whatsapp) — o grafo resolve os
-- IDs de template por subquery pelo nome único (sem placeholders).
-- Reexecutar não duplica (checagens por nome).
-- =====================================================================

-- ---------------------------------------------------------------------
-- (1) DDL de apoio
-- ---------------------------------------------------------------------

-- A/B persistente por participante (sorteado uma vez e mantido).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sequence_participants' AND COLUMN_NAME='ab_variant');
SET @s := IF(@c=0,'ALTER TABLE sequence_participants ADD COLUMN ab_variant VARCHAR(8) DEFAULT NULL AFTER status','SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Campanhas de captação (ICP, filtros, board, sequência, meta e janela).
CREATE TABLE IF NOT EXISTS apollo_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sequence_id INT DEFAULT NULL,
    board_id INT DEFAULT NULL,
    column_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    search_filters JSON DEFAULT NULL COMMENT 'filtros do People Search (person_titles, seniorities, locations, etc.)',
    icp_rules JSON DEFAULT NULL COMMENT 'regras de ICP + pesos de score',
    min_score INT NOT NULL DEFAULT 70,
    daily_target INT NOT NULL DEFAULT 12,
    search_per_page INT NOT NULL DEFAULT 50,
    search_page INT NOT NULL DEFAULT 1 COMMENT 'página atual da busca (avança a cada execução)',
    days_of_week VARCHAR(20) DEFAULT '1,2,3,4,5' COMMENT 'ISO-8601: 1=seg ... 7=dom. Vazio=todos',
    window_start TIME NOT NULL DEFAULT '08:00:00',
    window_end TIME NOT NULL DEFAULT '18:00:00',
    reveal_email TINYINT(1) NOT NULL DEFAULT 1,
    reveal_phone TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'telefone é progressivo (revelado no step de WhatsApp)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_campaign_name (name),
    KEY idx_active (is_active),
    FOREIGN KEY (sequence_id) REFERENCES email_sequences(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de captação / consumo de créditos (auditoria e analytics de custo).
CREATE TABLE IF NOT EXISTS apollo_prospecting_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT DEFAULT NULL,
    apollo_lead_id INT DEFAULT NULL,
    contact_id INT DEFAULT NULL,
    action VARCHAR(40) NOT NULL COMMENT 'searched, duplicated, out_of_icp, low_score, selected, reveal_email, reveal_phone, enrolled, done, search_failed, reveal_error',
    detail TEXT DEFAULT NULL,
    credits INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_campaign (campaign_id, created_at),
    KEY idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token do cron (usado pelo endpoint /cron/runProspecting). Mantém o já existente.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('cron_token', '');

-- ---------------------------------------------------------------------
-- (2) Sequência de follow-up (grafo completo)
-- ---------------------------------------------------------------------
-- Formato real do SequenceEngine. Nós de envio apontam para templates por ID
-- (resolvidos por subquery pelo nome único criado no 072/073).
-- send: A/B via template_id (variante A) + template_id_b (variante B).
-- reveal_phone: reveal progressivo (só gasta crédito se lead sem telefone).
-- condition kind=replied ramifica (respondeu → end; não respondeu → segue).

INSERT INTO email_sequences (name, description, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'Prospecção Apollo · Cold Outbound',
    'Sequência automática de prospecção comercial (Apollo Search → reveal → CRM). A/B no 1º contato, follow-ups, WhatsApp com reveal progressivo de telefone e tarefa de ligação.',
    JSON_OBJECT(
        'start', 'send1',
        'nodes', JSON_ARRAY(
            -- Coluna principal centralizada (x=360). Cada nó de envio traz o conteúdo
            -- inline (subject/body) E o template_id — assim o bloco aparece preenchido
            -- no editor e o engine usa o template ao renderizar.
            JSON_OBJECT('id','send1','type','send','x',360,'y',20,'next','wait1','data', JSON_OBJECT(
                'ab_enabled', true,
                'template_id',   (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'template_id_b', (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
                'subject',   (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'body',      (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'subject_b', (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
                'body_b',    (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)')
            )),
            JSON_OBJECT('id','wait1','type','wait','x',360,'y',140,'next','cond1','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','cond1','type','condition','x',360,'y',260,'nextYes','done','nextNo','send2','data', JSON_OBJECT('kind','replied')),
            JSON_OBJECT('id','send2','type','send','x',360,'y',380,'next','wait2','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up 1'),
                'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up 1'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up 1')
            )),
            JSON_OBJECT('id','wait2','type','wait','x',360,'y',500,'next','cond2','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','cond2','type','condition','x',360,'y',620,'nextYes','done','nextNo','revealph','data', JSON_OBJECT('kind','replied')),
            JSON_OBJECT('id','revealph','type','reveal_phone','x',360,'y',740,'next','waitph','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
            JSON_OBJECT('id','waitph','type','wait','x',360,'y',860,'next','wa1','data', JSON_OBJECT('amount',1,'unit','days')),
            JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',980,'next','wait3','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato')
            )),
            JSON_OBJECT('id','wait3','type','wait','x',360,'y',1100,'next','cond3','data', JSON_OBJECT('amount',2,'unit','days')),
            JSON_OBJECT('id','cond3','type','condition','x',360,'y',1220,'nextYes','done','nextNo','task1','data', JSON_OBJECT('kind','replied')),
            JSON_OBJECT('id','task1','type','tag','x',360,'y',1340,'next','send3','data', JSON_OBJECT('label','prospecao apollo - Sem Resposta','color','#f5a623')),
            JSON_OBJECT('id','send3','type','send','x',360,'y',1460,'next','wait4','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up Final'),
                'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up Final'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up Final')
            )),
            JSON_OBJECT('id','wait4','type','wait','x',360,'y',1580,'next','cond4','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','cond4','type','condition','x',360,'y',1700,'nextYes','done','nextNo','cold','data', JSON_OBJECT('kind','replied')),
            JSON_OBJECT('id','cold','type','tag','x',360,'y',1820,'next','done','data', JSON_OBJECT('label','prospecao apollo - Perdida','color','#d32f2f')),
            -- "Encerrar" à direita, ponto de convergência de todos os ramos "respondeu".
            JSON_OBJECT('id','done','type','end','x',720,'y',1940,'data', JSON_OBJECT())
        )
    ),
    1, 100, '08:00:00', '18:00:00', 0
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='Prospecção Apollo · Cold Outbound');

-- ---------------------------------------------------------------------
-- (3) Campanha de captação apontando para a sequência criada acima
-- ---------------------------------------------------------------------
INSERT INTO apollo_campaigns
    (name, is_active, sequence_id, board_id, column_id, assigned_to, created_by,
     search_filters, icp_rules, min_score, daily_target, search_per_page, search_page,
     days_of_week, window_start, window_end, reveal_email, reveal_phone)
SELECT
    'Captação Apollo · Padrão',
    1,
    (SELECT id FROM email_sequences WHERE name='Prospecção Apollo · Cold Outbound'),
    (SELECT b.id FROM crm_boards b ORDER BY b.id ASC LIMIT 1),
    (SELECT col.id FROM crm_columns col
        WHERE col.board_id = (SELECT b.id FROM crm_boards b ORDER BY b.id ASC LIMIT 1)
        ORDER BY col.position ASC LIMIT 1),
    (SELECT u.id FROM users u WHERE u.role='super_admin' AND u.is_active=1 ORDER BY u.id ASC LIMIT 1),
    (SELECT u.id FROM users u WHERE u.role='super_admin' AND u.is_active=1 ORDER BY u.id ASC LIMIT 1),
    JSON_OBJECT(
        'person_titles', JSON_ARRAY('ceo','diretor','gerente','head','proprietário','sócio'),
        'person_seniorities', JSON_ARRAY('owner','founder','c_suite','vp','head','director'),
        'person_locations', JSON_ARRAY('Brazil'),
        'organization_num_employees_ranges', JSON_ARRAY('11,50','51,200','201,500')
    ),
    JSON_OBJECT(
        'seniorities', JSON_ARRAY('owner','founder','c_suite','vp','head','director'),
        'titles_any', JSON_ARRAY('ceo','diretor','gerente','head','sócio','proprietário'),
        'employee_min', 11,
        'employee_max', 500,
        'require_website', true,
        'score', JSON_OBJECT('decisor',30,'title',20,'size',15,'region',10,'website',5,'technology',10)
    ),
    70, 12, 50, 1,
    '1,2,3,4,5',
    '08:00:00', '18:00:00',
    1, 0
WHERE NOT EXISTS (SELECT 1 FROM apollo_campaigns WHERE name='Captação Apollo · Padrão');
