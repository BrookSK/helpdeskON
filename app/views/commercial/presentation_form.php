<?php $pageTitle = 'Apresentação - ON Solutions Helpdesk'; $currentPage = 'presentations'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php $isPublished = $presentation['status'] === 'published'; ?>
<style>
    .sec-row { border:1px solid #e3e6f0; border-radius:8px; margin-bottom:8px; background:#fff; }
    .sec-row.open { border-color:#00BFA6; box-shadow:0 0 0 2px rgba(0,191,166,.15); }
    .sec-row.is-hidden .sec-head { opacity:.55; }
    .sec-head { display:flex; align-items:center; gap:8px; padding:8px 10px; cursor:pointer; user-select:none; }
    .sec-head:hover { background:#f8f9fc; border-radius:8px; }
    .sec-num { min-width:28px; height:28px; border-radius:6px; background:#eef0f6; color:#34407a; font-weight:700; font-size:.8rem; display:flex; align-items:center; justify-content:center; }
    .sec-title { flex:1; min-width:0; font-size:.88rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sec-type { font-size:.7rem; }
    .sec-actions .btn { padding:.1rem .4rem; }
    .sec-body { padding:4px 14px 14px; border-top:1px solid #eef0f6; }
    .sec-help { background:#f1f8ff; border-radius:6px; padding:8px 10px; font-size:.8rem; color:#2f4b6e; margin:10px 0; }
    .field-hint { font-size:.74rem; color:#8a90a8; }
    .item-row { display:flex; gap:6px; align-items:flex-start; margin-bottom:6px; }
    .item-row .item-n { font-size:.75rem; color:#8a90a8; width:18px; padding-top:6px; text-align:right; }
    #preview-wrap { position:sticky; top:12px; }
    #preview-frame { width:100%; height:calc(100vh - 150px); border:1px solid #e3e6f0; border-radius:10px; background:#eef0f6; }
</style>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Apresentação #<?= (int)$presentation['id'] ?></h5>
            <small class="text-muted"><?= escape($presentation['title']) ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= baseUrl('presentation') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button class="btn btn-sm btn-outline-primary" onclick="previewPresentation()"><i class="bi bi-box-arrow-up-right"></i> Abrir em nova aba</button>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="savePresentation()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-send" class="btn btn-sm btn-primary" onclick="sendPresentation()"><i class="bi bi-send"></i> Enviar ao cliente</button>
            <?php if (!empty($presentation['proposal_id'])): ?>
            <a href="<?= baseUrl('proposal/edit/' . (int)$presentation['proposal_id']) ?>" class="btn btn-sm btn-dark"><i class="bi bi-file-earmark-text"></i> Ver proposta</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- ===== Coluna do editor ===== -->
        <div class="col-xl-6">
            <div class="alert alert-light border small py-2 mb-3">
                <strong><i class="bi bi-info-circle"></i> Como editar:</strong>
                a apresentação já vem pronta com o conteúdo padrão da ON Solutions. Clique numa seção para abrir e editar os textos —
                a <strong>pré-visualização ao lado</strong> mostra na hora como o cliente vai ver. Use
                <i class="bi bi-arrow-up"></i><i class="bi bi-arrow-down"></i> para mudar a ordem e <i class="bi bi-eye"></i> para ocultar uma seção do cliente.
                Nada vai para o cliente antes de clicar em <strong>Salvar</strong>.
            </div>

            <div class="card mb-3">
                <div class="card-header py-2"><strong>1. Dados do cliente</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-12">
                            <label class="form-label small mb-1">Nome interno da apresentação</label>
                            <input id="a-title" class="form-control form-control-sm" value="<?= escape($presentation['title']) ?>">
                            <div class="field-hint">Só aparece aqui no Helpdesk, para você encontrar depois. O cliente não vê.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Cliente / empresa</label>
                            <input id="a-client-name" class="form-control form-control-sm" value="<?= escape($presentation['client_name'] ?? '') ?>" oninput="schedulePreview()">
                            <div class="field-hint">Onde houver <code>{{cliente}}</code> nos textos, entra este nome.</div>
                        </div>
                        <div class="col-md-4"><label class="form-label small mb-1">E-mail</label><input id="a-client-email" class="form-control form-control-sm" value="<?= escape($presentation['client_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small mb-1">Telefone (WhatsApp)</label><input id="a-client-phone" class="form-control form-control-sm" value="<?= escape($presentation['client_phone'] ?? '') ?>"></div>
                    </div>
                    <div class="field-hint mt-1">E-mail e telefone são usados no botão <strong>Enviar ao cliente</strong>.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>2. Conteúdo da apresentação <span class="text-muted fw-normal small" id="sec-count"></span></strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)" title="Fechar todas"><i class="bi bi-arrows-collapse"></i></button>
                        <select id="new-section-type" class="form-select form-select-sm" style="width:auto;">
                            <?php foreach ($sectionTypes as $type => $label): ?>
                            <option value="<?= $type ?>"><?= escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addSection()" title="Adiciona logo abaixo da seção aberta (ou no fim)"><i class="bi bi-plus"></i> Nova seção</button>
                    </div>
                </div>
                <div class="card-body p-2" id="sections-box"></div>
            </div>

            <div class="row g-3">
                <div class="col-md-7">
                    <div class="card h-100">
                        <div class="card-header py-2"><strong>3. Link para o cliente</strong></div>
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
                            <div class="field-hint mt-2">Com o link desativado, o cliente vê “apresentação indisponível”. Depois de salvar, as mudanças aparecem no link na hora.</div>
                            <div class="small text-muted mt-2">
                                Visualizações do cliente: <strong><?= (int)$presentation['view_count'] ?></strong>
                                <?php if (!empty($presentation['last_viewed_at'])): ?> · última em <?= date('d/m/Y H:i', strtotime($presentation['last_viewed_at'])) ?><?php endif; ?>
                                <?php if (!empty($presentation['sent_at'])): ?><br>Enviada em <?= date('d/m/Y H:i', strtotime($presentation['sent_at'])) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card h-100">
                        <div class="card-header py-2"><strong>Modelo padrão</strong></div>
                        <div class="card-body">
                            <p class="field-hint mb-2">Toda apresentação nova nasce com o conteúdo do modelo padrão. Se você ajustou algo que deve valer para as próximas, salve como modelo.</p>
                            <button class="btn btn-sm btn-outline-secondary w-100" onclick="saveAsTemplate()"><i class="bi bi-bookmark-check"></i> Salvar como modelo padrão</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Coluna da pré-visualização ===== -->
        <div class="col-xl-6">
            <div id="preview-wrap">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="small"><i class="bi bi-display"></i> Pré-visualização — como o cliente vai ver</strong>
                    <small class="text-muted" id="preview-status"></small>
                </div>
                <iframe id="preview-frame" title="Pré-visualização"></iframe>
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
let OPEN = null; // índice da seção aberta no editor

// Explicação de cada tipo de seção, mostrada ao abrir a seção.
const TYPE_HELP = {
    hero:    'Capa: a primeira tela, com o fundo em degradê. Usa título (grande), subtítulo e texto.',
    divider: 'Divisória: faixa escura que separa as partes do documento (ex.: “PARTE I — BASE COMERCIAL”). Usa só título e subtítulo.',
    text:    'Texto corrido. Para separar parágrafos, deixe uma linha em branco entre eles.',
    stats:   'Números em destaque. Em cada item: o número (ex.: 100+) e a legenda embaixo dele.',
    steps:   'Etapas numeradas automaticamente (01, 02, 03…). Cada item é uma etapa, na ordem da lista.',
    cards:   'Blocos lado a lado. Cada item vira um card com título em negrito e descrição.',
    table:   'Tabela. O 1º item é o CABEÇALHO (linha azul). Para 3 ou mais colunas, separe as colunas do 2º campo com o caractere | (barra vertical).',
    compare: 'Comparativo em duas colunas: o que o mercado oferece × o que nós entregamos.',
    list:    'Lista com ✓ verde. Cada item é uma linha da lista.',
    dont:    'Lista do que evitar, com ✗ vermelho. Cada item é uma linha da lista.',
    cta:     'Fechamento com contatos. Em cada item: o rótulo (ex.: E-mail) e o valor.',
};
// Rótulos dos dois campos de cada item, conforme o tipo da seção.
const ITEM_LABELS = {
    stats:   ['Número (ex.: 100+)', 'Legenda'],
    steps:   ['Nome da etapa', 'Descrição (opcional)'],
    cards:   ['Título do card', 'Texto do card'],
    table:   ['1ª coluna', 'Demais colunas (separe com |)'],
    compare: ['Mercado (low-code / freelancer)', 'Nós entregamos'],
    list:    ['Item', 'Detalhe abaixo do item (opcional)'],
    dont:    ['Item', 'Detalhe abaixo do item (opcional)'],
    cta:     ['Rótulo (ex.: E-mail)', 'Valor'],
};
// Quais campos cada tipo usa (o resto fica escondido para não confundir).
const TYPE_FIELDS = {
    hero:    ['subtitle', 'body'],
    divider: ['subtitle'],
};
const usesField = (type, f) => (TYPE_FIELDS[type] || ['subtitle', 'body', 'items', 'note']).includes(f);

function esc(v) {
    return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function sectionLabel(s) {
    if (s.title) return s.title;
    const first = (s.items || [])[0];
    return first && first.title ? first.title + '…' : '(sem título)';
}

function renderSections() {
    const box = document.getElementById('sections-box');
    document.getElementById('sec-count').textContent = `· ${SECTIONS.length} seções`;
    if (!SECTIONS.length) { box.innerHTML = '<p class="text-muted small m-2">Nenhuma seção. Adicione uma acima.</p>'; return; }
    box.innerHTML = SECTIONS.map((s, i) => {
        const open = OPEN === i;
        const head = `
            <div class="sec-head" onclick="toggleSection(${i})">
                <span class="sec-num">${i + 1}</span>
                <span class="badge bg-light text-secondary border sec-type">${esc(SECTION_TYPES[s.type] || s.type)}</span>
                <span class="sec-title">${esc(sectionLabel(s))}</span>
                ${s.visible ? '' : '<span class="badge bg-secondary sec-type">Oculta</span>'}
                <span class="sec-actions d-flex gap-1" onclick="event.stopPropagation()">
                    <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},-1)" ${i === 0 ? 'disabled' : ''} title="Subir"><i class="bi bi-arrow-up"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},1)" ${i === SECTIONS.length - 1 ? 'disabled' : ''} title="Descer"><i class="bi bi-arrow-down"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleVisible(${i})" title="${s.visible ? 'Ocultar do cliente' : 'Mostrar ao cliente'}"><i class="bi ${s.visible ? 'bi-eye' : 'bi-eye-slash'}"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeSection(${i})" title="Excluir seção"><i class="bi bi-trash"></i></button>
                </span>
                <i class="bi ${open ? 'bi-chevron-up' : 'bi-chevron-down'} text-muted"></i>
            </div>`;
        if (!open) return `<div class="sec-row ${s.visible ? '' : 'is-hidden'}" id="row-${i}">${head}</div>`;

        const labels = ITEM_LABELS[s.type];
        let fields = `
            <div class="sec-help"><i class="bi bi-lightbulb"></i> ${esc(TYPE_HELP[s.type] || '')}</div>
            <div class="mb-2">
                <label class="form-label small mb-1">Título da seção</label>
                <input class="form-control form-control-sm" value="${esc(s.title)}" oninput="setField(${i},'title',this.value)">
            </div>`;
        if (usesField(s.type, 'subtitle')) fields += `
            <div class="mb-2">
                <label class="form-label small mb-1">Subtítulo <span class="field-hint">(texto menor, logo abaixo do título — opcional)</span></label>
                <input class="form-control form-control-sm" value="${esc(s.subtitle)}" oninput="setField(${i},'subtitle',this.value)">
            </div>`;
        if (usesField(s.type, 'body')) fields += `
            <div class="mb-2">
                <label class="form-label small mb-1">Texto <span class="field-hint">(aparece antes dos itens — opcional)</span></label>
                <textarea class="form-control form-control-sm" rows="${Math.min(8, Math.max(2, Math.ceil((s.body || '').length / 90)))}" oninput="setField(${i},'body',this.value)">${esc(s.body)}</textarea>
            </div>`;
        if (labels && usesField(s.type, 'items')) fields += `
            <label class="form-label small mb-1">Itens <span class="field-hint">(${(s.items || []).length})</span></label>
            ${(s.items || []).map((it, j) => `
                <div class="item-row">
                    <span class="item-n">${j + 1}</span>
                    <input class="form-control form-control-sm" style="max-width:38%;" placeholder="${esc(labels[0])}" value="${esc(it.title)}" oninput="setItem(${i},${j},'title',this.value)">
                    <textarea class="form-control form-control-sm" rows="${Math.min(5, Math.max(1, Math.ceil((it.text || '').length / 60)))}" placeholder="${esc(labels[1])}" oninput="setItem(${i},${j},'text',this.value)">${esc(it.text)}</textarea>
                    <div class="d-flex flex-column gap-1">
                        <button class="btn btn-sm btn-outline-secondary py-0" onclick="moveItem(${i},${j},-1)" ${j === 0 ? 'disabled' : ''} title="Subir item"><i class="bi bi-chevron-up"></i></button>
                        <button class="btn btn-sm btn-outline-secondary py-0" onclick="moveItem(${i},${j},1)" ${j === s.items.length - 1 ? 'disabled' : ''} title="Descer item"><i class="bi bi-chevron-down"></i></button>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${i},${j})" title="Excluir item"><i class="bi bi-x"></i></button>
                </div>`).join('')}
            <button class="btn btn-sm btn-link px-0" onclick="addItem(${i})"><i class="bi bi-plus"></i> Adicionar item</button>`;
        if (usesField(s.type, 'note')) fields += `
            <div class="mt-2">
                <label class="form-label small mb-1">Destaque <span class="field-hint">(caixa verde no fim da seção, para avisos e dicas — opcional)</span></label>
                <textarea class="form-control form-control-sm" rows="2" oninput="setField(${i},'note',this.value)">${esc(s.note)}</textarea>
            </div>`;
        return `<div class="sec-row open ${s.visible ? '' : 'is-hidden'}" id="row-${i}">${head}<div class="sec-body">${fields}</div></div>`;
    }).join('');
}

function changed(rerender) {
    if (rerender) renderSections();
    schedulePreview();
}
function setField(i, key, value) {
    SECTIONS[i][key] = value;
    if (key === 'title') document.querySelector(`#row-${i} .sec-title`).textContent = sectionLabel(SECTIONS[i]);
    changed(false);
}
function setItem(i, j, key, value) { SECTIONS[i].items[j][key] = value; changed(false); }
function addItem(i) { (SECTIONS[i].items = SECTIONS[i].items || []).push({ title: '', text: '' }); changed(true); }
function removeItem(i, j) { SECTIONS[i].items.splice(j, 1); changed(true); }
function moveItem(i, j, dir) {
    const items = SECTIONS[i].items, k = j + dir;
    if (k < 0 || k >= items.length) return;
    [items[j], items[k]] = [items[k], items[j]];
    changed(true);
}
function toggleSection(i) {
    OPEN = OPEN === i ? null : i;
    renderSections();
    if (OPEN !== null) {
        document.getElementById('row-' + i).scrollIntoView({ block: 'nearest' });
        scrollPreviewTo(OPEN);
    }
}
function toggleAll() { OPEN = null; renderSections(); }
function toggleVisible(i) { SECTIONS[i].visible = !SECTIONS[i].visible; changed(true); }
function moveSection(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= SECTIONS.length) return;
    [SECTIONS[i], SECTIONS[j]] = [SECTIONS[j], SECTIONS[i]];
    if (OPEN === i) OPEN = j; else if (OPEN === j) OPEN = i;
    changed(true);
}
function removeSection(i) {
    if (!confirm(`Excluir a seção ${i + 1} “${sectionLabel(SECTIONS[i])}”?\n\nSe quiser só esconder do cliente, use o botão do olho.`)) return;
    SECTIONS.splice(i, 1);
    OPEN = null;
    changed(true);
}
function addSection() {
    const type = document.getElementById('new-section-type').value;
    const at = OPEN !== null ? OPEN + 1 : SECTIONS.length;
    SECTIONS.splice(at, 0, { type, title: SECTION_TYPES[type] || '', subtitle: '', body: '', note: '', visible: true, items: [] });
    OPEN = at;
    changed(true);
    document.getElementById('row-' + at).scrollIntoView({ block: 'center' });
}

// ===== Pré-visualização ao vivo =====
let PREVIEW_TIMER = null, PREVIEW_SEQ = 0;
function schedulePreview() {
    document.getElementById('preview-status').textContent = 'atualizando…';
    clearTimeout(PREVIEW_TIMER);
    PREVIEW_TIMER = setTimeout(refreshPreview, 600);
}
async function refreshPreview() {
    const seq = ++PREVIEW_SEQ;
    const frame = document.getElementById('preview-frame');
    let scrollY = 0;
    try { scrollY = frame.contentWindow.scrollY || 0; } catch (e) {}
    try {
        const html = await fetch(`${PRES_BASE}presentation/previewDraft/${PRESENTATION_ID}`, {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({ client_name: document.getElementById('a-client-name').value.trim(), sections: SECTIONS })
        }).then(r => r.text());
        if (seq !== PREVIEW_SEQ) return;
        frame.onload = () => {
            if (OPEN !== null) scrollPreviewTo(OPEN, true);
            else frame.contentWindow.scrollTo(0, scrollY);
        };
        frame.srcdoc = html;
        document.getElementById('preview-status').textContent = 'atualizado';
    } catch (e) {
        document.getElementById('preview-status').textContent = 'falha ao atualizar a pré-visualização';
    }
}
function scrollPreviewTo(i, instant) {
    const doc = document.getElementById('preview-frame').contentDocument;
    if (!doc) return;
    doc.querySelectorAll('.editing').forEach(el => el.classList.remove('editing'));
    const el = doc.getElementById('sec-' + i);
    if (!el) return;
    el.classList.add('editing');
    el.scrollIntoView({ behavior: instant ? 'auto' : 'smooth', block: 'start' });
}

// ===== Salvar / link / envio =====
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
    if (!confirm('Salvar este conteúdo como modelo padrão? As próximas apresentações criadas vão nascer com ele.')) return;
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
refreshPreview();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
