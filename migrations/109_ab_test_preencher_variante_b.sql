-- =====================================================================
-- 109_ab_test_preencher_variante_b.sql
-- ---------------------------------------------------------------------
-- Ativa o TESTE A/B nos blocos de e-mail (send) e WhatsApp (whatsapp) das 3
-- cadências "ON Solu" e injeta uma VARIANTE B ÚNICA e escrita à mão para cada
-- bloco (copies próprias, não derivadas da A). A variante A continua vindo do
-- template atual; a B é o texto inline abaixo.
--
-- Objetivo: testar a aba Performance com A vs B reais.
--
-- Técnica: JSON_TABLE extrai cada nó com seu id; um CASE por id injeta
-- data.ab_enabled=1 + data.subject_b/body_b. Estrutura do grafo preservada.
-- REQUER MySQL 8.0+ (JSON_TABLE). Migration nova (não edita SQLs anteriores).
-- =====================================================================

-- ---------------------------------------------------------------------
-- FLUXO 1 (Só E-mail): blocos e1..e7
-- ---------------------------------------------------------------------
UPDATE email_sequences s
JOIN (
    SELECT s2.id, JSON_OBJECT(
        'start', JSON_EXTRACT(s2.graph, '$.start'),
        'nodes', JSON_ARRAYAGG(
            CASE jt.nid
                WHEN 'e1' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','{{primeiro_nome}}, 3 números que revelam o custo do processo atual',
                    'body_b','Olá, {{primeiro_nome}}.\n\nSem rodeios: a maioria das operações perde tempo e oportunidades em três pontos — digitação/conferência manual, demora na primeira resposta ao cliente e follow-ups que não acontecem.\n\nNa ON Solutions a gente organiza e automatiza esse fluxo junto com a sua equipe. Faz sentido eu te mostrar como isso se aplicaria na {{empresa}}?'))))
                WHEN 'e2' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','{{primeiro_nome}}, retomando — vale 20 minutos?',
                    'body_b','{{primeiro_nome}}, tudo bem?\n\nSó retomando meu contato anterior. Empresas parecidas com a {{empresa}} ganharam tempo automatizando tarefas repetitivas do comercial e do operacional.\n\nSe fizer sentido, reservo 20 minutos para te mostrar como ficaria no seu caso. Prefere esta semana ou a próxima?'))))
                WHEN 'e3' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','Um caso rápido que lembra a {{empresa}}',
                    'body_b','{{primeiro_nome}}, um exemplo objetivo:\n\nUm cliente com um cenário parecido com o da {{empresa}} reduziu o retrabalho manual e passou a responder o cliente no mesmo dia, sem aumentar a equipe.\n\nTe mostro os números numa conversa rápida?'))))
                WHEN 'e4' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','O custo de não organizar o processo',
                    'body_b','{{primeiro_nome}}, um ponto que costuma passar batido: cada oportunidade sem próximo passo definido é receita que evapora silenciosamente.\n\nDá para dimensionar isso rápido. Quer que eu te mostre como calcular no contexto da {{empresa}}?'))))
                WHEN 'e5' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','Material de referência para a {{empresa}}',
                    'body_b','{{primeiro_nome}}, tenho um material curto que mostra na prática como organizamos processos comerciais e operacionais.\n\nPosso te enviar? Se preferir, resumo em 15 minutos de conversa.'))))
                WHEN 'e6' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','Uma linha, {{primeiro_nome}}',
                    'body_b','{{primeiro_nome}}, faz sentido conversarmos sobre automatizar o processo da {{empresa}}? Responde só com sim ou não que eu me oriento. Obrigado!'))))
                WHEN 'e7' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','Encerrando por aqui, {{primeiro_nome}}',
                    'body_b','{{primeiro_nome}}, como não tive retorno, vou encerrar meus contatos para não te incomodar.\n\nSe um dia fizer sentido organizar e automatizar o processo da {{empresa}}, é só me chamar. Sucesso!'))))
                ELSE jt.node
            END
        )
    ) AS newgraph
    FROM email_sequences s2
    JOIN JSON_TABLE(s2.graph, '$.nodes[*]' COLUMNS (node JSON PATH '$', nid VARCHAR(60) PATH '$.id')) jt
    WHERE s2.name = 'ON Solu · Fluxo 1 (Só E-mail)' AND JSON_VALID(s2.graph)
    GROUP BY s2.id
) x ON x.id = s.id
SET s.graph = x.newgraph;

