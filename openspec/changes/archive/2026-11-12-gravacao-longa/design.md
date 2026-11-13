## Context

A gravação v2 envia o vídeo em pedaços de 1 MiB (`recChunkV2`) e o áudio em segmentos separados (`recAudioV2`). O servidor recusa pedaços acima do teto da sessão (2 GiB, `VIDEO_RECORDING_MAX_BYTES`) com 413. O áudio não conta para esse teto. Ao parar, `recFinalizeV2` monta os pedaços, publica e liga os segmentos de áudio à gravação, o que libera a transcrição.

## Decisions

- **Teto de 1 Mbps no vídeo (`videoBitsPerSecond`).** Uma reunião é quase sempre rostos e tela compartilhada; 1 Mbps em 720p mantém a qualidade e põe ~4h dentro de 2 GiB. Não subimos o teto do servidor: disco e tempo de montagem crescem junto.
- **Finalização parcial em vez de falha.** Quando o único erro do envio foi o teto (413), a finalização manda `partial=1` com os pedaços confirmados. O servidor publica marcando `is_incomplete` e liga o áudio da reunião toda. A cópia local completa é baixada para quem gravou.
- **Injeção de duração só até 64 MiB.** O parser EBML sem FFmpeg carrega o arquivo inteiro e percorre byte a byte. Acima de 64 MiB a publicação segue sem a duração (o player perde só a barra de tempo exata). Com FFmpeg, o remux já resolve.
- **Recuperação pelo script de linha de comando existente.** `cron_transcribe.php` já publica sessões abandonadas e transcreve, sem o tempo limite de uma requisição web. Não criamos script novo.

## Risks / Trade-offs

- Gravação acima de ~4h ainda bate no teto. Agora o efeito é um vídeo incompleto com transcrição completa, e não a perda da gravação.
