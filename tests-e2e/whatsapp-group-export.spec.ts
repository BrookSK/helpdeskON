import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import http from 'node:http';
import { AddressInfo } from 'node:net';
import path from 'node:path';

/**
 * E2E: exportar para Excel os contatos (participantes) de todos os grupos do
 * WhatsApp, pelo botão da tela do chat.
 *
 * Para não depender (nem chamar) a Evolution API real, o teste sobe uma
 * Evolution FALSA (servidor HTTP do Node) e cria uma instância local apontando
 * para ela. O fluxo é o real: login -> chat -> botão -> download -> planilha.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/group-export-fixture.php';

type Fixture = {
  userId: number; email: string; password: string;
  instanceId: number; instanceName: string; contactIds: number[];
};

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}

const GROUPS = [
  {
    id: '120363000000000001@g.us',
    subject: 'Clientes E2E',
    participants: [
      { id: '5511987650001@s.whatsapp.net', admin: 'admin' },               // casa com o contato do CRM (sem o 9º dígito)
      { id: '999000111@lid', phoneNumber: '5521912340002@s.whatsapp.net', admin: null }, // LID com telefone real
      { id: '888000222@lid', admin: null },                                  // LID sem telefone
    ],
  },
  {
    id: '120363000000000002@g.us',
    subject: 'Fornecedores E2E',
    participants: [{ id: '5511987650001@s.whatsapp.net', admin: null }],
  },
];

const PROFILES: Record<string, object> = {
  '5521912340002': {
    name: 'Loja Dois', isBusiness: true, email: 'contato@lojadois.com.br',
    website: ['https://lojadois.com.br'], description: 'Moda feminina. Insta: @lojadois',
  },
  '5511987650001': { name: 'Nome do Perfil', isBusiness: false, status: { status: 'Disponível' } },
};

let server: http.Server;
let port = 0;
const calls: { url: string; apikey?: string; body: string }[] = [];
let fx: Fixture;

test.beforeAll(async () => {
  server = http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      calls.push({ url: req.url || '', apikey: req.headers['apikey'] as string | undefined, body });
      res.setHeader('Content-Type', 'application/json');
      if ((req.url || '').startsWith('/group/fetchAllGroups/')) {
        res.end(JSON.stringify(GROUPS));
      } else if ((req.url || '').startsWith('/chat/fetchProfile/')) {
        const num = String(JSON.parse(body || '{}').number || '');
        res.end(JSON.stringify(PROFILES[num] || { name: '', isBusiness: false }));
      } else {
        res.statusCode = 404;
        res.end('{"message":"not found"}');
      }
    });
  });
  await new Promise<void>((r) => server.listen(0, '127.0.0.1', () => r()));
  port = (server.address() as AddressInfo).port;
  fx = JSON.parse(php(['setup', String(port)]));
});

test.afterAll(async () => {
  if (fx) php(['teardown', JSON.stringify(fx)]);
  await new Promise<void>((r) => server.close(() => r()));
});

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(fx.email);
  await page.locator('input[name="password"]').fill(fx.password);
  await Promise.all([page.waitForURL(/dashboard/), page.locator('button[type="submit"]').click()]);
}

/** Abre o chat e restringe a exportação à instância falsa (nunca chama a Evolution real). */
async function openChat(page: Page) {
  await page.goto('/whatsapp/chat');
  const sel = page.locator('#filter-instance');
  if (await sel.count()) {
    await sel.selectOption(String(fx.instanceId));
  } else {
    // Sem seletor = existe uma única instância, que é a falsa.
    expect(fx.instanceId).toBeGreaterThan(0);
  }
}

async function exportAndRead(page: Page, mode: 'fast' | 'profiles'): Promise<string[][]> {
  await page.locator('#btn-export-groups').click();
  const item = page.locator(`[data-export-mode="${mode}"]`);
  await expect(item).toBeVisible();
  const [download] = await Promise.all([page.waitForEvent('download', { timeout: 60_000 }), item.click()]);
  expect(download.suggestedFilename()).toMatch(/^contatos_grupos_whatsapp_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx$/);
  const file = path.join(ROOT, 'tests-e2e', '.artifacts', `export_${Date.now()}.xlsx`);
  await download.saveAs(file);
  try {
    return JSON.parse(php(['read-xlsx', file]));
  } finally {
    rmSync(file, { force: true });
  }
}

const COL = { grupo: 0, nome: 1, telefone: 2, admin: 3, email: 4, site: 5, instagram: 6, recado: 10, business: 14, instancia: 15 };

