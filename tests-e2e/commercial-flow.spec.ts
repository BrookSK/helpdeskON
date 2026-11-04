import { test, expect, Page, Browser } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { existsSync } from 'node:fs';

/**
 * E2E do fluxo aceite até atendimento (change 02): versões da proposta, aceite
 * com nome, contrato com CPF e PDF assinado por upload, plano sugerido, emissão
 * real no sandbox do Asaas, baixa pelo webhook, link de credenciais, portal do
 * cliente e trava de garantia no chamado. ClickSign, SMTP e WhatsApp ficam sem
 * configuração no banco local.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');

function fixture(...args: string[]): any {
  const out = execFileSync(PHP, ['tests-e2e/commercial-flow-fixture.php', ...args], { cwd: ROOT });
  return JSON.parse(out.toString());
}

type Creds = { id: number; email: string; password: string };
let setup: { user: Creds; proposal: { id: number; token: string }; webhookToken: string; cpf: string };
let r3: { id: number; token: string };
let contractId = 0;
let financeId = 0;
let entryChargeId = 0;
let onboardingId = 0;
let portal: { client: Creds; contractA: number; contractB: number; project: number };

async function login(page: Page, who: Creds) {
  await page.goto('/login');
  await page.fill('input[name="email"]', who.email);
  await page.fill('input[name="password"]', who.password);
  await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('button[type="submit"]')]);
}

/** Aceita todo confirm/alert e guarda as mensagens. */
function collectDialogs(page: Page): string[] {
  const seen: string[] = [];
  page.on('dialog', (d) => { seen.push(d.message()); d.accept(); });
  return seen;
}

function inDays(n: number): string {
  const d = new Date(Date.now() + n * 86400000);
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
}

async function anonymousPage(browser: Browser) {
  const ctx = await browser.newContext();
  return { ctx, page: await ctx.newPage() };
}

