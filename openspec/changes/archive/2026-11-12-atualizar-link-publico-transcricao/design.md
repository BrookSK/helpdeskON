## Context

`watch.php` serve as duas telas da gravação. A interna (`/videocall/watch/<token>`) consulta `/videocall/recordingInfo/<token>` a cada 4s enquanto a transcrição está na fila. A pública (`/videocall/share/<token>`) não consultava nada, porque `recordingInfo` exige login e devolve dados internos (rascunho da minuta, resumo).

## Decisions

- **Endpoint público separado, não abrir o `recordingInfo`.** O `recordingInfo` devolve rascunho da minuta e texto do resumo. O novo `shareStatus` usa a mesma regra de visibilidade da página pública: transcrição só quando pronta, minuta só depois de enviada (`minutes_sent_at`).
- **Regra pura em `RecordingShareRules::publicStatus`.** Fica testável sem banco e documenta o que pode sair pelo link.
- **Erro com mensagem genérica.** O detalhe técnico do erro de transcrição não sai pelo endpoint público.
- **Polling para quando a transcrição fica pronta.** A página pública não espera a minuta, que só aparece depois de enviada.

## Risks / Trade-offs

- Quem tem o link consegue consultar o status. É o mesmo acesso que o link já dá à página inteira.