-- ---------------------------------------------------------------------
-- FLUXO 2 (E-mail + WhatsApp): e1,e2,e3 (email) e wa1,wa2,wa3 (whatsapp)
-- ---------------------------------------------------------------------
UPDATE email_sequences s
JOIN (
    SELECT s2.id, JSON_OBJECT(
        'start', JSON_EXTRACT(s2.graph, '$.start'),
        'nodes', JSON_ARRAYAGG(
            CASE jt.nid
                WHEN 'e1' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','{{primeiro_nome}}, uma ideia rápida para a {{empresa}}',
                    'body_b','Olá, {{primeiro_nome}}.\n\nAjudamos empresas a organizar e automatizar o comercial e o operacional — menos tarefa manual, respostas mais rápidas e nenhuma oportunidade esquecida.\n\nFaz sentido eu te mostrar como isso se aplicaria à {{empresa}}?'))))
                WHEN 'e2' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','{{primeiro_nome}}, seguimos?',
                    'body_b','{{primeiro_nome}}, só retomando: consigo te mostrar em 20 minutos como reduzir o trabalho manual do time da {{empresa}}.\n\nPrefere manhã ou tarde?'))))
                WHEN 'e3' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'subject_b','Caso prático parecido com a {{empresa}}',
                    'body_b','{{primeiro_nome}}, um cliente com cenário parecido passou a responder no mesmo dia e a não perder follow-up, sem contratar mais gente.\n\nTe mostro como replicar isso na {{empresa}}?'))))
                WHEN 'wa1' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','Oi, {{primeiro_nome}}! Aqui é da ON Solutions. Ajudamos a {{empresa}} a automatizar tarefas do comercial/operacional. Posso te explicar rapidinho como funciona?'))))
                WHEN 'wa2' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, o ganho costuma ser direto: menos tarefa repetitiva e resposta mais rápida ao cliente. Vale uns 20 min pra eu te mostrar no caso da {{empresa}}?'))))
                WHEN 'wa3' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, pra eu me orientar: faz sentido conversarmos sobre automatizar o processo da {{empresa}}? Pode responder só com sim ou não 🙂'))))
                ELSE jt.node
            END
        )
    ) AS newgraph
    FROM email_sequences s2
    JOIN JSON_TABLE(s2.graph, '$.nodes[*]' COLUMNS (node JSON PATH '$', nid VARCHAR(60) PATH '$.id')) jt
    WHERE s2.name = 'ON Solu · Fluxo 2 (E-mail + WhatsApp)' AND JSON_VALID(s2.graph)
    GROUP BY s2.id
) x ON x.id = s.id
SET s.graph = x.newgraph;

-- ---------------------------------------------------------------------
-- FLUXO 3 (Só WhatsApp): wa1..wa5
-- ---------------------------------------------------------------------
UPDATE email_sequences s
JOIN (
    SELECT s2.id, JSON_OBJECT(
        'start', JSON_EXTRACT(s2.graph, '$.start'),
        'nodes', JSON_ARRAYAGG(
            CASE jt.nid
                WHEN 'wa1' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','Oi, {{primeiro_nome}}! Aqui é da ON Solutions. A gente organiza e automatiza processos comerciais/operacionais. Posso te mostrar como isso ajudaria a {{empresa}}?'))))
                WHEN 'wa2' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, na prática o resultado é menos tarefa manual e resposta mais rápida ao cliente. Quer que eu te explique em 15 minutos?'))))
                WHEN 'wa3' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, um cliente parecido com a {{empresa}} parou de perder follow-up e ganhou horas por semana. Te mostro como replicar isso?'))))
                WHEN 'wa4' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, pra facilitar: consigo encaixar uma conversa rápida esta semana. Prefere manhã ou tarde?'))))
                WHEN 'wa5' THEN JSON_MERGE_PATCH(jt.node, JSON_OBJECT('data', JSON_MERGE_PATCH(JSON_EXTRACT(jt.node,'$.data'), JSON_OBJECT(
                    'ab_enabled',1,
                    'body_b','{{primeiro_nome}}, vou encerrar por aqui pra não te incomodar. Se um dia fizer sentido organizar o processo da {{empresa}}, é só me chamar. Abraço!'))))
                ELSE jt.node
            END
        )
    ) AS newgraph
    FROM email_sequences s2
    JOIN JSON_TABLE(s2.graph, '$.nodes[*]' COLUMNS (node JSON PATH '$', nid VARCHAR(60) PATH '$.id')) jt
    WHERE s2.name = 'ON Solu · Fluxo 3 (Só WhatsApp)' AND JSON_VALID(s2.graph)
    GROUP BY s2.id
) x ON x.id = s.id
SET s.graph = x.newgraph;
