-- =====================================================================
-- 109_ab_test_preencher_variante_b.sql  (REESCRITO — base = 094)
-- ---------------------------------------------------------------------
-- Reescreve as 3 cadências ON Solu EXATAMENTE como o 094 (modelo de resposta:
-- cada mensagem tem nextReply -> bloco 'connect' (triage) que puxa a Triagem IA),
-- e SÓ ADICIONA o teste A/B (ab_enabled + subject_b/body_b inline) em cada bloco
-- de e-mail/WhatsApp. Copies B únicas, escritas à mão.
--
-- Preserva start / next / nextReply / connect / tempos do 094. Idempotente.
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
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'ab_enabled',1,
            'subject_b','{{primeiro_nome}}, 3 números que revelam o custo do processo atual',
            'body_b','Olá, {{primeiro_nome}}.\n\nSem rodeios: a maioria das operações perde tempo e oportunidades em três pontos — digitação/conferência manual, demora na primeira resposta ao cliente e follow-ups que não acontecem.\n\nNa ON Solutions organizamos e automatizamos esse fluxo junto com a sua equipe. Faz sentido eu te mostrar como isso se aplicaria à {{empresa}}?')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
            'ab_enabled',1,
            'subject_b','{{primeiro_nome}}, retomando — vale 20 minutos?',
            'body_b','{{primeiro_nome}}, tudo bem?\n\nSó retomando meu contato anterior. Empresas parecidas com a {{empresa}} ganharam tempo automatizando tarefas repetitivas do comercial e do operacional.\n\nSe fizer sentido, reservo 20 minutos para te mostrar como ficaria no seu caso. Prefere esta semana ou a próxima?')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','e3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
            'ab_enabled',1,
            'subject_b','Um caso rápido que lembra a {{empresa}}',
            'body_b','{{primeiro_nome}}, um exemplo objetivo:\n\nUm cliente com cenário parecido com o da {{empresa}} reduziu o retrabalho manual e passou a responder no mesmo dia, sem aumentar a equipe.\n\nTe mostro os números numa conversa rápida?')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','e4','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e4','type','send','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
            'ab_enabled',1,
            'subject_b','O custo de não organizar o processo',
            'body_b','{{primeiro_nome}}, um ponto que costuma passar batido: cada oportunidade sem próximo passo definido é receita que evapora silenciosamente.\n\nDá para dimensionar isso rápido. Quer que eu te mostre como calcular no contexto da {{empresa}}?')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','e5','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e5','type','send','x',360,'y',980,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
            'ab_enabled',1,
            'subject_b','Material de referência para a {{empresa}}',
            'body_b','{{primeiro_nome}}, tenho um material curto que mostra na prática como organizamos processos comerciais e operacionais.\n\nPosso te enviar? Se preferir, resumo em 15 minutos de conversa.')),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1110,'next','e6','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e6','type','send','x',360,'y',1220,'next','w6','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
            'ab_enabled',1,
            'subject_b','Uma linha, {{primeiro_nome}}',
            'body_b','{{primeiro_nome}}, faz sentido conversarmos sobre automatizar o processo da {{empresa}}? Responde só com sim ou não que eu me oriento. Obrigado!')),
        JSON_OBJECT('id','w6','type','wait','x',360,'y',1350,'next','e7','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e7','type','send','x',360,'y',1460,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'ab_enabled',1,
            'subject_b','Encerrando por aqui, {{primeiro_nome}}',
            'body_b','{{primeiro_nome}}, como não tive retorno, vou encerrar meus contatos para não te incomodar.\n\nSe um dia fizer sentido organizar e automatizar o processo da {{empresa}}, é só me chamar. Sucesso!')),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1590,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1700,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1810,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
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
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
            'ab_enabled',1,
            'subject_b','{{primeiro_nome}}, uma ideia rápida para a {{empresa}}',
            'body_b','Olá, {{primeiro_nome}}.\n\nAjudamos empresas a organizar e automatizar o comercial e o operacional — menos tarefa manual, respostas mais rápidas e nenhuma oportunidade esquecida.\n\nFaz sentido eu te mostrar como isso se aplicaria à {{empresa}}?')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
            'ab_enabled',1,
            'subject_b','{{primeiro_nome}}, seguimos?',
            'body_b','{{primeiro_nome}}, só retomando: consigo te mostrar em 20 minutos como reduzir o trabalho manual do time da {{empresa}}.\n\nPrefere manhã ou tarde?')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','rev','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',500,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',610,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
            'ab_enabled',1,
            'body_b','Oi, {{primeiro_nome}}! Aqui é da ON Solutions. Ajudamos a {{empresa}} a automatizar tarefas do comercial/operacional. Posso te explicar rapidinho como funciona?')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',740,'next','wa2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',850,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, o ganho costuma ser direto: menos tarefa repetitiva e resposta mais rápida ao cliente. Vale uns 20 min pra eu te mostrar no caso da {{empresa}}?')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',980,'next','e3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','e3','type','send','x',360,'y',1090,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
            'ab_enabled',1,
            'subject_b','Caso prático parecido com a {{empresa}}',
            'body_b','{{primeiro_nome}}, um cliente com cenário parecido passou a responder no mesmo dia e a não perder follow-up, sem contratar mais gente.\n\nTe mostro como replicar isso na {{empresa}}?')),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1220,'next','wa3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1330,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, pra eu me orientar: faz sentido conversarmos sobre automatizar o processo da {{empresa}}? Pode responder só com sim ou não.')),
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
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'),
            'ab_enabled',1,
            'body_b','Oi, {{primeiro_nome}}! Aqui é da ON Solutions. A gente organiza e automatiza processos comerciais/operacionais. Posso te mostrar como isso ajudaria a {{empresa}}?')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','wa2','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, na prática o resultado é menos tarefa manual e resposta mais rápida ao cliente. Quer que eu te explique em 15 minutos?')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','wa3','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, um cliente parecido com a {{empresa}} parou de perder follow-up e ganhou horas por semana. Te mostro como replicar isso?')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','wa4','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa4','type','whatsapp','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, pra facilitar: consigo encaixar uma conversa rápida esta semana. Prefere manhã ou tarde?')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','wa5','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',980,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, vou encerrar por aqui pra não te incomodar. Se um dia fizer sentido organizar o processo da {{empresa}}, é só me chamar. Abraço!')),
        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1110,'next','nr_sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1220,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1330,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1440,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 3 (Só WhatsApp)';
