import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: Planejamento no celular (< 992px), no modelo do protótipo
 * planejamento-mobile.html.
 * - Lista vertical com abas de status (com contagem), busca, atalhos
 *   (Minhas/Atrasadas/Hoje), filtros em gaveta e cards com ação rápida
 *   (Concluir/Iniciar/Reabrir) + "Mover".
 * - Ações rápidas gravam depois de alguns segundos e podem ser desfeitas.
 * - Detalhe do card em gaveta: abas em 3 colunas, Status/Prioridade em botões
 *   grandes, rodapé fixo com Excluir/Salvar.
 * - No desktop o Kanban continua igual.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/planning-card-mobile-fixture.php';

type Fixture = {
  u: string; companyId: number; cardId: number;
  cards: { main: number; late: number; today: number; done: number };
  admin: { id: number; email: string; password: string };
};
type CardRow = { status: string; priority: string; assigned_to: number | null };

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}
const row = (id: number): CardRow => JSON.parse(php(['card', String(id)]));

let fx: Fixture;

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { fx = JSON.parse(php(['setup'])); });
test.beforeEach(() => { php(['reset', JSON.stringify(fx)]); });
test.afterAll(() => { if (fx) php(['teardown', JSON.stringify(fx)]); });

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(fx.admin.email);
  await page.locator('input[name="password"]').fill(fx.admin.password);
  await Promise.all([page.waitForURL(/dashboard/), page.locator('button[type="submit"]').click()]);
}

/** Abre o Planejamento (o super_admin entra pré-filtrado nos próprios cards = só os da fixture). */
async function openPlanning(page: Page, query = '') {
  await page.goto('/planning' + query);
  await expect(page.locator('#pm')).toBeVisible();
}

const card = (page: Page, id: number) => page.locator(`#pm .pm-card[data-id="${id}"]`);

async function waitModal(page: Page, id: string) {
  await expect(page.locator(`#${id}.show`)).toBeVisible();
  await expect.poll(() => page.locator(`#${id}`).evaluate(el => {
    const dialog = el.querySelector('.modal-dialog') as HTMLElement;
    return getComputedStyle(el).opacity === '1' && getComputedStyle(dialog).transform === 'none';
  })).toBe(true);
}

