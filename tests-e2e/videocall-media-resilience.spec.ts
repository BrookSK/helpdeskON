import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { existsSync } from 'node:fs';

/**
 * Sala de vídeo: a mídia não pode depender de a aba estar visível nem de a
 * câmera funcionar.
 *  - Gravação: o mosaico segue sendo redesenhado com a aba oculta (o Chrome
 *    para o requestAnimationFrame nesse caso e a imagem gravada congelava).
 *  - Áudio remoto: toca mesmo quando a pessoa entrou sem câmera ou quando a
 *    câmera dela não entrega nenhum quadro (antes ninguém a via nem ouvia).
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');

function fixture(...args: string[]): any {
  const out = execFileSync(PHP, ['tests-e2e/meeting-flow-fixture.php', ...args], { cwd: ROOT });
  return JSON.parse(out.toString());
}

let user: { id: number; email: string; password: string };

async function join(page: Page, token: string, name: string) {
  // Não espera o evento load: um <video> que nunca recebe quadro (cenário da
  // câmera sem imagem) segura esse evento indefinidamente.
  await page.goto(`/videocall/room/${token}`, { waitUntil: 'domcontentloaded' });
  await page.fill('#lb-name', name);
  await page.click('#lb-join');
  await expect(page.locator('#call')).toBeVisible({ timeout: 15_000 });
}

/** Há áudio de outro participante tocando de fato nesta página? */
function hearsRemoteAudio(page: Page): Promise<boolean> {
  return page.evaluate(() => Array.from(document.querySelectorAll('audio, video')).some((el) => {
    const m = el as HTMLMediaElement;
    const s = m.srcObject as MediaStream | null;
    return !!s && s.getAudioTracks().some((t) => t.readyState === 'live')
      && !m.muted && !m.paused && m.currentTime > 0.5;
  }));
}

