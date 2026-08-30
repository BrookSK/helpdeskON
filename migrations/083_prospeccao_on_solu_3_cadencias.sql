-- =====================================================================
-- 083_prospeccao_on_solu_3_cadencias.sql
-- ---------------------------------------------------------------------
-- Provisiona as 3 cadências "Prospecção ON Solu" prontas para uso, com
-- roteamento automático por canal na captação Apollo:
--
--   * Lead com E-MAIL + TELEFONE  -> "ON Solu · Fluxo 2 (E-mail + WhatsApp)"  (mixed)
--   * Lead SÓ com E-MAIL          -> "ON Solu · Fluxo 1 (Só E-mail)"          (email)
--   * Lead SÓ com TELEFONE        -> "ON Solu · Fluxo 3 (Só WhatsApp)"        (whatsapp)
--
-- Requer as migrations:
--   081_sequence_channel_type.sql      (coluna channel_type em email_sequences)
--   082_apollo_campaign_auto_route.sql (auto_route + sequence_id_email/whatsapp/mixed)
--
-- Idempotente: cada template/sequência/campanha é criado por nome único.
-- Copy (Banco de copies · versão 6): apresentação → ideia → método → prova →
-- convite → retomada → encerramento. Nunca diagnostica o destinatário.
--
-- Variáveis substituídas pelo motor: {{primeiro_nome}} {{empresa}} etc.
-- =====================================================================

-- =====================================================================
-- (A) TEMPLATES — FLUXO 1 (SÓ E-MAIL) · 7 toques
-- =====================================================================

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E1 Abertura', 'Apresentação · ON Solutions Brasil',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Sou da ON Solutions Brasil e escrevo para me apresentar.</p>
<p>Trabalhamos com organização e automação de processos comerciais e operacionais. Na prática, desenhamos o fluxo de trabalho de ponta a ponta, do primeiro contato com o cliente até o pós-venda, reunimos em um só lugar a informação que costuma circular entre planilhas, e-mails e aplicativos de mensagem, e automatizamos as etapas repetitivas que hoje consomem tempo da equipe.</p>
<p>Atendemos empresas de médio porte em fase de crescimento, principalmente em serviços, indústria e distribuição. Os projetos duram de seis a doze semanas, são conduzidos junto com a equipe interna e terminam com essa equipe operando o processo sozinha.</p>
<p>O motivo do contato é que a {{empresa}} tem o perfil das empresas com quem trabalhamos, e me parece útil que você saiba que existimos antes de o assunto entrar na pauta de vocês.</p>
<p>Se quiser conhecer melhor o trabalho, tenho um material curto que reúne as decisões que mais influenciam o resultado desse tipo de projeto. Respondo com ele no mesmo dia.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E1 Abertura');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E2 Follow', 'O que costuma decidir o resultado nesses projetos',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Uma ideia que talvez seja útil para a {{empresa}}, independentemente de conversarmos.</p>
<p>A maior parte do ganho em projetos de automação aparece antes da tecnologia entrar. Quando o fluxo está desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata. Quando não está, a ferramenta se torna mais um lugar onde a informação se divide.</p>
<p>É por isso que começamos todo projeto pelo desenho do processo e só depois pelo sistema. Em geral, é também o que faz o prazo cair pela metade.</p>
<p>Se quiser, te mando o material que detalha esse desenho.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E2 Follow');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E3 Caso', 'Como uma empresa reduziu retrabalho sem trocar de sistema',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Um exemplo costuma explicar melhor do que qualquer descrição.</p>
<p>Uma empresa de porte parecido com o da {{empresa}} tinha equipe experiente, atendia bem e mesmo assim gastava tempo repetindo informação entre as áreas. Não era falha de ninguém. O processo tinha crescido em camadas, como acontece em praticamente toda empresa que cresce rápido, e cada camada resolvia um problema da época.</p>
<p>Redesenhamos o fluxo, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento do que ficava parado. O time continuou o mesmo e o resultado apareceu em poucas semanas.</p>
<p>Se quiser, te mando o resumo do projeto, com etapas e prazos reais.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E3 Caso');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E4 Custo', 'Como calcular o custo do processo atual',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Uma pergunta que aparece em toda conversa sobre esse tema é quanto o processo de hoje custa. É uma conta que dá para fazer internamente, sem consultoria.</p>
<p>Três números costumam bastar: o tempo médio que a equipe gasta em digitação e conferência de informação, o intervalo entre o pedido do cliente e a primeira resposta, e a proporção de oportunidades que ficam sem próximo passo definido. Com esses três, o custo aparece com bastante clareza e serve para justificar ou descartar qualquer projeto.</p>
<p>Tenho a planilha que usamos para essa conta. Se quiser, ela é sua, com ou sem projeto no meio.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E4 Custo');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E5 Material', 'Vinte minutos com referências de mercado',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Se em algum momento fizer sentido, reservo vinte minutos para mostrar como empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números do que mudou em cada uma.</p>
<p>É uma conversa em que você sai com referência de mercado na mão, trabalhando conosco ou não.</p>
<p>Me diga dois horários que funcionem para você e eu envio o convite.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E5 Material');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E6 Uma linha', 'Re: Apresentação · ON Solutions Brasil',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Prefere que eu retome esse assunto mais para frente?</p>
<p>Posso reaparecer quando o planejamento do próximo ciclo estiver em pauta, se for um momento melhor.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E6 Uma linha');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F1 E7 Encerramento', 'Deixando o assunto em aberto',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Deixo o assunto em aberto por aqui.</p>
<p>O material que mencionei continua disponível. Basta responder esta mensagem quando quiser, mesmo daqui a um ano, e eu envio no mesmo dia.</p>
<p>E se o tema for responsabilidade de outra pessoa dentro da {{empresa}}, agradeço se puder me indicar o nome.</p>
<p>Obrigado pela atenção.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F1 E7 Encerramento');

