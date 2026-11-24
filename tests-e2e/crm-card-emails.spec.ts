import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: aba "E-mails" no card do CRM.
 * - Contagem na aba (verde quando o lead respondeu).
 * - Conversa em ordem: envio da sequência → resposta do lead → nossa resposta
 *   pela Caixa de entrada → nova resposta do lead.
 * - "Ver e-mail completo": HTML do envio num quadro isolado; resposta como texto.
 * - Envio que falhou aparece com o erro; assunto/texto com HTML não executa.
 * - Card sem e-mails mostra a explicação. Celular 390/320 sem rolagem lateral.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/crm-card-emails-fixture.php';

type Fixture = { u: string; boardId: number; cardId: number; emptyCardId: number; leadEmail: string; admin: { id: number; email: string; password: string } };
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

async function openCardEmails(page: Page, cardId: number) {
  await page.goto(`/crm/board/${fx.boardId}`);
  await page.locator(`.crm-card[data-card-id="${cardId}"]`).click();
  await waitModal(page, 'cardDetailModal');
  await page.locator('#tab-card-emails-btn').click();
  await waitEmailsTab(page);
}

/** Aba E-mails de fato visível (terminou o fade) e carregada. */
async function waitEmailsTab(page: Page) {
  await expect(page.locator('#tab-card-emails')).toHaveClass(/show/);
  await expect.poll(() => page.locator('#tab-card-emails').evaluate(el => getComputedStyle(el).opacity)).toBe('1');
  await expect(page.locator('#card-emails-content')).not.toContainText('Carregando e-mails');
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 900 } });

  test('conversa completa: enviado, resposta do lead, nossa resposta e nova resposta', async ({ page }, testInfo) => {
    let dialogs = 0;
    page.on('dialog', d => { dialogs++; d.dismiss(); });
    await login(page);
    await page.goto(`/crm/board/${fx.boardId}`);
    await page.locator(`.crm-card[data-card-id="${fx.cardId}"]`).click();
    await waitModal(page, 'cardDetailModal');
    const badge = page.locator('#card-emails-count');
    await expect(badge).toHaveText('5');
    await expect(badge).toHaveClass(/bg-success/);

    await page.locator('#tab-card-emails-btn').click();
    await waitEmailsTab(page);
    const box = page.locator('#card-emails-content');
    await expect(page.locator('#ce-summary')).toContainText('2 e-mails enviados');
    await expect(page.locator('#ce-summary')).toContainText('2 respostas do lead');
    await expect(page.locator('#ce-summary')).toContainText('1 envio falhou');

    const convs = box.locator('.ce-conv');
    await expect(convs).toHaveCount(2);
    const main = convs.nth(0);
    await expect(main.locator('.ce-conv-subject')).toHaveText(/Proposta ON/);
    await expect(main).toContainText('Lead respondeu');
    const msgs = main.locator('.ce-msg');
    await expect(msgs).toHaveCount(4);
    await expect(msgs.nth(0)).toHaveClass(/out/);
    await expect(msgs.nth(0)).toContainText(`Enviado por Evelyn E2E ${fx.u}`);
    await expect(msgs.nth(0)).toContainText(`Sequência: E2E Seq Mail ${fx.u}`);
    await expect(msgs.nth(0)).toContainText('Aberto 2x');
    await expect(msgs.nth(0)).toContainText('Respondido em');
    await expect(msgs.nth(1)).toHaveClass(/in/);
    await expect(msgs.nth(1)).toContainText('Resposta do lead');
    await expect(msgs.nth(1)).toContainText('Tenho interesse.');
    await expect(msgs.nth(2)).toContainText('Resposta pela Caixa de entrada');
    await expect(msgs.nth(2)).toContainText('Claro! Amanhã às 15h?');
    await expect(msgs.nth(3)).toContainText('Combinado.');

    // E-mail enviado completo: HTML num quadro isolado, com a formatação
    await msgs.nth(0).getByRole('button', { name: /Ver e-mail completo/ }).click();
    const frame = page.frameLocator(`.ce-msg[data-key] iframe.ce-frame`).first();
    await expect(frame.locator('strong')).toHaveText('Olá Carla');
    await expect(frame.locator('em')).toHaveText('proposta');
    await expect(msgs.nth(0).locator('iframe')).toHaveAttribute('sandbox', /allow-same-origin/);
    expect(await msgs.nth(0).locator('iframe').getAttribute('sandbox')).not.toContain('allow-scripts');

    // Resposta do lead completa: texto com a quebra de linha; HTML vira texto
    await msgs.nth(1).getByRole('button', { name: /Ver e-mail completo/ }).click();
    const full = msgs.nth(1).locator('.ce-msg-text');
    await expect(full).toBeVisible();
    expect(await full.innerText()).toContain('Tenho interesse.\nPode me ligar amanhã? <script>alert(1)</script>');
    await expect(msgs.nth(1).locator('script')).toHaveCount(0);
    await msgs.nth(1).getByRole('button', { name: /Recolher/ }).click();
    await expect(full).toBeHidden();

    // Envio que falhou, com assunto "perigoso" mostrado como texto
    const failed = convs.nth(1);
    await expect(failed.locator('.ce-conv-subject')).toContainText('<img src=x onerror=alert(1)> Novidades');
    await expect(failed.locator('img')).toHaveCount(0);
    await expect(failed.locator('.ce-msg.failed')).toContainText('Falhou');
    await expect(failed).toContainText('Erro: SMTP recusou');
    await page.screenshot({ path: testInfo.outputPath('card-emails-desktop.png') });
    expect(dialogs).toBe(0);
  });

  test('card sem e-mails explica o que aparece ali', async ({ page }) => {
    await login(page);
    await openCardEmails(page, fx.emptyCardId);
    await expect(page.locator('#card-emails-count')).toBeHidden();
    await expect(page.locator('#card-emails-content')).toContainText('Nenhum e-mail com este lead ainda');
    // Trocar de card com a aba aberta recarrega para o card certo
    await page.locator('#cardDetailModal .btn-close').click();
    await expect(page.locator('#cardDetailModal')).toBeHidden();
    await page.locator(`.crm-card[data-card-id="${fx.cardId}"]`).click();
    await waitModal(page, 'cardDetailModal');
    await expect(page.locator('#card-emails-content .ce-msg')).toHaveCount(5);
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 800 } });

    test('aba E-mails cabe na tela', async ({ page }, testInfo) => {
      await login(page);
      await openCardEmails(page, fx.cardId);
      const msgs = page.locator('#card-emails-content .ce-msg');
      await expect(msgs).toHaveCount(5);
      await msgs.nth(0).getByRole('button', { name: /Ver e-mail completo/ }).click();
      await expect(page.frameLocator('iframe.ce-frame').first().locator('strong')).toHaveText('Olá Carla');
      const overflow = await page.locator('#cardDetailModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      for (let i = 0; i < 5; i++) {
        const b = (await msgs.nth(i).boundingBox())!;
        expect(b.x).toBeGreaterThanOrEqual(0);
        expect(b.x + b.width).toBeLessThanOrEqual(width + 1);
      }
      const tabs = (await page.locator('#tab-card-emails-btn').boundingBox())!;
      expect(tabs.x + tabs.width).toBeLessThanOrEqual(width + 1);
      // O que está na tela no meio da aba é a conversa (não um espaço em branco)
      const hit = await page.evaluate(() => {
        const el = document.elementFromPoint(window.innerWidth / 2, 330) as HTMLElement | null;
        return !!(el && el.closest('#card-emails-content'));
      });
      expect(hit).toBe(true);
      await page.screenshot({ path: testInfo.outputPath(`card-emails-${width}.png`) });
    });
  });
}
