-- =====================================================================
-- SQL 3 (executar 2º) — TEMPLATE(S) DE WHATSAPP DA CAPTAÇÃO APOLLO
-- =====================================================================
-- Cria (idempotente) a mensagem de WhatsApp usada pela sequência de prospecção.
-- Idempotência garantida pelo nome único. Reexecutar não duplica.
-- Tabela real: message_templates (channel='whatsapp').
-- =====================================================================

INSERT INTO message_templates (channel, name, subject, body)
SELECT 'whatsapp', 'Apollo · WhatsApp 1º Contato', NULL,
'Olá {{primeiro_nome}}, tudo bem? Aqui é da ON Solutions Brasil.

Te enviei um e-mail recentemente porque vi a {{empresa}} e achei que poderia fazer sentido conversarmos sobre algumas possibilidades de melhoria e automação de processos.

Não quero tomar seu tempo por aqui. Se fizer sentido, posso te explicar rapidamente o motivo do contato.'
WHERE NOT EXISTS (SELECT 1 FROM message_templates WHERE name = 'Apollo · WhatsApp 1º Contato');
