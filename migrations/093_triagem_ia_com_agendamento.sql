-- =====================================================================
-- 093_triagem_ia_com_agendamento.sql  (complementar ao 087)
-- ---------------------------------------------------------------------
-- Corrige a sequência "ON Solu · Triagem IA (pós-resposta)" para, no ramo de
-- INTERESSE, enviar o link de AGENDAMENTO após a confirmação:
--   IA (sim) → e-mail confirmação → move "Qualificado" → tag "interessado"
--            → AGENDAMENTO (link com horários) → encerra
--   IA (não) → e-mail remoção → unsubscribe → move "Perdido" → tag → encerra
--
-- Requer os blocos 'ai', 'unsubscribe' e 'schedule' do SequenceEngine e a
-- tabela agenda_booking_links (090). Idempotente: regrava o grafo por nome.
-- =====================================================================

UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',1,'unit','days')),
        JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),

        JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_mail','nextNo','neg_mail','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (da empresa {{empresa}}) e o histórico de mensagens. decision=true SOMENTE se demonstrou INTERESSE (conversar, receber material, agendar reunião ou seguir). decision=false se desinteresse, pediu para parar, não é o momento ou já tem fornecedor. Em dúvida real, decision=false.')),

        -- INTERESSE: confirma → Qualificado → tag → AGENDAMENTO → encerra
        JSON_OBJECT('id','pos_mail','type','send','x',120,'y',440,'next','pos_move','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'))),
        JSON_OBJECT('id','pos_move','type','move','x',120,'y',560,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',680,'next','sched','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','sched','type','schedule','x',120,'y',800,'next','done','data', JSON_OBJECT(
            'channel','auto','duration',45,
            'title','Reunião com a ON Solutions Brasil',
            'message','{{primeiro_nome}}, que ótimo! Para avançarmos, escolha o melhor dia e horário para uma conversa rápida (online): {{link_agendamento}}')),

        -- SEM INTERESSE: confirma → unsubscribe → Perdido → tag → encerra
        JSON_OBJECT('id','neg_mail','type','send','x',600,'y',440,'next','neg_unsub','data', JSON_OBJECT(
            'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
            'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'))),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',560,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',600,'y',680,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',800,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),

        JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Triagem IA (pós-resposta)';
