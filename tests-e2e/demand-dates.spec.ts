import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: as datas da demanda (início, término, previsão de término) são definidas
 * pela EQUIPE. O cliente não vê os campos em "Nova demanda" nem o painel de
 * datas na demanda; a equipe vê os campos na criação e edita na demanda.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/demand-dates-fixture.php';

type Cred = { id: number; email: string; password: string };
type Fixture = { companyId: number; client: Cred; admin: Cred };
type Dates = { client_id: number; data_inicio: string | null; data_termino: string | null; data_prevista_entrega: string | null };

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}
const dates = (ticketId: number): Dates => JSON.parse(php(['dates', String(ticketId)]));

let fx: Fixture;
let ticketId = 0;

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => { fx = JSON.parse(php(['setup'])); });
test.afterAll(() => { if (fx) php(['teardown', JSON.stringify(fx)]); });

async function login(page: Page, cred: Cred) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(cred.email);
  await page.locator('input[name="password"]').fill(cred.password);
  await Promise.all([page.waitForURL(/dashboard/), page.locator('button[type="submit"]').click()]);
}

const DATE_FIELDS = ['data_inicio', 'data_termino', 'data_prevista_entrega'];

test.describe('Datas da demanda — definidas pela equipe', () => {
  test('cliente: nova demanda não mostra as datas e cria sem elas', async ({ page }) => {
    await login(page, fx.client);
    await page.goto('/tickets/create');
    await expect(page.locator('#field-title')).toBeVisible();

    for (const f of DATE_FIELDS) {
      await expect(page.locator(`[name="${f}"]`)).toHaveCount(0);
    }
    await expect(page.getByText('Data de início')).toHaveCount(0);
    await expect(page.getByText('Data de término')).toHaveCount(0);
    await expect(page.getByText('Previsão de término')).toHaveCount(0);

    await page.locator('#field-title').fill('E2E demanda sem datas');
    await page.locator('#field-description').fill('Criada pelo cliente sem informar datas.');
    await Promise.all([
      page.waitForURL(/tickets\/show\/\d+/),
      page.getByRole('button', { name: /Enviar Demanda/ }).click(),
    ]);
    ticketId = Number(page.url().match(/tickets\/show\/(\d+)/)![1]);

    const d = dates(ticketId);
    expect(d.client_id).toBe(fx.client.id);
    expect([d.data_inicio, d.data_termino, d.data_prevista_entrega]).toEqual([null, null, null]);

    // Na demanda, o cliente também não vê o painel de datas.
    await expect(page.locator('#demand-dates-card')).toHaveCount(0);
  });

  test('cliente: datas forjadas no POST são ignoradas', async ({ page }) => {
    await login(page, fx.client);
    await page.goto('/tickets/create');
    // Injeta os campos no formulário, simulando um POST manipulado.
    await page.evaluate((fields) => {
      const form = document.querySelector('form[action*="tickets/store"]')!;
      for (const f of fields) {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = f; i.value = '2026-11-10';
        form.appendChild(i);
      }
    }, DATE_FIELDS);
    await page.locator('#field-title').fill('E2E demanda POST forjado');
    await page.locator('#field-description').fill('Tentativa de definir datas pelo cliente.');
    await Promise.all([
      page.waitForURL(/tickets\/show\/\d+/),
      page.getByRole('button', { name: /Enviar Demanda/ }).click(),
    ]);
    const id = Number(page.url().match(/tickets\/show\/(\d+)/)![1]);
    const d = dates(id);
    expect([d.data_inicio, d.data_termino, d.data_prevista_entrega]).toEqual([null, null, null]);
  });

  test('equipe: nova demanda mostra as três datas obrigatórias', async ({ page }) => {
    await login(page, fx.admin);
    await page.goto('/tickets/create');
    for (const f of DATE_FIELDS) {
      const input = page.locator(`input[name="${f}"]`);
      await expect(input).toBeVisible();
      await expect(input).toHaveAttribute('required', '');
    }
  });

  test('equipe: define e limpa as datas na demanda do cliente', async ({ page }) => {
    expect(ticketId).toBeGreaterThan(0);
    await login(page, fx.admin);
    await page.goto(`/tickets/show/${ticketId}`);

    const card = page.locator('#demand-dates-card');
    await expect(card).toBeVisible();
    await card.locator('input[name="data_inicio"]').fill('2026-12-01');
    await card.locator('input[name="data_termino"]').fill('2026-12-10');
    await card.locator('input[name="data_prevista_entrega"]').fill('2026-12-15');
    // Botão único no topo: salva as seções alteradas e recarrega a página.
    await page.locator('#btn-save-all').click();
    await expect(page.getByText('Alterações salvas.')).toBeVisible();
    await expect(page.locator('#demand-dates-card input[name="data_inicio"]')).toHaveValue('2026-12-01');

    let d = dates(ticketId);
    expect([d.data_inicio, d.data_termino, d.data_prevista_entrega]).toEqual(['2026-12-01', '2026-12-10', '2026-12-15']);

    // Limpar a data de término.
    await page.locator('#demand-dates-card input[name="data_termino"]').fill('');
    await page.locator('#btn-save-all').click();
    await expect(page.getByText('Alterações salvas.')).toBeVisible();
    d = dates(ticketId);
    expect([d.data_inicio, d.data_termino, d.data_prevista_entrega]).toEqual(['2026-12-01', null, '2026-12-15']);
  });

  test('cliente não consegue gravar datas via tickets/saveDates', async ({ page }) => {
    expect(ticketId).toBeGreaterThan(0);
    await login(page, fx.client);
    await page.goto(`/tickets/show/${ticketId}`);
    await expect(page.locator('#demand-dates-card')).toHaveCount(0);

    // POST direto com o token CSRF da própria página (meta do layout; a tela
    // do cliente pode não ter nenhum formulário com o campo).
    const token = (await page.locator('meta[name="csrf-token"]').getAttribute('content')) ?? '';
    expect(token).not.toBe('');
    await page.request.post(`/tickets/saveDates/${ticketId}`, {
      form: { csrf_token: token, data_inicio: '2030-01-01', data_termino: '2030-01-02', data_prevista_entrega: '2030-01-03' },
      maxRedirects: 0,
    });
    const d = dates(ticketId);
    expect([d.data_inicio, d.data_termino, d.data_prevista_entrega]).toEqual(['2026-12-01', null, '2026-12-15']);
  });
});
