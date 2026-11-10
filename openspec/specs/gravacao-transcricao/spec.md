# gravacao-transcricao Specification

## Purpose
TBD - created by archiving change rodar-fila-transcricao-sem-token. Update Purpose after archive.

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
