# Proposal

## Why

Nos testes da esteira (2026-11-03), a Júlia não entendeu o bloco de documentos do prestador. Os itens do checklist têm círculos que parecem botões de seleção, o nome do documento é pedido mesmo com o tipo já escolhido, e quem registra sem tipo não marca nada no checklist.

## What Changes

- O checklist mostra o status de cada documento em texto: "Pendente" ou "Registrado".
- O tipo passa a ser obrigatório no registro, na tela e no servidor.
- Ao escolher um tipo do checklist, o nome é preenchido com o nome do tipo e pode ser editado. Em "Outro", o nome é obrigatório.
- A lista de documentos registrados mostra o nome do tipo no lugar da chave interna.
- Documento faltando continua sem bloquear o avanço do prestador. Isso depende de decisão do cliente.

## Capabilities

### New Capabilities
- `prestador-documentos`: registro de documentos do prestador e checklist por tipo de contratação.

### Modified Capabilities

Nenhuma.

## Impact

- `app/core/ProviderProposalRules.php`: validação do registro e nome legível do tipo.
- `app/controllers/ProviderController.php`: `addDocument()` usa a validação.
- `app/views/commercial/provider_detail.php`: checklist, formulário e lista de documentos.
- `tests/Unit/ProviderProposalRulesTest.php`: casos novos.
- Sem migration. Documentos já registrados continuam como estão.
