import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: editor de sequência e lista de sequências.
 * - Arrastar no fundo do quadro seleciona vários blocos; Shift+clique soma/tira.
 * - Arrastar um bloco selecionado move o grupo todo; setas movem um pouco.
 * - Duplicar (Ctrl+D), copiar (Ctrl+C) e colar (Ctrl+V), inclusive em outra
 *   sequência, mantendo as ligações entre os blocos copiados.
 * - Excluir vários de uma vez (Delete, com confirmação).
 * - Lista: botão "Duplicar" copia a sequência inteira (sem os leads) e abre a cópia.
 *
 * Obs.: salvar a sequência no banco local falha por falta da migration 138
 * (whatsapp_instance_id); por isso o resultado do editor é conferido pelo
 * grafo que seria salvo (buildGraph), o mesmo enviado no "Salvar".
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/sequence-multiselect-fixture.php';

type Fixture = { u: string; seqA: number; seqB: number; admin: { id: number; email: string; password: string } };
type GNode = { id: string; type: string; x: number; y: number; data: any; next?: string; nextYes?: string; nextNo?: string; nextReply?: string };
type SeqRow = { id: number; name: string; is_active: number; daily_limit: number; graph: { start: string; nodes: GNode[] }; leads: number };

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

/** Grafo que o "Salvar" enviaria (posições arredondadas: o mouse pode gerar frações). */
const graph = (page: Page): Promise<{ start: string; nodes: GNode[] }> => page.evaluate(() => {
  const g = (window as any).buildGraph();
  g.nodes.forEach((n: any) => { n.x = Math.round(n.x); n.y = Math.round(n.y); });
  return g;
});
const selected = (page: Page): Promise<string[]> => page.evaluate('Array.from(selection)') as Promise<string[]>;
const nodeEl = (page: Page, id: string) => page.locator(`.seq-node[data-id="${id}"]`);
const byId = (g: { nodes: GNode[] }, id: string) => g.nodes.find(n => n.id === id)!;

async function openEditor(page: Page, seqId: number) {
  await page.goto(`/sequences/edit/${seqId}`);
  await expect(page.locator('.seq-node').first()).toBeVisible();
}

/** Arrasta no fundo do quadro, em coordenadas do quadro (zoom 100%). */
async function marquee(page: Page, from: [number, number], to: [number, number], modifier?: 'Shift') {
  const box = (await page.locator('#canvas').boundingBox())!;
  if (modifier) await page.keyboard.down(modifier);
  await page.mouse.move(box.x + from[0], box.y + from[1]);
  await page.mouse.down();
  await page.mouse.move(box.x + to[0], box.y + to[1], { steps: 8 });
  await page.mouse.up();
  if (modifier) await page.keyboard.up(modifier);
}

