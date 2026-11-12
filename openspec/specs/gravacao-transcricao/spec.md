# gravacao-transcricao Specification

## Purpose
Transcrição no servidor das gravações de reunião: a fila de segmentos de áudio é processada pelos crons agendados, sem depender de configuração extra.

## Requirements

### Requirement: Fila de transcrição nos crons agendados
Os crons `/cron/runProspecting` e `/cron/finalizeRecordings` SHALL processar um lote da fila de transcrição das gravações a cada execução. O processamento MUST NOT depender de `cron_token` estar configurado.

#### Scenario: Produção sem cron_token
- **WHEN** `cron_token` não está configurado e o cron `/cron/runProspecting` roda
- **THEN** o sistema transcreve um lote dos segmentos de áudio na fila

#### Scenario: Gravação na fila
- **WHEN** uma gravação tem segmentos de áudio pendentes e o cron roda
- **THEN** os segmentos do lote são enviados para transcrição e a gravação sai de "Na fila de transcrição"

### Requirement: Endpoint dedicado protegido
O endpoint `/cron/transcribeRecordings` MUST exigir `cron_token` configurado e o token correto na chamada.

#### Scenario: Chamada sem token
- **WHEN** `/cron/transcribeRecordings` é chamado sem o token correto
- **THEN** o sistema responde 403 e não transcreve nada

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