async function expectNoHorizontalOverflow(page: Page, width: number) {
  const r = await page.evaluate(() => ({ iw: window.innerWidth, doc: document.documentElement.scrollWidth }));
  expect(r.iw).toBe(width);
  expect(r.doc).toBeLessThanOrEqual(width);
}

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('lista com abas de status, atalhos e cards; sem rolagem lateral', async ({ page }, testInfo) => {
      await login(page);
      await openPlanning(page);

      // Kanban e barra do layout ficam escondidos; cabeçalho próprio aparece.
      await expect(page.locator('#kanban-view')).toBeHidden();
      await expect(page.locator('#pm-top h1')).toContainText('Planejamento');

      // Aba "Todos" com a soma, e abas por status com a contagem.
      const tabAll = page.locator('#pm-tabs [data-tab="all"]');
      await expect(tabAll).toHaveAttribute('aria-selected', 'true');
      await expect(tabAll.locator('.n')).toHaveText('4');
      await expect(page.locator('#pm-tabs [data-tab="open"] .n')).toHaveText('2');
      await expect(page.locator('#pm-tabs [data-tab="completed"] .n')).toHaveText('1');

      // Atalhos com contagem do servidor.
      await expect(page.locator('#pm-quick [data-quick="mine"]')).toHaveAttribute('aria-pressed', 'true');
      await expect(page.locator('#pm-quick [data-quick="late"] b')).toHaveText('1');

      // Cards da fixture, com a ação rápida certa para o status.
      for (const id of Object.values(fx.cards)) await expect(card(page, id)).toBeVisible();
      await expect(card(page, fx.cards.main).locator('.go')).toContainText('Concluir');
      await expect(card(page, fx.cards.late).locator('.go')).toContainText('Iniciar');
      await expect(card(page, fx.cards.done).locator('.go')).toContainText('Reabrir');
      await expect(card(page, fx.cards.late)).toHaveClass(/late/);

      await expectNoHorizontalOverflow(page, width);
      await expect(page.locator('#pm-fab')).toBeInViewport({ ratio: 1 });
      await page.screenshot({ path: testInfo.outputPath(`pm-${width}-lista.png`) });

      // Aba de um status mostra só aquele status.
      await page.locator('#pm-tabs [data-tab="open"]').click();
      await expect(page.locator('#pm .pm-card')).toHaveCount(2);
      await expect(card(page, fx.cards.main)).toHaveCount(0);
    });

    test('detalhe do card em gaveta: abas em 3 colunas e rodapé Excluir/Salvar', async ({ page }, testInfo) => {
      await login(page);
      await openPlanning(page);
      await card(page, fx.cards.main).locator('h3').click();
      await waitModal(page, 'cardDetailModal');
      await expect(page.locator('#detail-title')).toContainText('Entrega de Processos');

      const tops = await page.locator('#cardTabs .nav-link').evaluateAll(els => els.map(e => Math.round(e.getBoundingClientRect().top)));
      expect(new Set(tops).size).toBe(3);
      // Nenhuma aba estoura a própria coluna (texto + contador cabem).
      const tabsFit = await page.locator('#cardTabs .nav-link').evaluateAll(els => els.every(e => e.scrollWidth <= e.clientWidth + 1));
      expect(tabsFit).toBe(true);
      // O título usa boa parte da largura (não fica espremido pelas etiquetas).
      const titleBox = await page.locator('#detail-title').boundingBox();
      expect(titleBox!.width).toBeGreaterThan(width * 0.55);
      // O X de fechar fica na mesma linha do título (topo da gaveta).
      const closeBox = await page.locator('#cardDetailModal .card-detail-close').boundingBox();
      expect(closeBox!.y).toBeLessThan(titleBox!.y + 20);
      expect(closeBox!.x + closeBox!.width).toBeLessThanOrEqual(width);

      const footer = page.locator('#cardDetailModal .card-detail-mobilebar');
      await expect(footer).toBeInViewport({ ratio: 1 });
      await expect(footer.getByRole('button', { name: /Salvar/ })).toBeVisible();
      await expect(footer.getByRole('button', { name: 'Excluir card' })).toBeVisible();
      await expect(page.locator('#detail-status-seg button[data-v="in_progress"]')).toHaveAttribute('aria-pressed', 'true');
      await expectNoHorizontalOverflow(page, width);
      await page.screenshot({ path: testInfo.outputPath(`pm-${width}-detalhe.png`) });

      await page.locator('#detail-status-seg').scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`pm-${width}-detalhe-props.png`) });
    });

    test('gaveta de filtros no modelo do protótipo', async ({ page }, testInfo) => {
      await login(page);
      await openPlanning(page);
      await page.locator('#pm-filter').click();
      const sheet = page.locator('#pm-sheet.open');
      await expect(sheet).toBeVisible();
      await expect(sheet.locator('h2')).toHaveText('Filtros');
      const sections = await sheet.locator('.pm-fh .t').allTextContents();
      expect(sections).toEqual(['Prazo', 'Prioridade', 'Atendente', 'Solicitante', 'Técnico', 'Empresa', 'Ordenar por']);
      // Prazo e Prioridade já abertos, como no protótipo.
      await expect(sheet.locator('.pm-fc[data-f="deadline"][data-v="late"]')).toBeVisible();
      await expect(sheet.locator('.pm-fc[data-f="priority"][data-v="high"]')).toBeVisible();
      await expect(page.locator('#pm-fapply')).toHaveText('Ver 4 cards');
      await expectNoHorizontalOverflow(page, width);
      await page.screenshot({ path: testInfo.outputPath(`pm-${width}-filtros.png`) });
    });
  });
}

