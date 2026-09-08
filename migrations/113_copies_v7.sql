-- =====================================================================
-- 113_copies_v7.sql
-- ---------------------------------------------------------------------
-- Copies de prospecção · VERSÃO 7 (arco: apresentação → ideia → caso →
-- o que dá errado → ferramenta → convite → despedida). Primeira pessoa,
-- assinada por {{remetente_nome}} (variável nova, resolvida pelo motor via
-- setting prospecting_sender_name / smtp_from_name).
--
-- Reescreve o grafo das 3 cadências ON Solu com o conteúdo INLINE (variante A)
-- + variante B (teste: profundidade x concisão, mesma voz). Mantém a estrutura
-- do 094 (cada mensagem -> nextReply -> bloco 'triage' connect) e os waits de
-- 2 min (TESTE — não altera o tempo por enquanto).
--
-- Também atualiza as mensagens de triagem (positivo/negativo) para a v7.
-- Idempotente (UPDATE por nome). Requer a sequência de triagem já existente.
-- =====================================================================

SET @triage := (SELECT id FROM email_sequences WHERE name='ON Solu · Triagem IA (pós-resposta)' LIMIT 1);

-- ---------------------------------------------------------------------
-- FLUXO 1 (Só E-mail) — 7 toques
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'subject','Apresentação · ON Solutions Brasil',
            'body','Olá, {{primeiro_nome}}.\n\nMeu nome é {{remetente_nome}} e trabalho na ON Solutions Brasil. Escrevo para me apresentar, sem nada para pedir hoje.\n\nOrganizamos e automatizamos processos comerciais e operacionais. Na prática, desenhamos o fluxo de trabalho de ponta a ponta, do primeiro contato com o cliente até o pós-venda, reunimos em um lugar só a informação que costuma circular entre planilhas, e-mails e aplicativos de mensagem, e automatizamos as etapas repetitivas que consomem o tempo da equipe. Os projetos duram de seis a doze semanas e são conduzidos junto com o time da casa, que assume a operação no final.\n\nAtendemos empresas de médio porte em fase de crescimento, principalmente em serviços, indústria e distribuição.\n\nEscrevo para a {{empresa}} porque ela tem o perfil das empresas com quem trabalhamos. Não sei se o tema está na pauta de vocês agora, e não tem problema se não estiver. Prefiro que você saiba que existimos antes de precisar.\n\nNas próximas semanas vou te mandar o que aprendemos nesses projetos: o que funciona, o que costuma dar errado e como medir se vale a pena mexer nisso. É conteúdo que serve mesmo que a gente nunca trabalhe junto. Se preferir não receber, responda com uma palavra e eu paro na hora.\n\nUm abraço,\n{{remetente_nome}}\nON Solutions Brasil',
            'ab_enabled',1,
            'subject_b','Uma apresentação, {{primeiro_nome}}',
            'body_b','Olá, {{primeiro_nome}}.\n\nMeu nome é {{remetente_nome}}, da ON Solutions Brasil. Organizamos e automatizamos processos comerciais e operacionais em empresas de médio porte, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.\n\nEscrevo porque a {{empresa}} tem o perfil dos nossos clientes, e prefiro me apresentar antes de o tema virar urgência aí.\n\nNas próximas semanas te mando o que aprendemos nesses projetos, incluindo o que costuma dar errado. Se preferir não receber, uma palavra basta.\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'subject','O que decide o resultado antes de qualquer sistema',
            'body','Olá, {{primeiro_nome}}.\n\nComo combinei, começo pela ideia que mais muda o resultado desse tipo de projeto.\n\nO ganho maior aparece antes da tecnologia entrar. Quando o fluxo está desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata, porque qualquer sistema decente executa bem um processo bem definido. Quando o desenho não existe, a ferramenta vira mais um lugar onde a informação se divide, e a empresa passa a ter o problema antigo com uma licença mensal.\n\nÉ por isso que começamos todo projeto pelo desenho e só depois pelo sistema. Costuma ser também o que faz o prazo cair pela metade, já que o tempo de implantação é quase todo tempo de decisão, e não de configuração.\n\nSe quiser ver como esse desenho fica no papel, tenho um documento curto com o passo a passo que usamos. Respondo com ele no mesmo dia.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','O desenho vem antes da ferramenta',
            'body_b','Olá, {{primeiro_nome}}.\n\nA ideia que mais muda o resultado nesses projetos: o ganho aparece antes da tecnologia entrar.\n\nCom o fluxo bem desenhado, quem faz, em que ordem e com qual registro, qualquer sistema decente dá conta. Sem esse desenho, a ferramenta vira mais um lugar onde a informação se divide.\n\nTenho um documento curto com o passo a passo desse desenho. Se quiser, te envio hoje.\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','e3','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e3','type','send','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'subject','O caso que eu mais gosto de contar',
            'body','Olá, {{primeiro_nome}}.\n\nFalei da ideia. Agora, como ela funciona na prática.\n\nUma empresa de porte parecido com o da {{empresa}} nos procurou querendo trocar de sistema. Equipe boa, atendimento com boa reputação, e ainda assim a mesma informação era digitada três vezes por pessoas diferentes.\n\nQuando sentamos com o time, ficou claro que o sistema não era o problema. O processo tinha crescido em camadas ao longo dos anos, e cada camada resolvia bem um problema da sua época. Ninguém tinha errado nada. Só nunca houve um momento para parar e olhar o conjunto.\n\nPassamos as primeiras semanas desenhando o fluxo com as pessoas que o executam todo dia. Depois reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento do que ficava parado. O sistema continuou o mesmo, e a equipe também.\n\nO que mais me marcou foi o comentário da gerente de atendimento no fim do projeto: era a primeira vez que ela conseguia responder um cliente sem precisar perguntar nada para ninguém.\n\nSe quiser, te mando o resumo do projeto, com etapas, prazos e o que ficou fora do escopo.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','Trocaram de processo, não de sistema',
            'body_b','Olá, {{primeiro_nome}}.\n\nUm cliente de porte parecido com o da {{empresa}} nos chamou para trocar de sistema. O sistema não era o problema: o processo tinha crescido em camadas ao longo dos anos, cada uma resolvendo bem um problema da sua época.\n\nDesenhamos o fluxo com quem executa, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento. Mesmo sistema, mesma equipe, resultado em poucas semanas.\n\nTe mando o resumo do projeto, com etapas, prazos e o que ficou fora do escopo?\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','e4','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e4','type','send','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'subject','O que costuma dar errado nesses projetos',
            'body','Olá, {{primeiro_nome}}.\n\nJá falei do que funciona. Acho justo falar do que não funciona, inclusive em projetos nossos.\n\nTrês coisas explicam quase toda frustração nessa área.\n\nA primeira é automatizar um processo ruim. A automação acelera o que existe, então um processo mal desenhado só passa a errar mais rápido e com mais confiança.\n\nA segunda é conduzir o projeto sem quem executa. Quando o desenho sai só da diretoria, o time volta para a planilha antiga na terceira semana e ninguém fica sabendo até o trimestre fechar.\n\nA terceira é escopo grande demais na largada. Já entramos em projeto querendo resolver a empresa inteira de uma vez, e aprendemos do jeito caro que é melhor entregar uma área funcionando bem e crescer a partir dela.\n\nEscrevo isso porque, se em algum momento vocês forem contratar alguém para esse tipo de trabalho, sendo nós ou não, essas três perguntas valem para qualquer fornecedor sentado na sua frente.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','Três erros que arruínam esse tipo de projeto',
            'body_b','Olá, {{primeiro_nome}}.\n\nJá falei do que funciona, então falo do que não funciona, inclusive em projetos nossos.\n\nAutomatizar processo ruim faz a empresa errar mais rápido. Projeto conduzido sem quem executa termina com o time de volta à planilha antiga. Escopo grande demais na largada trava tudo, e esse foi um erro nosso até aprendermos a entregar uma área por vez.\n\nValem como perguntas para qualquer fornecedor que vocês avaliarem, inclusive para mim.\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','e5','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e5','type','send','x',360,'y',980,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'subject','Como calcular o custo do processo atual',
            'body','Olá, {{primeiro_nome}}.\n\nA pergunta que aparece em toda conversa sobre esse tema é quanto o processo de hoje custa. Dá para responder internamente, sem consultoria, e acho melhor você ter esse número antes de falar com qualquer fornecedor, porque é ele que define se o projeto se paga.\n\nTrês medidas costumam bastar. Quanto tempo por semana a equipe gasta digitando e conferindo informação que já existe em outro lugar. Quanto tempo passa entre o pedido do cliente e a primeira resposta. Que proporção das oportunidades abertas está sem próximo passo definido.\n\nCom esses três números o custo aparece com clareza, e serve tanto para justificar um projeto quanto para descartar um. Já vi as duas conclusões acontecerem.\n\nTenho a planilha que usamos para essa conta, com as fórmulas prontas. Ela é sua, com ou sem projeto no meio. Se quiser, respondo com ela hoje.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','A conta que dá para fazer sem consultoria',
            'body_b','Olá, {{primeiro_nome}}.\n\nAntes de falar com qualquer fornecedor, vale ter o custo do processo atual na mão. São três medidas: tempo semanal gasto digitando e conferindo informação que já existe, tempo entre o pedido do cliente e a primeira resposta, e proporção de oportunidades sem próximo passo definido.\n\nTenho a planilha com as fórmulas prontas. Ela é sua, com ou sem projeto no meio. Te envio?\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1110,'next','e6','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e6','type','send','x',360,'y',1220,'next','w6','nextReply','triage','data', JSON_OBJECT(
            'subject','Vinte minutos, se fizer sentido',
            'body','Olá, {{primeiro_nome}}.\n\nMandei nas últimas semanas o que temos de mais útil sobre o assunto, então chego ao único pedido desta sequência.\n\nSe fizer sentido para você, reservo vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma e o que cada uma decidiu não fazer. Você sai com referência de mercado na mão, trabalhando conosco ou não.\n\nE se você for do tipo que prefere não marcar reunião, também respondo por escrito qualquer dúvida específica. Costuma ser mais prático para os dois lados.\n\nMe diga dois horários que funcionem, ou simplesmente o que você gostaria de perguntar.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','Vinte minutos ou uma pergunta por escrito',
            'body_b','Olá, {{primeiro_nome}}.\n\nChego ao único pedido desta sequência. Reservo vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma.\n\nSe preferir não marcar reunião, respondo por escrito qualquer dúvida específica. Me diga dois horários ou a sua pergunta.\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w6','type','wait','x',360,'y',1350,'next','e7','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e7','type','send','x',360,'y',1460,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'subject','Fico por aqui, {{primeiro_nome}}',
            'body','Olá, {{primeiro_nome}}.\n\nCheguei ao fim do que tinha para compartilhar, então paro de escrever.\n\nO que continua valendo: o documento do desenho de fluxo, a planilha de cálculo de custo e o resumo do projeto seguem disponíveis. Basta responder esta mensagem, hoje ou daqui a um ano, e eu envio no mesmo dia.\n\nSe o tema for responsabilidade de outra pessoa dentro da {{empresa}}, agradeço se puder me indicar o nome.\n\nE se em algum momento vocês tocarem esse assunto com equipe própria, fico à disposição para uma opinião pontual, sem projeto no meio. Obrigado pela atenção nessas semanas.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','Obrigado pela atenção, {{primeiro_nome}}',
            'body_b','Olá, {{primeiro_nome}}.\n\nCheguei ao fim do que tinha para compartilhar e paro por aqui.\n\nO documento, a planilha de custo e o resumo do projeto continuam disponíveis. É só responder esta mensagem, hoje ou daqui a um ano.\n\nSe o tema for de outra pessoa na {{empresa}}, agradeço a indicação do nome. Obrigado pela atenção.\n\nUm abraço,\n{{remetente_nome}}')),

        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1590,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1700,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1810,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1920,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 1 (Só E-mail)';

