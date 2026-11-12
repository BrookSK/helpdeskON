import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import http from 'node:http';
import { AddressInfo } from 'node:net';
import path from 'node:path';

/**
 * E2E: menções (@) nos grupos do WhatsApp.
 *  - "@" na conversa (e na aba Grupos) quando alguém marcou o nosso número;
 *  - autocomplete do @ ao escrever no grupo e envio com a menção real;
 *  - webhook detectando a menção ao nosso número (por LID) e o aviso (toast).
 *
 * Não chama a Evolution real: o teste sobe uma Evolution FALSA (servidor HTTP
 * do Node) e cria uma instância local apontando para ela.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/mentions-fixture.php';

type Fixture = {
  userId: number; email: string; password: string;
  instanceId: number; instanceName: string;
  group1: number; group1Jid: string; group2: number; group2Jid: string; contact: number;
};
type State = {
  groups: Record<'group1' | 'group2', { mention_count: number; unread_count: number }>;
  owner_phone: string | null; owner_lid: string | null; group2_last_mention: string | null;
};

// Telefones fictícios (DDD 99): não podem casar com contatos reais do banco local,
// senão o nome salvo no CRM (que tem prioridade) muda o rótulo esperado.
const OWNER_PHONE = '5599988887777';
const OWNER_LID = '156079530967127@lid';

// Formato real da Evolution v2 (grupo em LID): id = LID, telefone em phoneNumber
const PARTICIPANTS = {
  participants: [
    { id: '236932122603562@lid', phoneNumber: '5599900000001@s.whatsapp.net', admin: 'admin' },
    { id: '58935558037626@lid', phoneNumber: '5599900000002@s.whatsapp.net', admin: 'admin', name: null },
    { id: OWNER_LID, phoneNumber: `${OWNER_PHONE}@s.whatsapp.net`, admin: 'superadmin', name: null },
    { id: '134973977849932@lid', admin: null, name: 'Punta Cana para Brasileiros' },
  ],
};

function php(args: string[]): string {
  return execFileSync(PHP, [FIXTURE, ...args], { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString().trim();
}
const state = (): State => JSON.parse(php(['state', JSON.stringify(fx)]));

let server: http.Server;
const calls: { method: string; url: string; body: string }[] = [];
let fx: Fixture;

test.beforeAll(async () => {
  server = http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      const url = req.url || '';
      calls.push({ method: req.method || '', url, body });
      res.setHeader('Content-Type', 'application/json');
      if (url.startsWith('/instance/fetchInstances')) {
        res.end(JSON.stringify([{ name: fx.instanceName, ownerJid: `${OWNER_PHONE}@s.whatsapp.net`, connectionStatus: 'open' }]));
      } else if (url.startsWith(`/group/participants/${fx.instanceName}`)) {
        res.end(JSON.stringify(PARTICIPANTS));
      } else if (url.startsWith(`/message/sendText/${fx.instanceName}`)) {
        res.end(JSON.stringify({ key: { id: 'E2ESENT' + Date.now() }, status: 'PENDING' }));
      } else {
        res.statusCode = 404;
        res.end('{"message":"not found"}');
      }
    });
  });
  await new Promise<void>((r) => server.listen(0, '127.0.0.1', () => r()));
  fx = JSON.parse(php(['setup', String((server.address() as AddressInfo).port)]));
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

/** Abre o chat só com a instância falsa e vai para a aba Grupos. */
async function openGroups(page: Page) {
  await page.goto('/whatsapp/chat');
  const sel = page.locator('#filter-instance');
  if (await sel.count()) await sel.selectOption(String(fx.instanceId));
  await page.locator('.wpp-tab[data-tab="groups"]').click();
}

const groupItem = (page: Page, name: string) => page.locator('.wpp-contact-item', { hasText: name });

/** Abre o grupo e espera as mensagens e os participantes (autocomplete) carregarem. */
async function openGroup(page: Page, name: string) {
  const participants = page.waitForResponse((r) => r.url().includes('/whatsapp/groupParticipants/'));
  await groupItem(page, name).click();
  await participants;
  await expect(page.locator('#messages-area .wpp-msg').first()).toBeVisible();
}

