<?php $pageTitle = 'Apresentação - ON Solutions Helpdesk'; $currentPage = 'presentations'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php $isPublished = $presentation['status'] === 'published'; ?>
<style>
    /* Cartão de seção */
    .sec { border:1px solid #e6e8f0; border-radius:10px; background:#fff; margin-bottom:8px; transition:box-shadow .15s; }
    .sec:hover { box-shadow:0 2px 10px rgba(30,40,90,.07); }
    .sec.open { border-color:#00BFA6; box-shadow:0 4px 16px rgba(0,191,166,.15); }
    .sec.is-hidden { background:#fafafa; }
    .sec.is-hidden .sec-main { opacity:.5; }
    .sec-head { display:flex; align-items:center; gap:12px; padding:10px 12px; cursor:pointer; }
    .sec-icon { width:36px; height:36px; flex:0 0 36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; background:#e9f8f5; color:#0b8c7a; }
    .sec-main { flex:1; min-width:0; }
    .sec-title { font-size:.9rem; font-weight:600; color:#2b2f45; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sec-meta { font-size:.75rem; color:#8a90a8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sec-actions { display:flex; gap:4px; }
    .sec-actions .btn { padding:.15rem .45rem; }
    .sec-chevron { color:#b0b5c8; }
    .sec-body { padding:4px 16px 16px; border-top:1px solid #eef0f6; }
    .sec-help { display:flex; gap:8px; background:#f4f7fd; border-radius:8px; padding:8px 12px; font-size:.8rem; color:#3c4e70; margin:12px 0; }
    .field-hint { font-size:.74rem; color:#8a90a8; font-weight:400; }
    .items-box { background:#f8f9fc; border-radius:8px; padding:10px; }
    .item-row { display:flex; gap:6px; align-items:flex-start; margin-bottom:6px; }
    .item-row:last-of-type { margin-bottom:0; }
    .item-n { width:22px; flex:0 0 22px; height:22px; margin-top:4px; border-radius:50%; background:#fff; border:1px solid #dfe3ee; font-size:.7rem; color:#6b7088; display:flex; align-items:center; justify-content:center; }
</style>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Apresentação #<?= (int)$presentation['id'] ?></h5>
            <small class="text-muted"><?= escape($presentation['title']) ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= baseUrl('presentation') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button class="btn btn-sm btn-outline-primary" onclick="previewPresentation()"><i class="bi bi-eye"></i> Pré-visualizar</button>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="savePresentation()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-send" class="btn btn-sm btn-primary" onclick="sendPresentation()"><i class="bi bi-send"></i> Enviar ao cliente</button>
            <?php if (!empty($presentation['proposal_id'])): ?>
            <a href="<?= baseUrl('proposal/edit/' . (int)$presentation['proposal_id']) ?>" class="btn btn-sm btn-dark"><i class="bi bi-file-earmark-text"></i> Ver proposta</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Dados do cliente</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label small mb-1">Nome interno da apresentação <span class="field-hint">— só aparece aqui no Helpdesk</span></label>
                            <input id="a-title" class="form-control form-control-sm" value="<?= escape($presentation['title']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Cliente / empresa</label>
                            <input id="a-client-name" class="form-control form-control-sm" value="<?= escape($presentation['client_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4"><label class="form-label small mb-1">E-mail</label><input id="a-client-email" class="form-control form-control-sm" value="<?= escape($presentation['client_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small mb-1">Telefone (WhatsApp)</label><input id="a-client-phone" class="form-control form-control-sm" value="<?= escape($presentation['client_phone'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong>Conteúdo da apresentação</strong>
                        <div class="field-hint">Clique numa seção para abrir e editar. Clique de novo para fechar.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <select id="new-section-type" class="form-select form-select-sm" style="width:auto;">
                            <?php foreach ($sectionTypes as $type => $label): ?>
                            <option value="<?= $type ?>"><?= escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addSection()" title="Entra logo abaixo da seção aberta (ou no fim)"><i class="bi bi-plus-lg"></i> Nova seção</button>
                    </div>
                </div>
                <div class="card-body" id="sections-box"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Link para o cliente</strong></div>
                <div class="card-body">
                    <p class="small mb-2">
                        Status:
                        <span id="link-status" class="badge bg-<?= $isPublished ? 'success' : 'secondary' ?>"><?= $isPublished ? 'Link ativo' : 'Link desativado' ?></span>
                    </p>
                    <div class="input-group input-group-sm mb-2">
                        <input id="public-link" class="form-control" value="<?= escape($publicLink) ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyLink()" title="Copiar link"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="d-flex gap-2">
                        <button id="btn-publish" class="btn btn-sm btn-outline-success <?= $isPublished ? 'd-none' : '' ?>" onclick="publishLink()"><i class="bi bi-link-45deg"></i> Gerar link</button>
                        <button id="btn-unpublish" class="btn btn-sm btn-outline-danger <?= $isPublished ? '' : 'd-none' ?>" onclick="unpublishLink()"><i class="bi bi-x-circle"></i> Desativar link</button>
                    </div>
                    <small class="text-muted d-block mt-2">Enquanto o link estiver desativado, o cliente vê “apresentação indisponível”. As edições salvas aparecem no link imediatamente.</small>
                    <hr>
                    <div class="small text-muted">
                        Visualizações do cliente: <strong><?= (int)$presentation['view_count'] ?></strong>
                        <?php if (!empty($presentation['last_viewed_at'])): ?><br>Última: <?= date('d/m/Y H:i', strtotime($presentation['last_viewed_at'])) ?><?php endif; ?>
                        <?php if (!empty($presentation['sent_at'])): ?><br>Enviada em: <?= date('d/m/Y H:i', strtotime($presentation['sent_at'])) ?><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header py-2"><strong>Modelo</strong></div>
                <div class="card-body">
                    <p class="small text-muted">Novas apresentações nascem a partir do modelo padrão.</p>
                    <button class="btn btn-sm btn-outline-secondary w-100" onclick="saveAsTemplate()"><i class="bi bi-bookmark-check"></i> Salvar estas seções como modelo padrão</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2"><strong><i class="bi bi-question-circle"></i> Como editar</strong></div>
                <div class="card-body small">
                    <ul class="ps-3 mb-0">
                        <li class="mb-1">Clique numa seção para abrir e editar os textos.</li>
                        <li class="mb-1"><i class="bi bi-arrow-up"></i> <i class="bi bi-arrow-down"></i> mudam a ordem.</li>
                        <li class="mb-1"><i class="bi bi-eye"></i> esconde a seção do cliente sem apagar.</li>
                        <li class="mb-1">Use <strong>Pré-visualizar</strong> para ver como o cliente vai ver.</li>
                        <li>Nada muda para o cliente antes de clicar em <strong>Salvar</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PRES_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const PRESENTATION_ID = <?= (int)$presentation['id'] ?>;
const SECTION_TYPES = <?= json_encode($sectionTypes, JSON_UNESCAPED_UNICODE) ?>;
let SECTIONS = <?= json_encode($sections, JSON_UNESCAPED_UNICODE) ?>;
let OPEN = null;

const TYPE_ICONS = {
    hero: 'bi-image', text: 'bi-text-paragraph', stats: 'bi-123',
    steps: 'bi-list-ol', cards: 'bi-grid', table: 'bi-table', compare: 'bi-layout-split',
    list: 'bi-check2-square', dont: 'bi-x-octagon', cta: 'bi-telephone',
};
const TYPE_HELP = {
    hero:    'Capa: primeira tela da apresentação, com o fundo em degradê.',
    text:    'Texto corrido. Deixe uma linha em branco para separar parágrafos.',
    stats:   'Números em destaque. Em cada item: o número (ex.: 100+) e a legenda.',
    steps:   'Etapas numeradas automaticamente (01, 02, 03…), na ordem dos itens.',
    cards:   'Blocos lado a lado. Em clientes, o 3º campo é o caminho do logo (ex.: assets/proposal/clients/inpasa.jpg).',
    table:   'Tabela. O 1º item é o cabeçalho. Para 3 ou mais colunas, separe as colunas do 2º campo com |',
    compare: 'Comparativo em duas colunas: mercado × nós.',
    list:    'Lista com ✓ verde. Cada item é uma linha.',
    dont:    'Lista do que evitar, com ✗ vermelho. Cada item é uma linha.',
    cta:     'Fechamento com contatos. Em cada item: rótulo (ex.: E-mail) e valor.',
};
const ITEM_LABELS = {
    stats:   ['Número (ex.: 100+)', 'Legenda'],
    steps:   ['Nome da etapa', 'Descrição (opcional)'],
    cards:   ['Título do card', 'Texto do card', 'Logo (assets/...)'],
    table:   ['1ª coluna', 'Demais colunas (separe com |)'],
    compare: ['Mercado (low-code / freelancer)', 'Nós entregamos'],
    list:    ['Item', 'Detalhe (opcional)'],
    dont:    ['Item', 'Detalhe (opcional)'],
    cta:     ['Rótulo', 'Valor'],
};
const TYPE_FIELDS = { hero: ['subtitle', 'body'] };
const usesField = (type, f) => (TYPE_FIELDS[type] || ['subtitle', 'body', 'items', 'note']).includes(f);

function esc(v) {
    return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function sectionLabel(s) {
    if (s.title) return s.title;
    const first = (s.items || [])[0];
    return first && first.title ? first.title + '…' : '(sem título)';
}
function sectionMeta(s) {
    const parts = [SECTION_TYPES[s.type] || s.type];
    const n = (s.items || []).length;
    if (n) parts.push(n + (n === 1 ? ' item' : ' itens'));
    else if (s.body) parts.push(s.body.replace(/\s+/g, ' ').slice(0, 70) + (s.body.length > 70 ? '…' : ''));
    if (!s.visible) parts.push('oculta para o cliente');
    return parts.join(' · ');
}

function actionsHtml(i, s) {
    return `
        <span class="sec-actions" onclick="event.stopPropagation()">
            <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},-1)" ${i === 0 ? 'disabled' : ''} title="Subir"><i class="bi bi-arrow-up"></i></button>
            <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},1)" ${i === SECTIONS.length - 1 ? 'disabled' : ''} title="Descer"><i class="bi bi-arrow-down"></i></button>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleVisible(${i})" title="${s.visible ? 'Ocultar do cliente' : 'Mostrar ao cliente'}"><i class="bi ${s.visible ? 'bi-eye' : 'bi-eye-slash'}"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="removeSection(${i})" title="Excluir"><i class="bi bi-trash"></i></button>
        </span>`;
}

function fieldsHtml(i, s) {
    const labels = ITEM_LABELS[s.type];
    let h = `<div class="sec-help"><i class="bi bi-lightbulb"></i><span>${esc(TYPE_HELP[s.type] || '')}</span></div>`;
    h += `<div class="mb-2"><label class="form-label small fw-semibold mb-1">Título</label>
          <input class="form-control form-control-sm" value="${esc(s.title)}" oninput="setField(${i},'title',this.value)"></div>`;
    if (usesField(s.type, 'subtitle')) h += `<div class="mb-2"><label class="form-label small fw-semibold mb-1">Subtítulo <span class="field-hint">— opcional</span></label>
          <input class="form-control form-control-sm" value="${esc(s.subtitle)}" oninput="setField(${i},'subtitle',this.value)"></div>`;
    if (usesField(s.type, 'body')) h += `<div class="mb-2"><label class="form-label small fw-semibold mb-1">Texto <span class="field-hint">— opcional</span></label>
          <textarea class="form-control form-control-sm" rows="${Math.min(8, Math.max(2, Math.ceil((s.body || '').length / 90)))}" oninput="setField(${i},'body',this.value)">${esc(s.body)}</textarea></div>`;
    if (labels && usesField(s.type, 'items')) {
        h += `<label class="form-label small fw-semibold mb-1">Itens</label><div class="items-box">`;
        h += (s.items || []).map((it, j) => `
            <div class="item-row">
                <span class="item-n">${j + 1}</span>
                <input class="form-control form-control-sm" style="max-width:36%;" placeholder="${esc(labels[0])}" value="${esc(it.title)}" oninput="setItem(${i},${j},'title',this.value)">
                <textarea class="form-control form-control-sm" rows="${Math.min(5, Math.max(1, Math.ceil((it.text || '').length / 60)))}" placeholder="${esc(labels[1])}" oninput="setItem(${i},${j},'text',this.value)">${esc(it.text)}</textarea>
                ${s.type === 'cards' ? `<input class="form-control form-control-sm" style="max-width:28%;" placeholder="${esc(labels[2] || 'Logo (opcional)')}" value="${esc(it.image)}" oninput="setItem(${i},${j},'image',this.value)">` : ''}
                <div class="btn-group-vertical btn-group-sm">
                    <button class="btn btn-outline-secondary py-0" onclick="moveItem(${i},${j},-1)" ${j === 0 ? 'disabled' : ''} title="Subir"><i class="bi bi-chevron-up"></i></button>
                    <button class="btn btn-outline-secondary py-0" onclick="moveItem(${i},${j},1)" ${j === s.items.length - 1 ? 'disabled' : ''} title="Descer"><i class="bi bi-chevron-down"></i></button>
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${i},${j})" title="Excluir item"><i class="bi bi-x-lg"></i></button>
            </div>`).join('');
        h += `<button class="btn btn-sm btn-link px-0 mt-1" onclick="addItem(${i})"><i class="bi bi-plus-circle"></i> Adicionar item</button></div>`;
    }
    if (usesField(s.type, 'note')) h += `<div class="mt-2"><label class="form-label small fw-semibold mb-1">Destaque <span class="field-hint">— caixa verde no fim da seção (opcional)</span></label>
          <textarea class="form-control form-control-sm" rows="2" oninput="setField(${i},'note',this.value)">${esc(s.note)}</textarea></div>`;
    return h;
}

function renderSections() {
    const box = document.getElementById('sections-box');
    if (!SECTIONS.length) { box.innerHTML = '<p class="text-muted small mb-0">Nenhuma seção. Adicione uma acima.</p>'; return; }
    box.innerHTML = SECTIONS.map((s, i) => {
        const open = OPEN === i;
        return `
            <div class="sec ${open ? 'open' : ''} ${s.visible ? '' : 'is-hidden'}" id="row-${i}">
                <div class="sec-head" onclick="toggleSection(${i})">
                    <span class="sec-icon"><i class="bi ${TYPE_ICONS[s.type] || 'bi-square'}"></i></span>
                    <span class="sec-main">
                        <div class="sec-title">${esc(sectionLabel(s))}</div>
                        <div class="sec-meta">${esc(sectionMeta(s))}</div>
                    </span>
                    ${actionsHtml(i, s)}
                    <i class="bi ${open ? 'bi-chevron-up' : 'bi-chevron-down'} sec-chevron"></i>
                </div>
                ${open ? `<div class="sec-body">${fieldsHtml(i, s)}</div>` : ''}
            </div>`;
    }).join('');
}

function setField(i, key, value) { SECTIONS[i][key] = value; }
function setItem(i, j, key, value) { SECTIONS[i].items[j][key] = value; }
function addItem(i) { (SECTIONS[i].items = SECTIONS[i].items || []).push({ title: '', text: '', image: '' }); renderSections(); }
function removeItem(i, j) { SECTIONS[i].items.splice(j, 1); renderSections(); }
function moveItem(i, j, dir) {
    const items = SECTIONS[i].items, k = j + dir;
    if (k < 0 || k >= items.length) return;
    [items[j], items[k]] = [items[k], items[j]];
    renderSections();
}
function toggleSection(i) {
    OPEN = OPEN === i ? null : i;
    renderSections();
    if (OPEN !== null) document.getElementById('row-' + i).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}
function toggleVisible(i) { SECTIONS[i].visible = !SECTIONS[i].visible; renderSections(); }
function moveSection(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= SECTIONS.length) return;
    [SECTIONS[i], SECTIONS[j]] = [SECTIONS[j], SECTIONS[i]];
    if (OPEN === i) OPEN = j; else if (OPEN === j) OPEN = i;
    renderSections();
}
function removeSection(i) {
    if (!confirm(`Excluir a seção “${sectionLabel(SECTIONS[i])}”?\n\nSe quiser só esconder do cliente, use o botão do olho.`)) return;
    SECTIONS.splice(i, 1);
    OPEN = null;
    renderSections();
}
function addSection() {
    const type = document.getElementById('new-section-type').value;
    const at = OPEN !== null ? OPEN + 1 : SECTIONS.length;
    SECTIONS.splice(at, 0, { type, title: SECTION_TYPES[type] || '', subtitle: '', body: '', note: '', visible: true, items: [] });
    OPEN = at;
    renderSections();
    document.getElementById('row-' + at).scrollIntoView({ block: 'center', behavior: 'smooth' });
}

function payload(extra) {
    return Object.assign({
        title: document.getElementById('a-title').value.trim(),
        client_name: document.getElementById('a-client-name').value.trim(),
        client_email: document.getElementById('a-client-email').value.trim(),
        client_phone: document.getElementById('a-client-phone').value.trim(),
        sections: SECTIONS,
    }, extra || {});
}

let SAVING = false;
async function savePresentation(extra) {
    if (SAVING) return false;
    SAVING = true;
    const btn = document.getElementById('btn-save');
    const original = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
    try {
        const r = await fetch(`${PRES_BASE}presentation/save/${PRESENTATION_ID}`, {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify(payload(extra))
        }).then(x => x.json());
        if (r.error) { alert(r.error); btn.innerHTML = original; return false; }
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvo!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
        return true;
    } catch (e) {
        alert('Falha de rede ao salvar.');
        btn.innerHTML = original;
        return false;
    } finally {
        SAVING = false;
        btn.disabled = false;
    }
}

async function saveAsTemplate() {
    if (!confirm('Salvar estas seções como modelo padrão para as próximas apresentações?')) return;
    if (await savePresentation({ save_as_template: true })) alert('Modelo padrão atualizado.');
}

async function previewPresentation() {
    if (!await savePresentation()) return;
    window.open(`${PRES_BASE}presentation/preview/${PRESENTATION_ID}`, '_blank');
}

async function postAction(action) {
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    return fetch(`${PRES_BASE}presentation/${action}/${PRESENTATION_ID}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(x => x.json()).catch(() => ({ error: 'Falha de rede' }));
}

function setLinkState(active) {
    const st = document.getElementById('link-status');
    st.className = 'badge bg-' + (active ? 'success' : 'secondary');
    st.textContent = active ? 'Link ativo' : 'Link desativado';
    document.getElementById('btn-publish').classList.toggle('d-none', active);
    document.getElementById('btn-unpublish').classList.toggle('d-none', !active);
}

async function publishLink() {
    if (!await savePresentation()) return;
    const r = await postAction('publish');
    if (r.error) { alert(r.error); return; }
    document.getElementById('public-link').value = r.link;
    setLinkState(true);
    copyLink();
}

async function unpublishLink() {
    if (!confirm('Desativar o link público? O cliente deixará de conseguir abrir a apresentação.')) return;
    const r = await postAction('unpublish');
    if (r.error) { alert(r.error); return; }
    setLinkState(false);
}

function copyLink() {
    const input = document.getElementById('public-link');
    navigator.clipboard.writeText(input.value).then(
        () => alert('Link copiado:\n' + input.value),
        () => { input.select(); document.execCommand('copy'); }
    );
}

async function sendPresentation() {
    if (!await savePresentation()) return;
    if (!confirm('Ativar o link e enviar a apresentação ao cliente por WhatsApp/e-mail?')) return;
    const btn = document.getElementById('btn-send');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    const r = await postAction('send');
    btn.disabled = false; btn.innerHTML = orig;
    if (r.error) { alert(r.error); return; }
    document.getElementById('public-link').value = r.link;
    setLinkState(true);

    const canais = [];
    if (r.sent_whats) canais.push('WhatsApp');
    if (r.sent_email) canais.push('e-mail');
    if (canais.length) alert('Apresentação enviada ao cliente por: ' + canais.join(' e ') + '.');
    else if (r.no_contact) alert('O cliente não tem telefone/e-mail cadastrado. Copie o link e envie manualmente:\n' + r.link);
    else alert('Não foi possível enviar automaticamente (verifique WhatsApp/SMTP). Envie o link manualmente:\n' + r.link);
}

renderSections();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
