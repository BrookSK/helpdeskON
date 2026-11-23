import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: Prospecção Automática — a pessoa que criou a campanha consegue saber,
 * sozinha, o que está acontecendo.
 * - Coluna Status mostra a situação em texto (ex.: "Sem resultados no Apollo").
 * - "Hoje" mostra captados / meta.
 * - O painel "Acompanhamento" explica a situação, o que fazer, os números do
 *   dia, o horário, os filtros, os leads captados e um histórico agrupado
 *   (sem o JSON cru que aparecia antes).
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/prospecting-status-fixture.php';

type Fixture = {
  u: string;
  campaigns: { noResults: number; working: number; offHours: number };
  admin: { id: number; email: string; password: string };
};

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}

let fx: Fixture;

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { fx = JSON.parse(php(['setup'])); });
test.afterAll(() => { if (fx) php(['teardown', JSON.stringify(fx)]); });

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(fx.admin.email);
  await page.locator('input[name="password"]').fill(fx.admin.password);
  await Promise.all([page.waitForURL(/dashboard/), page.locator('button[type="submit"]').click()]);
}

const row = (page: Page, id: number) => page.locator(`#tab-campaigns tr[data-id="${id}"]`);

async function openPanel(page: Page, id: number) {
  await row(page, id).locator('.camp-situation').click();
  const modal = page.locator('#campLogModal.show');
  await expect(modal).toBeVisible();
  await expect(modal.locator('.cl-hero')).toBeVisible();
  // Espera a animação do modal terminar (screenshots fiéis).
  await expect.poll(() => page.locator('#campLogModal').evaluate(el => {
    const dialog = el.querySelector('.modal-dialog') as HTMLElement;
    return getComputedStyle(el).opacity === '1' && getComputedStyle(dialog).transform === 'none';
  })).toBe(true);
  return modal;
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('tabela mostra a situação de cada campanha em texto', async ({ page }) => {
    await login(page);
    await page.goto('/crm/prospecting');
    await expect(row(page, fx.campaigns.noResults).locator('.camp-situation')).toHaveText(/Sem resultados no Apollo/);
    await expect(row(page, fx.campaigns.noResults).locator('.camp-situation')).toHaveClass(/text-warning/);
    await expect(row(page, fx.campaigns.working).locator('.camp-situation')).toHaveText(/Funcionando \(2\/50\)/);
    await expect(row(page, fx.campaigns.working).locator('.camp-situation')).toHaveClass(/text-success/);
    await expect(row(page, fx.campaigns.offHours).locator('.camp-situation')).toHaveText(/Volta amanhã às 08:00/);
    // "Hoje" = captados / meta
    await expect(row(page, fx.campaigns.working).locator('td').nth(5)).toHaveText('2 / 50');
    await expect(row(page, fx.campaigns.noResults).locator('td').nth(5)).toHaveText('0 / 50');
  });

  test('painel explica "sem resultados" e agrupa as execuções repetidas', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/crm/prospecting');
    const modal = await openPanel(page, fx.campaigns.noResults);

    await expect(modal.locator('#camp-log-name')).toContainText('E2E Sem resultados');
    await expect(modal.locator('.cl-hero h6')).toHaveText('Rodando, mas sem resultados');
    await expect(modal.locator('.cl-todo')).toContainText('afrouxe os filtros');
    await expect(modal.locator('.cl-stat').nth(2)).toContainText('8');            // execuções hoje
    await expect(modal.locator('.cl-stat').nth(2)).toContainText('última há 1 min');
    await expect(modal).toContainText('Dentro do horário agora');

    // Filtros em linguagem simples
    const filters = modal.locator('.cl-filters');
    await expect(filters).toContainText('CEO, Diretor');
    await expect(filters).toContainText('11 a 50');
    await expect(filters).toContainText('logística');
    await expect(filters).toContainText('Dono, C-Level');

    // 8 execuções iguais viram UMA linha "8x" — sem JSON cru visível.
    const history = modal.locator('.cl-tl').last().locator('li');
    await expect(history).toHaveCount(1);
    await expect(history.first()).toContainText('O Apollo não encontrou nenhuma pessoa');
    await expect(history.first()).toContainText('8x');
    const visibleText = await modal.locator('.modal-body').evaluate(el => (el as HTMLElement).innerText);
    expect(visibleText).not.toContain('"campaign":');
    expect(visibleText).toContain('Rodando, mas sem resultados');
    // O cru continua acessível, recolhido, para o suporte.
    await expect(modal.locator('details summary')).toHaveText(/Detalhes técnicos/);
    await page.screenshot({ path: testInfo.outputPath('prosp-desktop-sem-resultados.png') });

    // "Editar campanha" abre o formulário da campanha
    await modal.locator('#camp-log-edit').click();
    await expect(page.locator('#campLogModal')).toBeHidden();
    await expect(page.locator('#camp-name')).toHaveValue(/E2E Sem resultados/);
  });

  test('painel de campanha funcionando lista os leads e os motivos de descarte', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/crm/prospecting');
    const modal = await openPanel(page, fx.campaigns.working);
    await expect(modal.locator('.cl-hero h6')).toHaveText('Funcionando');
    await expect(modal.locator('.cl-stat').first()).toContainText('2');
    await expect(modal.locator('.cl-stat').first()).toContainText('de 50');
    await expect(modal.locator('.cl-stat').nth(1)).toContainText('40');
    await expect(modal.locator('.cl-stat').nth(3)).toContainText('2');           // créditos
    await expect(modal).toContainText('30 já estavam no sistema');
    await expect(modal).toContainText('8 com score abaixo do mínimo');
    await expect(modal.locator('.cl-tl').first()).toContainText('Ana E2E');
    await expect(modal.locator('.cl-tl').first()).toContainText('Bruno E2E');
    await expect(modal.locator('.cl-tl').last()).toContainText('2 leads novos captados');
    await page.screenshot({ path: testInfo.outputPath('prosp-desktop-funcionando.png') });
  });

  test('fora do horário diz quando volta a rodar', async ({ page }) => {
    await login(page);
    await page.goto('/crm/prospecting');
    const modal = await openPanel(page, fx.campaigns.offHours);
    await expect(modal.locator('.cl-hero h6')).toHaveText('Fora do horário de captação');
    await expect(modal.locator('.cl-hero')).toContainText('amanhã às 08:00');
    await expect(modal).toContainText('Fora do horário agora');
    await expect(modal).toContainText('A campanha ainda não rodou nenhuma vez.');
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('painel ocupa a tela e não tem rolagem lateral', async ({ page }, testInfo) => {
      await login(page);
      await page.goto('/crm/prospecting');
      await row(page, fx.campaigns.noResults).scrollIntoViewIfNeeded();
      const modal = await openPanel(page, fx.campaigns.noResults);
      const box = await modal.locator('.modal-content').boundingBox();
      expect(Math.round(box!.width)).toBe(width);
      const overflow = await modal.locator('.modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await expect(modal.locator('#camp-log-refresh')).toBeInViewport();
      await page.screenshot({ path: testInfo.outputPath(`prosp-${width}-topo.png`) });
      await modal.locator('.cl-tl').last().scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`prosp-${width}-historico.png`) });

      // "Atualizar" recarrega sem fechar
      await modal.locator('#camp-log-refresh').click();
      await expect(modal.locator('#camp-log-updated')).toContainText('Atualizado às');
      await expect(modal.locator('.cl-hero h6')).toHaveText('Rodando, mas sem resultados');
    });
  });
}
