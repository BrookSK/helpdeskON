import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E:
 * - Leads da sequência: pesquisar por nome/e-mail (sem acento), filtrar pela
 *   situação, contador "x de y", lead tirado por e-mail devolvido identificado.
 * - Botão "E-mails devolvidos": mostra quem saiu das sequências e por quê.
 *   A resposta do servidor é simulada (a leitura real da caixa depende do IMAP).
 * - Card do CRM: e-mail do lead marcado como inválido e envio "Devolvido".
 * - Celular 390/320 sem rolagem lateral.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/sequence-leads-bounce-fixture.php';

type Fixture = { u: string; seqId: number; boardId: number; cardId: number; admin: { id: number; email: string; password: string } };
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

async function waitModal(page: Page, id: string) {
  await expect(page.locator(`#${id}.show`)).toBeVisible();
  await expect.poll(() => page.locator(`#${id}`).evaluate(el => {
    const dialog = el.querySelector('.modal-dialog') as HTMLElement;
    return getComputedStyle(el).opacity === '1' && getComputedStyle(dialog).transform === 'none';
  })).toBe(true);
}

async function openLeads(page: Page) {
  await page.goto(`/sequences/edit/${fx.seqId}`);
  await page.getByRole('button', { name: /Leads/ }).click();
  await waitModal(page, 'partModal');
  await expect(page.locator('#part-list tr[data-part]')).toHaveCount(3);
}

const fakeScan = (u: string) => ({
  success: true, accounts: 1, checked: 120, errors: [],
  bounces: [
    { handled: true, already: false, hard: true, reason: 'Endereço de e-mail não existe (5.1.1)', email: `cestari@${u}.example.test`, contact_name: `Cestari Club ${u}`, stopped: 2 },
    { handled: true, already: false, hard: false, reason: 'Caixa de entrada do lead cheia (5.2.2). O lead continua.', email: `flavia@${u}.example.test`, contact_name: `Flávia Damaso ${u}`, stopped: 0 },
  ],
});

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('pesquisar e filtrar os leads da sequência', async ({ page }, testInfo) => {
    await login(page);
    await openLeads(page);
    const rows = page.locator('#part-list tr[data-part]');
    await expect(page.locator('#part-count')).toHaveText('3 leads');

    // Sem acento e sem maiúsculas acha "Flávia"; destaca o trecho
    await page.locator('#part-search').fill('FLAVIA');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(`Flávia Damaso ${fx.u}`);
    await expect(rows.first().locator('mark').first()).toHaveText('Flávia');
    await expect(page.locator('#part-count')).toHaveText('1 de 3 leads');

    // Pelo e-mail
    await page.locator('#part-search').fill(`gustavo@${fx.u}`);
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('Gustavo Machado');

    await page.locator('#part-search').fill('ninguém-com-esse-nome');
    await expect(rows).toHaveCount(0);
    await expect(page.locator('#part-list')).toContainText('Nenhum lead encontrado');

    // Filtro "E-mail inválido (voltou)"
    await page.locator('#part-search').fill('');
    await page.locator('#part-status').selectOption('bounce');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(`Cestari Club ${fx.u}`);
    await expect(rows.first()).toContainText('Interrompido');
    await expect(rows.first()).toContainText('E-mail inválido (voltou)');
    await expect(rows.first().locator('.badge', { hasText: 'e-mail inválido' })).toBeVisible();
    await page.locator('#part-status').selectOption('active');
    await expect(rows).toHaveCount(2);
    await page.screenshot({ path: testInfo.outputPath('leads-sequencia-pesquisa.png') });
  });

  test('botão E-mails devolvidos mostra quem saiu e por quê', async ({ page }, testInfo) => {
    let calls = 0;
    await page.route('**/sequences/scanBounces', route => { calls++; route.fulfill({ json: fakeScan(fx.u) }); });
    await login(page);
    await page.goto('/sequences');
    await page.locator('#btn-scan-bounces').click();
    await waitModal(page, 'bounceScanModal');
    const body = page.locator('#bounce-scan-body');
    await expect(body).toContainText('últimas 120 mensagens de 1 caixa');
    await expect(body).toContainText('1 lead tirado das sequências');
    await expect(body.locator('.bs-item.hard')).toContainText(`Cestari Club ${fx.u}`);
    await expect(body.locator('.bs-item.hard')).toContainText('saiu de 2 sequências');
    await expect(body.locator('.bs-item:not(.hard)')).toContainText('Temporário');
    expect(calls).toBe(1);
    await page.screenshot({ path: testInfo.outputPath('emails-devolvidos.png') });

    // Pelo modal de leads também, recarregando a lista depois
    await page.locator('#bounceScanModal .btn-close').click();
    await openLeads(page);
    await page.locator('#part-scan-bounces').click();
    await waitModal(page, 'bounceScanModal');
    await expect(page.locator('#bounce-scan-body')).toContainText('1 lead tirado das sequências');
    expect(calls).toBe(2);
  });

  test('card do CRM marca o e-mail como inválido e o envio como devolvido', async ({ page }, testInfo) => {
    await login(page);
    await page.goto(`/crm/board/${fx.boardId}`);
    await page.locator(`.crm-card[data-card-id="${fx.cardId}"]`).click();
    await waitModal(page, 'cardDetailModal');
    await expect(page.locator('#card-email-bounced')).toHaveText('e-mail inválido');
    await page.locator('#tab-card-emails-btn').click();
    await expect(page.locator('#tab-card-emails')).toHaveClass(/show/);
    await expect(page.locator('#ce-summary')).toContainText('1 e-mail devolvido');
    const msg = page.locator('#card-emails-content .ce-msg').first();
    await expect(msg).toHaveClass(/failed/);
    await expect(msg).toContainText('Devolvido em');
    await expect(msg).toContainText('Devolvido: Endereço de e-mail não existe (5.1.1)');
    await page.screenshot({ path: testInfo.outputPath('card-email-devolvido.png') });
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 800 } });

    test('pesquisa dos leads e lista de devolvidos cabem na tela', async ({ page }, testInfo) => {
      await page.route('**/sequences/scanBounces', route => route.fulfill({ json: fakeScan(fx.u) }));
      await login(page);
      await openLeads(page);
      for (const id of ['#part-search', '#part-status', '#part-scan-bounces']) {
        const b = (await page.locator(id).boundingBox())!;
        expect(b.x).toBeGreaterThanOrEqual(0);
        expect(b.x + b.width).toBeLessThanOrEqual(width + 1);
      }
      const overflow = await page.locator('#partModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await page.locator('#part-search').fill('cestari');
      await expect(page.locator('#part-list tr[data-part]')).toHaveCount(1);
      await page.screenshot({ path: testInfo.outputPath(`leads-sequencia-${width}.png`) });

      await page.locator('#part-scan-bounces').click();
      await waitModal(page, 'bounceScanModal');
      const ov2 = await page.locator('#bounceScanModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(ov2).toBeLessThanOrEqual(1);
      await page.screenshot({ path: testInfo.outputPath(`emails-devolvidos-${width}.png`) });
    });
  });
}
