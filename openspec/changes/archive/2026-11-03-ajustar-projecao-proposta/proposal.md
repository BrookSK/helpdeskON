# Proposal

## Why

O cliente (áudio de 2026-11-03) apontou que o PDF da proposta mostra a página "Projeção de 12 meses" mesmo quando a proposta só tem serviço pontual: a tabela fica com o valor no mês 1 e R$ 0 nos outros 11 meses, sem informação útil. Ele também pediu que o prazo da projeção seja ajustável (3, 6 ou 12 meses) em vez de fixo em 12.

## What Changes

- A proposta ganha o campo "Prazo da projeção" com as opções 3, 6 ou 12 meses. O padrão é 12, então as propostas existentes continuam iguais.
- O PDF usa esse prazo no título, no gráfico, na tabela mês a mês, na linha de total, na comparação entre planos e na página de previsibilidade de retorno.
- O PDF deixa de mostrar a página de projeção quando a proposta só tem serviço pontual e não tem retorno mensal estimado.
- O cronograma de etapas continua com 12 colunas e o rótulo lateral deixa de dizer "Projeção de 12 meses".
- O cálculo da projeção passa a ignorar valores que começam depois do fim do prazo, em vez de jogá-los no último mês.

## Capabilities

### New Capabilities
- `proposta-projecao`: projeção de investimento e retorno exibida no PDF da proposta comercial, com prazo configurável e regras de quando aparece.

### Modified Capabilities

Nenhuma.

## Impact

- `app/core/ProposalRules.php`: novo campo de conteúdo `projection_months`, `projection()` e `plans()` recebem o prazo, `buildPresentationData()` expõe o prazo e se a projeção aparece.
- `app/views/commercial/proposal_form.php`: seletor do prazo no bloco "Projeção, retorno e fechamento".
- `app/views/commercial/proposal_pdf.php`: páginas de projeção, comparação de planos, previsibilidade de retorno e rótulo do cronograma.
- `tests/Unit/ProposalRulesTest.php`: casos novos para prazo e visibilidade.
- Sem migration: o prazo fica no JSON `content` da proposta, que já guarda os outros campos variáveis.
- A proposta online não mostra projeção, então não muda.
