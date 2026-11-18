-- =====================================================================
-- DETALHAMENTO DOS CARDS — QUATRI (company_id 2) — v1
-- Acrescenta ao FINAL da descrição de cada card o que foi dito nas
-- reuniões (transcrições do Fathom), com o link de cada reunião e um aviso
-- para o desenvolvedor assistir antes de fazer. NÃO apaga o texto atual.
-- As datas das reuniões vêm do título da gravação (ano 2026).
-- Rodar de uma vez no phpMyAdmin (produção). Em transação. Pode rodar de
-- novo: card que já tem o detalhamento não recebe outra cópia.
-- Não mexe: #350, #542, #547, #570 (nada novo nas reuniões enviadas).
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- #409 — 1.1 Página Clube
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>O que foi pedido (Vitor, reunião de 14/10):</strong></p>
<ul>
<li>Hoje a ação disponível na página <strong>cancela/exclui</strong> o item. Muitas vezes não se quer cancelar (ex.: o cliente ainda não pagou). "Se eu fechar aqui, ele vai excluir."</li>
<li>Poder <strong>selecionar quais itens fechar</strong> (multisseleção), em vez de fechar tudo.</li>
<li>Status do item: só <strong>"pontuado" / "não pontuado"</strong>. O Vitor pensou em "enviado para pagamento", mas descartou: se foi pago ou não "é problema lá do financeiro".</li>
<li>Campo de <strong>observação</strong> (descritivo) por item, para quem opera registrar o que foi feito.</li>
<li><strong>Anexo opcional</strong>: poder anexar um print (ex.: tela do clube comprovando a pontuação).</li>
</ul>
<p><strong>Contexto:</strong> o Vacari disse que isso já estava anotado no escopo desde a reunião anterior. O Vitor colocou Clube e Comissões como as primeiras prioridades. Em 13/11 a ordem ficou Página do Clube, Página de Comissões e Campos do João. Em 17/11 a ON comentou que a página "interage com a parte de financeiro".</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades com o Vitor</a> (trecho: "se eu fizer aqui, eu cancelo, e às vezes eu não quero cancelar")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização do cronograma</a> (trecho: "Página do clube, página de comissões, Campos do João")</li>
<li><a href="https://fathom.video/share/HrcRy_B-sh-xWY7qEY2ayyafratyVgjL" target="_blank">17/11 — repasse do cronograma (gravação 2)</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 409 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #516 — Mais filtros na tela de Orçamento
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Pedido original (03/11):</strong> faltam filtros na listagem de orçamentos. Exemplo: ver as vendas de um mês (dezembro). A ON avaliou como "ajustes um pouco mais simples".</p>
<p><strong>Problema encontrado em homologação (17/11):</strong></p>
<ul>
<li>Cliente do exemplo: <strong>Marcos Vinícius Rezende</strong>. O orçamento <strong>34138</strong> (cerca de R$ 115 mil) e seus adicionais (citados: 37231, de R$ 0,01, e 36926) não aparecem na listagem com os filtros novos, mas aparecem em outra busca. A transcrição dos números é confusa.</li>
<li>Antes bastava digitar o nome ou o número e o orçamento aparecia. Agora é preciso limpar e escolher "todas as datas".</li>
<li>Hipótese da ON: um filtro aplicado só no front, conflitando com outro. A Sil já tinha pedido um ajuste porque a busca por nome não respeitava o período de datas. Quando os dois filtros estão ativos, um precisa ter prioridade.</li>
<li>Uso real: o pessoal busca pelo <strong>nome do cliente</strong> ou pelo <strong>número do contrato</strong> e quase nunca por data ("eles não sabem a data que foi").</li>
</ul>
<p><strong>Decisão (17/11):</strong> o filtro de data vem por padrão em <strong>"todas as datas", sempre</strong>. Quem quiser 30 dias, hoje ou outra situação muda manualmente. Permitir buscar por nome ou número do contrato.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — Vitor: relatório gerencial, PDF da proposta e escopo</a> (trecho: "falta um pouco de filtro")</li>
<li><a href="https://fathom.video/share/HrcRy_B-sh-xWY7qEY2ayyafratyVgjL" target="_blank">17/11 — gravação 2</a> (trecho: "Mas o padrão vai ser todas as datas, sempre")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 516 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #520 — Ajuste da proposta (Vitor)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p>O card não tem descrição. Os ajustes de proposta que apareceram nas reuniões com o Vitor e o Caio foram estes:</p>
<p><strong>Mão de obra na proposta:</strong></p>
<ul>
<li>30/10 (Caio): a opção de mão de obra vem ativada e aparece na frente do cliente (ex.: "30 pau de mão de obra" num quadro do Alex negociado sem mão de obra). Pediu para vir <strong>sempre desativada</strong>. Quem destaca mão de obra é a Iluminação; a Automação nunca destaca.</li>
<li>03/11: a ON mostrou o que foi feito: para <strong>automação a mão de obra vem desativada</strong> e para <strong>iluminação vem ativada</strong>. Confirmar com o Caio se isso atende ao pedido de "sempre desativada".</li>
</ul>
<p><strong>Layout do PDF (03/11):</strong></p>
<ul>
<li>Com poucos produtos o conteúdo vem centralizado. Separa por local/ambiente quando há mais de um. Produto com foto usa o layout anterior. Com muitos produtos, duas colunas.</li>
<li>O layout usa imagem de fundo com os campos em posição fixa.</li>
<li>O Vitor não gostou, mas decidiu "deixar assim por enquanto". Pedidos: <strong>centralizar mais</strong> e <strong>aumentar a fonte</strong>. O marketing da Quatri vai preencher as laterais. A ON ia mandar ideias pelo WhatsApp para o Vitor e o marketing.</li>
<li>Uma V2 do layout fica para depois, com o marketing, e passa a ser <strong>horizontal</strong> (ver card #425).</li>
</ul>
<p><strong>Proposta de iluminação (14/10):</strong> o modelo "Proposta de iluminação 2026" precisa estar como <strong>padrão</strong>, porque o pessoal ainda usava a proposta antiga ("a preta"). O Vitor ia refazer uma proposta "do jeito certo" para avaliarem juntos. Isso combina com o status "esperando Vitor".</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/teaHXViUAQCmgG8vjyyeMHZsRd4sxjAB" target="_blank">30/10 — Caio: aditivo e mão de obra</a> (trecho: "Consegue deixar ele como sempre desativado?")</li>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — Vitor: PDF da proposta</a> (trecho: "quando é para a automação, a mão de obra vem desativada")</li>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — proposta de iluminação</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 520 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #410 — 1.2 Página Comissões
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>O que foi pedido (Vitor, 14/10):</strong> "do mesmo jeito que tem uma página pra Clube, eu queria que tivesse uma página pra comissão". Hoje existe um relatório-modelo do Caio feito fora do sistema, em que os consultores mandam o que fecharam. Isso expõe a venda de um consultor para os outros.</p>
<p><strong>Regras de comissão citadas:</strong></p>
<ul>
<li><strong>Um consultor não pode ver a venda de outro.</strong></li>
<li>Recebem comissão: <strong>consultor 1, consultor 2 e orçamentista</strong>.</li>
<li>Só entra cliente com <strong>pelo menos um pagamento efetivado</strong> (pode ser a entrada). Conceito "paga o pendente": a comissão só é paga se o cliente pagou.</li>
<li>Cálculo <strong>cliente a cliente</strong>, com componentes:
<ul>
<li><strong>Base 2,5%</strong> quando o consultor bateu a meta; <strong>2%</strong> para todos os clientes quando não bateu.</li>
<li><strong>Lucratividade</strong>: extra quando a venda tem margem alta (ex.: vendeu a 35%). Varia por cliente.</li>
<li><strong>Performance</strong>: +0,5% por estar acima da meta, aplicado a todos os clientes. Pode chegar a 1% vendendo acima de 600 mil (200% da meta).</li>
</ul></li>
<li>Em outra reunião (05/10) apareceu também: acima de 30% de margem o vendedor recebe bônus, e a comissão do consultor muda conforme a meta (ex.: 3% quando bateu).</li>
</ul>
<p><strong>Ligação com outros cards:</strong> em 17/11 ficou decidido que a <strong>Comissão Individualizada (#418)</strong> vai junto com esta página. A página "vai gerar toda a parte de comissões mediante todo o financeiro" e precisa considerar funcionários. Os Campos de Lucro do João (#411) não mudam o cálculo da comissão.</p>
<p><strong>Em aberto:</strong> o título fala em RT e funcionários, mas as reuniões enviadas não detalharam a comissão de RT.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "consultor 1, consultor 2 e orçamentista")</li>
<li><a href="https://fathom.video/share/_YyD__Egz5Bgsuy1KGJJkx5euY5ZrVkQ" target="_blank">05/10 — margem de lucro</a> (trecho: "acima de 30, o cara já recebe um percentual de bônus")</li>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — cronograma</a> e <a href="https://fathom.video/share/opPdhwn-MDXKx6exD9Mry-X6prEP9e78" target="_blank">17/11 — gravação 1</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 410 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #418 — 7 Comissão Individualizada de Vendedores
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>O que é (esclarecido em 17/11):</strong> é o <strong>controle de performance, produtividade e lucratividade por vendedor</strong> que uma pessoa da Quatri fez no <strong>Lovable</strong>. Hoje "todo mundo manda pra ele e ele faz pra galera". O Vitor vai encaminhar essa ferramenta como referência.</p>
<ul>
<li>Em 13/11 a ON descreveu: "é tudo pelos gerentes, são os dashboards. Tipo o painel gerencial que a gente fez, só que é por vendedor". Estimativa: 7 dias.</li>
<li>Tem que ser "bem painel" e criado com <strong>flexibilidade</strong> para mudar as métricas: lucratividade e produtividade podem ser medidas de um jeito na iluminação e de outro na automação, e de forma diferente por funcionário e para gerência e não gerência.</li>
<li>Precisa considerar <strong>funcionários</strong> na comissão.</li>
<li>Os Campos de Lucro do João (#411) "não vão mudar lucratividade, produtividade": são só outra forma de ver o orçamento.</li>
</ul>
<p><strong>Prioridade:</strong> antecipar para <strong>antes de janeiro</strong>, junto com a Página de Comissões (#410). Motivo: ajudar no fechamento ("não quero nem que faça o próximo na mão deles").</p>
<p><strong>Antes de começar:</strong> pedir ao Vitor a ferramenta do Lovable (ainda não foi recebida na data da reunião).</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/opPdhwn-MDXKx6exD9Mry-X6prEP9e78" target="_blank">17/11 — gravação 1</a> (trecho: "Tem que ser uma coisa que você já crie ele com uma certa flexibilidade")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "Tipo o painel gerencial que a gente fez, só que é por vendedor")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 418 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #411 — 1.3 Campos de Lucro (João)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Problema (05/10, com o João):</strong> no orçamento (condições negociadas), a margem de lucro "mente" quando entra o bancário. Exemplo usado, proposta de R$ 924.853,02:</p>
<ul>
<li>Sem desconto e sem bancário: margem 36,12%, lucro R$ 334.018,70.</li>
<li>Com 15% de desconto: venda R$ 786.125, margem 29,25%, lucro R$ 229.972.</li>
<li>Sem desconto, em 10x (bancário de 10%): margem mostrada 26,12%, lucro R$ 241.533. Valor líquido depois do bancário: R$ 832.368. 241.533 sobre 832.368 dá cerca de 29%.</li>
<li>Ou seja, em 10x sobra mais dinheiro que com 15% de desconto, mas aparece margem menor e fica vermelha. A margem mínima para vender é <strong>28%</strong> (também citado 27%); abaixo disso fica vermelho e o vendedor reduz o parcelamento. Acima de 30% o vendedor ganha bônus.</li>
</ul>
<p><strong>Decisão (05/10):</strong> não mexer no cálculo atual e <strong>acrescentar uma visão nova</strong>: lucro sobre o faturamento e <strong>lucro sobre o líquido</strong>.</p>
<ul>
<li>Ordem de cálculo: primeiro o <strong>desconto</strong>; o <strong>bancário</strong> é abatido sobre o valor já com desconto (ex.: R$ 1.000 com 10% vira R$ 900, e o bancário é aplicado sobre os 900).</li>
<li>Fórmula do campo novo: valor total com desconto, menos bancário, menos <strong>todas as despesas diretas</strong> (indicação, arquiteto, clube, comissão do consultor) = líquido. O <strong>custo do produto não entra</strong> nessa dedução. A porcentagem de lucro é calculada sobre esse líquido.</li>
<li>Os valores têm que se atualizar quando o vendedor muda os campos (ex.: comissão do consultor de 5% para 3% quando bateu a meta).</li>
<li>Cuidado: mesmo sendo visualização, isso "vai bater nos relatórios, nos orçamentos, nos contratos". Precisa de homologação bem feita.</li>
</ul>
<p><strong>Revisão da tela (14/10, com o Vitor e o João por telefone):</strong></p>
<ul>
<li>Trocar o label "Lucro sem líquido" por <strong>"Lucro sobre o líquido"</strong>.</li>
<li>Valor líquido = valor da venda (com desconto) menos o bancário. Exemplo: cerca de 129 mil de venda resultando em cerca de 116 mil líquidos, lucro de cerca de 31 mil, margem de 27% sobre o líquido.</li>
<li>Divergência de custo explicada: 84.339,97 + 12.906,72 = 97.246,69. O custo de baixo exclui o bancário porque ele já saiu antes do líquido.</li>
<li><strong>Em aberto:</strong> o campo "custo sobre líquido" (custo total menos taxas, dividido pelo líquido). Primeiro disseram que não serve e que sai; depois o Vitor disse "essa informação a gente nunca teve" e "a fórmula tá certa". Confirmar se fica ou sai.</li>
<li><strong>Em aberto:</strong> o líquido desconta <strong>só o bancário</strong> ou <strong>todas as despesas</strong>? O Narciso entendeu que, no fim, será para todas.</li>
<li>Em aberto: se o alerta vermelho de margem mínima passa a usar a margem nova.</li>
<li>O Narciso ficou de retrabalhar a tela (havia três versões feitas com IA a partir da conversa com o João).</li>
</ul>
<p><strong>Custo bancário do cartão no orçamento (28/10):</strong> o Vitor perguntou se, ao fazer um orçamento em 3x no cartão, o sistema mostra o custo efetivo correto. Resposta: ainda não; vai aparecer "naqueles campos que o João colocou". No financeiro o cartão já calcula taxas e acréscimos pelo cadastro de operadoras (Cadastros &gt; Financeiro &gt; Operadoras de cartão, com faixas por parcela, débito/crédito, maquininha/link e acréscimo). Em 22/10 a Silvia lembrou que, sem o cadastro das operadoras, quem monta o orçamento não consegue puxar esse custo.</p>
<p>Em 13/11: "não estava muito longe", já houve testes por fora, mas interage com o financeiro e por isso tem mais dias.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/_YyD__Egz5Bgsuy1KGJJkx5euY5ZrVkQ" target="_blank">05/10 — margem de lucro com o João</a> (trechos: por volta de 19:00 "toda vez que tiver bancário, a porcentagem tem que ser sobre o valor líquido"; por volta de 46:00 "O lucro sobre o faturamento e o lucro sobre o líquido")</li>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — revisão da tela</a> (trecho: "Aí é lucro, lucro sobre o líquido. Então, muda esse S")</li>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro</a> (trecho: "quando vai fazendo orçamento, eu coloco lá que é em três vezes")</li>
<li><a href="https://fathom.video/share/oGZX3RMyMsScFxTWB7LbeYefR-pKMfuz" target="_blank">22/10 — Silvia, operadoras de cartão</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 411 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #565 — Checklist de fechamento de vendas
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Pedido recente do financeiro (Jéssica). Em 13/11 ficou no alto do cronograma, avaliado como "relativamente tranquilo, porque todas as etapas anteriores já estão feitas".</li>
<li>Em 17/11 o Vitor mostrou na tela como funciona: o checklist mostra em que etapa está cada fechamento. Exemplo: um cliente que "vai mudar de boleto pra faturamento"; ao ver o checklist, "faturada, eu já sei como ela já fez o processo total". Outro exemplo: saber se a nota já foi emitida para aquele cliente. "Hoje fica muito perdido."</li>
<li>A explicação visual estava na tela compartilhada e não aparece na transcrição. Usar o fluxograma enviado pela Quatri (citado na descrição acima).</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/HrcRy_B-sh-xWY7qEY2ayyafratyVgjL" target="_blank">17/11 — gravação 2</a> (trecho: "Aí vai mudar de boleto pra faturamento")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "Esse checklist de fechamento")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 565 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #530 — Compras: lançamento financeiro e cartão
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Fluxo de compras da Quatri (Vitor, 17/11):</strong> (1) alguém <strong>solicita</strong> os produtos; (2) alguém <strong>cota</strong> produtos e valores; (3) alguém <strong>aprova</strong> a compra; (4) alguém faz o <strong>recebimento</strong> e o checklist de que chegou. "Talvez o agente seja o mesmo, mas a etapa é igual." Com o pagamento aprovado, <strong>Compras anexa a nota fiscal</strong>, e o material segue para estoque ou obra. A ON citou que a Vetrix tem um módulo de compras parecido. Ficou combinado um call para o Vitor apresentar o processo.</p>
<p><strong>Vínculo do Contas a Pagar com o pedido de compra (05/11):</strong></p>
<ul>
<li>Hoje o comprador faz o pedido e manda para aprovação do Vitor, e quem cadastra NF e boleto no Contas a Pagar é o financeiro. O financeiro não vê a que pedido ou cliente o pagamento se refere.</li>
<li>Caso real: o Vitor deixou de pagar uma NF de R$ 28 mil porque não reconhecia o valor. Ele paga NFs de 100 mil, 150 mil, com risco de erro de faturamento do fornecedor.</li>
<li><strong>Decisão:</strong> quem registra NF e boleto é <strong>Compras</strong>. Criar um <strong>campo próprio</strong> (não usar descrição/observação) que vincula o lançamento ao pedido, com <strong>link clicável</strong> para abrir o pedido de compra. No pedido o financeiro confere cliente, produtos e valor ("estão pagando mais caro do que tá lá?").</li>
<li>Lançamento vindo de Compras: preenche automático o <strong>número do pedido</strong> e o <strong>número da NF</strong>.</li>
<li>Lançamento manual do financeiro (papelaria, sulfite, PJs): campo de <strong>número de documento</strong> (NF, fatura) digitado. Ficou sugerido que seja obrigatório, mas não houve decisão.</li>
<li>Já existem: anexos no lançamento (sem busca por eles) e a descrição automática nos lançamentos de fechamento de fatura do cartão.</li>
</ul>
<p><strong>Compras no cartão de crédito (28/10, com a Jessyka):</strong></p>
<ul>
<li>Hoje o lançamento é feito direto no cartão corporativo, que gera o Contas a Pagar. O inverso não funciona: lançar no Contas a Pagar dizendo que foi no cartão não leva para a fatura.</li>
<li>Pedido: ao lançar, informar que foi compra no cartão e o item ir automaticamente para a fatura daquele cartão, vinculado ao cliente.</li>
<li>Processo desejado: "a pessoa de compras vai comprar, vai anexar a nota fiscal, vai anexar as datas de pagamento e lançar o Contas a Pagar. O financeiro só confere." Exemplo: compras no Mercado Livre; no fim do mês querem saber "que Mercado Livre é esse, foi do quê?".</li>
<li>Fluxo atual do cartão, para referência: Financeiro &gt; Controle de cartões &gt; Cartões corporativos &gt; Novo lançamento (cartão, descrição, valor, parcelas, data da 1ª parcela, operadora, tipo de operação, categoria, observação) &gt; consolidar fatura &gt; Faturas &gt; quitar fatura.</li>
</ul>
<p><strong>Prioridade (13/11 e 17/11):</strong> importante, mantido. O Vitor quer que o novo comprador da Quatri teste e passe todos os pontos de compras e expedição de uma vez; ele daria esse retorno até 15/01. Compras deve levar "15, 20 dias focado nele". O anexo no cartão corporativo (#578) vai junto com este card.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro com a Jessyka</a> (trecho: "ia descrever se era uma compra no cartão de crédito e automaticamente ele já ia jogando para essa fatura")</li>
<li><a href="https://fathom.video/share/gFAqLDjxDSxM1QfUbpAaNhGBvdN93myG" target="_blank">05/11 — compras e relatórios</a> (trechos: "Eu acho melhor criar um campo" e "Um link que você clicar vem é o pedido de compra")</li>
<li><a href="https://fathom.video/share/wDKcfC_4nLgL2_qD_VPAVJycGKyvs9Ym" target="_blank">17/11 — gravação 4</a> (trecho: "eu tenho uma pessoa que solicita os produtos, eu tenho uma pessoa que cota")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 530 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #578 — Cartão corporativo (anexo)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 28/10 a Jessyka perguntou se dava para incluir a <strong>nota fiscal ou o recibo</strong> do que foi comprado no lançamento do cartão. Resposta do Vacari: "aqui nessa tela não, mas no Contas a Pagar tem como". Hoje o anexo só existe no Contas a Pagar gerado pela fatura.</li>
<li>Tela: Financeiro &gt; Controle de cartões &gt; Cartões corporativos &gt; Novo lançamento. Os lançamentos viram parcelas, são consolidados em fatura e a fatura gera o Contas a Pagar. O anexo deve acompanhar o lançamento até a fatura e o Contas a Pagar.</li>
<li>Em 17/11: o card foi aberto pela Silvia no perfil da Jéssica. Decisão: fazer junto com o card de lançamento financeiro de Compras (#530), em paralelo.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro com a Jessyka</a> (trecho: "a gente consegue incluir nota fiscal do que foi comprado, o recibo, ou não?")</li>
<li><a href="https://fathom.video/share/HrcRy_B-sh-xWY7qEY2ayyafratyVgjL" target="_blank">17/11 — gravação 2</a> e <a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">gravação 3</a> (trecho: "é um que a Silvia abriu no perfil da Jéssica, pra colocar anexo lá na telinha")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 578 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #548 — Relatórios financeiros: valor original, juros, descrição
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li><strong>Juros e multa no lançamento:</strong> em 28/10 a ON mostrou que o registro de Contas a Pagar/Receber já tem onde informar valor pago, <strong>juros e multa</strong>. Isso responde ao "verificar se o lançamento já tem onde registrar os juros" da descrição.</li>
<li><strong>Referência do pedido no relatório (05/11):</strong> "Quando eu olho, só olho o produto. Tinha que ter algum referencial do pedido": mostrar "referente ao pedido tal do cliente tal", com um descritivo. Depende do campo de vínculo com o pedido de compra criado no #530.</li>
<li>Em 13/11: "no relatório aparecia a descrição", ou seja, mostrar a descrição da conta no relatório.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro</a> (trecho: "se teve algum valor pago, se teve algum juros, multa")</li>
<li><a href="https://fathom.video/share/gFAqLDjxDSxM1QfUbpAaNhGBvdN93myG" target="_blank">05/11 — compras e relatórios</a> (trecho: "no relatório tinha que ter alguma coisa")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 548 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #549 — Financeiro: Tela de Contratos
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 13/11 foi mantido e marcado como importante: trazer até o financeiro a lista de contratos, "porque eles já vão estar prontos".</li>
<li>Em 17/11 o Vitor comentou que a tela "é tão simples de fazer". A ON explicou que o prazo conta desenvolvimento, homologação e publicação.</li>
<li>Nenhum campo ou filtro novo foi detalhado nas reuniões enviadas.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "Financeiro, tela de contratos, beleza, importante")</li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a> (trecho: "Essa tela de contrato, isso é tão simples")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 549 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #421 — 10 Emissão de Boleto V2
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>A Quatri está <strong>centralizando tudo no Itaú</strong>, para não integrar com vários bancos (14/10).</li>
<li>A emissão de boleto "provavelmente vai ser junto com a conciliação" (#423), via <strong>API do Itaú</strong>. O Narciso ia estudar a API; as autorizações do banco costumam demorar, porque o software mexe na conta bancária.</li>
<li>Em 01/10 o roadmap citava "emissão de pagamento de banco".</li>
<li>Em 28/10 a Jessyka (financeiro) perguntou o prazo da integração com o banco e da emissão de boleto; o Vitor citou homologação com o banco, envio de remessa e API com o Itaú.</li>
<li>Em 17/11 ficou decidido antecipar os cards 10, 11, 12 e 13 para antes do Dash Técnico, voltando "forte no financeiro" no começo de janeiro, por causa da nova tributação.</li>
</ul>
<p><strong>Em aberto:</strong> nenhuma reunião enviada detalhou campos, layout do boleto ou regras de emissão. Precisa de reunião específica.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "eu já estou centralizando tudo no Itaú")</li>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro</a> e <a href="https://fathom.video/share/VxbY6yhFsSuftf-9N5Hx7baFtS1E5PK5" target="_blank">01/10 — roadmap de APIs</a></li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a> (trecho: "esse 10, 11, 12 e 13... Antes do Dash Técnico")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 421 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #422 — 11 Lembrete de Pagamento V2
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Pedido (14/10): disparar <strong>automaticamente para o cliente</strong> um lembrete de pagamento <strong>no dia</strong> do vencimento.</li>
<li>Em 01/10 o roadmap citava a "API do Zap" (WhatsApp). A ligação com o lembrete não foi dita, mas é o canal provável.</li>
<li>Em 14/10: a assinatura de contrato funciona, mas o WhatsApp desconecta no fornecedor atual; a ideia é a conexão passar a ser feita direto pelo servidor da Quatri.</li>
<li>Em 17/11: antecipado junto com os cards 10, 12 e 13, para antes do Dash Técnico.</li>
</ul>
<p><strong>Em aberto:</strong> canal (WhatsApp, e-mail), texto da mensagem, quantos dias antes/depois e para quais formas de pagamento. Não foi detalhado.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a></li>
<li><a href="https://fathom.video/share/VxbY6yhFsSuftf-9N5Hx7baFtS1E5PK5" target="_blank">01/10 — roadmap de APIs</a> (trecho: "API do Zap, API de nota fiscal e emissão de pagamento de banco")</li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 422 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #423 — 12 Conciliação V2
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 01/10 o Narciso não conseguiu dar prazo: precisava saber <strong>quais bancos</strong> seriam integrados.</li>
<li>Em 14/10 a resposta: a Quatri está <strong>centralizando tudo no Itaú</strong>. A conciliação deve ser feita junto com a emissão de boleto (#421), via API do Itaú, sujeita às autorizações do banco.</li>
<li>Em 22/10 a Silvia explicou como a conciliação é feita hoje: <strong>por extrato e por CNPJ</strong> (finais citados: 56, 18, 37, 09 e 76). Cada conta é paga no CNPJ do boleto ("CNPJ contra CNPJ", porque a empresa vai para lucro real).</li>
<li>Em 17/11: antecipado junto com os cards 10, 11 e 13, para antes do Dash Técnico.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/VxbY6yhFsSuftf-9N5Hx7baFtS1E5PK5" target="_blank">01/10 — roadmap de APIs</a></li>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "eu já estou centralizando tudo no Itaú")</li>
<li><a href="https://fathom.video/share/oGZX3RMyMsScFxTWB7LbeYefR-pKMfuz" target="_blank">22/10 — Silvia</a> (trecho: "o campo ele tem o seu mérito, mas para vocês não vai ser aplicável")</li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 423 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #424 — 13 Nota Fiscal
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Prioridade alta (14/10): "talvez entre até antes da agenda". A Quatri paga outro sistema para emitir nota e quer "dar tchau pro outro sistema".</li>
<li><strong>Decisão técnica (14/10 e 13/11):</strong> não desenvolver a emissão do zero (levaria mais de 6 meses, "uma burocracia sem fim"). Usar <strong>integração com um fornecedor de NF (API de terceiro)</strong>, como foi feito na assinatura de contratos. A sustentação legal fica com o fornecedor ("a gente só usa os campos").</li>
<li>Motivo: a <strong>reforma tributária</strong> do ano seguinte. Em 17/11: "vai ter nova tributação, a gente precisa estar com o financeiro o mais possível na nossa mão".</li>
<li>Ligação com outros cards: a geração de créditos de cliente (#460) passa a depender da NF de devolução, e a expedição também.</li>
<li>Histórico: a equipe anterior deixou integrações com credenciais no código; a ON refez a assinatura "da maneira certa" e fará o mesmo aqui.</li>
<li>Em 17/11: antecipado junto com os cards 10, 11 e 12, para antes do Dash Técnico.</li>
</ul>
<p><strong>Em aberto:</strong> escolha do fornecedor de NF, tipos de nota (produto, serviço, devolução) e em quais telas a emissão acontece.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "A nota fiscal, cara, é uma das coisas que talvez entre até antes da agenda")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "essa parte de nota fiscal vai funcionar mais ou menos no mesmo sentido dos contratos")</li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a> e <a href="https://fathom.video/share/VxbY6yhFsSuftf-9N5Hx7baFtS1E5PK5" target="_blank">01/10 — roadmap de APIs</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 424 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #414 — 3 Dash Técnico
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>O que é (Vitor, 14/10):</strong> "é o que o técnico vê, a página do técnico". Uma "visão unificada, mas simplificada" com:</p>
<ul>
<li>As <strong>plantas</strong> da obra para onde ele vai.</li>
<li>As informações da <strong>ordem de serviço</strong> atual.</li>
<li>As <strong>últimas ordens de serviço do cliente</strong>, para ver o que foi feito na última visita.</li>
<li>O <strong>dia</strong> dele e a <strong>agenda da semana</strong>.</li>
<li>As informações das atividades que está executando.</li>
</ul>
<p><strong>Relacionado (01/10, confiança baixa):</strong> no Mural, pediram mostrar as <strong>OS do dia</strong> para quem é técnico ou da produção ("Comecei meu dia, tem que fazer o que hoje?"), possivelmente trocando o contador "Eventos hoje" por OS.</p>
<p>Em 17/11 ficou decidido que os cards 10 a 13 (financeiro) entram antes deste. Ressalva do Vitor: não dá para empurrar muito a parte técnica, porque "é os caras que tocam a operação".</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "dash técnico, só pra você entender é o que o técnico vê")</li>
<li><a href="https://fathom.video/share/3rD5GTvSsPBTN_xk3qpKXx2sVsyGvnwT" target="_blank">01/10 — Mural e Gestão à Vista</a> (trecho: "Comecei meu dia, tem que fazer o que hoje?")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 414 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #415 — 4 Agenda Técnica
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 14/10 o Vitor colocou "a agenda lá da ordem de serviço" logo depois de Comissão e Clube: "eu preciso dar uma vazão nisso", "deve ser o mais complexo". Na lista, "agenda técnico" foi dada como entendida, sem detalhes.</li>
<li>Em 13/11: a Agenda Técnica "já foi conversada e está gravada" em outra reunião, que <strong>não está entre as transcrições enviadas</strong>. Mantida na posição; o Módulo de Assistência (#416) vai junto.</li>
<li>Já existe (03/11): na criação de OS, empresa, tipo de OS e serviço são cadastráveis por empresa (ex.: "serviço" e "vistoria" só para iluminação).</li>
</ul>
<p><strong>Antes de começar:</strong> localizar a gravação da reunião em que a Agenda Técnica foi detalhada.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "a agenda lá da ordem de serviço, que eu preciso dar uma vazão nisso")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a></li>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — tipos de OS cadastráveis</a> (trecho: "Agora, todos eles são cadastráveis")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 415 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #416 — 5 Módulo Assistência
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>O que foi pedido (Vitor, 14/10):</strong></p>
<ul>
<li>Fluxo: <strong>plantão &rarr; assistência &rarr; orçamento &rarr; contrato simplificado de assistência</strong>. "Aquele plantão pode virar uma assistência, aquela pessoa de assistência pode virar um orçamento, ele vai poder gerar ali um contrato mais simplificado."</li>
<li>Abertura por um <strong>link</strong>: o <strong>próprio cliente</strong> abre a assistência, ou o <strong>consultor</strong> abre. Quem abriu acompanha o <strong>status</strong>. Hoje "fica aberto, a gente não consegue monitorar".</li>
<li>Saber se o cliente está <strong>na garantia</strong> ou não.</li>
<li>A assistência <strong>não entra no Gestão à Vista</strong>: quando a obra é entregue, "a partir de agora ele é da assistência", com fluxo próprio.</li>
<li>Indicadores: quantas assistências foram feitas, quanto se vendeu e quanto não se vendeu de assistência por mês, quantos atendimentos e se houve retorno.</li>
</ul>
<p><strong>Relacionado:</strong> em 01/10, no fluxo de etapas da obra, depois do custom service a obra vai para <strong>assistência</strong> ou <strong>finalização</strong>, e a Ana puxa para a assistência. Em 13/11 o Vitor quer "esquemas para abrir o dashboard da assistência".</p>
<p><strong>Em aberto:</strong> em 13/11 e 17/11 a ON avisou que o módulo "não foi aprofundado" e precisa de uma reunião específica antes de desenvolver ("depois a gente vai ter que dar uma peneirada").</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "aquele plantão pode virar uma assistência")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "módulo de assistência a gente não efetivamente aprofundou nele")</li>
<li><a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a></li>
<li><a href="https://fathom.video/share/3rD5GTvSsPBTN_xk3qpKXx2sVsyGvnwT" target="_blank">01/10 — etapas da obra</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 416 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #413 — 2.1 Expedição: Pedido Quatri / Produção
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 13/11: "é a tela de expedição pra fazer toda a parte de expedição de pedido, quando sai, quando entra". A primeira versão <strong>já subiu</strong>, mas "tem alguns ajustes que o pessoal pediu". A descrição original do card era visual (fotos).</li>
<li><strong>Decisão (13/11):</strong> segurar até o <strong>novo comprador</strong> da Quatri entrar, testar e anotar tudo. Depois o Vitor marca uma reunião sobre expedição e compras e passa todos os pontos de uma vez. Retorno de Compras prometido até <strong>15/01</strong>.</li>
<li><strong>Demandas novas que entram neste bloco (13/11), ainda sem card:</strong>
<ul>
<li><strong>Transferência de material entre clientes</strong> (o Vitor pediu para criar o card e deixar junto com compras).</li>
<li><strong>Esteira/linha de produção:</strong> "alguns produtos eles pegam como insumo e eles viram um produto. Eles saem do estoque como módulos e voltam com um quadro". Depois o produto vai para o cliente como um produto.</li>
</ul></li>
<li>Em 14/10, as etapas da expedição mostradas na tela: compra feita &rarr; <strong>Aguardando receber</strong> (a expedição começa aqui) &rarr; recebido &rarr; <strong>A separar</strong> (vem da ordem de serviço). Há uma etapa "Alocar a expedição" que nem o Vitor sabia o que era.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trechos: "Deixa o cara de compras entrar. Pra gente fazer de uma vez só" e "Eles saem do estoque como módulos e voltam com um quadro")</li>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — etapas da expedição</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 413 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #460 — 2.2 Créditos de clientes por devolução
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Casos reais citados:</strong></p>
<ul>
<li><strong>Júlio César Campos Lobo (22/10):</strong> no Contas a Receber aparecia "Esse cliente possui um crédito disponível de R$ 144 mil", sem origem. Nenhum valor de contrato do cliente batia (26, 31, 134, 100 e 16 mil). O Narciso ligou ao furo de estoque: saídas de itens sem saldo (estoque negativo, mais de 300 itens) geravam lançamento financeiro. Em 28/10 o saldo foi zerado direto no banco, com registro da ocorrência.</li>
<li><strong>Marcelo Leandro (30/10, Caio):</strong> ao gerar um aditivo, o sistema trouxe o orçamento inteiro, e não só os produtos alterados, e o crédito ficou em cerca de R$ 26 a 31 mil em vez de cerca de R$ 5.500. Depois de <strong>excluir o aditivo, o crédito permaneceu</strong>, e o histórico de crédito não mostrava nada. Paliativo: a ON zerou o restante e deixou só R$ 5.591,58 usados; o Caio gerou um orçamento adicional.</li>
<li><strong>Aram, orçamentos 36937 e 36938 (30/10, Paulo):</strong> deveriam ser aditivos de dois orçamentos pai (cerca de R$ 202 mil e R$ 192 mil, troca de cabeado para Wi-Fi), mas o Paulo não tinha acesso aos pais, que estavam em nome de outro consultor, e fez orçamentos novos. Lançou o crédito como <strong>entrada</strong>, e o financeiro passou a ver como dinheiro a receber. Combinado: transformar essas entradas em crédito.</li>
</ul>
<p><strong>Pedidos ligados a esta regra:</strong></p>
<ul>
<li>A Sil (13/11): os créditos "só tinham que ser gerados efetivamente a partir da nota fiscal". Fica ligado à Nota Fiscal (#424) e à expedição.</li>
<li>Paulo (30/10): na <strong>Condição negociada</strong>, ao escolher crédito, pedir o <strong>orçamento pai</strong> e vincular os dois (como hoje existe "entrada"). Na época a opção de crédito na condição negociada e o ajuste manual de crédito estavam sendo criados.</li>
<li>Créditos excluídos ou estornados junto com o aditivo que os gerou.</li>
</ul>
<p><strong>Decisão (13/11 e 17/11):</strong> vai junto com o bloco de compras e expedição, depois da avaliação do novo comprador. A ON avisou que "vai ser uma treta" validar os números e vai precisar do financeiro da Quatri para conferir.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/oGZX3RMyMsScFxTWB7LbeYefR-pKMfuz" target="_blank">22/10 — Silvia</a> (trecho: "Esse cliente possui um crédito disponível de R$144 mil. Da onde surgiu isso?")</li>
<li><a href="https://fathom.video/share/teaHXViUAQCmgG8vjyyeMHZsRd4sxjAB" target="_blank">30/10 — Caio, aditivo</a> (trecho: "E aí eu excluí o aditivo, mas ele manteve o crédito")</li>
<li><a href="https://fathom.video/share/LCYEr-WWKZGKgA5v5TWvyssfrPDy92iv" target="_blank">30/10 — Paulo, crédito como entrada</a> (trecho: "eu coloquei lá como entrada o valor que seria o crédito")</li>
<li><a href="https://fathom.video/share/j-a2nVgubFxicSuiqR_ZibVsjFAx5mkY" target="_blank">28/10 — financeiro</a>, <a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> e <a href="https://fathom.video/share/UHFDMe_3FPbgdfybacet1zJ-cxKio8br" target="_blank">17/11 — gravação 3</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 460 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #412 — 2 Dash Expedição
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li><strong>Conteúdo (14/10):</strong> ver o que há para <strong>separar no dia</strong> e <strong>na semana</strong> e o que está <strong>para chegar</strong> (material pedido). "Hoje fica meio solto, ele tem que ficar procurando." Quando soltarem demandas para a expedição, elas vão para ele. Segue a mesma linha do Dash Técnico.</li>
<li>Vínculos: da etapa "A separar" em diante, ligado à <strong>ordem de serviço</strong>; as colunas anteriores são ligadas a <strong>pedidos</strong>.</li>
<li>Em 01/10 o Vitor aceitou priorizar os dashs de Compras e Expedição antes das integrações. Em 03/11 a melhoria estava prevista para 26/11.</li>
<li>Em 13/11: depois do Dash de Expedição vêm a Comissão Individualizada, a V2 do financeiro e a nova proposta comercial. A transferência de material entre clientes e a esteira de produção entram neste bloco (ver #413).</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "O de expedição é para ver o que ele tem para separar no dia")</li>
<li><a href="https://fathom.video/share/VxbY6yhFsSuftf-9N5Hx7baFtS1E5PK5" target="_blank">01/10 — Vitor</a> (trecho: "se você pudesse soltar esses dashs para mim de compras e expedição")</li>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — cronograma</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 412 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #425 — 14 Nova Proposta Comercial
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Origem (14/10):</strong> o item entrou na lista como "nova proposta comercial" depois da discussão do PDF da proposta de iluminação.</p>
<ul>
<li><strong>PDF longo:</strong> um orçamento de iluminação de cerca de R$ 29 mil (nº 36993, cliente Vivi do Alonso) gerou <strong>36 páginas</strong>. "No projeto de 300 mil vai ser 100 páginas." O Vitor quer mais de um ambiente por página.</li>
<li>Hoje o PDF agrupa por <strong>solução</strong>, uma página por grupo, com os ambientes abaixo. O layout usa <strong>imagem de fundo</strong> com campos em posição fixa (X/Y): não aproveita o espaço e a imagem do produto fica pequena.</li>
<li>Proposta do Narciso: layout <strong>gerado por código</strong>, que distribui a informação no número ideal de páginas (como o orçamento interno da ON). Fica mais "engessado" (a Quatri não troca o fundo), mas organizado. Exige mais testes.</li>
<li>Ideia do Vitor: permitir mais de um ambiente para o mesmo produto/solução e separar os ambientes no PDF. O <strong>local</strong> é um só por item.</li>
<li>Possível erro de uso na época: a usuária deveria ter usado a solução "luz técnica" e as opções de separar "por ambiente" e "ambiente e local".</li>
<li>Opção de <strong>ocultar a mão de obra</strong> (pedido do Caio): na iluminação o mercado trabalha com mão de obra; na automação não.</li>
</ul>
<p><strong>V2 do layout (03/11):</strong> será feita com o marketing da Quatri e passa a ser <strong>horizontal</strong>. Em 13/11: "é os ajustes todos que a gente estava comentando", último item do cronograma original.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — proposta de iluminação</a> (trecho: "36 páginas, cara, tá vendo?")</li>
<li><a href="https://fathom.video/share/q3YkA54UDwgyzawews236opcUeWk8qoV" target="_blank">03/11 — PDF da proposta</a> (trecho: "não vai ser mais vertical, vai ser horizontal")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a></li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 425 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #419 — 8 Ver na reunião (antes: Integração com Bitrix)
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li>Em 13/11 o Vitor queria fazer a integração com o Bitrix "concomitante com alguma coisa".</li>
<li><strong>Em 17/11 o Vitor decidiu trocar a integração com o Bitrix por um CRM em Kanban dentro do próprio sistema:</strong> "não faz sentido eu gastar esse envolvimento integrando se eu tenho todas as informações ali dentro", e não quer "ficar pagando mensalidade para os outros".</li>
<li><strong>Em aberto:</strong> o Bitrix é usado também para redes sociais integradas ("isso a gente resolve na hora que for com o chefão"). Falta definir as etapas do Kanban e quais informações o CRM vai usar. Por isso o card se chama "Ver na reunião".</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/wDKcfC_4nLgL2_qD_VPAVJycGKyvs9Ym" target="_blank">17/11 — gravação 4</a> (trecho: "ou se eu faço um CRMzinho de Kanban")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "A integração com o Bitrix, eu tento colocar ela como concomitante")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 419 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #417 — 6 Relatório de Fechamento de Obra
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li><strong>O que é (Vitor, 14/10):</strong> quando a obra acabar, um relatório dela com <strong>quantas visitas</strong> foram feitas e o que foi <strong>vendido x comprado</strong>, para ver se bateu. O Narciso resumiu: "um DRE da obra". Vitor: "É isso aí."</li>
<li>Na lista lida pelo Narciso apareciam juntos "arquiteta, assistência, relatório de fechamento, visitas, compras": "é basicamente relatório de tudo".</li>
<li><strong>Cuidado para não confundir:</strong> nas reuniões de 01/10 e 05/11 aparece um "relatório de fechamento" que é de <strong>vendas</strong> (faturamento por consultor e unidade no mês), não de obra. Esse assunto não é deste card.</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/t5iyfBYcy-EC6nyjsz--izdd_i6JpUHx" target="_blank">14/10 — roteiro e prioridades</a> (trecho: "Quando acabasse aquela obra, eu queria poder ter um relatório dela. Quantas visitas foram feitas")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 417 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #517 — OS V2 + Gestão à Vista Novo
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<p><strong>Mudança de escopo (13/11, Vitor):</strong> a maior parte do previsto para a V2 já foi entregue. Por enquanto, só <strong>corrigir bugs</strong> ("vamos só arrumar o que estiver errado"). Os usuários abrem tickets e, mais tarde, junta-se tudo o que estiver aberto sobre o Gestão à Vista num <strong>"V2 Plus"</strong>. Antes de desenvolver, cruzar a lista abaixo com os tickets abertos e o que já foi entregue.</p>
<p><strong>Mural e Tarefas (01/10 e 04/11):</strong></p>
<ul>
<li>O Mural e a tela Tarefas não mostram <strong>subtarefas</strong>, que é o que a equipe usa (subtarefas ficam dentro do card do cliente). Decisão: manter tarefas e subtarefas separadas, com as duas visíveis.</li>
<li>Tarefa avulsa não permite informar o <strong>cliente</strong>. Sugestão aceita: ao criar a tarefa, escolher projeto ou orçamento e o cliente vir automático.</li>
<li>Busca duplicada na tela de Tarefas: a de cima não filtra (buscando "Júlio" continuam todos). Remover uma.</li>
<li>Não dá para <strong>editar/reagendar</strong> tarefa vencida (ex.: Júlio César, "Validar informações do painel de LED"). Na tela de tarefas não há botão para editar, mudar vencimento nem ir direto ao card no Gestão à Vista com a tarefa destacada (ver texto no início desta descrição).</li>
<li>Subtarefa concluída no layout antigo aparece como vencida no novo (o novo parece ler outro campo de status).</li>
<li>Contadores: separar tarefas e subtarefas (hoje somam) e torná-los <strong>clicáveis</strong>.</li>
<li>Reformular "Minhas tarefas" (04/11): separar do bloco "Projetos e obras" e mostrar indicadores clicáveis de <strong>total, do dia, atrasadas, em aberto</strong> e <strong>finalizadas</strong>. Mostrar a quantidade de projetos ao lado. O filtro "Atrasadas" da tela "ver todas" não funciona, e a pesquisa também falhou. Os filtros "Em andamento/Auxiliando/Todas/Criadas por mim" confundem.</li>
<li>Mostrar no Mural as <strong>OS do dia</strong> para técnico/produção (ver também #414).</li>
</ul>
<p><strong>Card do Gestão à Vista (01/10 e 04/11):</strong></p>
<ul>
<li><strong>Urgente (04/11):</strong> a aba <strong>Documentos</strong> deixa o pessoal de obra abrir proposta comercial, contrato e valores; a aba <strong>Detalhes</strong> mostra valor total e histórico de revisões. Deve aparecer só <strong>proposta técnica e projeto</strong>.</li>
<li>Título do card mostra nome fantasia e razão social; deve mostrar o <strong>nome do projeto/obra</strong> (ex.: Grupo Mendes, 6 contratos que aparecem todos como "documentos").</li>
<li>Cards nascem <strong>vencidos</strong> porque recebem a data do contrato (ex.: criado em 30/10, vencido em 29/09). O vencimento que importa é o da etapa/subtarefa.</li>
<li>Descrição vem preenchida automaticamente com o texto antigo: deve vir vazia, ser editável e aceitar quebra de linha.</li>
<li>Categoria: definir como seleção única, com <strong>Automação</strong> automática na criação para quem é de automação. <strong>Cidade</strong> da obra automática a partir do contrato/projeto. Limpar categorias e status sem uso.</li>
<li>Filtros antigos: buscar por <strong>arquiteto</strong> e <strong>categoria</strong> (lista clicável), descrição completa ao passar o mouse, e voltar a opção de <strong>minimizar colunas</strong> (antes mostrava cerca de 15 cards com "ver mais").</li>
<li>Chat e comentários: editar por <strong>10 minutos</strong> com log, colar print (Ctrl+V) e <strong>@menções</strong>. Já existe "Nova sala" para chats separados.</li>
<li>Subtarefas e comentários criados pelo próprio usuário devem poder ser editados e excluídos (as subtarefas base, não).</li>
<li>Responsáveis: some/volta, vem com gente errada, não mostra a função de cada um. Notificações: opção de apagar todas (pode ser permissão). Aviso laranja aparece em quase todos os cards; usar para novidades como contrato adicional.</li>
<li>Documentos por etapa com <strong>versionamento</strong> (V1, V2, R3): mostrar só a última versão e guardar as antigas, com acesso rápido pelo celular. Permitir que o setor de Projetos anexe e todos vejam; separar "Produção".</li>
<li>Aba Aderência mostra só tarefas, não subtarefas.</li>
<li>Checklist das etapas customizável pela Quatri ("está na V2, está no escopo"), editável por card, só pelo gestor e pelo responsável, com subtarefa criada a partir do item e para outra pessoa.</li>
<li>Card único por obra (no lugar de Gestão, Projetos e Produção separados), com etapas: Conhecendo a obra, pré-projeto, projeto, infraestrutura, cabeamento, recorte, instalação, ajuste, custom service, e depois assistência ou finalização. Status por área (ex.: obra segue em gestão, mas volta para revisão em Projetos). Indícios mostram que parte disso já foi implementada.</li>
</ul>
<p><strong>OS no card (01/10):</strong> já existe botão "Nova OS" (tipo da OS, tipo de serviço, início, fim, observação) e lista das OS do card. Tipos de OS e serviços cadastráveis por empresa já existem (03/11).</p>
<p><strong>Bug de carregamento (12/11):</strong> a lista de cards fica carregando sem parar ao filtrar (ex.: responsável Lucas Souza) quando aparece a barra de rolagem/zoom do navegador. Antes carregava cerca de 10 cards com botão para mais; agora é rolagem infinita. Paliativo: zoom menor. Há ticket aberto.</p>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/3rD5GTvSsPBTN_xk3qpKXx2sVsyGvnwT" target="_blank">01/10 — Mural e Gestão à Vista novo</a> (trechos: "Eu não uso tarefas e só uso subtarefas" e "na minha cabeça, ter três cards é a confusão")</li>
<li><a href="https://fathom.video/share/wLaR_rirGo525-bdyd1MqBRa3F9kx5Ja" target="_blank">01/10 — continuação (Gerencial, Aderência, documentos)</a></li>
<li><a href="https://fathom.video/share/t1ZZe6WR484imG39vm-Ac9MxMf9nfKHA" target="_blank">04/11 — ajustes emergenciais do Gestão à Vista</a> (trechos: por volta de 39:58 "Ah, isso não pode, Vacari. Urgente" e por volta de 22:00 "total de tarefas, tarefas do dia, tarefas atrasadas")</li>
<li><a href="https://fathom.video/share/xx78g4wE4f9MLiy_owrketnVrzCSv9AB" target="_blank">12/11 — bug de carregamento</a> (trecho: "Antes não era infinito")</li>
<li><a href="https://fathom.video/share/pRQrehihuMnq5txsT83J1L4D_kqM7LPb" target="_blank">13/11 — repriorização</a> (trecho: "vamos só arrumar o que estiver errado")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda. Neste card isso é ainda mais importante: muitos itens podem já ter sido corrigidos como bug depois das reuniões.</p>')
WHERE id = 517 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

-- ---------------------------------------------------------------------
-- #568 — Filtro das Obras em Andamento
-- ---------------------------------------------------------------------
UPDATE planning_cards SET description = CONCAT(COALESCE(description, ''),
'<hr><p><strong>Detalhamento das reuniões (transcrições)</strong></p>
<ul>
<li><strong>Origem (01/10):</strong> se o Vitor pergunta quantas obras estão ativas, "ninguém tem essa resposta". Pedido: filtrando por gestor (ex.: Claudemir), mostrar nos quadradinhos do topo do Gestão à Vista a quantidade de obras por estágio: <strong>em projeto, em andamento, paradas, para acabar, em finalização</strong>. Depende de o gestor manter a etapa da obra atualizada.</li>
<li><strong>Contagem que muda sozinha (04/11):</strong> ao clicar em Automação o número muda (50, 47, 44; antes cerca de 102 obras, depois 51). "Obras em instalação geral" mostra 0. Causa vista: 23 cards antigos sem categoria (nem Iluminação nem Automação).</li>
<li><strong>Cards antigos não podem entrar na conta:</strong> foi acordado que os cards antigos seriam "só consulta" e não somariam em filtro nem no total. Hoje, filtrando por responsável, o total chega a 478.</li>
<li>A reclassificação dos cards antigos é trabalho manual da Quatri (Lucas Noya estava fazendo em 12/11).</li>
<li>Em 12/11 o Luiz avisou que a <strong>parte gerencial</strong> (análises e filtros) também vai precisar de ajuste, sem detalhar.</li>
<li>Em 13/11 este card foi para o fim do cronograma, junto com o Gestão à Vista (#517).</li>
</ul>
<p><strong>Reuniões:</strong></p>
<ul>
<li><a href="https://fathom.video/share/3rD5GTvSsPBTN_xk3qpKXx2sVsyGvnwT" target="_blank">01/10 — Gestão à Vista</a> (trecho: "se o Vitor perguntar hoje para a gente quantas obras a gente tem ativa, ninguém tem essa resposta")</li>
<li><a href="https://fathom.video/share/t1ZZe6WR484imG39vm-Ac9MxMf9nfKHA" target="_blank">04/11 — ajustes emergenciais</a> (trechos: por volta de 2:41 "mudar de 50 pra 47, pra 44" e por volta de 31:17 "esses antigos, era pra ser só consulta")</li>
<li><a href="https://fathom.video/share/xx78g4wE4f9MLiy_owrketnVrzCSv9AB" target="_blank">12/11 — Luiz e Lucas Noya</a> (trecho: "a parte gerencial a gente vai precisar ajustar")</li>
</ul>
<p><strong>Atenção, desenvolvedor:</strong> este detalhamento foi montado a partir das transcrições automáticas das reuniões e pode ter juntado a esta demanda um assunto parecido, mas diferente. Antes de começar, assista aos trechos das reuniões citadas para confirmar se o pedido é realmente este, e verifique se algum ponto já não foi resolvido em outra demanda.</p>')
WHERE id = 568 AND company_id = 2 AND COALESCE(description, '') NOT LIKE '%Detalhamento das reuniões (transcrições)%';

COMMIT;

-- ---------------------------------------------------------------------
-- Conferência: tamanho da descrição antes/depois (deve ter aumentado)
-- ---------------------------------------------------------------------
SELECT pc.id, pc.title, CHAR_LENGTH(pc.description) AS tamanho_descricao,
       (pc.description LIKE '%Detalhamento das reuniões (transcrições)%') AS tem_detalhamento
FROM planning_cards pc
WHERE pc.company_id = 2 AND pc.status NOT IN ('completed','archived','denied')
ORDER BY COALESCE(pc.start_date, pc.due_date, '2099-12-31'), pc.id;
