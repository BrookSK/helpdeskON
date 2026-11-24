import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: CRM
 * - O card mostra cargo/empresa, e-mail e a origem (Automática/Manual/Planilha).
 * - O detalhe do card tem o bloco "Dados do lead" (e-mail, cargo, empresa, LinkedIn).
 * - "Importar planilha": baixa o modelo, importa CSV (formato da exportação do
 *   Apollo), cria leads + cards na coluna escolhida e lista as linhas recusadas.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/crm-lead-import-fixture.php';

type Fixture = {
  u: string; boardId: number; columnId: number; contactId: number; cardId: number;
  admin: { id: number; email: string; password: string };
};
type ContactRow = { id: number; assigned_to: number; job_title: string | null; company_name: string | null; cards: number; origin: string | null; column_id: number; lead_source: string | null } | null;

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}
const contact = (email: string): ContactRow => JSON.parse(php(['contact', email]));

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

const cardEl = (page: Page, id: number) => page.locator(`.crm-card[data-card-id="${id}"]`);

/** CSV no formato da exportação de contatos do Apollo. */
function apolloCsv(u: string): string {
  return [
    'First Name,Last Name,Title,Company,Email,Mobile Phone,Person Linkedin Url,Website,City,State',
    `Bruno,Planilha ${u},CEO,Padaria São João,bruno@${u}.example.test,+55 99 98888-1111,http://www.linkedin.com/in/bruno-${u},http://padaria.example,Curitiba,PR`,
    `Duda,Planilha ${u},Sócia,"Lima, Costa & Cia",duda@${u}.example.test,,,,,`,
    `Sem,Contato ${u},Gerente,Empresa X,,,,,,`,
  ].join('\n');
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('card mostra cargo, empresa, e-mail e origem; detalhe tem "Dados do lead"', async ({ page }, testInfo) => {
    await login(page);
    await page.goto(`/crm/board/${fx.boardId}`);
    const c = cardEl(page, fx.cardId);
    await expect(c.locator('.crm-card-role')).toHaveText(/Diretora Clínica · Clínica Sorriso/);
    await expect(c.locator('.crm-origin-badge')).toHaveText(/Automática/);
    await expect(c).toContainText(`carla@${fx.u}.example.test`);

    await c.click();
    await waitModal(page, 'cardDetailModal');
    const prof = page.locator('#card-lead-profile');
    await expect(prof).toBeVisible();
    await expect(prof).toContainText('Dados do lead');
    await expect(prof.locator('a[href^="mailto:"]')).toHaveText(`carla@${fx.u}.example.test`);
    await expect(prof).toContainText('Diretora Clínica');
    await expect(prof).toContainText('Clínica Sorriso');
    await expect(prof.locator(`a[href="https://www.linkedin.com/in/carla-${fx.u}"]`)).toBeVisible();
    await expect(page.locator('#card-origin-badge')).toHaveText(/Prospecção automática · Captação Site Operacional/);
    await page.screenshot({ path: testInfo.outputPath('crm-card-detalhe.png') });
  });

  test('importar planilha do Apollo cria leads e cards e lista as linhas recusadas', async ({ page }, testInfo) => {
    await login(page);
    await page.goto(`/crm/board/${fx.boardId}`);
    await page.locator('#btn-import-sheet').click();
    await waitModal(page, 'importSheetModal');

    // Modelo para download
    const tpl = await page.request.get('/crm/leadsSheetTemplate');
    expect(tpl.ok()).toBe(true);
    expect(tpl.headers()['content-type']).toContain('text/csv');
    expect(await tpl.text()).toContain('"Nome";"Sobrenome";"E-mail";"Telefone";"Empresa";"Cargo"');

    // Board atual já vem selecionado.
    await expect(page.locator('#is-board')).toHaveValue(String(fx.boardId));
    await expect(page.locator('#is-column')).toHaveValue(String(fx.columnId));

    // Sem arquivo: avisa.
    await page.locator('#is-submit').click();
    await expect(page.locator('#is-error')).toHaveText('Escolha o arquivo da planilha.');

    await page.locator('#is-file').setInputFiles({ name: `apollo-${fx.u}.csv`, mimeType: 'text/csv', buffer: Buffer.from(apolloCsv(fx.u), 'utf-8') });
    await page.screenshot({ path: testInfo.outputPath('crm-import-form.png') });
    await page.locator('#is-submit').click();

    const result = page.locator('#is-result');
    await expect(result).toBeVisible({ timeout: 20_000 });
    await expect(result.locator('.is-num').nth(0)).toHaveText('2');  // leads novos
    await expect(result.locator('.is-num').nth(2)).toHaveText('2');  // cards
    await expect(result).toContainText('Linha 4: Sem e-mail, telefone ou LinkedIn.');
    await page.screenshot({ path: testInfo.outputPath('crm-import-resultado.png') });

    // Gravado de verdade: dono = quem importou, cargo/empresa nas colunas, card na coluna, origem planilha.
    const b = contact(`bruno@${fx.u}.example.test`);
    expect(b).toMatchObject({ assigned_to: fx.admin.id, job_title: 'CEO', company_name: 'Padaria São João', cards: 1, origin: 'planilha', column_id: fx.columnId, lead_source: 'import' });
    expect(contact(`duda@${fx.u}.example.test`)).toMatchObject({ company_name: 'Lima, Costa & Cia', cards: 1 });

    // Fechar recarrega o board com os cards novos marcados como Planilha.
    await Promise.all([page.waitForEvent('load'), page.locator('#is-close').click()]);
    const novo = page.locator('.crm-card', { hasText: `Bruno Planilha ${fx.u}` });
    await expect(novo).toBeVisible();
    await expect(novo.locator('.crm-origin-badge')).toHaveText(/Planilha/);
    await expect(novo.locator('.crm-card-role')).toHaveText(/CEO · Padaria São João/);

    // Reimportar o mesmo arquivo não duplica (já existiam, sem card novo).
    await page.locator('#btn-import-sheet').click();
    await waitModal(page, 'importSheetModal');
    await page.locator('#is-file').setInputFiles({ name: `apollo-${fx.u}.csv`, mimeType: 'text/csv', buffer: Buffer.from(apolloCsv(fx.u), 'utf-8') });
    await page.locator('#is-submit').click();
    await expect(page.locator('#is-result')).toBeVisible({ timeout: 20_000 });
    await expect(page.locator('#is-result .is-num').nth(0)).toHaveText('0');
    await expect(page.locator('#is-result .is-num').nth(1)).toHaveText('2');
    await expect(page.locator('#is-result .is-num').nth(2)).toHaveText('0');
    expect(contact(`bruno@${fx.u}.example.test`)!.cards).toBe(1);
  });

  test('temperatura explica de onde vem (pontos de engajamento) em Meus Leads e no card', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/crm/leads?q=' + encodeURIComponent(`Carla Auto ${fx.u}`));
    await expect(page.locator('#temp-legend')).toContainText('e-mail aberto +1, clique em link +3, resposta +5');
    const row = page.locator('tr', { hasText: `Carla Auto ${fx.u}` });
    await expect(row.locator('.lead-temp')).toContainText('Quente');
    await expect(row.locator('.lead-temp')).toHaveAttribute('title', /6 pontos de engajamento/);
    await expect(row.locator('.lead-temp-why')).toHaveText('6 pts');
    await page.screenshot({ path: testInfo.outputPath('crm-leads-temperatura.png') });

    await page.goto(`/crm/board/${fx.boardId}`);
    await expect(cardEl(page, fx.cardId).locator('.crm-temp-dot')).toHaveAttribute('title', /Quente: 6 pontos/);
  });

  test('planilha sem colunas de nome/contato é recusada com explicação', async ({ page }) => {
    await login(page);
    await page.goto('/crm/leads');
    await page.locator('#btn-import-sheet').click();
    await waitModal(page, 'importSheetModal');
    await expect(page.locator('#is-column option').first()).toBeAttached();
    await page.locator('#is-board').selectOption(String(fx.boardId));
    await page.locator('#is-file').setInputFiles({ name: 'ruim.csv', mimeType: 'text/csv', buffer: Buffer.from('Empresa;Cidade\nAcme;Curitiba\n') });
    await page.locator('#is-submit').click();
    await expect(page.locator('#is-error')).toContainText('Não encontrei as colunas de Nome');
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('dados do lead e modal de importação cabem na tela', async ({ page }, testInfo) => {
      await login(page);
      await page.goto(`/crm/board/${fx.boardId}`);
      await cardEl(page, fx.cardId).click();
      await waitModal(page, 'cardDetailModal');
      const prof = page.locator('#card-lead-profile');
      await expect(prof).toBeVisible();
      const pb = await prof.boundingBox();
      expect(pb!.x).toBeGreaterThanOrEqual(0);
      expect(pb!.x + pb!.width).toBeLessThanOrEqual(width + 1);
      await page.screenshot({ path: testInfo.outputPath(`crm-${width}-dados-lead.png`) });
      await page.locator('#cardDetailModal .btn-close').click();
      await expect(page.locator('#cardDetailModal')).toBeHidden();

      await page.goto('/crm/leads');
      await page.locator('#btn-import-sheet').click();
      await waitModal(page, 'importSheetModal');
      const box = await page.locator('#importSheetModal .modal-content').boundingBox();
      expect(Math.round(box!.width)).toBe(width);
      const overflow = await page.locator('#importSheetModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await expect(page.locator('#is-submit')).toBeInViewport();
      await page.screenshot({ path: testInfo.outputPath(`crm-${width}-importar.png`) });
    });
  });
}
