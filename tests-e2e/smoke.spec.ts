import { test, expect } from '@playwright/test';

/**
 * Teste de fumaça: garante que a infra E2E sobe (servidor PHP embutido) e que
 * o Playwright consegue acessar a aplicação.
 */
test.describe('smoke', () => {
  test('a página de login carrega', async ({ page }) => {
    const resp = await page.goto('/login');
    expect(resp?.status()).toBeLessThan(400);
    // A tela de login tem um campo de senha.
    await expect(page.locator('input[type="password"]')).toBeVisible();
  });

  test('rota desconhecida cai no front controller (sem erro 500)', async ({ page }) => {
    const resp = await page.goto('/rota-que-nao-existe');
    // O roteador manda para o LoginController por padrão — não deve dar 500.
    expect(resp?.status()).toBeLessThan(500);
  });
});
