import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { existsSync } from 'node:fs';

/**
 * E2E do fluxo comercial (change 01): agenda com gravação automática, aviso de
 * gravação para todos, segmentos de áudio no servidor, estado da transcrição,
 * revisão da minuta e proposta gerada da minuta. Canais externos (SMTP,
 * WhatsApp) e OpenAI ficam sem configuração no banco local.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');

function fixture(...args: string[]): any {
  const out = execFileSync(PHP, ['tests-e2e/meeting-flow-fixture.php', ...args], { cwd: ROOT });
  return JSON.parse(out.toString());
}

let user: { id: number; email: string; password: string };
let recs: { queued: string; old: string; draft: string; roomId: number };

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('button[type="submit"]')]);
}

test.describe.serial('fluxo comercial: reunião até proposta', () => {
  test.beforeAll(() => {
    user = fixture('user');
    recs = fixture('recordings', String(user.id));
  });
  test.afterAll(() => {
    if (user) fixture('cleanup', String(user.id));
  });

  test('sem login, transcrição e minuta recusam e não gravam nada', async ({ request }) => {
    const before = fixture('state', recs.draft).rec;
    const noFollow = { maxRedirects: 0 };
    const calls = [
      request.post(`/videocall/transcribeChunk/${recs.draft}`, { ...noFollow, multipart: { offset: '0', index: '0' } }),
      request.post(`/videocall/saveTranscript/${recs.draft}`, { ...noFollow, data: { segments: [{ start: 0, end: 1, text: 'invasor' }] } }),
      request.post(`/videocall/saveMinutes/${recs.draft}`, { ...noFollow, data: { minutes: 'invasor' } }),
      request.post(`/videocall/generateMinutes/${recs.draft}`, noFollow),
      request.post(`/videocall/sendMinutes/${recs.draft}`, noFollow),
      request.get(`/videocall/recordingInfo/${recs.draft}`, noFollow),
    ];
    for (const res of await Promise.all(calls)) {
      // AJAX recebe 401; navegação normal é mandada ao login.
      expect([401, 302]).toContain(res.status());
    }
    // Com Accept JSON a recusa é 401 explícito.
    const json = await request.post(`/videocall/saveTranscript/${recs.draft}`, {
      headers: { Accept: 'application/json' }, data: { segments: [{ start: 0, end: 1, text: 'invasor' }] }, maxRedirects: 0,
    });
    expect(json.status()).toBe(401);

    const after = fixture('state', recs.draft).rec;
    expect(after.transcript).toBe(before.transcript);
    expect(after.minutes).toBe(before.minutes);
    expect(after.minutes_sent_at).toBeNull();
  });

  test('tela da gravação mostra a fila e mantém o botão manual na gravação antiga', async ({ page }) => {
    await login(page);
    await page.goto(`/videocall/watch/${recs.queued}`);
    await expect(page.locator('#tr-empty')).toContainText('Na fila de transcrição');
    await expect(page.locator('#tr-btn')).toHaveCount(0);

    await page.goto(`/videocall/watch/${recs.old}`);
    await expect(page.locator('#tr-empty')).toContainText('ainda não foi transcrita');
    await expect(page.locator('#tr-btn')).toBeVisible();
  });

  test('revisão da minuta: editar, salvar e enviar', async ({ page }) => {
    page.on('dialog', (d) => d.accept());
    await login(page);
    await page.goto(`/videocall/watch/${recs.draft}`);
    await page.click('#tab-sm-btn');
    await expect(page.locator('#sm-state')).toContainText('Rascunho');
    await expect(page.locator('#min-comercial')).toContainText('Organizar leads');

    await page.click('#min-edit-btn');
    await page.fill('#f-resumo', 'Resumo revisado pelo comercial.');
    await page.fill('#f-orcamento', 'R$ 8 mil\nEntrada de 50%');
    await page.click('#min-save-btn');
    await expect(page.locator('#sm-state')).toContainText('editada em');

    let state = fixture('state', recs.draft).rec;
    expect(Number(state.minutes_edited_by)).toBe(user.id);
    expect(JSON.parse(state.summary).orcamento).toEqual(['R$ 8 mil', 'Entrada de 50%']);
    expect(state.minutes).toContain('Resumo revisado pelo comercial.');
    expect(state.minutes_sent_at).toBeNull();

    await page.reload();
    await page.click('#tab-sm-btn');
    await expect(page.locator('#rs-body')).toHaveText('Resumo revisado pelo comercial.', { useInnerText: true });

    await page.click('#min-send-btn');
    await expect(page.locator('#sm-state')).toContainText('Enviada');
    state = fixture('state', recs.draft).rec;
    expect(state.minutes_sent_at).not.toBeNull();
    expect(Number(state.minutes_sent_by)).toBe(user.id);
  });

  test('gerar proposta da minuta abre o editor com o entendimento preenchido', async ({ page }) => {
    const dialogs: string[] = [];
    page.on('dialog', (d) => { dialogs.push(d.message()); d.accept(); });
    await login(page);
    await page.goto(`/videocall/watch/${recs.draft}`);
    await page.click('#tab-sm-btn');
    await Promise.all([page.waitForURL(/\/proposal\/edit\/\d+/), page.click('#min-proposal-btn')]);

    // OpenAI não configurada no local: avisa e cria o rascunho mesmo assim.
    expect(dialogs.join(' ')).toContain('sugestão da IA falhou');
    await expect(page.locator('textarea[data-key="business_opportunity"]')).toHaveValue(/Organizar leads/);
    await expect(page.locator('textarea[data-key="business_profile"]')).toHaveValue(/Resumo revisado/);

    // Voltando à gravação, o link da proposta aparece e criar outra pede confirmação.
    await page.goto(`/videocall/watch/${recs.draft}`);
    await page.click('#tab-sm-btn');
    await expect(page.locator('#min-proposal-link')).toBeVisible();
  });

  test('agenda: reunião comercial com sala do sistema respeita "Gravar automaticamente"', async ({ page }) => {
    await login(page);
    for (const [title, gravar] of [['E2E comercial grava', true], ['E2E comercial sem gravar', false]] as const) {
      await page.goto('/agenda');
      await page.evaluate(() => (window as any).openMeetingModal());
      await expect(page.locator('#meetingModal')).toBeVisible();
      await page.fill('#mt-title', title);
      await page.selectOption('#mt-type', 'comercial');
      const when = new Date(Date.now() + 3 * 86400000);
      const local = new Date(when.getTime() - when.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
      await page.fill('#mt-meeting-at', local);
      await page.evaluate(() => { (window as any).eval("mtClients.push({ contact_id: null, name: 'Cliente E2E', email: 'cliente-e2e@example.test', phone: '' })"); });
      await page.click('label[for="mt-mode-room"]');
      await expect(page.locator('#mt-meet-hint')).toContainText('Link gerado', { timeout: 15_000 });
      // Reunião comercial vem com a gravação automática marcada.
      await expect(page.locator('#mt-room-auto-record')).toBeChecked();
      if (!gravar) await page.uncheck('#mt-room-auto-record');
      // saveMeeting recarrega a página depois de salvar: espera o novo load.
      const saved = page.waitForResponse((r) => r.url().includes('/agenda/create') && r.request().method() === 'POST');
      const reloaded = page.waitForEvent('load');
      await page.evaluate(() => (window as any).saveMeeting());
      expect((await saved).status()).toBe(200);
      await reloaded;
      expect(fixture('meeting-room', title).auto_record).toBe(gravar ? 1 : 0);
    }
  });

  test('sala: aviso de gravação para quem entra depois e segmentos de áudio no servidor', async ({ browser }) => {
    test.setTimeout(120_000);
    const room = fixture('room', String(user.id));
    const a = await browser.newContext();
    const b = await browser.newContext();
    const pa = await a.newPage();
    const pb = await b.newPage();

    await pa.goto(`/videocall/room/${room.token}?audio_seg_sec=5`);
    await pa.fill('#lb-name', 'Gravador');
    await pa.click('#lb-join');
    await expect(pa.locator('#call')).toBeVisible({ timeout: 15_000 });
    await pa.click('#btn-rec');
    await expect(pa.locator('#rec-indicator')).toBeVisible();

    // B entra depois do início: o aviso precisa aparecer pelo reenvio periódico.
    await pb.goto(`/videocall/room/${room.token}`);
    await pb.fill('#lb-name', 'Cliente');
    await pb.click('#lb-join');
    await expect(pb.locator('#call')).toBeVisible({ timeout: 15_000 });
    await expect(pb.locator('#rec-indicator')).toBeVisible({ timeout: 25_000 });
    await expect(pb.locator('#rec-label')).toContainText('Sendo gravada');

    await pa.waitForTimeout(13_000);
    await pa.click('#btn-rec');
    await expect(pa.locator('#rec-modal')).toBeVisible({ timeout: 30_000 });

    const r = fixture('room-recording', String(room.id));
    expect(r.rec).not.toBeNull();
    expect(r.rec.transcribe_status).toBe('queued');
    expect(r.segments.length).toBeGreaterThanOrEqual(2);
    expect(r.segments.map((s: any) => s.seq)).toEqual([...r.segments.keys()].map((i) => i + 1));
    for (const s of r.segments) {
      expect(s.size).toBeGreaterThan(0);
      expect(s.mime).toMatch(/^audio\//);
    }
    expect(r.segments[1].start_sec).toBeGreaterThanOrEqual(4);
    await a.close();
    await b.close();
  });
});
