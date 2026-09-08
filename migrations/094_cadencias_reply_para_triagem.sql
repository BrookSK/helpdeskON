-- =====================================================================
-- 094_cadencias_reply_para_triagem.sql
-- ---------------------------------------------------------------------
-- Reescreve as 3 cadências ON Solu num modelo mais claro de RESPOSTA:
--
--   Cada bloco de mensagem (e-mail/WhatsApp) tem a saída "Resposta recebida"
--   (nextReply) ligada a um único bloco "Conexão de sequência" (connect), que
--   puxa a sequência "ON Solu · Triagem IA (pós-resposta)".
--
--   Assim fica explícito que a qualificação ocorre assim que o lead responde
--   EM QUALQUER ETAPA (o motor detecta a resposta por e-mail e WhatsApp e segue
--   o nextReply), e não apenas no bloco de Condição.
--
--   Fluxo principal (sem resposta): mensagem → aguardar → próxima mensagem →
--   ... → esgota → "Sem resposta" → encerra.
--
-- Requer: blocos 'connect' e 'schedule' no SequenceEngine; a sequência de
-- triagem (087/093). Idempotente: regrava o grafo por nome.
-- =====================================================================

SET @triage := (SELECT id FROM email_sequences WHERE name='ON Solu · Triagem IA (pós-resposta)' LIMIT 1);

-- ---------------------------------------------------------------------
-- FLUXO 1 (Só E-mail)
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E2 Follow'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','e3','data', JSON_OBJECT('amount',4,'unit','days')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E3 Caso'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','e4','data', JSON_OBJECT('amount',5,'unit','days')),
        JSON_OBJECT('id','e4','type','send','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E4 Custo'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','e5','data', JSON_OBJECT('amount',6,'unit','days')),
        JSON_OBJECT('id','e5','type','send','x',360,'y',980,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E5 Material'))),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1110,'next','e6','data', JSON_OBJECT('amount',7,'unit','days')),
        JSON_OBJECT('id','e6','type','send','x',360,'y',1220,'next','w6','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'))),
        JSON_OBJECT('id','w6','type','wait','x',360,'y',1350,'next','e7','data', JSON_OBJECT('amount',7,'unit','days')),
        JSON_OBJECT('id','e7','type','send','x',360,'y',1460,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'))),
        -- Sem resposta: encerra no "Sem resposta"
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1590,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1700,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1810,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        -- Conexão de sequência (destino de todas as saídas "Resposta recebida")
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1920,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 1 (Só E-mail)';

-- ---------------------------------------------------------------------
-- FLUXO 2 (E-mail + WhatsApp)
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','days')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','rev','data', JSON_OBJECT('amount',2,'unit','days')),
        JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',500,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',610,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',740,'next','wa2','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',850,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',980,'next','e3','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',1090,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E3 Caso'))),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1220,'next','wa3','data', JSON_OBJECT('amount',4,'unit','days')),
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
-- FLUXO 3 (Só WhatsApp)
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wa1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'))),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','wa2','data', JSON_OBJECT('amount',3,'unit','days')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'))),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','wa3','data', JSON_OBJECT('amount',4,'unit','days')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'))),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','wa4','data', JSON_OBJECT('amount',4,'unit','days')),
        JSON_OBJECT('id','wa4','type','whatsapp','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'))),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','wa5','data', JSON_OBJECT('amount',4,'unit','days')),
        JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',980,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'))),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1110,'next','nr_sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1220,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1330,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1440,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 3 (Só WhatsApp)';
