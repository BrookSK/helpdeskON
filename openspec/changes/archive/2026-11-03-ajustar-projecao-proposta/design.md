# Design

## Context

`ProposalRules::buildPresentationData()` é a fonte de dados do PDF e da proposta online. A projeção (`projection()`) e os planos (`plans()`) têm 12 meses fixos. O PDF (`proposal_pdf.php`) recalcula `plans()` por conta própria e escreve "12" nos títulos. Os campos variáveis da proposta ficam no JSON `content`, saneados por `normalizeContent()` a partir de `CONTENT_FIELDS`.

## Goals / Non-Goals

**Goals:**
- Um único valor de prazo (3, 6 ou 12) que alimenta projeção, planos e textos do PDF.
- Propostas antigas geram o mesmo PDF de antes, exceto quando são só pontuais sem retorno (a página de projeção some).

**Non-Goals:**
- Cronograma de etapas: continua com 12 colunas, porque mostra datas dos serviços e não a projeção.
- Página de produtos a mais por mês: continua baseada no contrato mínimo de cada plano.
- Resumo final, "Total do 1º ano", variável `{total_12_meses}` e valores do aceite: continuam em 12 meses.

## Decisions

- **Prazo no JSON `content` (`projection_months`)** em vez de coluna nova. Os outros campos variáveis já estão lá e o formulário salva todos os `.p-content` automaticamente. Alternativa descartada: migration com coluna `projection_months`, que exigiria mudar model, controller e testes de integração sem ganho.
- **`ProposalRules::projectionMonths(array $content): int`** devolve 3, 6 ou 12 (padrão 12). PDF e `buildPresentationData()` usam essa função, sem repetir a regra.
- **`projection()` recebe `$months`** (padrão 12). O mês de cada valor é calculado sem limitar ao último mês; valores com mês igual ou maior que o prazo ficam fora. Antes, um valor depois do 12º mês caía no 12º mês, o que inflava o total. Com prazo de 3 meses isso ficaria visivelmente errado.
- **`plans()` recebe `$months`** (padrão 12). `cum` e `index` passam a ter `$months` pontos, com base no custo do plano 3 meses no último mês do prazo. Campos novos `period_total`, `period_avg` e `save_period` alimentam a página de comparação. `year`, `avg`, `save_year`, `term` e `index12` continuam iguais, porque resumo final, aceite e produtos a mais dependem deles.
- **Visibilidade**: `buildPresentationData()` expõe `show_projection = has_monthly || expected_return > 0`. O PDF usa a mesma condição para decidir se mostra a página.

## Risks / Trade-offs

- [Proposta antiga com serviço que começa depois do 12º mês passa a ter total de projeção menor] → É a correção do comportamento: esse valor não acontece dentro dos 12 meses. Caso raro, coberto por teste.
- [Prazo curto com plano de 12 meses na comparação] → A comparação mostra só o custo acumulado no período escolhido. Resumo final continua com o contrato mínimo de cada plano.
