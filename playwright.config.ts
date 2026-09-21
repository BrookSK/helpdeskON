import { defineConfig, devices } from '@playwright/test';

/**
 * Configuração do Playwright (testes E2E) do helpdeskON.
 *
 * A aplicação é servida pelo servidor embutido do PHP (php -S) com um router
 * que replica o .htaccess (tests-e2e/server-router.php). O Playwright sobe esse
 * servidor automaticamente (webServer) numa porta local estável e o derruba ao
 * final — sem depender da configuração de vhost do WAMP.
 *
 * Observação: o front usa o mesmo banco detectado por config/database.php
 * (ambiente local = helpdesk_on). Os testes E2E não escrevem no banco de teste
 * do PHPUnit; criam seus próprios dados via API/UI e limpam quando aplicável.
 */

const PHP = process.env.PHP_BIN || 'C:\\php\\php.exe';
const HOST = '127.0.0.1';
const PORT = Number(process.env.E2E_PORT || 8199);
const BASE_URL = `http://${HOST}:${PORT}`;

export default defineConfig({
  testDir: './tests-e2e',
  // Só arquivos .spec.ts são testes (evita pegar helpers/utilitários).
  testMatch: '**/*.spec.ts',
  fullyParallel: false,
  workers: 1,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  retries: 0,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'tests-e2e/report' }]],
  outputDir: 'tests-e2e/.artifacts',

  use: {
    baseURL: BASE_URL,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    video: 'off',
    // Concede permissões de câmera/microfone para os testes da sala de vídeo,
    // e usa mídia falsa (sem hardware) no Chromium.
    permissions: ['camera', 'microphone'],
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: [
            '--use-fake-ui-for-media-stream',
            '--use-fake-device-for-media-stream',
          ],
        },
      },
    },
  ],

  // Sobe a aplicação via servidor embutido do PHP e espera responder.
  webServer: {
    command: `"${PHP}" -S ${HOST}:${PORT} tests-e2e/server-router.php`,
    url: BASE_URL + '/login',
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
});
