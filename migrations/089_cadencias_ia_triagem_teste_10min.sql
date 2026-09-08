-- =====================================================================
-- 089_cadencias_ia_triagem_teste_10min.sql  (SOMENTE TESTE)
-- ---------------------------------------------------------------------
-- Igual ao 088 (cadências com triagem por IA), mas com TODAS as esperas em
-- 10 MINUTOS, janela 24h/fim de semana aberta e destrava dos participantes,
-- para validar o fluxo completo rapidamente. Reverter com o 088.
--
-- Depende dos templates do 088 (rode o 088 pelo menos uma vez antes, ou
-- garanta que os templates de triagem existam). Idempotente.
-- =====================================================================

-- Garante os templates de triagem (idempotente) — caso o 088 não tenha rodado.
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · Triagem Interesse (Positivo)', 'Que bom, {{primeiro_nome}}! Vamos avançar',
'<p>Olá, {{primeiro_nome}}.</p><p>Fico feliz com o seu retorno. Um especialista entrará em contato para entender o cenário da {{empresa}} e propor os próximos passos.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Interesse (Positivo)');
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · Triagem Sem Interesse (Remoção)', 'Entendido, {{primeiro_nome}}',
'<p>Olá, {{primeiro_nome}}.</p><p>Obrigado pelo retorno. Removi seu contato da lista para não enviar mais mensagens. Se quiser retomar, é só responder. Sucesso à {{empresa}}.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Sem Interesse (Remoção)');
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · Triagem Interesse (Positivo) WA', NULL,
'{{primeiro_nome}}, que ótimo! Um especialista da ON Solutions entra em contato para falar sobre a {{empresa}}.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Interesse (Positivo) WA');
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · Triagem Sem Interesse (Remoção) WA', NULL,
'{{primeiro_nome}}, entendido, obrigado. Removi seu contato da lista. Se quiser retomar, é só chamar. Sucesso à {{empresa}}!'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Sem Interesse (Remoção) WA');