/** Arrasta um bloco pelo cabeçalho. */
async function dragNode(page: Page, id: string, dx: number, dy: number) {
  const b = (await nodeEl(page, id).locator('.hd').boundingBox())!;
  const x = b.x + 40, y = b.y + b.height / 2;
  await page.mouse.move(x, y);
  await page.mouse.down();
  await page.mouse.move(x + dx, y + dy, { steps: 10 });
  await page.mouse.up();
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 900 } });

  test('arrastar no fundo seleciona vários e mover arrasta o grupo junto', async ({ page }, testInfo) => {
    await login(page);
    await openEditor(page, fx.seqA);
    await expect(page.locator('#sel-count')).toHaveText('Nenhum bloco selecionado');
    await expect(page.locator('#sel-dup')).toBeDisabled();

    // Retângulo pega a1 (y 80) e a2 (y 240), não a3 (y 400)
    await marquee(page, [30, 30], [320, 300]);
    expect((await selected(page)).sort()).toEqual(['a1', 'a2']);
    await expect(page.locator('.seq-node.sel')).toHaveCount(2);
    await expect(page.locator('#sel-count')).toHaveText('2 blocos selecionados');
    await expect(page.locator('#sel-dup')).toBeEnabled();
    await expect(page.locator('#inspector-drawer')).not.toHaveClass(/open/);
    await page.screenshot({ path: testInfo.outputPath('sequencia-selecao-area.png') });

    await dragNode(page, 'a1', 120, 60);
    let g = await graph(page);
    expect([byId(g, 'a1').x, byId(g, 'a1').y]).toEqual([200, 140]);
    expect([byId(g, 'a2').x, byId(g, 'a2').y]).toEqual([200, 300]);
    expect([byId(g, 'a3').x, byId(g, 'a3').y]).toEqual([80, 400]);
    expect(byId(g, 'a1').next).toBe('a2');

    // Setas: 10px (Shift = 50px), o grupo todo
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Shift+ArrowDown');
    g = await graph(page);
    expect([byId(g, 'a1').x, byId(g, 'a1').y]).toEqual([210, 190]);
    expect([byId(g, 'a2').x, byId(g, 'a2').y]).toEqual([210, 350]);

    // Clique sem arrastar num bloco do grupo: abre só esse bloco
    await nodeEl(page, 'a2').locator('.bd').click();
    expect(await selected(page)).toEqual(['a2']);
    await expect(page.locator('#inspector-drawer')).toHaveClass(/open/);

    // Shift+clique soma; Esc limpa
    await nodeEl(page, 'a3').locator('.bd').click({ modifiers: ['Shift'] });
    expect((await selected(page)).sort()).toEqual(['a2', 'a3']);
    await expect(page.locator('#inspector-drawer')).not.toHaveClass(/open/);
    await nodeEl(page, 'a3').locator('.bd').click({ modifiers: ['Shift'] });
    expect(await selected(page)).toEqual(['a2']);
    await page.keyboard.press('Escape');
    expect(await selected(page)).toEqual([]);

    // Clique no fundo também limpa; Ctrl+A seleciona tudo
    await page.keyboard.press('Control+a');
    expect((await selected(page)).sort()).toEqual(['a1', 'a2', 'a3']);
    const box = (await page.locator('#canvas').boundingBox())!;
    await page.mouse.click(box.x + 700, box.y + 450);
    expect(await selected(page)).toEqual([]);
  });

  test('duplicar, copiar e colar (inclusive em outra sequência) mantém as ligações', async ({ page }, testInfo) => {
    await login(page);
    await openEditor(page, fx.seqA);
    await marquee(page, [30, 30], [320, 300]);
    await page.keyboard.press('Control+d');
    let g = await graph(page);
    expect(g.nodes).toHaveLength(5);
    expect(g.start).toBe('a1');
    const copies = g.nodes.filter(n => !['a1', 'a2', 'a3'].includes(n.id));
    const sendCopy = copies.find(n => n.type === 'send')!;
    const waitCopy = copies.find(n => n.type === 'wait')!;
    expect([sendCopy.x, sendCopy.y]).toEqual([120, 120]);
    expect(sendCopy.data.subject).toBe('Primeiro contato');
    expect(sendCopy.next).toBe(waitCopy.id);            // ligação refeita entre as cópias
    expect(waitCopy.next).toBeUndefined();             // a3 não foi copiado: sem ligação para o original
    expect(byId(g, 'a2').next).toBe('a3');             // original intacto
    expect((await selected(page)).sort()).toEqual([sendCopy.id, waitCopy.id].sort());
    await expect(page.locator('#sel-toast')).toContainText('2 blocos duplicados');

    // Editar a cópia não altera o original (cópia independente)
    await page.evaluate((id) => { (window as any).selectNode(id); (window as any).setData('subject', 'Alterado na cópia'); }, sendCopy.id);
    g = await graph(page);
    expect(byId(g, 'a1').data.subject).toBe('Primeiro contato');

    // Excluir vários (com confirmação). O retângulo pega só a faixa direita das
    // cópias (x 270–310), que os originais (até x 270) não alcançam.
    await marquee(page, [330, 110], [290, 330]);
    expect((await selected(page)).sort()).toEqual([sendCopy.id, waitCopy.id].sort());
    page.once('dialog', d => { expect(d.message()).toContain('Excluir 2 blocos'); d.accept(); });
    await page.locator('#sel-del').click();
    g = await graph(page);
    expect(g.nodes.map(n => n.id).sort()).toEqual(['a1', 'a2', 'a3']);

    // Copiar a1+a2 e colar na sequência B
    await marquee(page, [30, 30], [320, 300]);
    await page.keyboard.press('Control+c');
    await expect(page.locator('#sel-toast')).toContainText('2 blocos copiados');
    await expect(page.locator('#sel-paste')).toBeEnabled();

    await openEditor(page, fx.seqB);
    await expect(page.locator('#sel-paste')).toBeEnabled();
    await page.keyboard.press('Control+v');
    g = await graph(page);
    expect(g.nodes).toHaveLength(3);
    expect(g.start).toBe('b1');
    const pasted = g.nodes.filter(n => n.id !== 'b1');
    const ps = pasted.find(n => n.type === 'send')!, pw = pasted.find(n => n.type === 'wait')!;
    expect(ps.next).toBe(pw.id);
    expect(ps.data.body).toContain('<strong>Oi</strong>');
    expect(pw.y - ps.y).toBe(160);                     // distância entre os blocos preservada
    await expect(page.locator('.seq-node.sel')).toHaveCount(2);
    await page.screenshot({ path: testInfo.outputPath('sequencia-colada-em-outra.png') });

    // Botão Colar de novo: mais uma cópia
    await page.locator('#sel-paste').click();
    expect((await graph(page)).nodes).toHaveLength(5);
  });

  test('lista: Duplicar copia a sequência inteira sem os leads e abre a cópia', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/sequences');
    const nameA = `E2E SeqSel A ${fx.u}`;
    const card = page.locator('#tab-sequences .card').filter({ hasText: nameA });
    await expect(card.locator('.seq-dup-btn')).toBeVisible();
    await expect(card.locator('.seq-dup-btn')).toHaveText(/Duplicar/);
    await page.screenshot({ path: testInfo.outputPath('lista-sequencias-duplicar.png') });

    await Promise.all([page.waitForURL(/\/sequences\/edit\/\d+\?copiada=1/), card.locator('.seq-dup-btn').click()]);
    await expect(page.locator('#seq-copy-notice')).toBeVisible();
    await expect(page.locator('#seq-name')).toHaveValue(`${nameA} (cópia)`);
    await expect(page.locator('.seq-node')).toHaveCount(3);

    const rows: SeqRow[] = JSON.parse(php(['sequences', fx.u]));
    const orig = rows.find(r => r.id === fx.seqA)!;
    const copy = rows.find(r => r.name === `${nameA} (cópia)`)!;
    expect(copy).toBeTruthy();
    expect(page.url()).toContain(`/sequences/edit/${copy.id}`);
    expect(copy.graph).toEqual(orig.graph);
    expect(copy.daily_limit).toBe(orig.daily_limit);
    expect(copy.is_active).toBe(orig.is_active);
    expect(Number(copy.leads)).toBe(0);
    await page.screenshot({ path: testInfo.outputPath('sequencia-copia-aberta.png') });
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 800 } });

    test('barra de seleção e botão Duplicar cabem na tela', async ({ page }, testInfo) => {
      await login(page);
      await openEditor(page, fx.seqA);
      const bar = page.locator('#seq-sel-bar');
      await expect(bar).toBeVisible();
      await page.locator('#sel-all').click();
      await expect(page.locator('#sel-count')).toHaveText('3 blocos selecionados');
      for (const id of ['#sel-all', '#sel-dup', '#sel-copy', '#sel-paste', '#sel-del']) {
        const b = (await page.locator(id).boundingBox())!;
        expect(b.x).toBeGreaterThanOrEqual(0);
        expect(b.x + b.width).toBeLessThanOrEqual(width + 1);
      }
      const barBox = (await bar.boundingBox())!;
      expect(barBox.x + barBox.width).toBeLessThanOrEqual(width + 1);
      await page.screenshot({ path: testInfo.outputPath(`sequencia-editor-${width}.png`), fullPage: true });

      await page.goto('/sequences');
      const card = page.locator('#tab-sequences .card').filter({ hasText: `E2E SeqSel B ${fx.u}` });
      const dup = (await card.locator('.seq-dup-btn').boundingBox())!;
      expect(dup.x + dup.width).toBeLessThanOrEqual(width + 1);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await card.scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`lista-sequencias-${width}.png`) });
    });
  });
}
