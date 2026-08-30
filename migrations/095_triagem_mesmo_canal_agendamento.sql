-- =====================================================================
-- 095_triagem_mesmo_canal_agendamento.sql  (substitui o grafo do 093)
-- ---------------------------------------------------------------------
-- Ajusta a sequência "ON Solu · Triagem IA (pós-resposta)" para:
--   * Responder ao lead pelo MESMO canal em que ele respondeu (bloco 'reply').
--   * Enviar o link de AGENDAMENTO também pelo mesmo canal (schedule channel=reply).
--
-- Fluxo:
--   Aguarda resposta → IA (decisão de interesse)
--     INTERESSE  → Responder (positivo, mesmo canal) → move "Qualificado"
--                → tag "interessado" → AGENDAMENTO (mesmo canal) → encerra
--     SEM INTERESSE → Responder (remoção, mesmo canal) → unsubscribe
--                → move "Perdido" → tag "sem interesse" → encerra
--
-- Requer blocos 'ai','reply','unsubscribe','schedule'. Idempotente.
-- =====================================================================

UPDATE email_sequences SET graph = JSON_OBJECT(
    'start', 'wresp',
    'nodes', JSON_ARRAY(
        JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',1,'unit','days')),
        JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),

        JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_reply','nextNo','neg_reply','data', JSON_OBJECT(
            'mode','decision','model','gpt-4o-mini',
            'prompt','Analise a última resposta do lead {{primeiro_nome}} (da empresa {{empresa}}) e o histórico de mensagens. decision=true SOMENTE se demonstrou INTERESSE (conversar, receber material, agendar reunião ou seguir). decision=false se desinteresse, pediu para parar, não é o momento ou já tem fornecedor. Em dúvida real, decision=false.')),

        -- INTERESSE: responde no mesmo canal → Qualificado → tag → agenda (mesmo canal) → encerra
        JSON_OBJECT('id','pos_reply','type','reply','x',120,'y',440,'next','pos_move','data', JSON_OBJECT(
            'subject','Que bom, {{primeiro_nome}}! Vamos avançar',
            'body','{{primeiro_nome}}, que ótimo! Vou preparar as próximas informações e um especialista da ON Solutions entra em contato para entender melhor o cenário da {{empresa}} e propor os próximos passos.')),
        JSON_OBJECT('id','pos_move','type','move','x',120,'y',560,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
        JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',680,'next','sched','data', JSON_OBJECT('label','interessado','color','#28a745')),
        JSON_OBJECT('id','sched','type','schedule','x',120,'y',800,'next','done','data', JSON_OBJECT(
            'channel','reply','duration',45,
            'title','Reunião com a ON Solutions Brasil',
            'message','{{primeiro_nome}}, para avançarmos, escolha o melhor dia e horário para uma conversa rápida (online): {{link_agendamento}}')),

        -- SEM INTERESSE: responde no mesmo canal → unsubscribe → Perdido → tag → encerra
        JSON_OBJECT('id','neg_reply','type','reply','x',600,'y',440,'next','neg_unsub','data', JSON_OBJECT(
            'subject','Entendido, {{primeiro_nome}}',
            'body','{{primeiro_nome}}, entendido, obrigado pelo retorno. Removi seu contato da nossa lista de acompanhamento para não enviar mais mensagens. Se quiser retomar no futuro, é só responder. Sucesso à {{empresa}}!')),
        JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',560,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
        JSON_OBJECT('id','neg_move','type','move','x',600,'y',680,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
        JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',800,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),

        JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
    )
)
WHERE name = 'ON Solu · Triagem IA (pós-resposta)';