-- =====================================================================
-- (B) TEMPLATES — FLUXO 2 (E-MAIL + WHATSAPP)
--     (reutiliza F1 E1 no D0; abaixo os específicos do fluxo 2)
-- =====================================================================

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F2 E2 Follow curto', 'Re: Apresentação · ON Solutions Brasil',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Complemento o contato anterior com a ideia que mais influencia esse tipo de projeto.</p>
<p>O ganho maior aparece antes da tecnologia entrar. Quando o fluxo está desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata. Quando não está, a ferramenta se torna mais um lugar onde a informação se divide.</p>
<p>É por isso que começamos todo projeto pelo desenho do processo e só depois pelo sistema.</p>
<p>Se quiser, te mando o material que detalha esse desenho.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F2 E2 Follow curto');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F2 WA1 Primeiro contato', NULL,
'Olá, {{primeiro_nome}}, tudo bem? Aqui é da ON Solutions Brasil.

Te escrevi por e-mail nos últimos dias com uma apresentação do nosso trabalho: organização e automação de processos comerciais e operacionais, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.

O contato é porque a {{empresa}} tem o perfil dos clientes com quem trabalhamos. Se for mais prático por aqui, te envio o material de referência que citei no e-mail.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F2 WA1 Primeiro contato');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F2 WA2 Valor', NULL,
'{{primeiro_nome}}, uma conta que costuma ser útil para quem avalia esse tema, mesmo sem projeto no meio.

Três números bastam para dimensionar o custo do processo atual: o tempo que a equipe gasta em digitação e conferência de informação, o intervalo entre o pedido do cliente e a primeira resposta, e a proporção de oportunidades que ficam sem próximo passo definido.

Tenho a planilha que usamos para esse cálculo. Se quiser, te envio por aqui.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F2 WA2 Valor');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · F2 E3 Caso', 'Como uma empresa reduziu retrabalho sem trocar de sistema',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Um exemplo concreto do que comentei.</p>
<p>Uma empresa de porte parecido com o da {{empresa}} tinha equipe experiente e boa reputação com clientes, e ainda assim gastava tempo repetindo informação entre áreas. O processo tinha crescido em camadas, cada uma resolvendo um problema da época.</p>
<p>Redesenhamos o fluxo, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento do que ficava parado. Mesma equipe, resultado em poucas semanas.</p>
<p>Se quiser, te mando o resumo do projeto, com etapas e prazos reais.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F2 E3 Caso');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F2 WA3 Pergunta binária', NULL,
'{{primeiro_nome}}, se em algum momento fizer sentido, reservo vinte minutos para mostrar como empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada caso.

