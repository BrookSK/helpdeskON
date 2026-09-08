-- =====================================================================
-- 087_sequencia_triagem_ia.sql
-- ---------------------------------------------------------------------
-- Cria uma sequência de TRIAGEM por IA (ChatGPT) para pós-resposta do lead:
--
--   IA (decisão) classifica se o lead demonstrou INTERESSE ou NÃO:
--     * INTERESSE  → e-mail de confirmação positiva → move card para "Qualificado" → encerra
--     * SEM INTERESSE → e-mail de confirmação/agradecimento → REMOVE da lista
--                       (bloco unsubscribe) → move card para "Perdido" → encerra
--
-- Requer os blocos 'ai' e 'unsubscribe' do SequenceEngine/editor.
-- Usa a chave openai_api_key de Configurações. Idempotente por nome.
--
-- Observação: o e-mail de confirmação vem ANTES do bloco de remoção, porque o
-- unsubscribe bloqueia novos envios ao lead.
-- =====================================================================

-- ---------------------------------------------------------------------
-- (A) Templates de confirmação
-- ---------------------------------------------------------------------

-- Interesse confirmado
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · Triagem Interesse (Positivo)', 'Que bom, {{primeiro_nome}}! Vamos avançar',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Fico feliz com o seu retorno. Vou preparar as próximas informações e um de nossos especialistas entrará em contato para entender melhor o cenário da {{empresa}} e propor os próximos passos.</p>
<p>Desde já, obrigado pelo interesse.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Interesse (Positivo)');

-- Sem interesse — confirmação e remoção
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'ON Solu · Triagem Sem Interesse (Remoção)', 'Entendido, {{primeiro_nome}}',
'<p>Olá, {{primeiro_nome}}.</p>
<p>Obrigado pelo retorno. Entendo que não faz sentido no momento, e removi o seu contato da nossa lista de acompanhamento para não gerar mensagens desnecessárias.</p>
<p>Se em algum momento quiser retomar, é só responder este e-mail. Desejo sucesso à {{empresa}}.</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'ON Solu · Triagem Sem Interesse (Remoção)');

-- ---------------------------------------------------------------------
-- (B) Sequência de triagem por IA
-- ---------------------------------------------------------------------
INSERT INTO email_sequences (name, description, channel_type, graph, is_active, daily_limit, window_start, window_end, send_weekends)
SELECT
    'ON Solu · Triagem IA (pós-resposta)',
    'Após a resposta do lead, a IA (ChatGPT) classifica se há interesse. Com interesse: confirma e move para Qualificado. Sem interesse: confirma, remove da lista e move para Perdido.',
    'email',
    JSON_OBJECT(
        'start', 'wresp',
        'nodes', JSON_ARRAY(
            -- Aguarda até o lead responder (checa periodicamente). Se não responder,
            -- segue aguardando; o encerramento fica por conta das cadências principais.
            JSON_OBJECT('id','wresp','type','wait','x',360,'y',20,'next','cresp','data', JSON_OBJECT('amount',1,'unit','days')),
            JSON_OBJECT('id','cresp','type','condition','x',360,'y',140,'nextYes','ia','nextNo','wresp','data', JSON_OBJECT('kind','replied')),

            -- IA classifica o interesse do lead a partir da resposta + histórico
            JSON_OBJECT('id','ia','type','ai','x',360,'y',280,'nextYes','pos_mail','nextNo','neg_mail','data', JSON_OBJECT(
                'mode','decision',
                'model','gpt-4o-mini',
                'prompt', 'Analise a última resposta do lead {{primeiro_nome}} (da empresa {{empresa}}) e o histórico de mensagens. Responda decision=true SOMENTE se o lead demonstrou INTERESSE em conversar, receber material, agendar reunião ou seguir com a ON Solutions. Responda decision=false se ele demonstrou desinteresse, pediu para não receber mais mensagens, disse que não é o momento ou que já tem fornecedor. Em caso de dúvida real, prefira decision=false.'
            )),

            -- Ramo INTERESSE: confirma → move para Qualificado → encerra
            JSON_OBJECT('id','pos_mail','type','send','x',120,'y',440,'next','pos_move','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Interesse (Positivo)'))),
            JSON_OBJECT('id','pos_move','type','move','x',120,'y',560,'next','pos_tag','data', JSON_OBJECT('column_name','Qualificado')),
            JSON_OBJECT('id','pos_tag','type','tag','x',120,'y',680,'next','done','data', JSON_OBJECT('label','interessado','color','#28a745')),

            -- Ramo SEM INTERESSE: confirma (ANTES de remover) → remove da lista → move para Perdido → encerra
            JSON_OBJECT('id','neg_mail','type','send','x',600,'y',440,'next','neg_unsub','data', JSON_OBJECT(
                'template_id',(SELECT id FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
                'subject',(SELECT subject FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'),
                'body',(SELECT body FROM message_templates WHERE name='ON Solu · Triagem Sem Interesse (Remoção)'))),
            JSON_OBJECT('id','neg_unsub','type','unsubscribe','x',600,'y',560,'next','neg_move','data', JSON_OBJECT('reason','Sem interesse (classificado pela IA)')),
            -- Move para "Perdido" se a coluna existir no board; se não existir, o motor
            -- ignora sem erro (o lead já foi removido da lista e etiquetado). Ajuste o
            -- nome abaixo para uma coluna real do seu board, se desejar outro destino.
            JSON_OBJECT('id','neg_move','type','move','x',600,'y',680,'next','neg_tag','data', JSON_OBJECT('column_name','Perdido')),
            JSON_OBJECT('id','neg_tag','type','tag','x',600,'y',800,'next','done','data', JSON_OBJECT('label','sem interesse','color','#dc3545')),

            JSON_OBJECT('id','done','type','end','x',360,'y',940,'data', JSON_OBJECT())
        )
    ),
    1, 200, '08:30:00', '17:00:00', 0
WHERE NOT EXISTS (SELECT 1 FROM email_sequences WHERE name='ON Solu · Triagem IA (pós-resposta)');