test.describe('celular: ações', () => {
  test.use({ viewport: { width: 390, height: 760 }, isMobile: true, hasTouch: true });

  test('Concluir no card grava como concluído (após o tempo de desfazer)', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await card(page, fx.cards.main).locator('.go').click();
    await expect(page.locator('#pm-toast')).toContainText('Concluído');
    await expect(page.locator('#pm-undo')).toBeVisible();
    // Na tela já foi para Concluído.
    await expect(page.locator('#pm-tabs [data-tab="completed"] .n')).toHaveText('2');
    await expect(card(page, fx.cards.main).locator('.go')).toContainText('Reabrir');
    await expect.poll(() => row(fx.cards.main).status, { timeout: 12_000 }).toBe('completed');

    // Recarregando, continua concluído.
    await page.reload();
    await expect(page.locator('#pm-tabs [data-tab="completed"] .n')).toHaveText('2');
  });

  test('Desfazer cancela a mudança antes de gravar', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await card(page, fx.cards.late).locator('.go').click();
    await expect(page.locator('#pm-tabs [data-tab="in_progress"] .n')).toHaveText('2');
    await page.locator('#pm-undo').click();
    await expect(page.locator('#pm-tabs [data-tab="in_progress"] .n')).toHaveText('1');
    await page.waitForTimeout(6000);
    expect(row(fx.cards.late).status).toBe('open');
  });

  test('Mover (…) escolhe qualquer status', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await card(page, fx.cards.today).locator('.mv').click();
    const sheet = page.locator('#pm-sheet.open');
    await expect(sheet.locator('h2')).toContainText('Mover #' + fx.cards.today);
    await sheet.locator('.pm-opt', { hasText: 'Em Homologação' }).click();
    await expect.poll(() => row(fx.cards.today).status, { timeout: 12_000 }).toBe('em_homologacao');
  });

  test('filtro de prioridade e atalho Atrasadas recarregam com o recorte certo', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await page.locator('#pm-filter').click();
    await page.locator('#pm-sheet .pm-fc[data-f="priority"][data-v="high"]').click();
    await expect(page.locator('#pm-fapply')).toHaveText('Ver 1 card');
    await Promise.all([page.waitForURL(/priority%5B%5D=high/), page.locator('#pm-fapply').click()]);
    await expect(page.locator('#pm .pm-card')).toHaveCount(1);
    await expect(card(page, fx.cards.main)).toBeVisible();
    await expect(page.locator('#pm-fbadge')).toHaveText('2'); // Minhas + Alta
    const chip = page.locator('#pm-actives .pm-ac', { hasText: 'Prior. Alta' });
    await expect(chip).toBeVisible();

    // Remove o chip: volta aos 4 cards.
    await Promise.all([page.waitForURL(url => !/priority/.test(url.toString())), chip.click()]);
    await expect(page.locator('#pm .pm-card')).toHaveCount(4);

    // Atalho "Atrasadas".
    await Promise.all([page.waitForURL(/deadline=late/), page.locator('#pm-quick [data-quick="late"]').click()]);
    await expect(page.locator('#pm .pm-card')).toHaveCount(1);
    await expect(card(page, fx.cards.late)).toBeVisible();
  });

  test('busca filtra na hora os cards carregados', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await page.locator('#pm-q').fill('proposta comercial');
    await expect(page.locator('#pm .pm-card')).toHaveCount(1);
    await expect(card(page, fx.cards.today)).toBeVisible();
    await page.locator('#pm-qclr').click();
    await expect(page.locator('#pm .pm-card')).toHaveCount(4);
  });

  test('detalhe: Status "Concluído" nos botões grandes + Salvar grava', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await card(page, fx.cards.main).locator('h3').click();
    await waitModal(page, 'cardDetailModal');
    await page.locator('#detail-status-seg button[data-v="completed"]').click();
    await expect(page.locator('#detail-status')).toHaveValue('completed');
    await expect(page.locator('#detail-status-seg button[data-v="completed"]')).toHaveAttribute('aria-pressed', 'true');
    await page.locator('#detail-priority-seg button[data-v="urgent"]').click();
    await expect(page.locator('#detail-priority')).toHaveValue('urgent');
    await page.locator('#cardDetailModal .card-detail-mobilebar').getByRole('button', { name: /Salvar/ }).click();
    await expect.poll(() => row(fx.cards.main), { timeout: 10_000 }).toMatchObject({ status: 'completed', priority: 'urgent' });
    await expect(page.locator('#cardDetailModal')).toBeHidden();
    // A lista reflete sem recarregar.
    await expect(page.locator('#pm-tabs [data-tab="completed"] .n')).toHaveText('2');
    await expect(card(page, fx.cards.main).locator('.pr')).toHaveText('Urgente');
  });

  test('seleção em lote: definir prioridade de 2 cards', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await page.locator('#pm-btn-sel').click();
    await expect(page.locator('#pm-selbar')).toBeVisible();
    await card(page, fx.cards.late).locator('h3').click();
    await card(page, fx.cards.today).locator('h3').click();
    await expect(page.locator('#pm-sel-cnt')).toHaveText('2 marcados');
    await page.locator('#pm-sel-prio').click();
    await page.locator('#pm-sheet .pm-opt', { hasText: 'Urgente' }).click();
    await expect(page.locator('#pm-selbar')).toBeHidden();
    await expect.poll(() => [row(fx.cards.late).priority, row(fx.cards.today).priority], { timeout: 12_000 }).toEqual(['urgent', 'urgent']);
  });

  test('novo card pelo botão flutuante cria no status da aba', async ({ page }) => {
    await login(page);
    await openPlanning(page);
    await page.locator('#pm-tabs [data-tab="in_progress"]').click();
    await page.locator('#pm-fab').click();
    await waitModal(page, 'createCardModal');
    await expect(page.locator('#create-submit-mobile-label')).toHaveText('Criar em Em andamento');
    await page.locator('#createCardForm input[name="title"]').fill('E2E criado no celular ' + fx.u);
    await page.locator('#createCardModal .pm-seg button[data-v="high"]').click();
    await page.locator('#createCardForm select[name="assigned_to"]').selectOption(String(fx.admin.id)).catch(() => {});
    await page.locator('#createCardForm button[type="submit"]').click();
    await expect(page.locator('#createCardModal')).toBeHidden();
    const created = page.locator('#pm .pm-card', { hasText: 'E2E criado no celular ' + fx.u });
    await expect(created).toBeVisible();
    await expect(created.locator('.pr')).toHaveText('Alta');
  });
});

test.describe('desktop (sem regressão)', () => {
  test.use({ viewport: { width: 1366, height: 800 } });

  test('Kanban e detalhe em duas colunas continuam iguais', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/planning');
    await expect(page.locator('#pm')).toBeHidden();
    await expect(page.locator('#kanban-view')).toBeVisible();
    await expect(page.locator(`#kanban-view .planning-card[data-id="${fx.cards.main}"]`)).toBeVisible();

    await page.goto(`/planning?card=${fx.cards.main}`);
    await waitModal(page, 'cardDetailModal');
    await expect(page.locator('.card-detail-mobilebar')).toBeHidden();
    await expect(page.locator('#detail-status-seg')).toBeHidden();
    await expect(page.locator('#detail-status')).toBeVisible();
    const main = await page.locator('#cardDetailModal .card-detail-main').boundingBox();
    const side = await page.locator('#card-detail-props').boundingBox();
    expect(side!.x).toBeGreaterThan(main!.x + main!.width - 2);
    expect(Math.abs(side!.y - main!.y)).toBeLessThan(2);
    await expect(page.locator('#card-detail-props button:has-text("Salvar")')).toBeInViewport();
    await page.screenshot({ path: testInfo.outputPath('pm-desktop.png') });
  });
});
