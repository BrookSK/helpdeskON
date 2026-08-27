-- =====================================================================
-- SQL 2 (executar 1º) — TEMPLATES DE E-MAIL DA CAPTAÇÃO APOLLO
-- =====================================================================
-- Cria (idempotente) todos os templates de e-mail usados pela sequência de
-- prospecção. A idempotência é garantida pelo nome único de cada template.
-- Reexecutar não duplica.
--
-- Tabela real: message_templates (channel ENUM('email','whatsapp'), name, subject, body).
-- Variáveis reais suportadas por MessageTemplate::render:
--   {{nome}} {{primeiro_nome}} {{email}} {{telefone}} {{empresa}} {{cargo}}
--   {{cidade}} {{estado}} {{setor}} {{linkedin}}
-- =====================================================================

-- Primeiro contato — Variante A (Dor)
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'Apollo · 1º Contato A (Dor)', 'Uma dúvida sobre a {{empresa}}',
'<p>Olá {{primeiro_nome}}, tudo bem?</p>
<p>Vi que você atua na {{empresa}} e queria entender como vocês lidam hoje com os processos manuais que costumam travar a operação.</p>
<p>Temos trabalhado justamente na melhoria e automação desse tipo de processo.</p>
<p>Se fizer sentido, posso te mostrar rapidamente como estamos fazendo isso em outras operações.</p>
<p>Abraço,<br>Equipe ON Solutions Brasil</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'Apollo · 1º Contato A (Dor)');

-- Primeiro contato — Variante B (Resultado)
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'Apollo · 1º Contato B (Resultado)', '{{empresa}} + automação',
'<p>Olá {{primeiro_nome}}, tudo bem?</p>
<p>Tenho conversado com empresas que buscam reduzir processos manuais e melhorar o fluxo de informações entre operação, administrativo e gestão.</p>
<p>Vi a {{empresa}} e achei que poderia fazer sentido conversarmos.</p>
<p>Posso te explicar rapidamente a abordagem?</p>
<p>Abraço,<br>Equipe ON Solutions Brasil</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'Apollo · 1º Contato B (Resultado)');

-- Follow-up 1 (D+3)
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'Apollo · Follow-up 1', 'Retomando o contato — {{empresa}}',
'<p>Olá {{primeiro_nome}},</p>
<p>Retomando a minha mensagem anterior. Queria entender se a melhoria/automação desse tipo de processo é algo que vocês estão avaliando atualmente na {{empresa}}.</p>
<p>Se fizer sentido, podemos conversar rapidamente.</p>
<p>Abraço,<br>Equipe ON Solutions Brasil</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'Apollo · Follow-up 1');

-- Follow-up final
INSERT INTO message_templates (channel, name, subject, body)
SELECT 'email', 'Apollo · Follow-up Final', 'Encerrando meus contatos — {{empresa}}',
'<p>Olá {{primeiro_nome}},</p>
<p>Como não consegui falar com você, imagino que talvez este não seja o momento ou que eu possa estar procurando a pessoa errada.</p>
<p>Caso exista alguém na {{empresa}} responsável por esse tema, agradeço se puder me indicar.</p>
<p>Caso contrário, encerro meus contatos por aqui.</p>
<p>Abraço,<br>Equipe ON Solutions Brasil</p>'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'Apollo · Follow-up Final');