test.describe('sala de vídeo: mídia resiliente', () => {
  test.beforeAll(() => { user = fixture('user'); });
  test.afterAll(() => { if (user) fixture('cleanup', String(user.id)); });

  test('a gravação segue redesenhando o mosaico com a aba oculta', async ({ browser }) => {
    test.setTimeout(60_000);
    const room = fixture('room', String(user.id));
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    // Guarda os canvas capturados para ler o mosaico da gravação (1920x1080).
    await page.addInitScript(() => {
      const w = window as any;
      w.__captured = [];
      const orig = HTMLCanvasElement.prototype.captureStream;
      HTMLCanvasElement.prototype.captureStream = function (...a: any[]) {
        w.__captured.push(this);
        return orig.apply(this, a as any);
      };
    });
    await join(page, room.token, 'Quem grava');
    // Com gravação automática a sala já começa sozinha (1,5 s após entrar);
    // sem ela, inicia pelo botão.
    const rec = page.locator('#rec-indicator');
    await page.waitForTimeout(2_500);
    if (!(await rec.isVisible())) await page.click('#btn-rec');
    await expect(rec).toBeVisible({ timeout: 15_000 });

    // Aba oculta: o navegador deixa de chamar requestAnimationFrame.
    await page.evaluate(() => { window.requestAnimationFrame = () => 0; });
    const sample = () => page.evaluate(() => {
      const src = (window as any).__captured.find((c: HTMLCanvasElement) => c.width === 1920);
      if (!src) return 'sem mosaico';
      const c = document.createElement('canvas');
      c.width = 192; c.height = 108;
      const x = c.getContext('2d')!;
      x.drawImage(src, 0, 0, c.width, c.height);
      let h = 0;
      for (const b of x.getImageData(0, 0, c.width, c.height).data) h = (h * 31 + b) >>> 0;
      return String(h);
    });
    await page.waitForTimeout(500); // o último quadro já agendado ainda roda
    const before = await sample();
    expect(before).not.toBe('sem mosaico');
    // A câmera falsa do Chromium muda a cada quadro: o mosaico tem que mudar junto.
    await expect.poll(sample, { timeout: 10_000 }).not.toBe(before);
    await ctx.close();
  });

  test('entrar espera a câmera e o microfone abrirem', async ({ browser }) => {
    const room = fixture('room', String(user.id));
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    // A câmera leva 2 s para abrir; o clique em entrar vem antes disso.
    await page.addInitScript(() => {
      const md = navigator.mediaDevices;
      const orig = md.getUserMedia.bind(md);
      md.getUserMedia = (c?: MediaStreamConstraints) => new Promise((ok) => setTimeout(ok, 2_000)).then(() => orig(c));
    });
    await page.goto(`/videocall/room/${room.token}`, { waitUntil: 'domcontentloaded' });
    await page.fill('#lb-name', 'Apressado');
    await page.click('#lb-mic'); // escolhe entrar mudo antes de o microfone abrir
    await page.click('#lb-join');
    await expect(page.locator('#call')).toBeVisible({ timeout: 15_000 });
    // Ao entrar, a mídia já está pronta: as conexões nascem com câmera e microfone.
    expect(await page.evaluate('localStream ? localStream.getTracks().map((t) => t.kind).sort().join(",") : ""')).toBe('audio,video');
    // O mudo escolhido antes de o microfone abrir vale de verdade, não só no ícone.
    await expect(page.locator('#btn-mic')).toHaveClass(/\boff\b/);
    expect(await page.evaluate('localStream.getAudioTracks()[0].enabled')).toBe(false);
    await ctx.close();
  });

  test('quem entra antes de a câmera ficar pronta passa a ser visto e ouvido', async ({ browser }) => {
    test.setTimeout(90_000);
    const room = fixture('room', String(user.id));
    const a = await browser.newContext();
    const b = await browser.newContext();
    const pa = await a.newPage();
    const pb = await b.newPage();
    // Câmera e microfone do primeiro demoram 12 s para abrir (permissão pendente,
    // câmera lenta): ele entra na sala antes disso.
    await pa.addInitScript(() => {
      const md = navigator.mediaDevices;
      const orig = md.getUserMedia.bind(md);
      md.getUserMedia = (c?: MediaStreamConstraints) => new Promise((ok) => setTimeout(ok, 12_000)).then(() => orig(c));
    });
    await join(pa, room.token, 'Primeiro');
    const idA = await pa.evaluate('peerId') as string;
    // O segundo entra logo em seguida, com o primeiro ainda sem mídia.
    await join(pb, room.token, 'Segundo');
    expect(await pa.evaluate('!localStream')).toBe(true);

    // Quando a mídia do primeiro fica pronta, o segundo passa a vê-lo e ouvi-lo.
    const videoA = pb.locator(`#tile-${idA} video`);
    await expect.poll(() => videoA.evaluate((v: HTMLVideoElement) => v.videoWidth).catch(() => 0), { timeout: 30_000 }).toBeGreaterThan(0);
    await expect.poll(() => hearsRemoteAudio(pb), { timeout: 20_000 }).toBe(true);
    await expect.poll(() => hearsRemoteAudio(pa), { timeout: 20_000 }).toBe(true);
    await a.close();
    await b.close();
  });

  test('compartilhar a tela com som não cala o microfone de quem compartilha', async ({ browser }) => {
    test.setTimeout(90_000);
    const room = fixture('room', String(user.id));
    const a = await browser.newContext();
    const b = await browser.newContext();
    const pa = await a.newPage();
    const pb = await b.newPage();
    // Tela falsa com som: canvas animado + tom contínuo.
    await pb.addInitScript(() => {
      navigator.mediaDevices.getDisplayMedia = async () => {
        const c = document.createElement('canvas');
        c.width = 640; c.height = 360;
        const x = c.getContext('2d')!;
        let n = 0;
        setInterval(() => { x.fillStyle = `hsl(${(n += 20) % 360} 80% 50%)`; x.fillRect(0, 0, c.width, c.height); }, 100);
        const ac = new AudioContext();
        const osc = ac.createOscillator();
        const dest = ac.createMediaStreamDestination();
        osc.connect(dest); osc.start();
        return new MediaStream([c.captureStream(10).getVideoTracks()[0], dest.stream.getAudioTracks()[0]]);
      };
    });
    await join(pa, room.token, 'Assiste');
    await join(pb, room.token, 'Compartilha');
    const idB = await pb.evaluate('peerId') as string;
    await expect(pa.locator(`#tile-${idB}`)).toBeVisible({ timeout: 20_000 });
    await expect.poll(() => hearsRemoteAudio(pa), { timeout: 20_000 }).toBe(true);

    /** Ids das tracks de áudio tocando agora na página de quem assiste. */
    const playing = () => pa.evaluate(() => Array.from(document.querySelectorAll('audio'))
      .filter((el) => !el.paused && el.srcObject && (el.srcObject as MediaStream).getAudioTracks().some((t) => t.readyState === 'live'))
      .map((el) => (el.srcObject as MediaStream).getAudioTracks()[0].id).sort());
    const micOnly = await playing();
    expect(micOnly).toHaveLength(1);

    await pb.click('#btn-screen');
    await expect(pa.locator(`#tile-${idB}-screen`)).toBeVisible({ timeout: 20_000 });
    // Microfone e som da tela tocam juntos; o microfone é a mesma track de antes.
    await expect.poll(async () => (await playing()).length, { timeout: 20_000 }).toBe(2);
    expect(await playing()).toContain(micOnly[0]);

    // Parou de compartilhar: some a tela e o som dela; o microfone segue tocando.
    await pb.evaluate('stopScreen()');
    await expect(pa.locator(`#tile-${idB}-screen`)).toHaveCount(0, { timeout: 20_000 });
    await expect.poll(playing, { timeout: 20_000 }).toEqual(micOnly);
    await a.close();
    await b.close();
  });

  const scenarios = [
    {
      title: 'quem entra sem câmera é visto (avatar) e ouvido pelos outros',
      camOff: true,
      // Câmera ocupada por outro programa: só o microfone é liberado.
      tamper: () => {
        const md = navigator.mediaDevices;
        const orig = md.getUserMedia.bind(md);
        md.getUserMedia = (c?: MediaStreamConstraints) => (c && c.video
          ? Promise.reject(new DOMException('Câmera em uso', 'NotReadableError'))
          : orig(c));
      },
    },
    {
      title: 'câmera que não entrega quadros não cala o áudio da pessoa',
      camOff: false,
      // A câmera abre, mas nunca produz imagem (canvas capturado sem desenho).
      tamper: () => {
        const md = navigator.mediaDevices;
        const orig = md.getUserMedia.bind(md);
        md.getUserMedia = async (c?: MediaStreamConstraints) => {
          const s = await orig(c);
          if (!c || !c.video) return s;
          s.getVideoTracks().forEach((t) => { s.removeTrack(t); t.stop(); });
          s.addTrack(document.createElement('canvas').captureStream(0).getVideoTracks()[0]);
          return s;
        };
      },
    },
  ];

  for (const sc of scenarios) {
    test(sc.title, async ({ browser }) => {
      test.setTimeout(90_000);
      const room = fixture('room', String(user.id));
      const a = await browser.newContext();
      const b = await browser.newContext();
      const pa = await a.newPage();
      const pb = await b.newPage();
      await pa.addInitScript(sc.tamper);

      // Quem tem o problema entra primeiro; o outro entra em seguida.
      await join(pa, room.token, 'Primeiro');
      const idA = await pa.evaluate('peerId') as string;
      await join(pb, room.token, 'Segundo');

      const tileA = pb.locator(`#tile-${idA}`);
      await expect(tileA).toBeVisible({ timeout: 20_000 });
      if (sc.camOff) await expect(tileA).toHaveClass(/\bcam-off\b/);
      await expect.poll(() => hearsRemoteAudio(pb), { timeout: 20_000 }).toBe(true);
      // O caminho de volta continua funcionando: o primeiro ouve o segundo.
      await expect.poll(() => hearsRemoteAudio(pa), { timeout: 20_000 }).toBe(true);
      await a.close();
      await b.close();
    });
  }
});
