# Design

## Decisões

- A validação fica em `ProviderProposalRules::normalizeDocumentEntry()`, que é pura e testável. O controller só repassa o resultado.
- O nome padrão é aplicado também no servidor: o preenchimento na tela é uma conveniência e o registro continua correto sem JavaScript.
- Os tipos aceitos são os do checklist do tipo de contratação atual, mais `outro`. Um tipo de outra contratação é recusado.
- `documentTypeLabel()` procura o nome em todos os tipos de contratação, porque o prestador pode ter mudado de contratação depois de registrar o documento. Chave desconhecida aparece como está.
- A lista esconde o selo do tipo quando ele é igual ao nome do documento, para não repetir o mesmo texto.

## Fora do escopo

- Bloquear o avanço para "Ativo" quando falta documento. `missingDocuments()` continua sem uso nas telas.
- Upload de arquivo. O campo continua sendo um link opcional.
