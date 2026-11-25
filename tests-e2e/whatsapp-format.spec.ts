import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: formatação das mensagens de WhatsApp (igual ao editor de e-mail).
 * - Templates (canal WhatsApp): barra com negrito, itálico, tachado, monoespaçado,
 *   listas, citação e emojis de destaque; prévia "como chega no WhatsApp"; salva
 *   texto com os marcadores do WhatsApp (*negrito*, _itálico_, ~tachado~).
 * - Troca de canal e-mail ↔ WhatsApp mantém a formatação. LinkedIn segue texto simples.
 * - Sequência: o bloco "Enviar WhatsApp" tem a mesma barra e a prévia.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/whatsapp-format-fixture.php';

type Fixture = { u: string; templateId: number; sequenceId: number; admin: { id: number; email: string; password: string } };
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

async function waitModal(page: Page, id: string) {
  await expect(page.locator(`#${id}.show`)).toBeVisible();
  await expect.poll(() => page.locator(`#${id}`).evaluate(el => {
    const dialog = el.querySelector('.modal-dialog') as HTMLElement;
    return getComputedStyle(el).opacity === '1' && getComputedStyle(dialog).transform === 'none';
  })).toBe(true);
}

/** Seleciona o trecho `text` (1ª ocorrência) dentro do textarea. */
async function selectText(ta: Locator, text: string) {
  await ta.evaluate((el: HTMLTextAreaElement, t) => {
    const i = el.value.indexOf(t);
    if (i < 0) throw new Error('trecho não encontrado: ' + t);
    el.focus();
    el.setSelectionRange(i, i + t.length);
  }, text);
}
async function cursorAtEnd(ta: Locator) {
  await ta.evaluate((el: HTMLTextAreaElement) => { el.focus(); el.setSelectionRange(el.value.length, el.value.length); });
}