Você sai da conversa com referência de mercado na mão, de qualquer forma.

Fico à disposição para quando for um bom momento.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F2 WA3 Pergunta binária');

-- =====================================================================
-- (C) TEMPLATES — FLUXO 3 (SÓ WHATSAPP) · 5 toques (sem áudio)
-- =====================================================================

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F3 WA1 Abertura', NULL,
'Olá, {{primeiro_nome}}, tudo bem? Aqui é da ON Solutions Brasil, e escrevo para me apresentar.

Trabalhamos com organização e automação de processos comerciais e operacionais em empresas de médio porte de serviços, indústria e distribuição. Desenhamos o fluxo de trabalho de ponta a ponta, reunimos a informação em um só lugar e automatizamos as etapas repetitivas, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.

O contato é porque a {{empresa}} tem o perfil dos clientes com quem trabalhamos.

Se quiser conhecer melhor, tenho um material curto sobre o tema e te envio por aqui.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F3 WA1 Abertura');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F3 WA2 Valor', NULL,
'{{primeiro_nome}}, complemento com a ideia que mais influencia esse tipo de projeto.

O ganho maior aparece antes da tecnologia entrar. Com o fluxo desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata. Sem isso, a ferramenta vira mais um lugar onde a informação se divide.

É por isso que começamos sempre pelo desenho e só depois pelo sistema.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F3 WA2 Valor');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F3 WA3 Prova', NULL,
'{{primeiro_nome}}, um exemplo concreto.

Uma empresa de porte parecido com o da {{empresa}} tinha equipe experiente e boa reputação com clientes, e mesmo assim repetia informação entre áreas porque o processo tinha crescido em camadas ao longo dos anos.

Redesenhamos o fluxo, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento. Mesma equipe, resultado em poucas semanas.

Posso te mandar o resumo do projeto, com etapas e prazos reais.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F3 WA3 Prova');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F3 WA4 Pergunta binária', NULL,
'{{primeiro_nome}}, se fizer sentido em algum momento, reservo vinte minutos para mostrar como empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada caso.

É uma conversa em que você sai com referência de mercado, trabalhando conosco ou não.

Fico à disposição para quando for um bom momento.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F3 WA4 Pergunta binária');

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'ON Solu · F3 WA5 Encerramento', NULL,
'{{primeiro_nome}}, deixo o assunto em aberto por aqui.

O material continua disponível. É só me chamar nesta conversa quando quiser, mesmo daqui a um bom tempo, e eu envio no mesmo dia.

Obrigado pela atenção.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · F3 WA5 Encerramento');

-- =====================================================================
-- (D) SEQUÊNCIA — FLUXO 1 (SÓ E-MAIL) · channel_type = 'email'
--     Cadência: D0 → D3 → D7 → D12 → D18 → D25 → D32
--     Cada e-mail é seguido de espera + condição "respondeu?" (encerra se sim).
-- =====================================================================
INSERT INTO email_sequences (name, description, channel_type, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'ON Solu · Fluxo 1 (Só E-mail)',
    'Cadência de 7 e-mails em 32 dias para leads sem telefone ou de cargo alto. Encerra ao primeiro retorno (e-mail ou WhatsApp).',
    'email',
    JSON_OBJECT(
        'start', 'e1',
        'nodes', JSON_ARRAY(
            JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
            JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','done','nextNo','e2','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e2','type','send','x',360,'y',340,'next','w2','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E2 Follow'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E2 Follow'))),
            JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',4,'unit','days')),
            JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','done','nextNo','e3','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e3','type','send','x',360,'y',660,'next','w3','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E3 Caso'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E3 Caso'))),
            JSON_OBJECT('id','w3','type','wait','x',360,'y',780,'next','c3','data', JSON_OBJECT('amount',5,'unit','days')),
            JSON_OBJECT('id','c3','type','condition','x',360,'y',880,'nextYes','done','nextNo','e4','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e4','type','send','x',360,'y',980,'next','w4','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E4 Custo'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E4 Custo'))),
            JSON_OBJECT('id','w4','type','wait','x',360,'y',1100,'next','c4','data', JSON_OBJECT('amount',6,'unit','days')),
            JSON_OBJECT('id','c4','type','condition','x',360,'y',1200,'nextYes','done','nextNo','e5','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e5','type','send','x',360,'y',1300,'next','w5','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E5 Material'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E5 Material'))),
            JSON_OBJECT('id','w5','type','wait','x',360,'y',1420,'next','c5','data', JSON_OBJECT('amount',7,'unit','days')),
            JSON_OBJECT('id','c5','type','condition','x',360,'y',1520,'nextYes','done','nextNo','e6','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e6','type','send','x',360,'y',1620,'next','w6','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E6 Uma linha'))),
            JSON_OBJECT('id','w6','type','wait','x',360,'y',1740,'next','c6','data', JSON_OBJECT('amount',7,'unit','days')),
            JSON_OBJECT('id','c6','type','condition','x',360,'y',1840,'nextYes','done','nextNo','e7','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e7','type','send','x',360,'y',1940,'next','tagsr','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E7 Encerramento'))),
            JSON_OBJECT('id','tagsr','type','tag','x',360,'y',2060,'next','sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
            JSON_OBJECT('id','sc','type','score','x',360,'y',2180,'next','done','data', JSON_OBJECT('delta',-10)),

            JSON_OBJECT('id','done','type','end','x',720,'y',2300,'data', JSON_OBJECT())
        )
    ),
    1, 50, '08:30:00', '17:00:00', 0
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='ON Solu · Fluxo 1 (Só E-mail)');

