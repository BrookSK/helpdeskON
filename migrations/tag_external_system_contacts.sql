-- =============================================================================
-- Aplica a etiqueta "Sistema Externo" nos contatos de WhatsApp cujos telefones
-- vieram do outro sistema (lista de usuarios cadastrados).
--
-- Objetivo: quando esses numeros mandarem mensagem, o contato ja aparece
-- etiquetado, permitindo filtrar/ocultar na caixa do WhatsApp.
--
-- Estrategia de casamento: comparar pelos ULTIMOS 8 DIGITOS do telefone.
-- Isso ignora diferencas de codigo do pais (55) e do 9o digito, exatamente
-- como o proprio sistema faz no WhatsappContact::upsert().
--
-- IMPORTANTE: so etiqueta contatos que JA EXISTEM em whatsapp_contacts.
-- Contatos so sao criados quando ha troca de mensagem. Se um numero ainda
-- nao existir, rode este script novamente depois que ele conversar,
-- OU aplique a etiqueta automaticamente no recebimento (ver observacao no chat).
--
-- Seguro para rodar mais de uma vez (idempotente).
-- =============================================================================

-- 1) Cria a etiqueta caso ainda nao exista.
INSERT INTO whatsapp_labels (name, color)
SELECT 'Sistema Externo', '#6c757d'
WHERE NOT EXISTS (
    SELECT 1 FROM whatsapp_labels WHERE name = 'Sistema Externo'
);

-- 2) Vincula a etiqueta a todos os contatos cujo telefone (ultimos 8 digitos)
--    esteja na lista importada. INSERT IGNORE respeita o UNIQUE KEY
--    (contact_id, label_id) e evita duplicatas.
INSERT IGNORE INTO whatsapp_contact_labels (contact_id, label_id)
SELECT c.id, l.id
FROM whatsapp_contacts c
CROSS JOIN whatsapp_labels l
WHERE l.name = 'Sistema Externo'
  AND COALESCE(c.is_group, 0) = 0
  AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(c.phone,' ',''),'-',''),'+',''),'(','') , 8) IN (
        '49634337', -- Gabriela Kosso da Silva
        '89258350', -- Joao Franciel Neves
        '19510090', -- Jefferson Duarte do Nascimento
        '46201395', -- Jefferson Duarte do Nascimento (2)
        '94045047', -- Gleice Aline Bernardi
        '40025675', -- Rodrigo Bastos
        '88567829', -- Alex Brilhante Freitas
        '91253062', -- Lucas Narciso
        '91190528', -- Lucas Rodrigues Vacari / TESTE Usuario
        '34283365', -- Erico Rocha
        '64558622', -- Eduardo Andrade
        '94517800', -- Mayara Alves da Silva
        '81628213', -- Bianca Narciso
        '91429485', -- Lucas Campagna
        '98333837', -- Eduardo Carvalho
        '92404368', -- Daiana Menogini de Lima
        '46442548', -- Kaue Didone
        '99999999'  -- Cainam Ribeiro
  );

-- 3) (Opcional) Conferir o resultado: lista os contatos que ficaram etiquetados.
-- SELECT c.id, c.contact_name, c.phone
-- FROM whatsapp_contacts c
-- JOIN whatsapp_contact_labels cl ON cl.contact_id = c.id
-- JOIN whatsapp_labels l ON l.id = cl.label_id
-- WHERE l.name = 'Sistema Externo'
-- ORDER BY c.contact_name;
