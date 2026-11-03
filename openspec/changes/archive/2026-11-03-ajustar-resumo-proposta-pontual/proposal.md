# Proposal

## Why

Nos testes da proposta (2026-11-03), a Júlia viu que o resumo final do PDF de uma proposta só pontual ainda diz "O Plano 12 meses tem a menor mensalidade e o menor custo médio por mês". Essa proposta não tem planos nem mensalidade, então a frase não se aplica.

## What Changes

- O resumo final do PDF deixa de mostrar a frase sobre o Plano 12 meses quando a proposta não tem serviço mensal.
- O resto do texto do resumo final continua igual, inclusive o que o comercial editou.
- Vale para propostas já salvas, porque a frase é retirada na hora de gerar o PDF.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities
- `proposta-projecao`: o resumo final passa a depender de a proposta ter mensalidade.

## Impact

- `app/core/ProposalRules.php`: novo `summaryText()`.
- `app/views/commercial/proposal_pdf.php`: resumo final usa `summaryText()`.
- `tests/Unit/ProposalRulesTest.php`: caso novo.
- Sem migration.
