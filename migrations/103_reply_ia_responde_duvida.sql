-- =====================================================================
-- 103_reply_ia_responde_duvida.sql
-- ---------------------------------------------------------------------
-- Ativa a opção "Responder a dúvida do lead com IA" (ai_reply) no bloco
-- "Responder ao lead" (pos_reply) da sequência de Triagem IA (pós-resposta).
--
-- Assim, ao qualificar o lead com interesse, a resposta deixa de ser genérica:
-- a IA responde brevemente a dúvida/mensagem do lead (usando company_info +
-- histórico) e o motor acrescenta o convite para a reunião em seguida — tudo no
-- MESMO canal e (no e-mail) num único envio.
--
-- Reescreve o grafo (mesma estrutura da 099) apenas adicionando ai_reply/model/
-- company_info ao nó pos_reply. Não edita SQLs anteriores (migration nova).
-- Idempotente (UPDATE por nome).
-- =====================================================================

UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',1,'unit','days')),
        JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),

        JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_move','nextNo','neg_reply','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (da empresa {{empresa}}) e o histórico de mensagens. decision=true SOMENTE se demonstrou INTERESSE (conversar, receber material, agendar reunião ou seguir). decision=false se desinteresse, pediu para parar, não é o momento ou já tem fornecedor. Em dúvida real, decision=false.')),

        -- INTERESSE: Qualificado -> tag -> Responder (IA responde a dúvida + convite) -> Agendamento -> encerra
        JSON_OBJECT('id','pos_move','type','move','x',120,'y',420,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',540,'next','pos_reply','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','pos_reply','type','reply','x',120,'y',660,'next','sched','data', JSON_OBJECT(
            'subject','Que bom, {{primeiro_nome}}! Vamos avançar',
            'ai_reply', 1,
            'model','gpt-4o-mini',
            'company_info','A ON Solutions Brasil organiza e automatiza processos comerciais e operacionais (CRM, prospecção, atendimento e integrações). Projetos conduzidos junto com a equipe do cliente, normalmente de 6 a 12 semanas. Diferenciais: implantação prática, acompanhamento próximo e foco em resultado (mais organização, respostas mais rápidas e oportunidades sem cair no esquecimento). Não informamos preço fechado por mensagem — o valor depende do escopo, e por isso o ideal é uma conversa rápida.',
            'body','Para avançarmos, separei um espaço na agenda para uma conversa rápida (online, cerca de 45 minutos).')),
        JSON_OBJECT('id','sched','type','schedule','x',120,'y',780,'next','done','data', JSON_OBJECT(
            'channel','reply','duration',45,
            'title','Agende sua reunião com a ON Solutions Brasil',
            'message', CONCAT(
                '👉 Escolha o melhor dia e horário para você neste link:\n',
                '{{link_agendamento}}\n\n',
                'Assim que confirmar, envio o link da reunião (Google Meet) e um lembrete antes do horário.\n\n',
                'Fico no aguardo!'
            ))),

        -- SEM INTERESSE: Responder (mesmo canal) -> unsubscribe -> Perdido -> tag -> encerra
        JSON_OBJECT('id','neg_reply','type','reply','x',600,'y',420,'next','neg_unsub','data', JSON_OBJECT(
            'subject','Entendido, {{primeiro_nome}}',
            'body','{{primeiro_nome}}, entendido, e obrigado pelo retorno!\n\nRemovi o seu contato da nossa lista de acompanhamento para não enviar mais mensagens. Se em algum momento fizer sentido retomar, é só me chamar por aqui. Sucesso à {{empresa}}!')),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',540,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',600,'y',660,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',780,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),

        JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Triagem IA (pós-resposta)';