-- =====================================================================
-- (E) SEQUÊNCIA — FLUXO 2 (E-MAIL + WHATSAPP) · channel_type = 'mixed'
--     Cadência: E1 D0 → E2 D+2 → WA1 D+4 → WA2 D+7 → E3 D+10 → WA3 D+14.
--     Antes do 1º WhatsApp, revela o telefone (Apollo). Blocos de WhatsApp
--     são pulados automaticamente pelo motor se o lead não tiver telefone.
-- =====================================================================
INSERT INTO email_sequences (name, description, channel_type, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'ON Solu · Fluxo 2 (E-mail + WhatsApp)',
    'Cadência mista de e-mail e WhatsApp em 14 dias, com revelação de telefone (Apollo). Encerra ao primeiro retorno (e-mail ou WhatsApp).',
    'mixed',
    JSON_OBJECT(
        'start', 'e1',
        'nodes', JSON_ARRAY(
            JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F1 E1 Abertura'))),
            JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',2,'unit','days')),
            JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','done','nextNo','e2','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e2','type','send','x',360,'y',340,'next','w2','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E2 Follow curto'))),
            JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',2,'unit','days')),
            JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','done','nextNo','rev','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',660,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
            JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',780,'next','w3','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA1 Primeiro contato'))),
            JSON_OBJECT('id','w3','type','wait','x',360,'y',900,'next','c3','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','c3','type','condition','x',360,'y',1000,'nextYes','done','nextNo','wa2','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',1100,'next','w4','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA2 Valor'))),
            JSON_OBJECT('id','w4','type','wait','x',360,'y',1220,'next','c4','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','c4','type','condition','x',360,'y',1320,'nextYes','done','nextNo','e3','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','e3','type','send','x',360,'y',1420,'next','w5','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · F2 E3 Caso'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 E3 Caso'))),
            JSON_OBJECT('id','w5','type','wait','x',360,'y',1540,'next','c5','data', JSON_OBJECT('amount',4,'unit','days')),
            JSON_OBJECT('id','c5','type','condition','x',360,'y',1640,'nextYes','done','nextNo','wa3','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1740,'next','tagsr','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F2 WA3 Pergunta binária'))),
            JSON_OBJECT('id','tagsr','type','tag','x',360,'y',1860,'next','sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
            JSON_OBJECT('id','sc','type','score','x',360,'y',1980,'next','done','data', JSON_OBJECT('delta',-10)),

            JSON_OBJECT('id','done','type','end','x',720,'y',2100,'data', JSON_OBJECT())
        )
    ),
    1, 50, '08:30:00', '17:00:00', 0
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='ON Solu · Fluxo 2 (E-mail + WhatsApp)');

-- =====================================================================
-- (F) SEQUÊNCIA — FLUXO 3 (SÓ WHATSAPP) · channel_type = 'whatsapp'
--     Cadência: WA1 D0 → WA2 D+3 → WA3 D+7 → WA4 D+11 → WA5 D+15.
--     Reply-check entre toques. Sem áudio: todos os toques são texto.
-- =====================================================================
INSERT INTO email_sequences (name, description, channel_type, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'ON Solu · Fluxo 3 (Só WhatsApp)',
    'Cadência de 5 toques de WhatsApp em 15 dias para leads sem e-mail válido. Encerra ao primeiro retorno.',
    'whatsapp',
    JSON_OBJECT(
        'start', 'wa1',
        'nodes', JSON_ARRAY(
            JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',20,'next','w1','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA1 Abertura'))),
            JSON_OBJECT('id','w1','type','wait','x',360,'y',140,'next','c1','data', JSON_OBJECT('amount',3,'unit','days')),
            JSON_OBJECT('id','c1','type','condition','x',360,'y',240,'nextYes','done','nextNo','wa2','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',340,'next','w2','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA2 Valor'))),
            JSON_OBJECT('id','w2','type','wait','x',360,'y',460,'next','c2','data', JSON_OBJECT('amount',4,'unit','days')),
            JSON_OBJECT('id','c2','type','condition','x',360,'y',560,'nextYes','done','nextNo','wa3','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',660,'next','w3','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA3 Prova'))),
            JSON_OBJECT('id','w3','type','wait','x',360,'y',780,'next','c3','data', JSON_OBJECT('amount',4,'unit','days')),
            JSON_OBJECT('id','c3','type','condition','x',360,'y',880,'nextYes','done','nextNo','wa4','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa4','type','whatsapp','x',360,'y',980,'next','w4','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA4 Pergunta binária'))),
            JSON_OBJECT('id','w4','type','wait','x',360,'y',1100,'next','c4','data', JSON_OBJECT('amount',4,'unit','days')),
            JSON_OBJECT('id','c4','type','condition','x',360,'y',1200,'nextYes','done','nextNo','wa5','data', JSON_OBJECT('kind','replied')),

            JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',1300,'next','tagsr','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · F3 WA5 Encerramento'))),
            JSON_OBJECT('id','tagsr','type','tag','x',360,'y',1420,'next','sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
            JSON_OBJECT('id','sc','type','score','x',360,'y',1540,'next','done','data', JSON_OBJECT('delta',-10)),

            JSON_OBJECT('id','done','type','end','x',720,'y',1660,'data', JSON_OBJECT())
        )
    ),
    1, 30, '08:30:00', '17:00:00', 0
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='ON Solu · Fluxo 3 (Só WhatsApp)');

