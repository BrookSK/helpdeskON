import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: pedidos da Evelyn para a Prospecção Automática.
 * - Botão "Duplicar campanha" na linha: abre o formulário preenchido com tudo da
 *   original, como campanha nova, "(cópia)" no nome e inativa. Salvar cria outra
 *   campanha com as mesmas configurações e a original fica igual.
 * - Intervalo variado entre WhatsApps: configurado na sequência (de X a Y min),
 *   com explicação, e lembrado no formulário da campanha.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/campaign-duplicate-fixture.php';

type Fixture = { u: string; sequenceId: number; campaignId: number; admin: { id: number; email: string; password: string } };
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

const row = (page: Page, id: number) => page.locator(`#tab-campaigns tr[data-id="${id}"]`);
const campaigns = () => JSON.parse(php(['campaigns', fx.u])) as any[];

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('duplicar campanha: formulário vem preenchido, salva como nova e inativa', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/crm/prospecting');
    const dupBtn = row(page, fx.campaignId).getByRole('button', { name: 'Duplicar campanha' });
    await expect(dupBtn).toBeVisible();
    await dupBtn.click();
    await waitModal(page, 'campaignModal');

    await expect(page.locator('#camp-modal-title')).toHaveText('Duplicar campanha');
    await expect(page.locator('#camp-copy-note')).toBeVisible();
    await expect(page.locator('#camp-copy-note')).toContainText('inativa');
    await expect(page.locator('#camp-id')).toHaveValue('');
    await expect(page.locator('#camp-name')).toHaveValue(`E2E Captação Site 11-50 ${fx.u} (cópia)`);
    await expect(page.locator('#camp-active')).not.toBeChecked();
    await expect(page.locator('#camp-sequence')).toHaveValue(String(fx.sequenceId));
    await expect(page.locator('#camp-daily')).toHaveValue('20');
    await expect(page.locator('#camp-minscore')).toHaveValue('60');
    await expect(page.locator('#camp-perpage')).toHaveValue('40');
    await expect(page.locator('#camp-wstart')).toHaveValue('09:00');
    await expect(page.locator('#camp-wend')).toHaveValue('17:00');
    await expect(page.locator('#camp-reveal-phone')).toBeChecked();
    // Sequência de WhatsApp: o formulário lembra o intervalo entre mensagens
    await expect(page.locator('#camp-channel-hint')).toContainText('intervalo variado de 3 a 12 min');
    await page.screenshot({ path: testInfo.outputPath('campanha-duplicar.png') });

    // Muda só o necessário (ex.: meta) e salva
    await page.locator('#camp-daily').fill('25');
    await Promise.all([page.waitForEvent('load'), page.locator('#camp-save').click()]);

    const rows = campaigns();
    expect(rows).toHaveLength(2);
    const orig = rows.find(r => +r.id === fx.campaignId);
    const copy = rows.find(r => +r.id !== fx.campaignId);
    expect(copy.name).toBe(`E2E Captação Site 11-50 ${fx.u} (cópia)`);
    expect(+copy.is_active).toBe(0);
    expect(+copy.sequence_id).toBe(fx.sequenceId);
    expect(+copy.daily_target).toBe(25);
    expect(+copy.min_score).toBe(60);
    expect(+copy.search_per_page).toBe(40);
    expect(+copy.search_page).toBe(1);
    expect(copy.window_start).toBe('09:00:00');
    expect(copy.window_end).toBe('17:00:00');
    expect(copy.days_of_week).toBe('1,2,3,4,5');
    expect(+copy.reveal_email).toBe(1);
    expect(+copy.reveal_phone).toBe(1);
    const f = JSON.parse(copy.search_filters);
    // O salvar da campanha grava os cargos em minúsculas (igual ao editar)
    expect(f.person_titles.map((t: string) => t.toLowerCase())).toEqual(['ceo', 'diretor']);
    expect(f.organization_num_employees_ranges).toEqual(['11,50']);
    expect(JSON.stringify(f)).toContain('engenharia');
    const icp = JSON.parse(copy.icp_rules);
    expect(+icp.employee_min).toBe(11);
    expect(+icp.employee_max).toBe(50);
    // A original não mudou
    expect(+orig.is_active).toBe(1);
    expect(+orig.daily_target).toBe(20);
    // A cópia aparece na lista como Inativa
    await expect(row(page, +copy.id)).toContainText('Inativa');
  });

  test('duplicar de novo não repete o nome', async ({ page }) => {
    await login(page);
    await page.goto('/crm/prospecting');
    await row(page, fx.campaignId).getByRole('button', { name: 'Duplicar campanha' }).click();
    await waitModal(page, 'campaignModal');
    await expect(page.locator('#camp-name')).toHaveValue(`E2E Captação Site 11-50 ${fx.u} (cópia 2)`);
    // "Nova campanha" depois volta ao normal (sem aviso de cópia)
    await page.locator('#campaignModal .btn-close').click();
    await expect(page.locator('#campaignModal')).toBeHidden();
    await page.evaluate(() => (window as any).openCampaign());
    await waitModal(page, 'campaignModal');
    await expect(page.locator('#camp-modal-title')).toHaveText('Nova campanha');
    await expect(page.locator('#camp-copy-note')).toBeHidden();
  });

  test('sequência: intervalo entre WhatsApps aparece, explica e vai no salvar', async ({ page }, testInfo) => {
    await login(page);
    await page.goto(`/sequences/edit/${fx.sequenceId}`);
    await page.getByRole('button', { name: /Configurações/ }).click();
    await expect(page.locator('#seq-config.show')).toBeVisible();
    await expect(page.locator('#seq-gap-min')).toHaveValue('3');
    await expect(page.locator('#seq-gap-max')).toHaveValue('12');
    await expect(page.locator('#seq-gap-hint')).toContainText('entre 3 e 12 min');
    await expect(page.locator('#seq-gap-hint')).toContainText('20 mensagens');
    await page.screenshot({ path: testInfo.outputPath('sequencia-intervalo.png') });

    await page.locator('#seq-gap-min').fill('0');
    await page.locator('#seq-gap-max').fill('0');
    await expect(page.locator('#seq-gap-hint')).toContainText('risco de bloqueio');
    await page.locator('#seq-gap-min').fill('5');
    await page.locator('#seq-gap-max').fill('5');
    await expect(page.locator('#seq-gap-hint')).toContainText('Sempre 5 min');
    await page.locator('#seq-gap-min').fill('15');
    await page.locator('#seq-gap-max').fill('4');

    // O que o botão Salvar envia (o banco local está sem colunas antigas da
    // sequência, então a resposta do servidor é simulada aqui).
    let posted = '';
    await page.route('**/sequences/save', async route => {
      posted = route.request().postData() || '';
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, id: fx.sequenceId }) });
    });
    await page.evaluate(() => (window as any).saveSeq());
    await expect.poll(() => posted).toContain('wa_gap_min_minutes');
    expect(posted).toMatch(/name="wa_gap_min_minutes"\r\n\r\n15\r\n/);
    expect(posted).toMatch(/name="wa_gap_max_minutes"\r\n\r\n4\r\n/);
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('botão Duplicar, formulário e intervalo cabem na tela', async ({ page }, testInfo) => {
      await login(page);
      await page.goto('/crm/prospecting');
      const docOverflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(docOverflow).toBeLessThanOrEqual(1);
      const btn = row(page, fx.campaignId).getByRole('button', { name: 'Duplicar campanha' });
      await btn.scrollIntoViewIfNeeded();
      await expect(btn).toBeVisible();
      await btn.click();
      await waitModal(page, 'campaignModal');
      await expect(page.locator('#camp-copy-note')).toBeVisible();
      const overflow = await page.locator('#campaignModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await page.screenshot({ path: testInfo.outputPath(`campanha-duplicar-${width}.png`) });
      await page.locator('#campaignModal .btn-close').click();
      await expect(page.locator('#campaignModal')).toBeHidden();

      await page.goto(`/sequences/edit/${fx.sequenceId}`);
      await page.getByRole('button', { name: /Configurações/ }).click();
      await expect(page.locator('#seq-config.show')).toBeVisible();
      await page.locator('#seq-wa-gap').scrollIntoViewIfNeeded();
      const box = await page.locator('#seq-wa-gap').boundingBox();
      expect(box!.x + box!.width).toBeLessThanOrEqual(width + 1);
      const seqOverflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(seqOverflow).toBeLessThanOrEqual(1);
      await page.screenshot({ path: testInfo.outputPath(`sequencia-intervalo-${width}.png`) });
    });
  });
}
