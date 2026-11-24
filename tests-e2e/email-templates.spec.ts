import { test, expect, Page, FrameLocator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

/**
 * E2E: templates de mensagem formatados e e-mail com o layout do sistema.
 * - Tela própria de Templates (menu Prospecção): criar com negrito/itálico,
 *   editar, duplicar, pré-visualizar "como chega" (layout + assinatura).
 * - Prospecção E-mail: link para a tela de templates; template entra formatado.
 * - Sequência: no bloco "Enviar e-mail", o template entra formatado no editor,
 *   dá para formatar e o que vai salvo é o HTML formatado.
 */

const PHP = process.env.PHP_BIN
  || (existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'C:\\wamp64\\bin\\php\\php8.5.0\\php.exe');
const ROOT = path.resolve(__dirname, '..');
const FIXTURE = 'tests-e2e/email-templates-fixture.php';

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

/** Pré-visualização carregada no iframe. */
async function previewFrame(page: Page): Promise<FrameLocator> {
  await waitModal(page, 'emailPreviewModal');
  const frame = page.frameLocator('#ep-frame');
  await expect(frame.locator('table[data-onsolu-layout]')).toBeAttached({ timeout: 15_000 });
  return frame;
}

test.describe('desktop', () => {
  test.use({ viewport: { width: 1366, height: 860 } });

  test('tela de templates no menu: criar template formatado e ver como chega', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/prospection');
    await expect(page.locator('#pf-manage-templates')).toBeVisible();
    await expect(page.locator('.sidebar a[href$="prospection/templates"], a.nav-link[href$="prospection/templates"]').first()).toBeVisible();
    await page.locator('#pf-manage-templates').click();
    await expect(page).toHaveURL(/prospection\/templates/);
    await expect(page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u}")`)).toBeVisible();

    await page.locator('#btn-new-template').click();
    await waitModal(page, 'tplEditModal');
    const name = `E2E Novo ${fx.u}`;
    await page.locator('#te-name').fill(name);
    await page.locator('#te-subject').fill('Proposta para {{empresa}}');
    const ed = page.locator('#te-editor .ql-editor');
    await ed.click();
    await page.keyboard.type('Olá ');
    await page.locator('#te-chips .re-var[data-k="primeiro_nome"]').click();
    await page.keyboard.type(', ');
    await page.locator('#te-editor .ql-bold').click();
    await page.keyboard.type('texto em negrito');
    await page.locator('#te-editor .ql-bold').click();
    await page.keyboard.type(' e ');
    await page.locator('#te-editor .ql-italic').click();
    await page.keyboard.type('itálico');
    await expect(ed.locator('strong')).toHaveText('texto em negrito');
    await expect(ed.locator('em')).toHaveText('itálico');
    await page.screenshot({ path: testInfo.outputPath('tpl-editor.png') });

    // Ver como chega: layout do sistema, formatação e variáveis com exemplo.
    await page.locator('#te-preview').click();
    const frame = await previewFrame(page);
    await expect(frame.locator('strong', { hasText: 'texto em negrito' })).toBeVisible();
    await expect(frame.locator('em', { hasText: 'itálico' })).toBeVisible();
    await expect(frame.locator('body')).toContainText('Olá Maria, ');
    await expect(frame.locator('[data-onsolu-signature]')).toBeAttached();
    await expect(page.locator('#ep-subject')).toHaveText('Assunto: Proposta para Empresa Exemplo');
    await page.screenshot({ path: testInfo.outputPath('tpl-preview.png') });
    await page.locator('#emailPreviewModal .btn-close').click();
    await expect(page.locator('#emailPreviewModal')).toBeHidden();

    await page.locator('#te-save').click();
    await expect(page.locator('#tplEditModal')).toBeHidden();
    await expect(page.locator(`.tpl-card:has-text("${name}")`)).toBeVisible();

    const saved = JSON.parse(php(['template', name]));
    expect(saved.channel).toBe('email');
    expect(saved.subject).toBe('Proposta para {{empresa}}');
    expect(saved.body).toContain('<strong>texto em negrito</strong>');
    expect(saved.body).toContain('<em>itálico</em>');
    expect(saved.body).toContain('{{primeiro_nome}}');
  });

  test('editar e duplicar mantêm a formatação; WhatsApp vira texto simples', async ({ page }) => {
    await login(page);
    await page.goto('/prospection/templates');
    const card = page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u}")`);
    await card.getByRole('button', { name: /Editar/ }).click();
    await waitModal(page, 'tplEditModal');
    await expect(page.locator('#te-editor .ql-editor strong')).toHaveText('Negrito do modelo');
    await expect(page.locator('#te-editor .ql-editor li')).toHaveText('Primeiro ponto');
    await page.locator('#tplEditModal .btn-close').click();
    await expect(page.locator('#tplEditModal')).toBeHidden();

    await card.locator('button[title="Duplicar"]').click();
    await waitModal(page, 'tplEditModal');
    await expect(page.locator('#te-name')).toHaveValue(`E2E Modelo ${fx.u} (cópia)`);
    await page.locator('#te-channel').selectOption('whatsapp');
    await expect(page.locator('#te-text')).toBeVisible();
    await expect(page.locator('#te-subject-wrap')).toBeHidden();
    await expect(page.locator('#te-text')).toHaveValue(/Negrito do modelo e itálico/);
    await page.locator('#te-save').click();
    await expect(page.locator('#tplEditModal')).toBeHidden();
    const dup = JSON.parse(php(['template', `E2E Modelo ${fx.u} (cópia)`]));
    expect(dup.channel).toBe('whatsapp');
    expect(dup.body).not.toContain('<');

    // Filtro por canal
    await page.locator('.tpl-filters [data-ch="whatsapp"]').click();
    await expect(page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u} (cópia)")`)).toBeVisible();
    await expect(page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u}") >> nth=0`)).toBeVisible();
  });

  test('prospecção: template entra formatado no editor e a pré-visualização tem o layout', async ({ page }) => {
    await login(page);
    await page.goto('/prospection');
    await page.locator('#pf-template').selectOption(String(fx.templateId));
    await expect(page.locator('#pf-subject')).toHaveValue('Oi {{primeiro_nome}}');
    await expect(page.locator('#pf-editor .ql-editor strong')).toHaveText('Negrito do modelo');
    await page.locator('#pf-preview').click();
    const frame = await previewFrame(page);
    await expect(frame.locator('strong', { hasText: 'Negrito do modelo' })).toBeVisible();
    await expect(page.locator('#ep-subject')).toHaveText('Assunto: Oi Maria');
  });

  test('sequência: bloco de e-mail usa o template formatado, formata e salva o HTML', async ({ page }, testInfo) => {
    page.on('dialog', d => d.accept());
    await login(page);
    await page.goto(`/sequences/edit/${fx.sequenceId}`);
    await page.locator('.seq-node[data-id="n1"] .bd').click();
    await expect(page.locator('#inspector-drawer.open')).toBeVisible();
    await expect(page.locator('#inspector-drawer')).toHaveClass(/wide/);
    // Conteúdo antigo (texto puro) aparece no editor formatado.
    await expect(page.locator('#insp-rich .ql-editor')).toContainText('Texto antigo');
    await expect(page.locator('#insp-rich .ql-toolbar .ql-bold')).toBeVisible();

    // Escolhe o template: entra formatado.
    await expect.poll(() => page.locator('#insp-template option').count()).toBeGreaterThan(1);
    await page.locator('#insp-template').selectOption(String(fx.templateId));
    await expect(page.locator('#insp-subject')).toHaveValue('Oi {{primeiro_nome}}');
    await expect(page.locator('#insp-rich .ql-editor strong')).toHaveText('Negrito do modelo');

    // Edita: acrescenta texto em itálico no fim.
    await page.locator('#insp-rich .ql-editor li').click();
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.locator('#insp-rich .ql-italic').click();
    await page.keyboard.type('Assinado pela equipe');
    await expect(page.locator('#insp-rich .ql-editor em', { hasText: 'Assinado pela equipe' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('seq-email-node.png') });

    await page.locator('#insp-preview').click();
    const frame = await previewFrame(page);
    await expect(frame.locator('em', { hasText: 'Assinado pela equipe' })).toBeVisible();
    await page.locator('#emailPreviewModal .btn-close').click();
    await expect(page.locator('#emailPreviewModal')).toBeHidden();

    // O painel lateral fica por cima da barra do topo: fecha antes de salvar (como o usuário faz).
    await page.locator('#inspector-drawer .drawer-hd button').click();
    await expect(page.locator('#inspector-drawer.open')).toHaveCount(0);
    // O que o botão Salvar envia (o grafo do bloco). Obs.: o banco LOCAL está sem a
    // migration 138 (email_sequences.whatsapp_instance_id), então o POST de salvar
    // falha aqui por schema — por isso a conferência é no grafo enviado, não no banco.
    const g = await page.evaluate(() => (window as any).buildGraph());
    const node = g.nodes.find((n: any) => n.id === 'n1');
    expect(node.data.subject).toBe('Oi {{primeiro_nome}}');
    expect(node.data.body).toContain('<strong>Negrito do modelo</strong>');
    expect(node.data.body).toContain('<em>Assinado pela equipe</em>');
    expect(node.data.template_id).toBeUndefined();

    // Reabrindo o bloco, continua formatado.
    await page.locator('.seq-node[data-id="n1"] .bd').click();
    await expect(page.locator('#insp-rich .ql-editor em', { hasText: 'Assinado pela equipe' })).toBeVisible();
    await expect(page.locator('#insp-rich .ql-editor strong')).toHaveText('Negrito do modelo');
  });
});