test.describe('WhatsApp — exportar contatos dos grupos', () => {
  test('sem login não baixa planilha', async ({ request }) => {
    const resp = await request.get('/whatsapp/exportGroupContacts', { maxRedirects: 0 });
    expect(resp.status()).toBeLessThan(500);
    expect(resp.headers()['content-type'] || '').not.toContain('spreadsheetml');
  });

  test('exportação rápida: grupo, nome, telefone (um por grupo)', async ({ page }) => {
    calls.length = 0;
    await login(page);
    await openChat(page);
    const rows = await exportAndRead(page, 'fast');

    expect(rows[0].slice(0, 3)).toEqual(['Grupo', 'Nome', 'Telefone']);
    const data = rows.slice(1);
    expect(data).toHaveLength(4); // 3 em "Clientes" + 1 em "Fornecedores"

    const cliente = data.find((r) => r[COL.grupo] === 'Clientes E2E' && r[COL.telefone] === '+55 (11) 98765-0001')!;
    expect(cliente).toBeTruthy();
    expect(cliente[COL.nome]).toBe('Cliente CRM E2E');           // nome salvo no CRM
    expect(cliente[COL.email]).toBe('cliente.e2e@example.test'); // e-mail do CRM
    expect(cliente[COL.admin]).toBe('Sim');
    expect(cliente[COL.instancia]).toContain('E2E Fake');

    expect(data.some((r) => r[COL.grupo] === 'Clientes E2E' && r[COL.telefone] === '+55 (21) 91234-0002')).toBe(true);
    expect(data.some((r) => r[COL.grupo] === 'Clientes E2E' && r[COL.telefone] === 'Oculto pelo WhatsApp')).toBe(true);
    const forn = data.find((r) => r[COL.grupo] === 'Fornecedores E2E')!;
    expect(forn[COL.telefone]).toBe('+55 (11) 98765-0001');
    expect(forn[COL.admin]).toBe('Não');

    // Ordenado por grupo
    expect(data.map((r) => r[COL.grupo])).toEqual([...data.map((r) => r[COL.grupo])].sort((a, b) => a.localeCompare(b)));

    // Chamou a Evolution falsa com participantes e a apikey; modo rápido não consulta perfis
    expect(calls.some((c) => c.url.includes(`/group/fetchAllGroups/${fx.instanceName}`) && c.url.includes('getParticipants=true') && c.apikey === 'e2e-key')).toBe(true);
    expect(calls.some((c) => c.url.startsWith('/chat/fetchProfile/'))).toBe(false);
  });

  test('exportação completa traz site, Instagram, e-mail e recado do perfil', async ({ page }) => {
    calls.length = 0;
    await login(page);
    await openChat(page);
    const data = (await exportAndRead(page, 'profiles')).slice(1);

    const loja = data.find((r) => r[COL.telefone] === '+55 (21) 91234-0002')!;
    expect(loja[COL.nome]).toBe('Loja Dois');
    expect(loja[COL.site]).toBe('https://lojadois.com.br');
    expect(loja[COL.instagram]).toBe('https://instagram.com/lojadois');
    expect(loja[COL.email]).toBe('contato@lojadois.com.br');
    expect(loja[COL.business]).toBe('Sim');

    const cliente = data.find((r) => r[COL.grupo] === 'Clientes E2E' && r[COL.telefone] === '+55 (11) 98765-0001')!;
    expect(cliente[COL.nome]).toBe('Cliente CRM E2E'); // CRM continua com prioridade sobre o perfil
    expect(cliente[COL.recado]).toBe('Disponível');

    // Um fetchProfile por telefone único (LID sem telefone não é consultado)
    const profileCalls = calls.filter((c) => c.url.startsWith('/chat/fetchProfile/'));
    expect(profileCalls.map((c) => JSON.parse(c.body).number).sort()).toEqual(['5511987650001', '5521912340002']);
  });

  test('erro do servidor aparece como aviso na tela, sem download', async ({ page }) => {
    await login(page);
    await openChat(page);
    await page.route('**/whatsapp/exportGroupContacts**', (route) =>
      route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ success: false, message: 'Nenhum participante de grupo encontrado.' }) }));

    let downloaded = false;
    page.on('download', () => (downloaded = true));
    await page.locator('#btn-export-groups').click();
    await page.locator('[data-export-mode="fast"]').click();
    await expect(page.getByText('Nenhum participante de grupo encontrado.')).toBeVisible();
    await expect(page.locator('#btn-export-groups')).toBeEnabled();
    expect(downloaded).toBe(false);
  });
});
