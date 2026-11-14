import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { existsSync } from 'node:fs';

/**
 * Nome de quem falou na transcrição: na sala, o navegador de quem grava
 * registra quem estava com o contorno verde (mídia falsa do Chromium faz o
 * microfone de cada um bipar), envia com os segmentos de áudio e a tela da
 * gravação mostra "Nome: fala". A OpenAI é simulada pelo fixture
 * (transcribe-fake), com a mesma junção que o cron usa.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');

function fixture(...args: string[]): any {
  const out = execFileSync(PHP, ['tests-e2e/meeting-flow-fixture.php', ...args], { cwd: ROOT });
  return JSON.parse(out.toString());
}

let user: { id: number; email: string; password: string };

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);
  await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('button[type="submit"]')]);
}

async function join(page: Page, token: string, name: string, query = '') {
  await page.goto(`/videocall/room/${token}${query}`);
  await page.fill('#lb-name', name);
  await page.click('#lb-join');
  await expect(page.locator('#call')).toBeVisible({ timeout: 15_000 });
}

test.describe.serial('transcrição com o nome de quem falou', () => {
  test.beforeAll(() => { user = fixture('user'); });
  test.afterAll(() => { if (user) fixture('cleanup', String(user.id)); });

  test('quem fala na reunião aparece com o nome na transcrição', async ({ browser }) => {
    test.setTimeout(300_000);
    const room = fixture('room', String(user.id));
    const a = await browser.newContext();
    const b = await browser.newContext();
    const pa = await a.newPage();
    const pb = await b.newPage();
    // Erro de JavaScript na sala de quem grava quebraria a gravação: registra para o relatório.
    const jsErrors: string[] = [];
    const recCalls: string[] = [];
    pa.on('pageerror', (e) => jsErrors.push(String(e)));
    pa.on('response', (r) => {
      const m = r.url().match(/\/videocall\/(rec\w+)\//);
      if (m) recCalls.push(`${m[1]} ${r.status()}`);
    });
    test.info().annotations.push({ type: 'diagnóstico', description: 'veja jsErrors/recCalls no console em caso de falha' });
    const dump = () => console.log('jsErrors:', JSON.stringify(jsErrors), '\nrecCalls:', JSON.stringify(recCalls.slice(-40)));

    // O primeiro a entrar começa a gravar sozinho (gravação automática da sala).
    await join(pa, room.token, 'Lucas Vacari', '?audio_seg_sec=5');
    await expect(pa.locator('#rec-indicator')).toBeVisible({ timeout: 15_000 });
    await join(pb, room.token, 'Narciso');
    // O microfone falso do Narciso chega ao gravador (contorno verde no tile dele).
    await expect(pa.locator('.tile.speaking')).not.toHaveCount(0, { timeout: 30_000 });
    await pa.waitForTimeout(13_000);
    await pa.click('#btn-rec');
    try {
      // Antes de publicar, a sala termina de subir os pedaços de vídeo (Chromium
      // sem GPU codifica o mosaico devagar): dá tempo de sobra.
      await expect(pa.locator('#rec-modal')).toBeVisible({ timeout: 120_000 });
    } finally {
      dump();
    }
    expect(jsErrors).toEqual([]);

    // O que chegou ao servidor: intervalos com o nome de cada participante.
    const spk = fixture('room-speakers', String(room.id));
    expect(spk.token).toBeTruthy();
    // O bipe do microfone falso passa pela supressão de ruído do Chromium e às
    // vezes some de um dos lados: exige pelo menos um nome e só nomes da sala.
    const names = [...new Set(spk.speakers.map((s: any) => s.n))];
    expect(names.length).toBeGreaterThan(0);
    for (const n of names) expect(['Lucas Vacari', 'Narciso']).toContain(n);
    for (const s of spk.speakers) {
      expect(s.s).toBeGreaterThanOrEqual(0);
      expect(s.e).toBeGreaterThan(s.s);
      expect(s.e).toBeLessThan(300); // tempo da gravação (segundos), não hora do relógio
    }

    // Cron com OpenAI simulada: cada trecho recebe o nome de quem falava.
    const t = fixture('transcribe-fake', spk.token);
    expect(t.status).toBe('done');
    expect(t.transcript).toMatch(/^\[\d\d:\d\d\] (Lucas Vacari|Narciso): Fala do trecho \d+$/m);
    const named = t.segments.filter((s: any) => s.speaker);
    expect(named.length).toBeGreaterThan(0);
    for (const s of named) expect(names).toContain(s.speaker);

    // Tela da gravação (equipe) e link público mostram o nome antes da fala.
    await login(pa);
    await pa.goto(`/videocall/watch/${spk.token}`);
    await expect(pa.locator('#tr-list .seg .who').first()).toHaveText(/^(Lucas Vacari|Narciso):$/);
    await expect(pa.locator('#tr-list .seg').first()).toContainText('Fala do trecho 1');
    // "Copiar transcrição" leva o nome junto.
    await a.grantPermissions(['clipboard-read', 'clipboard-write']);
    pa.on('dialog', (d) => d.accept());
    await pa.evaluate(() => (window as any).copyTranscript());
    await expect.poll(() => pa.evaluate(() => navigator.clipboard.readText())).toMatch(/\] (Lucas Vacari|Narciso): Fala do trecho \d+/);

    const pub = await browser.newPage();
    await pub.goto(`/videocall/share/${spk.token}`);
    await expect(pub.locator('#tr-list .seg .who').first()).toHaveText(/^(Lucas Vacari|Narciso):$/);
    await pub.close();

    // Celular: o nome aparece na linha e a página não ganha rolagem lateral.
    for (const width of [320, 390]) {
      const m = await browser.newPage({ viewport: { width, height: 800 } });
      await m.goto(`/videocall/share/${spk.token}`);
      const who = m.locator('#tr-list .seg .who').first();
      await who.scrollIntoViewIfNeeded();
      await expect(who).toBeVisible();
      const overflow = await m.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await m.screenshot({ path: test.info().outputPath(`share-${width}.png`), fullPage: true });
      await m.close();
    }
    await a.close();
    await b.close();
  });
});