test.describe.serial('fluxo aceite até atendimento', () => {
  test.beforeAll(() => {
    setup = fixture('setup');
  });
  test.afterAll(() => {
    if (setup) fixture('cleanup');
  });

  test('proposta: R01 enviada vira R02 e R03 com histórico de versões', async ({ page }) => {
    collectDialogs(page);
    await login(page, setup.user);
    await page.goto(`/proposal/edit/${setup.proposal.id}`);
    await expect(page.locator('#p-revision')).toHaveText('R01');

    let current = setup.proposal.id;
    for (const label of ['R02', 'R03']) {
      await Promise.all([page.waitForURL((u) => /\/proposal\/edit\/\d+/.test(u.pathname) && !u.pathname.endsWith(`/${current}`)), page.click('#btn-reopen')]);
      current = Number(page.url().match(/edit\/(\d+)/)![1]);
      await expect(page.locator('#p-revision')).toHaveText(label);
      // O envio ao cliente depende de SMTP/WhatsApp: a fixture marca como enviada.
      fixture('set-status', String(current), 'sent');
      await page.reload();
    }
    await expect(page.locator('#revisions-card tbody tr')).toHaveCount(3);

    await page.goto(`/proposal/edit/${setup.proposal.id}`);
    await expect(page.locator('#superseded-alert')).toBeVisible();
    const p = fixture('proposal', String(current));
    expect(p.revisions).toBe(3);
    r3 = { id: current, token: p.public_token };
  });

  test('aceite público exige nome e registra nome, IP e navegador; versão antiga não aceita', async ({ browser }) => {
    const { ctx, page } = await anonymousPage(browser);
    const dialogs = collectDialogs(page);

    await page.goto(`/proposal/show/${setup.proposal.token}`);
    await expect(page.locator('#respond-box')).toHaveCount(0);

    await page.goto(`/proposal/show/${r3.token}`);
    await page.click('#respond-box .btn-ok');
    expect(dialogs.join(' ')).toContain('Informe seu nome');
    await page.fill('#accept-name', 'Maria E2E da Silva');
    const accepted = page.waitForResponse((r) => r.url().includes('/proposal/accept/'));
    await page.click('#respond-box .btn-ok');
    expect((await accepted).status()).toBe(200);

    const p = fixture('proposal', String(r3.id));
    expect(p.status).toBe('accepted');
    expect(p.accepted_name).toBe('Maria E2E da Silva');
    expect(p.accepted_ip).toBe('127.0.0.1');
    expect(p.accepted_user_agent).toMatch(/Chrome/);
    expect(Number(p.accepted_revision)).toBe(3);
    expect(p.accepted_plan).toBeTruthy();
    await ctx.close();
  });

  test('contrato: CPF no rascunho, assinatura bloqueada sem CPF e PDF assinado por upload abre o financeiro', async ({ page }) => {
    const dialogs = collectDialogs(page);
    await login(page, setup.user);

    await page.goto(`/proposal/edit/${r3.id}`);
    await page.click('button:has-text("Gerar contrato")');
    await expect(page.locator('#genContractModal')).toBeVisible();
    await Promise.all([page.waitForURL(/\/contract\/edit\/\d+/), page.click('#gc-confirm')]);
    contractId = Number(page.url().match(/edit\/(\d+)/)![1]);

    await page.fill('#c-document', setup.cpf);
    await page.fill('#c-address', 'Rua E2E, 100, São Paulo/SP');
    const saved = page.waitForResponse((r) => r.url().includes(`/contract/save/${contractId}`));
    await page.click('#btn-save');
    expect((await saved).status()).toBe(200);
    expect(fixture('contract', String(contractId)).client_document.replace(/\D/g, '')).toBe(setup.cpf);
    // Aprovação do cliente é da change 01: a fixture aprova.
    fixture('approve-contract', String(contractId));

    // Contrato aprovado sem CPF: o envio para assinatura fica bloqueado.
    const bare = fixture('bare-contract');
    await page.goto(`/contract/edit/${bare.id}`);
    await expect(page.locator('#clicksign-missing')).toBeVisible();
    await page.click('button[data-bs-target="#signModal"]');
    await expect(page.locator('#signModal')).toBeVisible();
    const blocked = page.waitForResponse((r) => r.url().includes(`/contract/sendForSignature/${bare.id}`));
    await page.click('#sign-confirm');
    expect((await blocked).status()).toBe(400);
    await expect.poll(() => dialogs.join(' ')).toContain('CPF ou CNPJ');

    // Sem ClickSign: o PDF assinado entra por upload e o contrato fica assinado.
    await page.goto(`/contract/edit/${contractId}`);
    await expect(page.locator('#clicksign-missing')).toBeVisible();
    await page.setInputFiles('#signed-file-input', { name: 'nao-e-pdf.pdf', mimeType: 'application/pdf', buffer: Buffer.from('texto qualquer') });
    await page.click('#btn-upload-signed');
    await expect.poll(() => dialogs.join(' ')).toContain('não é um PDF');

    await page.setInputFiles('#signed-file-input', { name: 'assinado.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n') });
    const reloaded = page.waitForEvent('load');
    await page.click('#btn-upload-signed');
    await reloaded;
    await expect(page.locator('#signed-file-status')).toContainText('PDF guardado');

    const c = fixture('contract', String(contractId));
    expect(c.status).toBe('signed');
    expect(c.signed_via).toBe('upload');
    expect(c.file_exists).toBe(true);
    expect(c.finance).toHaveLength(1);
    financeId = c.finance[0];

    // Iniciar financeiro de novo não duplica.
    await Promise.all([page.waitForURL(/\/finance\/edit\/\d+/), page.click('button:has-text("Iniciar financeiro")')]);
    expect(page.url()).toContain(`/finance/edit/${financeId}`);
  });

  test('financeiro: sugestão da proposta, selo SANDBOX e emissão real no sandbox do Asaas', async ({ page }) => {
    test.setTimeout(90_000);
    const dialogs = collectDialogs(page);
    await login(page, setup.user);
    await page.goto(`/finance/edit/${financeId}`);

    const sg = page.locator('#plan-suggestion');
    await expect(sg).toContainText('pontual R$ 8.000,00');
    await expect(sg).toContainText('entrada de 50% (R$ 4.000,00)');
    await expect(sg).toContainText('entrega R$ 4.000,00');
    // O aceite ficou com o plano de 12 meses (o padrão marcado na página): 400 com 30% de desconto.
    await expect(sg).toContainText('mensalidade R$ 280,00 (plano 12 meses)');
    await expect(page.locator('#f-entry')).toHaveValue('4000');
    await expect(page.locator('#f-monthly')).toHaveValue('280');

    await page.fill('#f-entry-due', inDays(3));
    // A conta sandbox não tem Pix liberado (o Asaas exige conta aprovada): boleto.
    await page.selectOption('#f-entry-method', 'boleto');
    await page.fill('#f-inst-due', inDays(40));
    await page.fill('#f-monthly-start', inDays(30));
    await page.uncheck('#f-notify');
    const reloaded = page.waitForEvent('load');
    await page.click('button:has-text("Gerar plano")');
    await reloaded;

    const rows = page.locator('#charges-body tr');
    await expect(rows).toHaveCount(3);
    await expect(page.locator('.asaas-env').first()).toHaveText('SANDBOX');
    const entryRow = rows.filter({ hasText: 'Entrada' }).first();
    await expect(entryRow).toContainText('R$ 4.000,00');

    entryChargeId = Number(await entryRow.locator('.btn-issue').getAttribute('data-charge'));
    const issued = page.waitForResponse((r) => r.url().includes(`/finance/issue/${entryChargeId}`), { timeout: 60_000 });
    await entryRow.locator('.btn-issue').click();
    // A página recarrega depois da emissão; em erro, o motivo fica em issue_error.
    const issuedStatus = (await issued).status();
    const issueError = () => fixture('finance', String(financeId)).charges.find((c: any) => Number(c.id) === entryChargeId)?.issue_error;
    expect(issuedStatus, issuedStatus === 200 ? '' : String(issueError())).toBe(200);
    expect(dialogs.join(' ')).toContain('SANDBOX');
    await page.waitForLoadState('load');

    const f = fixture('finance', String(financeId));
    const entry = f.charges.find((c: any) => Number(c.id) === entryChargeId);
    expect(entry.issue_error).toBeNull();
    expect(entry.asaas_charge_id).toMatch(/^pay_/);
    expect(entry.invoice_url).toMatch(/^https:\/\/sandbox\.asaas\.com\//);
    await expect(page.locator(`#charges-body tr:has(button[data-charge="${entryChargeId}"])`)).toHaveCount(0);
    await expect(page.locator('#charges-body a:has-text("Link de pagamento")')).toHaveAttribute('href', entry.invoice_url);
  });

  test('webhook com o pagamento real do sandbox dá baixa uma vez e abre o onboarding', async ({ page, request }) => {
    test.setTimeout(90_000);
    const paid = fixture('asaas-pay', String(entryChargeId));
    expect(paid.paid_http).toBe(200);
    expect(paid.payload.payment.status).toBe('RECEIVED_IN_CASH');

    expect((await request.post('/finance/asaasWebhook', { data: paid.payload })).status()).toBe(401);
    expect((await request.post('/finance/asaasWebhook?token=errado', { data: paid.payload })).status()).toBe(401);
    for (let i = 0; i < 2; i++) {
      const r = await request.post(`/finance/asaasWebhook?token=${setup.webhookToken}`, { data: paid.payload });
      expect(r.status()).toBe(200);
    }

    const f = fixture('finance', String(financeId));
    expect(f.charges.find((c: any) => Number(c.id) === entryChargeId).status).toBe('paid');
    expect(f.onboardings).toHaveLength(1);
    onboardingId = f.onboardings[0];

    await login(page, setup.user);
    await page.goto(`/finance/edit/${financeId}`);
    await expect(page.locator('a:has-text("Abrir onboarding")')).toHaveAttribute('href', new RegExp(`/onboarding/edit/${onboardingId}$`));
    await expect(page.locator('#finance-events')).toContainText(/paga|baixa/i);
  });

  test('onboarding: cliente envia credencial pelo link e ela vai criptografada para o cofre', async ({ page, browser }) => {
    await login(page, setup.user);
    await page.goto(`/onboarding/edit/${onboardingId}`);
    await expect(page.locator('#cred-link-blocked')).toHaveCount(0);
    await page.click('#btn-cred-link');
    await expect(page.locator('#cred-link-url')).toHaveValue(/\/onboarding\/credentials\/[0-9a-f]{48}$/);
    const url = await page.locator('#cred-link-url').inputValue();

    const { ctx, page: client } = await anonymousPage(browser);
    await client.goto('/onboarding/credentials/0000');
    await expect(client.locator('#link-invalid')).toBeVisible();

    await client.goto(new URL(url).pathname);
    await client.fill('#service_label', 'Hospedagem E2E');
    await client.fill('#url', 'https://painel.example.test');
    await client.fill('#username', 'admin-e2e');
    await client.fill('#secret', 'E2E-segredo-123');
    await client.click('#btn-send');
    await expect(client.locator('#sent-ok')).toBeVisible();
    await expect(client.locator('#secret')).toHaveValue('');
    await ctx.close();

    const creds = fixture('credentials', String(onboardingId));
    expect(creds).toHaveLength(1);
    expect(creds[0].service_label).toBe('Hospedagem E2E');
    expect(creds[0].username).toBe('admin-e2e');
    expect(creds[0].stored_plain).toBe(false);
    expect(creds[0].decrypted).toBe('E2E-segredo-123');

    await page.reload();
    await expect(page.locator('.cred-link-row').first()).toBeVisible();
  });

  test('portal: cliente vê só a própria empresa e não abre contrato de outra pela URL', async ({ page }) => {
    portal = fixture('portal');
    await login(page, portal.client);
    await page.goto('/portal');
    await expect(page.locator('#portal-contracts tbody tr')).toHaveCount(1);
    await expect(page.locator('#portal-contracts')).toContainText('Contrato E2E a');
    await expect(page.locator('#portal-contracts')).not.toContainText('Contrato E2E b');
    await expect(page.locator('#portal-charges')).toContainText('Entrada E2E a');
    await expect(page.locator('#portal-charges')).not.toContainText('Entrada E2E b');
    await expect(page.locator('#portal-charges a:has-text("Pagar")')).toHaveAttribute('href', 'https://sandbox.asaas.com/i/e2ea');
    await expect(page.locator('#portal-projects')).toContainText('Projeto E2E vencido');

    const own = await page.request.get(`/portal/contract/${portal.contractA}`);
    expect(own.status()).toBe(200);
    expect(own.headers()['content-type']).toContain('application/pdf');
    expect((await page.request.get(`/portal/contract/${portal.contractB}`)).status()).toBe(404);
  });

  test('chamado: projeto com garantia vencida e sem suporte bloqueia o cliente', async ({ page }) => {
    await login(page, portal.client);
    await page.goto('/tickets/create');
    await expect(page.locator('#project-field')).toBeVisible();
    await expect(page.locator('#field-project')).toHaveValue(String(portal.project));
    await expect(page.locator('#project-blocked')).toContainText('contratar o suporte');

    await page.fill('#field-title', 'Chamado E2E fora da garantia');
    await page.fill('#field-description', 'Teste do bloqueio de garantia.');
    await Promise.all([page.waitForURL(/\/tickets\/create/), page.click('form[action$="tickets/store"] button[type="submit"]')]);
    await expect(page.locator('.alert-danger')).toContainText('contratar o suporte');
  });
});
