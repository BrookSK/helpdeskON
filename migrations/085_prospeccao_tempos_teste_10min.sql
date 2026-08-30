-- =====================================================================
-- 085_prospeccao_tempos_teste_10min.sql  (SOMENTE PARA TESTE)
-- ---------------------------------------------------------------------
-- Ajusta TODAS as esperas das 3 cadências "ON Solu" para 10 MINUTOS, para
-- validar o fluxo completo rapidamente (sem esperar dias entre os toques).
--
-- Mantém a estrutura idêntica ao estado atual (083 + 084), incluindo o nó
-- 'moved' (move → "Respondeu") no ramo de resposta. Só muda os waits.
--
-- Como o fluxo pausa quando o lead responde, para testar até o fim NÃO
-- interaja com o lead (não responder e-mail/WhatsApp).
--
-- REVERTER com: 086_prospeccao_tempos_producao.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- FLUXO 1 (Só E-mail) — todas as esperas em 10 minutos
-- ---------------------------------------------------------------------
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

        JSON_OBJECT('id','e7','type','send','x',360,'y',1940,'next','tagsr','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'))),
        JSON_OBJECT('id','tagsr','type','tag','x',360,'y',2060,'next','sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','sc','type','score','x',360,'y',2180,'next','done','data', JSON_OBJECT('delta',-10)),

        JSON_OBJECT('id','moved','type','move','x',720,'y',240,'next','done','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','done','type','end','x',720,'y',2300,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 1 (Só E-mail)';

-- ---------------------------------------------------------------------
-- FLUXO 2 (E-mail + WhatsApp) — todas as esperas em 10 minutos
-- ---------------------------------------------------------------------
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

        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1740,'next','tagsr','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'))),
        JSON_OBJECT('id','tagsr','type','tag','x',360,'y',1860,'next','sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','sc','type','score','x',360,'y',1980,'next','done','data', JSON_OBJECT('delta',-10)),

        JSON_OBJECT('id','moved','type','move','x',720,'y',240,'next','done','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','done','type','end','x',720,'y',2100,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 2 (E-mail + WhatsApp)';

-- ---------------------------------------------------------------------
-- FLUXO 3 (Só WhatsApp) — todas as esperas em 10 minutos
-- ---------------------------------------------------------------------
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

        JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',1300,'next','tagsr','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'))),
        JSON_OBJECT('id','tagsr','type','tag','x',360,'y',1420,'next','sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','sc','type','score','x',360,'y',1540,'next','done','data', JSON_OBJECT('delta',-10)),

        JSON_OBJECT('id','moved','type','move','x',720,'y',240,'next','done','data', JSON_OBJECT('column_name','Respondeu')),
        JSON_OBJECT('id','done','type','end','x',720,'y',1660,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 3 (Só WhatsApp)';