-- ---------------------------------------------------------------------
-- FLUXO 2 (E-mail + WhatsApp) — 6 toques
--   e1 (apresentação) -> e2 (ideia) -> rev -> wa1 (apresentação no canal)
--   -> wa2 (o que dá errado) -> e3 (caso) -> wa3 (convite)
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'e1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','e1','type','send','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'subject','Apresentação · ON Solutions Brasil',
            'body','Olá, {{primeiro_nome}}.\n\nMeu nome é {{remetente_nome}} e trabalho na ON Solutions Brasil. Escrevo para me apresentar, sem nada para pedir hoje.\n\nOrganizamos e automatizamos processos comerciais e operacionais. Na prática, desenhamos o fluxo de trabalho de ponta a ponta, do primeiro contato com o cliente até o pós-venda, reunimos em um lugar só a informação que costuma circular entre planilhas, e-mails e aplicativos de mensagem, e automatizamos as etapas repetitivas que consomem o tempo da equipe. Os projetos duram de seis a doze semanas e são conduzidos junto com o time da casa, que assume a operação no final.\n\nAtendemos empresas de médio porte em fase de crescimento, principalmente em serviços, indústria e distribuição.\n\nEscrevo para a {{empresa}} porque ela tem o perfil das empresas com quem trabalhamos. Não sei se o tema está na pauta de vocês agora, e não tem problema se não estiver. Prefiro que você saiba que existimos antes de precisar.\n\nNas próximas semanas vou te mandar o que aprendemos nesses projetos: o que funciona, o que costuma dar errado e como medir se vale a pena mexer nisso. É conteúdo que serve mesmo que a gente nunca trabalhe junto. Se preferir não receber, responda com uma palavra e eu paro na hora.\n\nUm abraço,\n{{remetente_nome}}\nON Solutions Brasil',
            'ab_enabled',1,
            'subject_b','Uma apresentação, {{primeiro_nome}}',
            'body_b','Olá, {{primeiro_nome}}.\n\nMeu nome é {{remetente_nome}}, da ON Solutions Brasil. Organizamos e automatizamos processos comerciais e operacionais em empresas de médio porte, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.\n\nEscrevo porque a {{empresa}} tem o perfil dos nossos clientes, e prefiro me apresentar antes de o tema virar urgência aí.\n\nNas próximas semanas te mando o que aprendemos nesses projetos, incluindo o que costuma dar errado. Se preferir não receber, uma palavra basta.\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','e2','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e2','type','send','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'subject','O que decide o resultado antes de qualquer sistema',
            'body','Olá, {{primeiro_nome}}.\n\nComo combinei, começo pela ideia que mais muda o resultado desse tipo de projeto.\n\nO ganho maior aparece antes da tecnologia entrar. Com o fluxo desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata. Sem esse desenho, a ferramenta vira mais um lugar onde a informação se divide, e a empresa passa a ter o problema antigo com uma licença mensal.\n\nÉ por isso que começamos todo projeto pelo desenho e só depois pelo sistema. Costuma ser também o que faz o prazo cair pela metade.\n\nSe quiser ver como esse desenho fica no papel, tenho um documento curto com o passo a passo. Respondo com ele no mesmo dia.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','O desenho vem antes da ferramenta',
            'body_b','Olá, {{primeiro_nome}}.\n\nA ideia que mais muda o resultado nesses projetos: o ganho aparece antes da tecnologia entrar. Com o fluxo bem desenhado, qualquer sistema decente dá conta. Sem ele, a ferramenta vira mais um lugar onde a informação se divide.\n\nTenho um documento curto com o passo a passo desse desenho. Te envio?\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','rev','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','rev','type','reveal_phone','x',360,'y',500,'next','wa1','data', JSON_OBJECT('reveal_phone',1,'reveal_email',0)),
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',610,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'body','Olá, {{primeiro_nome}}, tudo bem? Aqui é {{remetente_nome}}, da ON Solutions Brasil.\n\nTe escrevi por e-mail nos últimos dias me apresentando. Nosso trabalho é organizar e automatizar processos comerciais e operacionais, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.\n\nEscrevi para a {{empresa}} porque ela tem o perfil dos nossos clientes. Se for mais prático por aqui, te envio o documento que citei no e-mail, com o passo a passo do desenho de fluxo que usamos.',
            'ab_enabled',1,
            'body_b','Olá, {{primeiro_nome}}, tudo bem? Aqui é {{remetente_nome}}, da ON Solutions Brasil.\n\nTe escrevi por e-mail me apresentando. Organizamos e automatizamos processos comerciais e operacionais em empresas de médio porte.\n\nSe for mais prático por aqui, te mando o documento que citei no e-mail.')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',740,'next','wa2','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',850,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, mando aqui o que costuma dar errado nesses projetos, inclusive em projetos nossos. É a parte que quase ninguém conta.\n\nAutomatizar um processo ruim faz a empresa errar mais rápido. Projeto conduzido sem quem executa termina com o time de volta à planilha antiga na terceira semana. E escopo grande demais na largada trava tudo, erro que cometemos até aprender a entregar uma área por vez.\n\nValem como perguntas para qualquer fornecedor que vocês avaliarem, inclusive para mim.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, os três erros que arruínam esse tipo de projeto, inclusive projetos nossos: automatizar processo ruim, conduzir sem quem executa e começar com escopo grande demais.\n\nValem como perguntas para qualquer fornecedor que vocês avaliarem, inclusive para mim.')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',980,'next','e3','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','e3','type','send','x',360,'y',1090,'next','w5','nextReply','triage','data', JSON_OBJECT(
            'subject','O caso que eu mais gosto de contar',
            'body','Olá, {{primeiro_nome}}.\n\nUm exemplo concreto do que comentei.\n\nUma empresa de porte parecido com o da {{empresa}} nos procurou querendo trocar de sistema. Equipe boa, atendimento com boa reputação, e ainda assim a mesma informação era digitada três vezes por pessoas diferentes.\n\nO sistema não era o problema. O processo tinha crescido em camadas ao longo dos anos, e cada camada resolvia bem um problema da sua época. Ninguém tinha errado nada. Só nunca houve um momento para parar e olhar o conjunto.\n\nDesenhamos o fluxo com as pessoas que o executam todo dia, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento do que ficava parado. Mesmo sistema, mesma equipe, resultado em poucas semanas.\n\nSe quiser, te mando o resumo do projeto, com etapas, prazos e o que ficou fora do escopo.\n\nUm abraço,\n{{remetente_nome}}',
            'ab_enabled',1,
            'subject_b','Trocaram de processo, não de sistema',
            'body_b','Olá, {{primeiro_nome}}.\n\nUm cliente de porte parecido com o da {{empresa}} nos chamou para trocar de sistema. O sistema não era o problema: o processo tinha crescido em camadas ao longo dos anos.\n\nDesenhamos o fluxo com quem executa, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento. Mesmo sistema, mesma equipe, resultado em poucas semanas.\n\nTe mando o resumo do projeto, com etapas, prazos e o que ficou fora do escopo?\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','w5','type','wait','x',360,'y',1220,'next','wa3','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',1330,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, chego ao único pedido que tenho.\n\nSe fizer sentido para você, reservo vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma e o que cada uma decidiu não fazer. Você sai com referência de mercado, trabalhando conosco ou não.\n\nE se preferir não marcar reunião, respondo por escrito qualquer dúvida específica. Também tenho a planilha de cálculo de custo, se for útil.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, o único pedido que tenho: vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma.\n\nSe preferir não marcar reunião, respondo sua dúvida por escrito. Fico à disposição das duas formas.')),

        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1460,'next','nr_sc','data', JSON_OBJECT('label','prospeccao apollo - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1570,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1680,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1790,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 2 (E-mail + WhatsApp)';

-- ---------------------------------------------------------------------
-- FLUXO 3 (Só WhatsApp) — 5 toques
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wa1',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wa1','type','whatsapp','x',360,'y',20,'next','w1','nextReply','triage','data', JSON_OBJECT(
            'body','Olá, {{primeiro_nome}}, tudo bem? Aqui é {{remetente_nome}}, da ON Solutions Brasil, e escrevo para me apresentar.\n\nOrganizamos e automatizamos processos comerciais e operacionais em empresas de médio porte de serviços, indústria e distribuição. Desenhamos o fluxo de trabalho de ponta a ponta, reunimos a informação em um lugar só e automatizamos as etapas repetitivas, em projetos de seis a doze semanas conduzidos junto com a equipe da casa.\n\nEscrevo para a {{empresa}} porque ela tem o perfil dos nossos clientes. Não sei se o tema está na pauta de vocês agora, e não tem problema se não estiver.\n\nNos próximos dias te mando o que aprendemos nesses projetos, incluindo o que costuma dar errado. Se preferir não receber, uma palavra basta e eu paro na hora.',
            'ab_enabled',1,
            'body_b','Olá, {{primeiro_nome}}, tudo bem? Aqui é {{remetente_nome}}, da ON Solutions Brasil.\n\nOrganizamos e automatizamos processos comerciais e operacionais em empresas de médio porte, em projetos curtos conduzidos junto com a equipe da casa.\n\nEscrevo para a {{empresa}} porque ela tem o perfil dos nossos clientes. Nos próximos dias te mando o que aprendemos nesses projetos. Se preferir não receber, uma palavra basta.')),
        JSON_OBJECT('id','w1','type','wait','x',360,'y',150,'next','wa2','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa2','type','whatsapp','x',360,'y',260,'next','w2','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, começo pela ideia que mais muda o resultado desse tipo de projeto.\n\nO ganho maior aparece antes da tecnologia entrar. Com o fluxo desenhado com clareza, quem faz, em que ordem e com qual registro, a escolha da ferramenta vira uma decisão simples e barata. Sem esse desenho, a ferramenta vira mais um lugar onde a informação se divide.\n\nÉ por isso que começamos sempre pelo desenho e só depois pelo sistema.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, a ideia que mais muda o resultado nesses projetos: o ganho aparece antes da tecnologia entrar.\n\nCom o fluxo bem desenhado, qualquer sistema decente dá conta. Sem ele, a ferramenta vira mais um lugar onde a informação se divide.')),
        JSON_OBJECT('id','w2','type','wait','x',360,'y',390,'next','wa3','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa3','type','whatsapp','x',360,'y',500,'next','w3','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, como isso funciona na prática.\n\nUma empresa de porte parecido com o da {{empresa}} nos chamou querendo trocar de sistema. Equipe boa, atendimento com boa reputação, e a mesma informação sendo digitada três vezes por pessoas diferentes.\n\nO sistema não era o problema. O processo tinha crescido em camadas ao longo dos anos, e cada camada resolvia bem um problema da sua época. Desenhamos o fluxo com quem executa, reunimos o histórico do cliente em um lugar só e automatizamos o acompanhamento. Mesmo sistema, mesma equipe, resultado em poucas semanas.\n\nPosso te mandar o resumo do projeto, com etapas, prazos e o que ficou fora do escopo.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, um exemplo concreto.\n\nUm cliente de porte parecido com o da {{empresa}} nos chamou para trocar de sistema, mas o sistema não era o problema. Desenhamos o fluxo com quem executa, reunimos a informação em um lugar só e automatizamos o acompanhamento. Mesmo sistema, mesma equipe, resultado em poucas semanas.\n\nTe mando o resumo do projeto?')),
        JSON_OBJECT('id','w3','type','wait','x',360,'y',630,'next','wa4','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa4','type','whatsapp','x',360,'y',740,'next','w4','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, chego ao único pedido que tenho.\n\nSe fizer sentido para você, reservo vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma e o que cada uma decidiu não fazer. Você sai com referência de mercado, trabalhando conosco ou não.\n\nE se preferir não marcar reunião, respondo por escrito qualquer dúvida específica.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, o único pedido que tenho: vinte minutos para mostrar como três empresas parecidas com a {{empresa}} organizaram esse fluxo, com os números de cada uma.\n\nSe preferir não marcar reunião, respondo sua dúvida por escrito.')),
        JSON_OBJECT('id','w4','type','wait','x',360,'y',870,'next','wa5','data', JSON_OBJECT('amount',2,'unit','minutes')),

        JSON_OBJECT('id','wa5','type','whatsapp','x',360,'y',980,'next','nr_tag','nextReply','triage','data', JSON_OBJECT(
            'body','{{primeiro_nome}}, cheguei ao fim do que tinha para compartilhar, então paro por aqui.\n\nO documento do desenho de fluxo, a planilha de cálculo de custo e o resumo do projeto continuam disponíveis. É só me chamar nesta conversa, hoje ou daqui a um bom tempo, e eu envio no mesmo dia.\n\nObrigado pela atenção nessas semanas.',
            'ab_enabled',1,
            'body_b','{{primeiro_nome}}, cheguei ao fim do que tinha para compartilhar e paro por aqui.\n\nO documento, a planilha de custo e o resumo do projeto continuam disponíveis. É só me chamar quando quiser. Obrigado pela atenção.')),

        JSON_OBJECT('id','nr_tag','type','tag','x',360,'y',1110,'next','nr_sc','data', JSON_OBJECT('label','zap - sem resposta','color','#f5a623')),
        JSON_OBJECT('id','nr_sc','type','score','x',360,'y',1220,'next','nr_move','data', JSON_OBJECT('delta',-10)),
        JSON_OBJECT('id','nr_move','type','move','x',360,'y',1330,'next','done','data', JSON_OBJECT('column_name','Sem resposta')),
        JSON_OBJECT('id','triage','type','connect','x',760,'y',260,'data', JSON_OBJECT('sequence_id', @triage, 'stop_current', 1)),
        JSON_OBJECT('id','done','type','end','x',360,'y',1440,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Fluxo 3 (Só WhatsApp)';

-- ---------------------------------------------------------------------
-- TRIAGEM IA (pós-resposta) — mensagens v7 (mantém estrutura da 104)
-- wresp em 2 min (TESTE). pos_reply usa IA para responder a dúvida + convite.
-- ---------------------------------------------------------------------
UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',2,'unit','minutes')),
        JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),

        JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_move','nextNo','neg_reply','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt', CONCAT(
                'Você classifica o INTERESSE do lead {{primeiro_nome}} (empresa {{empresa}}) a partir de TODAS as últimas mensagens dele (podem vir picadas em várias linhas) e do histórico.\n',
                'decision=true (INTERESSE) para QUALQUER sinal de interesse ou curiosidade, incluindo: "sim", "tenho interesse", "quero", "como funciona", "quero saber mais", "tem material?", "quanto custa?", "me explica", pedir reunião/demonstração/proposta ou fazer perguntas sobre a solução.\n',
                'decision=false (SEM INTERESSE) SOMENTE com recusa EXPLÍCITA: "não tenho interesse", "não quero", "pode parar", "remover", "descadastrar", "não é o momento", "já tenho fornecedor".\n',
                'IMPORTANTE: perguntas do lead indicam interesse (decision=true), nunca recusa. Se houver qualquer sinal positivo entre as mensagens, decision=true. Só use false diante de recusa clara.'
            ))),

        JSON_OBJECT('id','pos_move','type','move','x',120,'y',420,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',540,'next','pos_reply','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','pos_reply','type','reply','x',120,'y',660,'next','sched','data', JSON_OBJECT(
            'subject','Combinado, {{primeiro_nome}}',
            'ai_reply', 1,
            'model','gpt-4o-mini',
            'company_info','A ON Solutions Brasil organiza e automatiza processos comerciais e operacionais (CRM, prospecção, atendimento e integrações). Projetos conduzidos junto com a equipe do cliente, normalmente de 6 a 12 semanas. Diferenciais: implantação prática, acompanhamento próximo e foco em resultado. Não informamos preço fechado por mensagem — o valor depende do escopo, e por isso o ideal é uma conversa rápida.',
            'body','Obrigado pelo retorno, fico contente que tenha feito sentido.\n\nPara a conversa render, me responda quando puder duas coisas: qual área hoje mais te incomoda em termos de processo e quem mais da {{empresa}} deveria estar junto. Com isso eu chego preparado e a gente não gasta os primeiros minutos montando contexto.')),
        JSON_OBJECT('id','sched','type','schedule','x',120,'y',780,'next','done','data', JSON_OBJECT(
            'channel','reply','duration',45,
            'title','Agende sua reunião com a ON Solutions Brasil',
            'message', CONCAT(
                'Vou deixar aqui a agenda para você escolher o melhor dia e horário:\n',
                '{{link_agendamento}}\n\n',
                'Assim que confirmar, envio o link da reunião (Google Meet) e um lembrete antes do horário.'
            ))),

        JSON_OBJECT('id','neg_reply','type','reply','x',600,'y',420,'next','neg_unsub','data', JSON_OBJECT(
            'subject','Entendido, {{primeiro_nome}}',
            'body','{{primeiro_nome}}, entendido, e obrigado por ter respondido. Sei que responder um contato como o meu dá trabalho, e agradeço por isso.\n\nTirei o seu contato da nossa lista de acompanhamento, então você não recebe mais mensagens minhas.\n\nO documento do desenho de fluxo e a planilha de cálculo de custo continuam à disposição. Se quiser recebê-los mesmo sem projeto no meio, é só responder que envio no mesmo dia. Sucesso à {{empresa}}!\n\nUm abraço,\n{{remetente_nome}}')),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',540,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',600,'y',660,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',780,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),

        JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Triagem IA (pós-resposta)';

-- ---------------------------------------------------------------------
-- Nome do remetente (assinatura pessoal) usado por {{remetente_nome}}.
-- Ajuste este valor para o nome real de quem assina a prospecção.
-- O motor resolve {{remetente_nome}} por: prospecting_sender_name > smtp_from_name.
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value)
SELECT 'prospecting_sender_name', 'Lucas Vacari'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'prospecting_sender_name');
