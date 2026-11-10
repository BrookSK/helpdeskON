# Design

## Decisões

- A condição de `cron_token` em `processRecordingQueues()` é removida. O gasto com a OpenAI depende só das gravações enviadas, não de quantas vezes o cron é chamado: `RecordingSegment::reserve()` reserva cada segmento com um UPDATE condicional, e `TranscriptionQueueRules::MAX_ATTEMPTS` limita as novas tentativas.
- `/cron/transcribeRecordings` mantém `validateStrictToken()`. Quem agenda esse endpoint escolhe proteger com token.
- Sem a chave da OpenAI, `RecordingTranscriber::run()` continua retornando sem fazer nada. Esse caso fica fora deste change.

## Fora do escopo

- Campo de `cron_token` na tela de Configurações.
- Mostrar erro na gravação quando a chave da OpenAI não está configurada.
- Vídeo incompleto quando o envio dos pedaços para no meio da reunião.
