## ADDED Requirements

### Requirement: Status do checklist em texto
O checklist de documentos do prestador SHALL mostrar cada documento exigido com o status "Pendente" ou "Registrado". O checklist MUST NOT usar elementos que pareçam botões de seleção.

#### Scenario: Documento ainda não registrado
- **WHEN** o prestador freelancer não tem documento do tipo "RG e CPF"
- **THEN** o checklist mostra "RG e CPF" com o status "Pendente"

#### Scenario: Documento registrado
- **WHEN** um documento do tipo "RG e CPF" é registrado
- **THEN** o checklist mostra "RG e CPF" com o status "Registrado"

### Requirement: Tipo obrigatório no registro
O registro de documento SHALL exigir um tipo do checklist do prestador ou "Outro". O sistema MUST recusar registro sem tipo ou com tipo fora dessa lista.

#### Scenario: Sem tipo
- **WHEN** o registro é enviado sem tipo
- **THEN** o sistema recusa e pede que o tipo seja selecionado

#### Scenario: Tipo de outra contratação
- **WHEN** o registro de um prestador freelancer é enviado com o tipo `cnpj`
- **THEN** o sistema recusa o registro

### Requirement: Nome do documento a partir do tipo
Quando o tipo vem do checklist e o nome está vazio, o sistema SHALL usar o nome do tipo como nome do documento. O nome preenchido pelo usuário SHALL ser mantido. Quando o tipo é "Outro", o nome MUST ser informado.

#### Scenario: Tipo do checklist sem nome
- **WHEN** o usuário escolhe "RG e CPF" e deixa o nome vazio
- **THEN** o documento é registrado com o nome "RG e CPF"

#### Scenario: Nome editado
- **WHEN** o usuário escolhe "RG e CPF" e informa "RG João Silva"
- **THEN** o documento é registrado com o nome "RG João Silva"

#### Scenario: Outro sem nome
- **WHEN** o usuário escolhe "Outro" e deixa o nome vazio
- **THEN** o sistema recusa e pede o nome do documento

### Requirement: Tipo legível na lista de documentos
A lista de documentos registrados SHALL mostrar o nome do tipo, e MUST NOT mostrar a chave interna de um tipo conhecido. O tipo MUST NOT ser repetido quando for igual ao nome do documento.

#### Scenario: Nome diferente do tipo
- **WHEN** um documento se chama "RG João Silva" e tem o tipo `rg_cpf`
- **THEN** a lista mostra "RG João Silva" com o selo "RG e CPF"

#### Scenario: Nome igual ao tipo
- **WHEN** um documento se chama "RG e CPF" e tem o tipo `rg_cpf`
- **THEN** a lista mostra "RG e CPF" sem selo
