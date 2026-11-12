## Why

Uma reunião de pouco mais de 2 horas gravada na produção não apareceu em Gravações, nem a transcrição. O navegador de quem gravava baixou um arquivo ao parar.

Causas encontradas no código:

- A gravação não limita a taxa do vídeo. O Chrome usa cerca de 2,5 Mbps, e o servidor aceita no máximo 2 GiB por gravação. Com ~1h54 o envio do vídeo para, sem aviso.
- Ao parar, a finalização falha por causa do limite e o navegador baixa a cópia local. Nada é publicado, nem o áudio, que subiu inteiro.
- O cron que publica sessões abandonadas, sem FFmpeg, lê o arquivo inteiro na memória para injetar a duração. Com um arquivo grande o PHP morre por falta de memória antes de gravar no banco, em toda execução, e a gravação nunca é publicada.

## What Changes

- O vídeo da gravação passa a ser limitado a 1 Mbps. Cabem cerca de 4 horas em 2 GiB.
- Se o vídeo atingir o limite, quem grava é avisado na hora. A gravação continua no computador e o áudio continua subindo.
- Ao parar depois do limite, o sistema publica o vídeo até o limite (marcado como incompleto), libera a transcrição da reunião toda e baixa a cópia completa no computador de quem gravou.
- A injeção de duração sem FFmpeg pula arquivos acima de 64 MiB. A gravação é publicada sem a duração no cabeçalho em vez de derrubar o cron.

## Capabilities

### New Capabilities

### Modified Capabilities
- `gravacao-transcricao`: gravações longas são publicadas e transcritas.

## Impact

- `app/views/videocall/room.php`, `public/assets/js/recording-upload.js`
- `app/controllers/VideocallController.php` (`recFinalizeV2`, `injectWebmDuration`)
- `app/core/RecordingMedia.php`
- Recuperação da gravação presa na produção: rodar `php cron_transcribe.php <host>` pela linha de comando depois de atualizar o código.
