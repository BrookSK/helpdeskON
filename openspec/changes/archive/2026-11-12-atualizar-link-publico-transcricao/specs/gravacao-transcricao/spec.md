## ADDED Requirements

### Requirement: Link público acompanha a transcrição
A página do link público da gravação (`/videocall/share/<token>`) SHALL consultar o status da transcrição enquanto ela estiver na fila ou em processamento e SHALL mostrar a transcrição quando ficar pronta, sem recarregar a página. O status público MUST NOT exigir login e MUST NOT devolver o rascunho da minuta nem o detalhe técnico de um erro.

#### Scenario: Transcrição termina com a página pública aberta
- **WHEN** alguém está com o link público aberto em "Na fila de transcrição" e a transcrição termina
- **THEN** a página mostra a transcrição em poucos segundos, sem recarregar

#### Scenario: Minuta ainda não enviada
- **WHEN** o status público de uma gravação transcrita é consultado e a minuta não foi enviada ao cliente
- **THEN** a resposta traz a transcrição e não traz a minuta

#### Scenario: Transcrição com erro
- **WHEN** o status público de uma gravação com erro de transcrição é consultado
- **THEN** a resposta traz o status de erro com mensagem genérica, sem o detalhe técnico