for (const width of [390, 320]) {
  test.describe(`celular ${width}px`, () => {
    test.use({ viewport: { width, height: 760 }, isMobile: true, hasTouch: true });

    test('tela de templates, editor e pré-visualização cabem na tela', async ({ page }, testInfo) => {
      await login(page);
      await page.goto('/prospection/templates');
      await expect(page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u}")`).first()).toBeVisible();
      const doc = await page.evaluate(() => document.documentElement.scrollWidth);
      expect(doc).toBeLessThanOrEqual(width);
      await page.screenshot({ path: testInfo.outputPath(`tpl-${width}-lista.png`) });

      await page.locator(`.tpl-card:has-text("E2E Modelo ${fx.u}")`).first().getByRole('button', { name: /Editar/ }).click();
      await waitModal(page, 'tplEditModal');
      const box = await page.locator('#tplEditModal .modal-content').boundingBox();
      expect(Math.round(box!.width)).toBe(width);
      const overflow = await page.locator('#tplEditModal .modal-body').evaluate(el => el.scrollWidth - el.clientWidth);
      expect(overflow).toBeLessThanOrEqual(1);
      await expect(page.locator('#te-save')).toBeInViewport();
      await page.screenshot({ path: testInfo.outputPath(`tpl-${width}-editor.png`) });

      await page.locator('#te-preview').click();
      const frame = await previewFrame(page);
      await expect(frame.locator('strong', { hasText: 'Negrito do modelo' })).toBeVisible();
      await page.screenshot({ path: testInfo.outputPath(`tpl-${width}-preview.png`) });
    });
  });
}
