import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * E2E do bug corrigido: ao desligar a câmera no preview (lobby) e entrar, o
 * ícone de câmera na toolbar deve refletir o estado "desativado" (classe .off /
 * vermelho). Antes da correção, a câmera abria desligada mas o ícone continuava
 * como "ligado".
 */

const PHP = process.env.PHP_BIN || 'C:\\php\\php.exe';
const ROOT = path.resolve(__dirname, '..');

/** Cria uma sala pública de teste e devolve o token. */
function createRoom(): string {
  const out = execFileSync(PHP, ['tests-e2e/make-room.php'], { cwd: ROOT });
  return out.toString().trim();
}

test.describe('sala de vídeo — ícone de câmera', () => {
  test('entrar com a câmera desligada no preview deixa o ícone da toolbar como off', async ({ page }) => {
    const token = createRoom();
    expect(token).toMatch(/^[a-f0-9]{32}$/);

    await page.goto(`/videocall/room/${token}`);

    // Lobby visível e câmera começa ligada (botão sem .off).
    const lobby = page.locator('#lobby');
    await expect(lobby).toBeVisible();
    const lbCam = page.locator('#lb-cam');
    await expect(lbCam).toBeVisible();
    await expect(lbCam).not.toHaveClass(/\boff\b/);

    // Desliga a câmera no preview.
    await lbCam.click();
    await expect(lbCam).toHaveClass(/\boff\b/);

    // Entra na chamada.
    await page.locator('#lb-join').click();

    // A sala aparece e o ícone de câmera da toolbar precisa estar "off".
    const call = page.locator('#call');
    await expect(call).toBeVisible({ timeout: 15_000 });

    const btnCam = page.locator('#btn-cam');
    await expect(btnCam).toBeVisible();
    // >>> A asserção do bug: o ícone reflete a câmera desativada. <<<
    await expect(btnCam).toHaveClass(/\boff\b/);
    // E o microfone (que ficou ligado) NÃO deve estar off.
    await expect(page.locator('#btn-mic')).not.toHaveClass(/\boff\b/);
  });

  test('entrar com a câmera ligada mantém o ícone normal (sem off)', async ({ page }) => {
    const token = createRoom();
    await page.goto(`/videocall/room/${token}`);

    await expect(page.locator('#lobby')).toBeVisible();
    // Não mexe na câmera (fica ligada) e entra.
    await page.locator('#lb-join').click();

    await expect(page.locator('#call')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('#btn-cam')).not.toHaveClass(/\boff\b/);
  });
});
