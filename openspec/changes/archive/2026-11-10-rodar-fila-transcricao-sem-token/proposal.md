# Proposal

## Why

Em 2026-11-09, uma reunião de cerca de 40 minutos gravada na produção ficou parada em "Na fila de transcrição", sem erro. A produção chama `/cron/runProspecting` a cada minuto, sem token. O passo da transcrição dentro desse cron só roda quando `cron_token` está configurado, e a tela de Configurações não tem campo para ele. Resultado: a fila nunca anda e ninguém vê o motivo.

## What Changes

- Os crons já agendados (`/cron/runProspecting` e `/cron/finalizeRecordings`) passam a rodar um lote da fila de transcrição mesmo sem `cron_token` configurado.
- `/cron/transcribeRecordings` continua exigindo token. Ele é o endpoint dedicado e segue protegido.
- Disparar o cron mais vezes não aumenta o gasto com a OpenAI: só são transcritos segmentos que já estão na fila, com reserva atômica e no máximo 3 tentativas por segmento.

## Capabilities

### New Capabilities
- `gravacao-transcricao`: fila de transcrição das gravações processada pelos crons do servidor.

### Modified Capabilities

Nenhuma.

## Impact

- `app/controllers/CronController.php`: `processRecordingQueues()` sem a condição de `cron_token`; texto de ajuda do endpoint.
- `tests/Integration/CronRecordingQueueTest.php`: teste novo.
- Sem migration e sem configuração nova.