-- =====================================================================
-- (G) CAMPANHA DE CAPTAÇÃO com ROTEAMENTO AUTOMÁTICO por canal
--     e-mail+telefone → Fluxo 2 (mixed)
--     só e-mail        → Fluxo 1 (email)
--     só telefone      → Fluxo 3 (whatsapp)
--
-- Requer as colunas de 082 (auto_route, sequence_id_email/whatsapp/mixed).
-- sequence_id (padrão/fallback) aponta para o Fluxo 2.
-- =====================================================================
INSERT INTO apollo_campaigns
    (name, is_active, lead_source, auto_route,
     sequence_id, sequence_id_email, sequence_id_whatsapp, sequence_id_mixed,
     board_id, column_id, assigned_to, created_by,
     search_filters, icp_rules, min_score, daily_target, search_per_page, search_page,
     days_of_week, window_start, window_end, reveal_email, reveal_phone, global_dedupe)
SELECT
    'ON Solu · Captação Apollo (roteada por canal)',
    1, 'apollo', 1,
    (SELECT id FROM email_sequences WHERE name='ON Solu · Fluxo 2 (E-mail + WhatsApp)'),
    (SELECT id FROM email_sequences WHERE name='ON Solu · Fluxo 1 (Só E-mail)'),
    (SELECT id FROM email_sequences WHERE name='ON Solu · Fluxo 3 (Só WhatsApp)'),
    (SELECT id FROM email_sequences WHERE name='ON Solu · Fluxo 2 (E-mail + WhatsApp)'),
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
    '2,3,4',
    '08:30:00', '17:00:00',
    1, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM apollo_campaigns WHERE name='ON Solu · Captação Apollo (roteada por canal)');
