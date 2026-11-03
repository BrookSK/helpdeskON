# proposta-projecao Specification

## Purpose
Define como o PDF da proposta comercial projeta investimento e retorno ao longo do tempo: qual prazo usa e quando a projeção aparece.

## Requirements

### Requirement: Prazo da projeção configurável
A proposta SHALL ter um prazo de projeção de 3, 6 ou 12 meses, escolhido no formulário da proposta. Quando o prazo não foi escolhido ou tem valor inválido, o sistema SHALL usar 12 meses.

#### Scenario: Proposta sem prazo escolhido
- **WHEN** uma proposta criada antes desta mudança gera o PDF
- **THEN** a projeção cobre 12 meses, como antes

#### Scenario: Valor inválido
- **WHEN** o prazo salvo é diferente de 3, 6 ou 12
- **THEN** o sistema trata o prazo como 12 meses

#### Scenario: Prazo de 6 meses
- **WHEN** o comercial escolhe 6 meses e gera o PDF
- **THEN** a página de projeção se chama "Projeção de 6 meses"
- **AND** o gráfico e a tabela mês a mês têm 6 meses, a partir do mês de início
- **AND** a linha de total diz "6 meses" e soma só esses 6 meses

### Requirement: Valores fora do prazo não entram na projeção
A projeção SHALL considerar só os valores que caem dentro do prazo. Um serviço pontual que começa depois do último mês do prazo MUST NOT ser somado a nenhum mês da projeção. Um serviço mensal entra só nos meses do prazo a partir do seu início.

#### Scenario: Pontual depois do prazo
- **WHEN** o prazo é 3 meses e um serviço pontual começa no 5º mês
- **THEN** esse serviço não aparece na projeção e o total dos 3 meses não o inclui

### Requirement: Projeção oculta em proposta só pontual sem retorno
O PDF MUST NOT mostrar a página de projeção quando a proposta não tem serviço mensal e não tem retorno mensal estimado. Quando a proposta só pontual tem retorno mensal estimado, o PDF SHALL mostrar a projeção de investimento e retorno no prazo escolhido.

#### Scenario: Só pontual, sem retorno
- **WHEN** a proposta tem só serviços pontuais e o retorno mensal estimado está vazio
- **THEN** o PDF não tem a página "Projeção de N meses"

#### Scenario: Só pontual, com retorno
- **WHEN** a proposta tem só serviços pontuais e retorno mensal estimado de R$ 2.000
- **THEN** o PDF mostra a projeção com investimento e retorno acumulados no prazo escolhido
- **AND** a página de previsibilidade de retorno mostra investimento, retorno e resultado "em N meses"

### Requirement: Comparação de planos no prazo escolhido
Quando a proposta tem mensalidade, a página de comparação entre os planos SHALL usar o prazo escolhido no título, no gráfico de custo acumulado, na economia do período e no custo médio por mês. O resumo final e o aceite SHALL continuar com os valores de 12 meses e do contrato mínimo de cada plano.

#### Scenario: Planos com prazo de 3 meses
- **WHEN** a proposta tem mensalidade e o prazo é 3 meses
- **THEN** a página se chama "Projeção de 3 meses"
- **AND** o gráfico tem 3 meses e a linha de economia mostra a economia em 3 meses
