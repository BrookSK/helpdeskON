# Tasks

## 1. Regras

- [x] 1.1 Adicionar `projection_months` em `CONTENT_FIELDS`, sanear em `normalizeContent()` e criar `projectionMonths()`. Verificar com teste unitário de valores 3, 6, 12, vazio e inválido.
- [x] 1.2 `projection()` com parâmetro de prazo e exclusão de valores fora do prazo. Verificar com teste de prazo 3 e pontual no 5º mês.
- [x] 1.3 `plans()` com parâmetro de prazo e campos `period_total`, `period_avg`, `save_period`. Verificar com teste de prazo 6 mantendo `year` e `index12`.
- [x] 1.4 `buildPresentationData()` expõe `projection_months` e `show_projection`. Verificar com teste de proposta só pontual com e sem retorno.

## 2. Telas

- [x] 2.1 Seletor "Prazo da projeção" no formulário da proposta. Verificar salvando a proposta e conferindo o JSON `content`.
- [x] 2.2 PDF usa o prazo nas páginas de projeção, comparação de planos e previsibilidade de retorno, e esconde a projeção quando `show_projection` é falso. Verificar gerando PDFs de exemplo (só pontual sem retorno, só pontual com retorno, com mensalidade em 6 meses).

## 3. Verificação

- [x] 3.1 Rodar a suíte unitária completa sem regressão (`vendor/bin/phpunit --testsuite Unit` ou equivalente).

## Workflow follow-up

- Arquivar o change com `openspec archive ajustar-projecao-proposta` depois da implementação.
