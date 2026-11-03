## ADDED Requirements

### Requirement: Resumo final sem frase de planos em proposta só pontual
Quando a proposta não tem serviço mensal, o resumo final do PDF MUST NOT mostrar a frase "O Plano 12 meses tem a menor mensalidade e o menor custo médio por mês.". O restante do texto do resumo final SHALL aparecer como foi salvo.

#### Scenario: Só pontual com o texto padrão
- **WHEN** a proposta tem só serviços pontuais e o resumo final é o texto padrão
- **THEN** o PDF mostra o resumo começando em "A implantação é cobrada uma só vez."

#### Scenario: Com mensalidade
- **WHEN** a proposta tem serviço mensal
- **THEN** o resumo final mostra o texto salvo completo, com a frase do Plano 12 meses
