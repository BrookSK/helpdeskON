-- =====================================================================
-- MODO TESTE — Sequência de prospecção rápida (fluxo completo em ~5 min)
-- =====================================================================
-- Cria (idempotente):
--   1) Contato de teste (Lucas Vacari — lucas@lrvweb.com.br / 17991190528).
--   2) Sequência "Prospecção Apollo · TESTE (5min)" com waits em MINUTOS.
--   3) Inscreve o contato de teste na sequência (pronto para rodar já).
--
-- Executar APÓS 072/073 (templates). O cron/runSequences processa os passos.
-- Para acompanhar: /crm/prospecting → aba "Logs de execução".
--
-- Reexecutar não duplica (checagens por e-mail / nome único / unique key).
-- =====================================================================

-- ---------------------------------------------------------------------
-- (1) Contato de teste (Lead = whatsapp_contacts)
-- ---------------------------------------------------------------------
SET @inst := (SELECT id FROM whatsapp_instances WHERE is_default = 1 LIMIT 1);
SET @inst := COALESCE(@inst, (SELECT id FROM whatsapp_instances ORDER BY id ASC LIMIT 1));

INSERT INTO whatsapp_contacts (instance_id, remote_jid, phone, lead_email, contact_name, assigned_to)
SELECT @inst, '17991190528@s.whatsapp.net', '17991190528', 'lucas@lrvweb.com.br', 'Lucas Vacari (TESTE)',
       (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1)
WHERE @inst IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM whatsapp_contacts WHERE lead_email = 'lucas@lrvweb.com.br');

SET @contact_id := (SELECT id FROM whatsapp_contacts WHERE lead_email = 'lucas@lrvweb.com.br' ORDER BY id ASC LIMIT 1);

-- Garante que o contato de teste não esteja bloqueado/descadastrado
UPDATE whatsapp_contacts SET unsubscribed = 0, email_bounced = 0 WHERE id = @contact_id;

-- Briefing mínimo (empresa/cargo para as variáveis dos templates)
INSERT INTO commercial_briefings (contact_id, need, notes)
SELECT @contact_id, 'Tecnologia', 'Cargo: Diretor | Empresa: LRV Web | LinkedIn: '
WHERE @contact_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM commercial_briefings WHERE contact_id = @contact_id);

-- ---------------------------------------------------------------------
-- (2) Sequência de TESTE — mesmos passos, waits em MINUTOS (total ~5 min)
--     1min → checa → follow-up → 1min → reveal → 1min → whatsapp → 1min →
--     tag → follow-up final → 1min → encerra. Sem janela de horário/fim de semana.
-- ---------------------------------------------------------------------
INSERT INTO email_sequences (name, description, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'Prospecção Apollo · TESTE (5min)',
    'Modo teste: fluxo completo em ~5 minutos (waits em minutos, sem janela).',
    JSON_OBJECT(
        'start', 'send1',
        'nodes', JSON_ARRAY(
            JSON_OBJECT('id','send1','type','send','x',360,'y',20,'next','wait1','data', JSON_OBJECT(
                'ab_enabled', true,
                'template_id',   (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'template_id_b', (SELECT id FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
                'subject',   (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'body',      (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato A (Dor)'),
                'subject_b', (SELECT subject FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)'),
                'body_b',    (SELECT body FROM message_templates WHERE name='Apollo · 1º Contato B (Resultado)')
            )),
            JSON_OBJECT('id','wait1','type','wait','x',360,'y',140,'next','cond1','data', JSON_OBJECT('amount',1,'unit','minutes')),
            JSON_OBJECT('id','cond1','type','condition','x',360,'y',260,'nextYes','done','nextNo','send2','data', JSON_OBJECT('kind','replied')),
            JSON_OBJECT('id','send2','type','send','x',360,'y',380,'next','wait2','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up 1'),
                'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up 1'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up 1')
            )),
            JSON_OBJECT('id','wait2','type','wait','x',360,'y',500,'next','revealph','data', JSON_OBJECT('amount',1,'unit','minutes')),
            JSON_OBJECT('id','revealph','type','reveal_phone','x',360,'y',620,'next','waitph','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
            JSON_OBJECT('id','waitph','type','wait','x',360,'y',740,'next','wa1','data', JSON_OBJECT('amount',1,'unit','minutes')),
            JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',860,'next','wait3','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · WhatsApp 1º Contato')
            )),
            JSON_OBJECT('id','wait3','type','wait','x',360,'y',980,'next','tag1','data', JSON_OBJECT('amount',1,'unit','minutes')),
            JSON_OBJECT('id','tag1','type','tag','x',360,'y',1100,'next','send3','data', JSON_OBJECT('label','prospecao apollo - Sem Resposta','color','#f5a623')),
            JSON_OBJECT('id','send3','type','send','x',360,'y',1220,'next','done','data', JSON_OBJECT(
                'template_id', (SELECT id FROM message_templates WHERE name='Apollo · Follow-up Final'),
                'subject',     (SELECT subject FROM message_templates WHERE name='Apollo · Follow-up Final'),
                'body',        (SELECT body FROM message_templates WHERE name='Apollo · Follow-up Final')
            )),
            JSON_OBJECT('id','done','type','end','x',360,'y',1340,'data', JSON_OBJECT())
        )
    ),
    1, 1000, '00:00:00', '23:59:59', 1
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='Prospecção Apollo · TESTE (5min)');

SET @seq_id := (SELECT id FROM email_sequences WHERE name='Prospecção Apollo · TESTE (5min)' ORDER BY id ASC LIMIT 1);

-- ---------------------------------------------------------------------
-- (3) Inscreve o contato de teste na sequência (pronto para rodar já)
-- ---------------------------------------------------------------------
INSERT INTO sequence_participants (sequence_id, contact_id, status, current_node, next_run_at, added_by)
SELECT @seq_id, @contact_id, 'active', NULL, NOW(),
       (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id ASC LIMIT 1)
WHERE @seq_id IS NOT NULL AND @contact_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM sequence_participants WHERE sequence_id = @seq_id AND contact_id = @contact_id);

-- Se já existia (reexecução), reativa para rodar de novo do início
UPDATE sequence_participants
SET status='active', current_node=NULL, next_run_at=NOW(), stop_reason=NULL, finished_at=NULL, ab_variant=NULL
WHERE sequence_id = @seq_id AND contact_id = @contact_id;

-- Registra o início no log de prospecção (para aparecer na aba de logs)
INSERT INTO apollo_prospecting_log (campaign_id, contact_id, action, detail)
SELECT NULL, @contact_id, 'enrolled', 'Contato de teste inscrito na sequência TESTE (5min)'
WHERE @contact_id IS NOT NULL;
