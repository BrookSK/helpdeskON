## Why

A página do link público da gravação (`/videocall/share/<token>`) fica em "Na fila de transcrição" mesmo depois que a transcrição termina. A fila funciona (no beta, um vídeo curto foi transcrito em 45 segundos), mas a página pública não consulta o status: só a tela interna faz isso, e o endpoint que ela usa exige login. Quem abre o link pelo botão da sala vê a gravação parada até recarregar a página.

## What Changes

- Novo endpoint público `/videocall/shareStatus/<token>`, sem login, que devolve só o que a página pública já mostra: status, transcrição pronta e minuta já enviada.
- A página pública passa a consultar esse status enquanto a transcrição estiver na fila ou em processamento e mostra a transcrição sem recarregar.

## Capabilities

### New Capabilities

### Modified Capabilities
- `gravacao-transcricao`: o link público acompanha a transcrição até ficar pronta.

## Impact

- `app/controllers/VideocallController.php` (ação `shareStatus`)
- `app/core/RecordingShareRules.php` (novo)
- `app/views/videocall/watch.php` (polling também na página pública)
- Testes: `tests/Unit/RecordingShareRulesTest.php`, `tests-e2e/meeting-flow.spec.ts`