function webhook(page: Page, id: string, text: string, mentioned: string[]) {
  return page.request.post('/whatsapp/webhook', {
    data: {
      event: 'messages.upsert',
      instance: fx.instanceName,
      data: {
        key: { remoteJid: fx.group2Jid, fromMe: false, id, participant: '58935558037626@lid' },
        pushName: 'Lucas Vacari',
        message: { extendedTextMessage: { text, contextInfo: { mentionedJid: mentioned } } },
        messageTimestamp: Math.floor(Date.now() / 1000),
      },
    },
  });
}

test.describe('WhatsApp — menções (@) em grupos', () => {
  test('grupo que marcou o nosso número mostra "@"; abrir o grupo apaga', async ({ page }) => {
    await login(page);
    await openGroups(page);

    const marcado = groupItem(page, 'Grupo Menções E2E');
    await expect(marcado.locator('.wpp-mention-badge')).toBeVisible();
    await expect(groupItem(page, 'Grupo Webhook E2E').locator('.wpp-mention-badge')).toHaveCount(0);
    await expect(page.locator('#groups-mention-flag')).toBeVisible();

    await openGroup(page, 'Grupo Menções E2E');

    // A mensagem que marcou a gente fica destacada e a menção vira "@Você"
    const msgMencao = page.locator('.wpp-msg.mentions-me');
    await expect(msgMencao).toHaveCount(1);
    await expect(msgMencao.locator('.wpp-mention-me')).toHaveText('@Você');
    await expect(msgMencao.locator('.wpp-msg-sender')).toHaveText('Ana Cliente');

    // Some na hora e continua sumido depois do polling da lista (servidor zerou)
    await expect(marcado.locator('.wpp-mention-badge')).toHaveCount(0);
    await expect(page.locator('#groups-mention-flag')).toBeHidden();
    await expect.poll(() => state().groups.group1.mention_count).toBe(0);
    await page.waitForTimeout(3500);
    await expect(marcado.locator('.wpp-mention-badge')).toHaveCount(0);

    // Abrir o grupo descobriu o nosso número/LID pela Evolution (para detectar menções)
    const s = state();
    expect(s.owner_phone).toBe(OWNER_PHONE);
    expect(s.owner_lid).toBe(OWNER_LID);
  });

  test('autocomplete do @ marca o participante e envia a menção real', async ({ page }) => {
    await login(page);
    await openGroups(page);
    await openGroup(page, 'Grupo Menções E2E');

    const input = page.locator('#message-input');
    await input.click();
    await input.pressSequentially('Bom dia @luc');

    const box = page.locator('#mention-suggest');
    await expect(box).toBeVisible();
    await expect(box.locator('.wpp-mention-item')).toHaveCount(1);
    await expect(box.locator('.wpp-mention-item').first()).toContainText('Lucas Vacari');
    await expect(box.locator('.wpp-mention-item').first()).toContainText('+55 (99) 90000-0002');

    await input.press('Enter'); // escolhe, não envia
    await expect(box).toBeHidden();
    await expect(input).toHaveValue('Bom dia @Lucas Vacari ');

    // "@" sozinho lista todos, menos o nosso número
    await input.pressSequentially('e @');
    await expect(box).toBeVisible();
    await expect(box).toContainText('Ana Cliente');
    await expect(box).toContainText('Punta Cana para Brasileiros');
    await expect(box).not.toContainText('98888-7777');
    await expect(box.locator('.wpp-mention-item')).toHaveCount(3);
    await input.press('ArrowDown'); // "Lucas Vacari" (2º em ordem alfabética)
    await input.press('Escape');
    await expect(box).toBeHidden();
    await input.pressSequentially('cli'); // filtra pelo nome (sem acento/caixa)
    await expect(box.locator('.wpp-mention-item')).toHaveCount(1);
    await expect(box.locator('.wpp-mention-item').first()).toContainText('Ana Cliente');
    await box.locator('.wpp-mention-item').first().click();
    await expect(input).toHaveValue('Bom dia @Lucas Vacari e @Ana Cliente ');
    await input.pressSequentially('podem olhar?');

    calls.length = 0;
    const sent = page.waitForResponse((r) => r.url().includes('/whatsapp/send') && r.request().method() === 'POST');
    await input.press('Enter');
    expect((await sent).status()).toBe(200);

    const sendCall = calls.find((c) => c.url.startsWith(`/message/sendText/${fx.instanceName}`));
    expect(sendCall).toBeTruthy();
    const payload = JSON.parse(sendCall!.body);
    expect(payload.number).toBe(fx.group1Jid);
    // O texto leva "@<número>" (o WhatsApp mostra o nome) e a assinatura no topo
    expect(payload.text).toMatch(/\nBom dia @58935558037626 e @236932122603562 podem olhar\?$/);
    expect(payload.mentioned).toEqual(['58935558037626@lid', '236932122603562@lid']);
    expect(payload).not.toHaveProperty('mentionsEveryOne');

    // Na tela a bolha mostra os nomes
    const mine = page.locator('.wpp-msg.mine').last();
    await expect(mine).toContainText('@Lucas Vacari');
    await expect(mine).toContainText('@Ana Cliente');
    await expect(input).toHaveValue('');
  });

  test('nome digitado à mão (sem escolher na lista) não vira menção', async ({ page }) => {
    await login(page);
    await openGroups(page);
    await openGroup(page, 'Grupo Menções E2E');

    const input = page.locator('#message-input');
    await input.click();
    await input.pressSequentially('@zzz');
    await expect(page.locator('#mention-suggest')).toContainText('Nenhum participante encontrado');
    await input.press('Escape');
    await input.fill('falando do @Lucas Vacari aqui');

    calls.length = 0;
    const sent = page.waitForResponse((r) => r.url().includes('/whatsapp/send') && r.request().method() === 'POST');
    await input.press('Enter');
    await sent;
    const payload = JSON.parse(calls.find((c) => c.url.startsWith('/message/sendText/'))!.body);
    expect(payload.text).toContain('falando do @Lucas Vacari aqui');
    expect(payload).not.toHaveProperty('mentioned');
  });

  test('em conversa individual o @ não abre a lista', async ({ page }) => {
    await login(page);
    await page.goto('/whatsapp/chat');
    const sel = page.locator('#filter-instance');
    if (await sel.count()) await sel.selectOption(String(fx.instanceId));
    await page.locator('.wpp-contact-item', { hasText: 'Contato Individual E2E' }).click();
    const input = page.locator('#message-input');
    await expect(input).toBeVisible();
    await input.click();
    await input.pressSequentially('oi @');
    await page.waitForTimeout(300);
    await expect(page.locator('#mention-suggest')).toBeHidden();
  });

  test('webhook: menção ao nosso número (LID) liga o "@" e o aviso; outras não', async ({ page }) => {
    php(['reset-owner', JSON.stringify(fx)]);
    expect(state().owner_lid).toBeNull();

    // Alguém marca outra pessoa: nada muda
    let r = await webhook(page, 'E2EWH_OUTRO_' + Date.now(), '@236932122603562 pode ver?', ['236932122603562@lid']);
    expect(r.status()).toBe(200);
    expect(state().groups.group2.mention_count).toBe(0);

    // Marca o nosso número: o sistema descobre telefone + LID na Evolution e conta
    const id = 'E2EWH_MIM_' + Date.now();
    r = await webhook(page, id, '@156079530967127 consegue olhar isso?', [OWNER_LID]);
    expect(r.status()).toBe(200);
    let s = state();
    expect(s.groups.group2.mention_count).toBe(1);
    expect(s.owner_phone).toBe(OWNER_PHONE);
    expect(s.owner_lid).toBe(OWNER_LID);
    expect(s.group2_last_mention).toBe('@156079530967127 consegue olhar isso?');

    // Reenvio da mesma mensagem pelo webhook não conta de novo
    await webhook(page, id, '@156079530967127 consegue olhar isso?', [OWNER_LID]);
    s = state();
    expect(s.groups.group2.mention_count).toBe(1);

    // Aviso (toast) fora da tela do chat
    await login(page);
    const notifs = await (await page.request.get('/whatsapp/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })).json();
    const n = notifs.find((x: any) => Number(x.contact_id) === fx.group2);
    expect(n).toBeTruthy();
    expect(n.is_mention).toBe(true);
    expect(n.sender_name).toBe('Lucas Vacari');
    expect(n.contact_name).toBe('Grupo Webhook E2E');

    const toast = page.locator('.wpp-push-toast', { hasText: 'Grupo Webhook E2E' });
    await expect(toast).toBeVisible({ timeout: 10_000 });
    await expect(toast.locator('.wpp-push-mention')).toHaveText('@');
    await expect(toast).toContainText('Lucas Vacari te mencionou');

    // Na lista do chat o grupo aparece com "@"
    await openGroups(page);
    await expect(groupItem(page, 'Grupo Webhook E2E').locator('.wpp-mention-badge')).toBeVisible();
  });
});
