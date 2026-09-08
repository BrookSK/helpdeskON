-- =====================================================================
-- 097_teste_fluxo2_triagem_2min.sql  (SOMENTE TESTE)
-- ---------------------------------------------------------------------
-- Coloca os intervalos (esperas) em 2 MINUTOS para validar rapidamente:
--   * "ON Solu · Fluxo 2 (E-mail + WhatsApp)"  (modelo com saída "Resposta
--     recebida" → Conexão de sequência → Triagem)
--   * "ON Solu · Triagem IA (pós-resposta)"    (espera antes de checar resposta)
--
-- Também abre a janela de envio (24h/fim de semana) e destrava os participantes
-- dessas duas sequências para o processamento pegá-los de imediato.
--
-- Reverter: reaplique 094 (Fluxo 2) e 096 (Triagem) para os prazos reais, e
-- 086 para a janela comercial.
-- =====================================================================

SET @triage := (SELECT id FROM email_sequences WHERE name='ON Solu · Triagem IA (pós-resposta)' LIMIT 1);

-- ---------------------------------------------------------------------
-- FLUXO 2 (E-mail + WhatsApp) — todas as esperas em 2 minutos
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','rev','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',500,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',610,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',740,'next','wa2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',850,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',980,'next','e3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',1090,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E3 Caso'))),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1220,'next','wa3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1330,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'))),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1460,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1570,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1680,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1790,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 2 (E-mail + WhatsApp)';

-- ---------------------------------------------------------------------
-- TRIAGEM IA — espera antes de checar resposta em 2 minutos
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_move','nextNo','neg_reply','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (da empresa {{empresa}}) e o histórico de mensagens. decision=true SOMENTE se demonstrou INTERESSE (conversar, receber material, agendar reunião ou seguir). decision=false se desinteresse, pediu para parar, não é o momento ou já tem fornecedor. Em dúvida real, decision=false.')),
        JSON_OBJECT('id','pos_move','type','move','x',120,'y',420,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',540,'next','pos_reply','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','pos_reply','type','reply','x',120,'y',660,'next','sched','data', JSON_OBJECT(
            'subject','Que bom, {{primeiro_nome}}! Vamos avançar',
            'body','{{primeiro_nome}}, que ótimo receber seu retorno!\n\nPara avançarmos, separei um espaço na agenda para uma conversa rápida (online, cerca de 45 minutos).')),
        JSON_OBJECT('id','sched','type','schedule','x',120,'y',780,'next','done','data', JSON_OBJECT(
            'channel','reply','duration',45,
            'title','Agende sua reunião com a ON Solutions Brasil',
            'message', CONCAT(
                '👉 Escolha o melhor dia e horário para você neste link:\n',
                '{{link_agendamento}}\n\n',
                'Assim que confirmar, envio o link da reunião (Google Meet) e um lembrete antes do horário.\n\n',
                'Fico no aguardo!'
            ))),
        JSON_OBJECT('id','neg_reply','type','reply','x',600,'y',440,'next','neg_unsub','data', JSON_OBJECT(
            'subject','Entendido, {{primeiro_nome}}',
            'body','{{primeiro_nome}}, entendido, e obrigado pelo retorno!\n\nRemovi o seu contato da nossa lista de acompanhamento para não enviar mais mensagens. Se em algum momento fizer sentido retomar, é só me chamar por aqui. Sucesso à {{empresa}}!')),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',560,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',600,'y',680,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',800,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),
        JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Triagem IA (pós-resposta)';

-- ---------------------------------------------------------------------
-- Janela 24h + fim de semana (somente teste) para as duas sequências
-- ---------------------------------------------------------------------
UPDATE email_sequences
SET window_start='00:00:00', window_end='23:59:59', send_weekends=1
WHERE name IN ('ON Solu · Fluxo 2 (E-mail + WhatsApp)', 'ON Solu · Triagem IA (pós-resposta)');

-- ---------------------------------------------------------------------
-- Destrava os participantes dessas sequências (reinicia do começo)
-- ---------------------------------------------------------------------
UPDATE sequence_participants sp
JOIN email_sequences s ON s.id = sp.sequence_id
SET sp.status='active', sp.current_node=NULL, sp.next_run_at=NOW(),
    sp.stop_reason=NULL, sp.finished_at=NULL, sp.ab_variant=NULL
WHERE s.name IN ('ON Solu · Fluxo 2 (E-mail + WhatsApp)', 'ON Solu · Triagem IA (pós-resposta)');