-- FLUXO 1 (Só E-mail) — esperas de 10 min + triagem IA
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','moved','nextNo','e2','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',340,'next','w2','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E2 Follow'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','moved','nextNo','e3','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',660,'next','w3','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E3 Caso'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',780,'next','c3','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c3','type','condition','x',360,'y',880,'nextYes','moved','nextNo','e4','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e4','type','send','x',360,'y',980,'next','w4','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E4 Custo'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',1100,'next','c4','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c4','type','condition','x',360,'y',1200,'nextYes','moved','nextNo','e5','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e5','type','send','x',360,'y',1300,'next','w5','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E5 Material'))),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1420,'next','c5','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c5','type','condition','x',360,'y',1520,'nextYes','moved','nextNo','e6','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e6','type','send','x',360,'y',1620,'next','w6','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'))),
        JSON_OBJECT('id','w6','type','wait','x',360,'y',1740,'next','c6','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c6','type','condition','x',360,'y',1840,'nextYes','moved','nextNo','e7','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e7','type','send','x',360,'y',1940,'next','nr_tag','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'))),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',2060,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',2180,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',2300,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','moved','type','move','x',760,'y',240,'next','ia','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','ia','type','ai','x',760,'y',360,'nextYes','pos_mail','nextNo','neg_mail','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (empresa {{empresa}}) e o histórico. decision=true SOMENTE se demonstrou INTERESSE. decision=false se sem interesse, pediu para parar, não é o momento ou já tem fornecedor. Em dúvida, decision=false.')),
        JSON_OBJECT('id','pos_mail','type','send','x',560,'y',500,'next','pos_move','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'))),
        JSON_OBJECT('id','pos_move','type','move','x',560,'y',620,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',560,'y',740,'next','done','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','neg_mail','type','send','x',960,'y',500,'next','neg_unsub','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'))),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',960,'y',620,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',960,'y',740,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',960,'y',860,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),
        JSON_OBJECT('id','done','type','end','x',760,'y',1000,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 1 (Só E-mail)';

-- FLUXO 2 (E-mail + WhatsApp) — esperas de 10 min + triagem IA
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','moved','nextNo','e2','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',340,'next','w2','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','moved','nextNo','rev','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',660,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',780,'next','w3','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',900,'next','c3','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c3','type','condition','x',360,'y',1000,'nextYes','moved','nextNo','wa2','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',1100,'next','w4','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',1220,'next','c4','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c4','type','condition','x',360,'y',1320,'nextYes','moved','nextNo','e3','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',1420,'next','w5','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E3 Caso'))),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1540,'next','c5','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c5','type','condition','x',360,'y',1640,'nextYes','moved','nextNo','wa3','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1740,'next','nr_tag','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'))),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1860,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1980,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',2100,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','moved','type','move','x',760,'y',240,'next','ia','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','ia','type','ai','x',760,'y',360,'nextYes','pos_mail','nextNo','neg_mail','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (empresa {{empresa}}) e o histórico (e-mail e WhatsApp). decision=true SOMENTE se demonstrou INTERESSE. decision=false se sem interesse. Em dúvida, decision=false.')),
        JSON_OBJECT('id','pos_mail','type','send','x',560,'y',500,'next','pos_move','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'))),
        JSON_OBJECT('id','pos_move','type','move','x',560,'y',620,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',560,'y',740,'next','done','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','neg_mail','type','send','x',960,'y',500,'next','neg_unsub','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'))),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',960,'y',620,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',960,'y',740,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',960,'y',860,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),
        JSON_OBJECT('id','done','type','end','x',760,'y',1000,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 2 (E-mail + WhatsApp)';

-- FLUXO 3 (Só WhatsApp) — esperas de 10 min + triagem IA
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wa1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',20,'next','w1','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','moved','nextNo','wa2','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',340,'next','w2','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','moved','nextNo','wa3','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',660,'next','w3','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',780,'next','c3','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c3','type','condition','x',360,'y',880,'nextYes','moved','nextNo','wa4','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa4','type','whatsapp','x',360,'y',980,'next','w4','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',1100,'next','c4','data', JSON_OBJECT('amount',10,'unit','minutes')),
        JSON_OBJECT('id','c4','type','condition','x',360,'y',1200,'nextYes','moved','nextNo','wa5','data', JSON_OBJECT('kind','replied')),
        JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',1300,'next','nr_tag','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'))),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1420,'next','nr_sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1540,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1660,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','moved','type','move','x',760,'y',240,'next','ia','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','ia','type','ai','x',760,'y',360,'nextYes','pos_wa','nextNo','neg_wa','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (empresa {{empresa}}) e o histórico de WhatsApp. decision=true SOMENTE se demonstrou INTERESSE. decision=false se sem interesse. Em dúvida, decision=false.')),
        JSON_OBJECT('id','pos_wa','type','whatsapp','x',560,'y',500,'next','pos_move','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo) WA'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo) WA'))),
        JSON_OBJECT('id','pos_move','type','move','x',560,'y',620,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',560,'y',740,'next','done','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','neg_wa','type','whatsapp','x',960,'y',500,'next','neg_unsub','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção) WA'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção) WA'))),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',960,'y',620,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',960,'y',740,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',960,'y',860,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),
        JSON_OBJECT('id','done','type','end','x',760,'y',1000,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 3 (Só WhatsApp)';

-- Janela 24h + fim de semana e destrava dos participantes (somente teste)
UPDATE email_sequences SET window_start='00:00:00', window_end='23:59:59', send_weekends=1
WHERE name IN ('ON Solu · Fluxo 1 (Só E-mail)','ON Solu · Fluxo 2 (E-mail + WhatsApp)','ON Solu · Fluxo 3 (Só WhatsApp)');

UPDATE sequence_participants sp
JOIN email_sequences s ON s.id = sp.sequence_id
SET sp.status='active', sp.current_node=NULL, sp.next_run_at=NOW(), sp.stop_reason=NULL, sp.finished_at=NULL, sp.ab_variant=NULL
WHERE s.name IN ('ON Solu · Fluxo 1 (Só E-mail)','ON Solu · Fluxo 2 (E-mail + WhatsApp)','ON Solu · Fluxo 3 (Só WhatsApp)');
