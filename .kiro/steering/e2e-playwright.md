# Testes E2E (Playwright) — helpdeskON

Regras e instruções para os testes de ponta a ponta (navegador) do projeto.

## Ambiente

- Node.js + npm já instalados. Dependência: `@playwright/test` (em `node_modules`).
- Navegador: Chromium do Playwright (baixado via `npx playwright install chromium`).
- PHP: `C:\php\php.exe` (o Playwright sobe a app com o servidor embutido do PHP).

## Como a app é servida nos testes

- `playwright.config.ts` inicia automaticamente um servidor PHP embutido
  (`php -S 127.0.0.1:8199 tests-e2e/server-router.php`) e o derruba ao final.
- `tests-e2e/server-router.php` replica o `.htaccess`: serve assets/uploads e
  arquivos reais; qualquer outra rota vai para `public/index.php` com `?url=`.
- baseURL dos testes: `http://127.0.0.1:8199`. Para trocar a porta use `E2E_PORT`.
- Para apontar outro PHP use `PHP_BIN`.

## Banco de dados

- O front nos testes E2E usa o banco detectado por `config/database.php`
  (ambiente local = `helpdesk_on`). Diferente do PHPUnit, que usa `helpdesk_on_test`.
- Testes E2E devem criar os próprios dados (via UI ou script) e evitar deixar
  lixo. Ex.: `tests-e2e/make-room.php` cria uma sala pública e imprime o token.

## Como rodar

- Suíte completa: `run_e2e.bat` (grava a saída em `e2e_result.txt`) ou `npx playwright test`.
- Com navegador visível: `npm run test:e2e:headed`.
- Modo UI interativo: `npm run test:e2e:ui`.
- Relatório HTML: `npm run test:e2e:report` (após uma execução).

## Convenções

- Arquivos de teste ficam em `tests-e2e/` e terminam em `.spec.ts`.
- Utilitários/helpers (não-teste) podem coexistir em `tests-e2e/` sem `.spec.ts`.
- Fluxos que exigem câmera/microfone: o config já concede permissões e usa
  mídia falsa no Chromium (`--use-fake-device-for-media-stream`).

## Quando usar E2E vs PHPUnit

- PHPUnit: regras de negócio e persistência (models + banco de teste).
- Playwright/E2E: comportamento pela interface (fluxos de usuário, estado visual
  de botões/ícones, navegação). Ex.: o ícone de câmera refletir o preview ao
  entrar na sala de vídeo.