async function openNewWhatsappTemplate(page: Page) {
  await page.goto('/prospection/templates');
  await page.locator('#btn-new-template').click();
  await waitModal(page, 'tplEditModal');
  await page.locator('#te-channel').selectOption('whatsapp');
  await expect(page.locator('#te-wa-toolbar')).toBeVisible();
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('template de WhatsApp: barra formata, prévia mostra e salva com os marcadores', async ({ page }, testInfo) => {
    await login(page);
    await openNewWhatsappTemplate(page);
    const name = `E2E WA Novo ${fx.u}`;
    await page.locator('#te-name').fill(name);
    const ta = page.locator('#te-text');
    const tb = page.locator('#te-wa-toolbar');
    await expect(page.locator('#te-wa-preview [data-wa-bubble]')).toContainText('A mensagem aparece aqui.');

    await ta.fill('Olá ');
    await cursorAtEnd(ta);
    await page.locator('#te-chips-text .var-chip[data-k="primeiro_nome"]').click();
    await ta.press('End');
    await page.keyboard.type(', temos uma proposta especial para você.\nPrazo curto\nPreço justo\nSuporte incluso');

    // Negrito: a palavra selecionada com o espaço do lado (o espaço fica fora do marcador)
    await selectText(ta, 'proposta ');
    await tb.locator('[data-wa="bold"]').click();
    await expect(ta).toHaveValue(/temos uma \*proposta\* especial/);
    // Clicar de novo no trecho já formatado tira o negrito
    await selectText(ta, 'proposta');
    await tb.locator('[data-wa="bold"]').click();
    await expect(ta).toHaveValue(/temos uma proposta especial/);
    await selectText(ta, 'proposta');
    await tb.locator('[data-wa="bold"]').click();
    // Itálico pelo atalho, tachado pela barra
    await selectText(ta, 'especial');
    await page.keyboard.press('Control+i');
    await selectText(ta, 'justo');
    await tb.locator('[data-wa="strike"]').click();
    // Lista com tópicos nas 3 últimas linhas
    await ta.evaluate((el: HTMLTextAreaElement) => { const i = el.value.indexOf('Prazo curto'); el.focus(); el.setSelectionRange(i, el.value.length); });
    await tb.locator('[data-wa="bullet"]').click();
    await expect(ta).toHaveValue(/\n• Prazo curto\n• Preço ~justo~\n• Suporte incluso$/);
    // Enter continua a lista; Enter na linha vazia da lista encerra
    await cursorAtEnd(ta);
    await page.keyboard.press('Enter');
    await page.keyboard.type('Sem fidelidade');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Posso te ligar?');
    await expect(ta).toHaveValue(/\n• Sem fidelidade\nPosso te ligar\?$/);
    // Citação + emoji de destaque
    await selectText(ta, 'Posso te ligar?');
    await tb.locator('[data-wa="quote"]').click();
    await cursorAtEnd(ta);
    await tb.locator('[data-wa-emoji-toggle]').click();
    await expect(tb.locator('.wa-emoji-menu')).toBeVisible();
    await expect(tb.locator('.wa-emoji-note')).toContainText('não aceita cor de letra');
    await tb.locator('.wa-emoji[data-emoji="🟢"]').click();
    await expect(ta).toHaveValue(/> Posso te ligar\?🟢$/);

    // Prévia: formatação, lista, citação e a variável com dado de exemplo
    const bubble = page.locator('#te-wa-preview [data-wa-bubble]');
    await expect(bubble.locator('strong')).toHaveText('proposta');
    await expect(bubble.locator('em')).toHaveText('especial');
    await expect(bubble.locator('s')).toHaveText('justo');
    await expect(bubble.locator('.wa-li')).toHaveCount(4);
    await expect(bubble.locator('.wa-li .wa-mark').first()).toHaveText('•');
    await expect(bubble.locator('.wa-quote')).toHaveText('Posso te ligar?🟢');
    await expect(bubble.locator('.wa-var')).toHaveText('Maria');
    await expect(bubble).not.toContainText('*');
    await expect(page.locator('#te-help')).toContainText('não aceita cor de letra');
    await page.screenshot({ path: testInfo.outputPath('wa-template-editor.png') });

    await page.locator('#te-save').click();
    await expect(page.locator('#tplEditModal')).toBeHidden();
    const saved = JSON.parse(php(['template', name]));
    expect(saved.channel).toBe('whatsapp');
    expect(saved.body).toBe('Olá {{primeiro_nome}}, temos uma *proposta* _especial_ para você.\n'
      + '• Prazo curto\n• Preço ~justo~\n• Suporte incluso\n• Sem fidelidade\n> Posso te ligar?🟢');

    // Cartão da lista mostra o texto sem os marcadores
    const card = page.locator(`.tpl-card:has-text("${name}")`);
    await expect(card.locator('.tpl-snippet')).toContainText('temos uma proposta especial');
    await expect(card.locator('.tpl-snippet')).not.toContainText('*');

    // Reabrir: o texto volta com os marcadores e a prévia formatada
    await card.getByRole('button', { name: /Editar/ }).click();
    await waitModal(page, 'tplEditModal');
    await expect(page.locator('#te-wa-toolbar')).toBeVisible();
    await expect(page.locator('#te-wa-preview strong')).toHaveText('proposta');
  });

  test('lista numerada, monoespaçado e regras do WhatsApp na prévia', async ({ page }) => {
    await login(page);
    await openNewWhatsappTemplate(page);
    const ta = page.locator('#te-text');
    const tb = page.locator('#te-wa-toolbar');
    await ta.fill('Primeiro\nSegundo\nTerceiro');
    await ta.evaluate((el: HTMLTextAreaElement) => { el.focus(); el.setSelectionRange(0, el.value.length); });
    await tb.locator('[data-wa="number"]').click();
    await expect(ta).toHaveValue('1. Primeiro\n2. Segundo\n3. Terceiro');
    await cursorAtEnd(ta);
    await page.keyboard.press('Enter');
    await expect(ta).toHaveValue('1. Primeiro\n2. Segundo\n3. Terceiro\n4. ');
    // Trocar para tópicos converte a numeração
    await ta.evaluate((el: HTMLTextAreaElement) => { el.focus(); el.setSelectionRange(0, el.value.length); });
    await tb.locator('[data-wa="bullet"]').click();
    await expect(ta).toHaveValue(/^• Primeiro\n• Segundo\n• Terceiro/);
    // Sem seleção: o botão insere o par de marcadores com o cursor no meio
    await ta.fill('Código: ');
    await cursorAtEnd(ta);
    await tb.locator('[data-wa="mono"]').click();
    await page.keyboard.type('ABC-123');
    await expect(ta).toHaveValue('Código: ```ABC-123```');
    await expect(page.locator('#te-wa-preview pre')).toHaveText('ABC-123');

    // Regras do WhatsApp: marcador só vale encostado no texto e com separação
    const html = await page.evaluate(() => {
      const W = (window as any).WaFormat;
      return {
        spaced: W.toHtml('2 * 3 * 4'),
        glued: W.toHtml('palavra*meio*palavra'),
        varItalic: W.toHtml('_{{primeiro_nome}}_'),
        varName: W.toHtml('{{primeiro_nome}} e {{empresa}}'),
        unclosed: W.toHtml('abre ``` e não fecha'),
        xss: W.toHtml('<img src=x onerror=alert(1)> *ok*'),
        strip: W.strip('*a* _b_ ~c~ ```d```'),
      };
    });
    expect(html.spaced).not.toContain('<strong>');
    expect(html.glued).not.toContain('<strong>');
    expect(html.varItalic).toContain('<em><span class="wa-var">Maria</span></em>');
    expect(html.varName).toContain('Maria</span> e <span class="wa-var">Empresa Exemplo');
    expect(html.unclosed).toContain('```');
    expect(html.xss).not.toContain('<img');
    expect(html.xss).toContain('&lt;img');
    expect(html.xss).toContain('<strong>ok</strong>');
    expect(html.strip).toBe('a b c d');
  });

  test('trocar e-mail ↔ WhatsApp mantém a formatação; LinkedIn fica sem barra', async ({ page }) => {
    await login(page);
    await page.goto('/prospection/templates');
    await page.locator('#btn-new-template').click();
    await waitModal(page, 'tplEditModal');
    const ed = page.locator('#te-editor .ql-editor');
    await ed.click();
    await page.locator('#te-editor .ql-bold').click();
    await page.keyboard.type('Importante');
    await page.locator('#te-editor .ql-bold').click();
    await page.keyboard.type(' leia ');
    await page.locator('#te-editor .ql-italic').click();
    await page.keyboard.type('hoje');

    await page.locator('#te-channel').selectOption('whatsapp');
    await expect(page.locator('#te-text')).toHaveValue('*Importante* leia _hoje_');
    await expect(page.locator('#te-wa-preview strong')).toHaveText('Importante');

    await page.locator('#te-channel').selectOption('email');
    await expect(ed.locator('strong')).toHaveText('Importante');
    await expect(ed.locator('em')).toHaveText('hoje');

    await page.locator('#te-channel').selectOption('whatsapp');
    await page.locator('#te-channel').selectOption('linkedin');
    await expect(page.locator('#te-wa-toolbar')).toBeHidden();
    await expect(page.locator('#te-wa-side')).toBeHidden();
    await expect(page.locator('#te-text')).toHaveValue('Importante leia hoje');
    await expect(page.locator('#te-help')).toContainText('LinkedIn não aceita formatação');
  });

  test('sequência: bloco Enviar WhatsApp tem a barra, a prévia e grava os marcadores', async ({ page }, testInfo) => {
    page.on('dialog', d => d.accept());
    await login(page);
    await page.goto(`/sequences/edit/${fx.sequenceId}`);
    await page.locator('.seq-node[data-id="n1"] .bd').click();
    await expect(page.locator('#inspector-drawer.open')).toBeVisible();
    const ta = page.locator('#insp-body');
    const tb = page.locator('#insp-wa-toolbar');
    await expect(tb.locator('[data-wa="bold"]')).toBeVisible();
    await expect(ta).toHaveValue('Texto antigo');

    // Template de WhatsApp formatado entra no bloco e a prévia acompanha
    await expect.poll(() => page.locator('#insp-template option').count()).toBeGreaterThan(1);
    await page.locator('#insp-template').selectOption(String(fx.templateId));
    await expect(ta).toHaveValue(/\*proposta pronta\*/);
    await page.locator('#inspector-drawer details summary').first().click();
    const bubble = page.locator('#insp-wa-preview [data-wa-bubble]');
    await expect(bubble.locator('strong')).toHaveText('proposta pronta');
    await expect(bubble.locator('.wa-li')).toHaveCount(2);

    // Formata pela barra
    await selectText(ta, 'Oi');
    await tb.locator('[data-wa="bold"]').click();
    await expect(bubble.locator('strong').first()).toHaveText('Oi');
    await page.screenshot({ path: testInfo.outputPath('seq-wa-node.png') });

    // Teste A/B: a variante B também tem a barra
    await page.locator('#insp-ab').check();
    await expect(page.locator('#insp-wa-toolbar-b [data-wa="bold"]')).toBeVisible();
    const tab = page.locator('#insp-body-b');
    await tab.fill('Variante B');
    await selectText(tab, 'B');
    await page.locator('#insp-wa-toolbar-b [data-wa="italic"]').click();
    await expect(tab).toHaveValue('Variante _B_');

    // O que vai salvo no grafo (o POST de salvar falha no banco local sem a migration 138)
    const g = await page.evaluate(() => (window as any).buildGraph());
    const node = g.nodes.find((n: any) => n.id === 'n1');
    expect(node.data.body).toBe('*Oi* {{primeiro_nome}}, *proposta pronta* 🟢\n• Prazo _curto_\n• Preço ~antigo~ novo');
    expect(node.data.body_b).toBe('Variante _B_');
    // Cartão do bloco mostra o texto sem os marcadores
    await expect(page.locator('.seq-node[data-id="n1"] .bd')).toContainText('Oi {{primeiro_nome}}, proposta pronta');
    await expect(page.locator('.seq-node[data-id="n1"] .bd')).not.toContainText('*');
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('editor de WhatsApp (template e sequência) cabe na tela', async ({ page }, testInfo) => {
      await login(page);
      await page.goto('/prospection/templates');
      await page.locator(`.tpl-card:has-text("E2E WA Modelo ${fx.u}")`).first().getByRole('button', { name: /Editar/ }).click();
      await waitModal(page, 'tplEditModal');
      await expect(page.locator('#te-wa-toolbar')).toBeVisible();
      await expect(page.locator('#te-wa-preview strong')).toHaveText('proposta pronta');
      const overflow = await page.locator('#tplEditModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      const tbBox = await page.locator('#te-wa-toolbar').boundingBox();
      expect(tbBox!.x + tbBox!.width).toBeLessThanOrEqual(width + 1);
      await page.locator('#te-wa-toolbar [data-wa-emoji-toggle]').click();
      const menu = await page.locator('#te-wa-toolbar .wa-emoji-menu').boundingBox();
      expect(menu!.x).toBeGreaterThanOrEqual(-1);
      expect(menu!.x + menu!.width).toBeLessThanOrEqual(width + 1);
      await page.screenshot({ path: testInfo.outputPath(`wa-${width}-template.png`) });
      await page.locator('#te-wa-toolbar [data-wa-emoji-toggle]').click();
      await expect(page.locator('#te-wa-toolbar .wa-emoji-menu')).toBeHidden();
      await page.locator('#te-wa-preview').scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`wa-${width}-preview.png`) });
      await page.locator('#tplEditModal .btn-close').click();
      await expect(page.locator('#tplEditModal')).toBeHidden();

      await page.goto(`/sequences/edit/${fx.sequenceId}`);
      await page.locator('.seq-node[data-id="n1"] .bd').click();
      await expect(page.locator('#inspector-drawer.open')).toBeVisible();
      await expect(page.locator('#insp-wa-toolbar [data-wa="bold"]')).toBeVisible();
      // Espera o painel terminar de deslizar (transição) antes de medir
      await expect.poll(() => page.locator('#inspector-drawer').evaluate(el => {
        const r = el.getBoundingClientRect();
        return Math.round(r.right) <= window.innerWidth && getComputedStyle(el).transform.replace(/\s/g, '') in { 'none': 1, 'matrix(1,0,0,1,0,0)': 1 };
      })).toBe(true);
      const dBox = await page.locator('#insp-wa-toolbar').boundingBox();
      expect(dBox!.x + dBox!.width).toBeLessThanOrEqual(width + 1);
      const dOverflow = await page.locator('#inspector-drawer').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(dOverflow).toBeLessThanOrEqual(1);
      await page.screenshot({ path: testInfo.outputPath(`wa-${width}-sequencia.png`) });
    });
  });
}
