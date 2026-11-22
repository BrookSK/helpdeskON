-- =====================================================================
-- CARD NOVO — QUATRI (company_id 2)
-- Contas a Receber / Pagar: pesquisa não acha o título (orçamento 34138)
-- Início 18/11/2026, fim 20/11/2026 (sexta). Sem predecessora: não mexe
-- no restante do cronograma.
-- Rodar de uma vez no phpMyAdmin (produção). Pode rodar de novo: se o card
-- já existir, não cria outro.
-- =====================================================================

SET @campagna := (SELECT id FROM users WHERE name LIKE 'Lucas Campagna%' ORDER BY id LIMIT 1);
SET @author   := COALESCE(@campagna, (SELECT id FROM users WHERE role='super_admin' AND is_active=1 ORDER BY id LIMIT 1));
SELECT @campagna AS campagna, @author AS autor;

INSERT INTO planning_cards (title, description, company_id, assigned_to, created_by, priority, status, position,
  start_date, end_date, due_date, client_start_date, client_end_date, predecessor_id, predecessor_lag_days)
SELECT
  'Contas a Receber / Pagar — Pesquisa não encontra o título (orçamento 34138): abrir em "Todas as datas"',
  CONCAT(
  '<p><strong>Resumo do problema</strong></p>',
  '<p>Em Financeiro &gt; Contas a Receber (e também no Contas a Pagar), quem pesquisa pelo nome do cliente ou pelo número do orçamento/contrato não encontra os títulos, a não ser que clique antes em "Todas as datas". Antes bastava digitar o nome ou o número que o sistema já trazia, independente da data.</p>',
  '<p><strong>Caso real (reunião de 17/11):</strong> cliente Marcos Vinícius Rezende, orçamento 34138 (cerca de R$ 115 mil), com os adicionais 37231 (R$ 0,01) e 36926. Na tela de Orçamentos o 34138 aparece. No Contas a Receber, pesquisando 34138, não aparece nada até selecionar "Todas as datas".</p>',
  '<p><br></p>',
  '<p><strong>Causa (conferida no código do BeERP, branch main)</strong></p>',
  '<p>• As telas listagem-contas-receber e listagem-contas-pagar abrem com semPeriodo = false, e listarTabela() coloca dataInicio = dataFim = hoje. Ou seja, a tela abre filtrando só os títulos que vencem hoje.</p>',
  '<p>• No commit 0eb0270d (10/11, mensagem errada: "feat(gestao-vista)...") a lupa e o Enter da pesquisa deixaram de ligar "Todas as datas" sozinhos (pesquisarNomeTodasDatas virou pesquisarPorNome) e passaram a respeitar o período. Esse ajuste foi pedido para quem quer ver um cliente dentro de um período. Somado ao padrão "hoje", a pesquisa ficou quase sempre vazia.</p>',
  '<p>• A pesquisa livre compara só com o campo nome do título (car_c_nome / cap_c_nome), que é gerado como "... - ORC: 34138 - R01". Por isso "34138" acha, mas "34138 R01" (o número do contrato como aparece na tela de contrato) não acha.</p>',
  '<p>• Na tela de Orçamentos a busca roda sobre todos os orçamentos, sem período. Por isso lá o 34138 aparece.</p>',
  '<p><br></p>',
  '<p><strong>Decisão do cliente (Vitor, 17/11)</strong></p>',
  '<p>• O padrão das telas passa a ser <strong>sempre "Todas as datas"</strong>. Quem quiser "Hoje", "Últimos 30 dias", um intervalo ou a Situação escolhe na hora.</p>',
  '<p>• O pessoal quase nunca pesquisa por data ("eles não sabem a data que foi"). Pesquisa pelo nome do cliente ou pelo número do contrato.</p>',
  '<p>• A regra de 10/11 continua: se a pessoa escolher um período e pesquisar, o resultado respeita o período. Não voltar ao comportamento antigo de ignorar a data.</p>',
  '<p><br></p>',
  '<p><strong>O que fazer (Contas a Receber e Contas a Pagar)</strong></p>',
  '<p>1. Padrão ao abrir: semPeriodo = true (CR ts por volta da linha 179, CP ts por volta da linha 187). O botão "Todas as datas" já abre marcado como ativo. listarTabela() não pode forçar a data de hoje quando semPeriodo for true.</p>',
  '<p>2. Filtros salvos: restaurarFiltros() continua devolvendo a escolha feita na mesma sessão (sessionStorage, chaves contasreceber / contaspagar). Sem filtro salvo, abre em "Todas as datas". Conferir também limparFiltros / botão Limpar: limpar volta para "Todas as datas", não para hoje.</p>',
  '<p>3. Botões "Hoje" e "Últimos 30 dias": mostrar destaque de ativo, como já faz o "Todas as datas", para o usuário saber qual período está aplicado.</p>',
  '<p>4. Textos: ajustar o tooltip "Pesquisar (respeita o período selecionado)" e a mensagem de lista vazia para sugerir clicar em "Todas as datas" quando houver período aplicado.</p>',
  '<p>5. Número do contrato: aceitar na pesquisa o formato do contrato ("34138 R01", "34138 A01", "34138-R01"). Normalizar no servidor para casar com "ORC: 34138 - R01" no nome do título (o sistema já usa uma regex de "ORC: número" em outros pontos para achar os títulos de um orçamento). Só o número ("34138") continua trazendo todas as revisões.</p>',
  '<p>6. Nome do cliente: conferir no banco se o nome do cliente faz parte do nome do título gerado pela procedure usp_gera_lancamentos (ela não está no repositório). Se não fizer, incluir na pesquisa livre o nome do cadastro do cliente (pessoa do título), feito no servidor, em OU com o nome do título.</p>',
  '<p>7. Desempenho: com "Todas as datas" a primeira carga traz todos os títulos. A listagem já é paginada no servidor (listagemPaginada, máximo 100 por página) e os cards de resumo são somados no SQL. Medir o tempo de abertura com a base da Quatri. Se ficar lento, revisar índice por vencimento/empresa antes de entregar.</p>',
  '<p>8. Bug do filtro de tela (citado pelo Narciso na reunião: "selecionando um e filtrando o outro"): nos filtros com autocomplete do popover (Cliente, Status, Categoria etc.) a tela mostra o texto do campo, mas a consulta usa filtro.valor, que só muda quando o usuário clica numa opção (onValorSelected). Se ele apaga e digita outro cliente sem clicar, a tela mostra um cliente e filtra pelo anterior. Apagar o texto também não limpa o filtro. O seletor E/Ou (onOperadorLogicoSelected) tem o mesmo problema. Corrigir: texto alterado sem escolher opção invalida o valor (ou resolve pela opção exata); campo vazio remove o filtro.</p>',
  '<p>9. Testes: manter beERP.API.Tests/ListagemContasFiltroNomePeriodoTests.cs (período + nome continua valendo). Adicionar teste da pesquisa pelo número do contrato e, se o passo 6 for feito, pelo nome do cliente.</p>',
  '<p><br></p>',
  '<p><strong>Critérios de aceite (como testar)</strong></p>',
  '<p>☐ Abrir o Contas a Receber numa aba nova: "Todas as datas" ativo e lista com títulos de várias datas.</p>',
  '<p>☐ Sem mexer na data, pesquisar 34138: aparecem os títulos do orçamento 34138 do Marcos Vinícius Rezende. Pesquisar 37231 e 36926: aparecem os dos adicionais.</p>',
  '<p>☐ Pesquisar "34138 R01" (formato do contrato): aparecem os mesmos títulos da revisão R01.</p>',
  '<p>☐ Pesquisar "Marcos Vinícius Rezende": aparecem os títulos dele.</p>',
  '<p>☐ Escolher "Últimos 30 dias" e pesquisar o mesmo cliente: aparecem só os títulos do período, e o botão "Últimos 30 dias" fica destacado.</p>',
  '<p>☐ Clicar em Limpar: volta para "Todas as datas".</p>',
  '<p>☐ No popover, escolher o cliente A, apagar e digitar o cliente B sem clicar na opção: a lista não pode continuar filtrando pelo cliente A. Apagar o campo remove o filtro.</p>',
  '<p>☐ Repetir tudo no Contas a Pagar.</p>',
  '<p>☐ Tempo de abertura da tela com "Todas as datas" aceitável na base da Quatri.</p>',
  '<p><br></p>',
  '<p><strong>Em aberto — confirmar com o cliente</strong></p>',
  '<p>• Na reunião só foi mostrado o Contas a Receber. Confirmar que o Contas a Pagar também deve abrir em "Todas as datas" (a ON entendeu que sim).</p>',
  '<p>• "Número do contrato": confirmar que é o número do orçamento com a revisão (ex.: 34138 R01), como aparece no contrato.</p>',
  '<p><br></p>',
  '<p><strong>Reunião de referência</strong></p>',
  '<p>• <a href="https://fathom.video/share/HrcRy_B-sh-xWY7qEY2ayyafratyVgjL" target="_blank">17/11 — orçamento 34138 não aparece no Contas a Receber</a> — trechos: "É o 34138, um centavozinho, né? Ele não aparece para mim" (por volta de 2:48), "agora tem que ficar selecionando todas as datas" (por volta de 5:20) e "Mas o padrão vai ser todas as datas, sempre" (por volta de 7:15).</p>',
  '<p><br></p>',
  '<p><strong>Atenção, desenvolvedor:</strong> assista ao trecho da reunião (de 1:20 a 7:30) antes de começar, para confirmar o pedido, e verifique se algum ponto já não foi resolvido em outra demanda.</p>'
  ),
  2, @campagna, @author, 'high', 'open', 0,
  '2026-11-18 09:00:00', '2026-11-20 18:00:00', '2026-11-20 18:00:00', '2026-11-18', '2026-11-20', NULL, 0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM planning_cards
  WHERE company_id = 2
    AND title = 'Contas a Receber / Pagar — Pesquisa não encontra o título (orçamento 34138): abrir em "Todas as datas"' COLLATE utf8mb4_unicode_ci
);

-- ---------------------------------------------------------------------
-- Conferência
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, pc.status, pc.priority, pc.start_date, pc.end_date, u.name AS atendente,
       CHAR_LENGTH(pc.description) AS tamanho_descricao
FROM planning_cards pc LEFT JOIN users u ON pc.assigned_to = u.id
WHERE pc.company_id = 2
  AND pc.title LIKE 'Contas a Receber / Pagar — Pesquisa não encontra%' COLLATE utf8mb4_unicode_ci;
