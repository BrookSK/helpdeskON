-- =====================================================================
-- 104_triagem_prompt_interesse_reforcado.sql
-- ---------------------------------------------------------------------
-- Reforça o PROMPT de decisão da Triagem IA (pós-resposta) para valorizar
-- sinais EXPLÍCITOS de interesse. Antes, respostas como "Sim, tenho interesse",
-- "Como funciona?", "Tem algum material?" podiam ser classificadas como NÃO.
--
-- Regra nova (prompt):
--   * decision=true quando há QUALQUER sinal de interesse/curiosidade:
--     "sim", "tenho interesse", "como funciona", "quero saber mais",
--     "tem material?", "quanto custa?", pedir reunião/demonstração/proposta.
--   * decision=false SOMENTE com recusa explícita: "não tenho interesse",
--     "pare", "remova", "não é o momento", "já tenho fornecedor".
--   * Perguntas do lead = interesse (true), nunca recusa.
--
-- Mantém a mesma estrutura da 103 (ai_reply no pos_reply). Migration nova.
-- Idempotente (UPDATE por nome).
-- =====================================================================

UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',1,'unit','days')),
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
