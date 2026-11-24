<?php $pageTitle = 'Templates de mensagem'; $currentPage = 'prospection_templates'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<style>
.tpl-card { border:1px solid #e9ecef; border-radius:10px; padding:12px 14px; background:#fff; height:100%; display:flex; flex-direction:column; }
.tpl-card h6 { font-size:0.9rem; margin:0; word-break:break-word; }
.tpl-card .tpl-subj { font-size:0.78rem; color:#6c757d; margin-top:2px; word-break:break-word; }
.tpl-card .tpl-snippet { font-size:0.8rem; color:#555; margin:8px 0; flex:1; overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; word-break:break-word; }
.tpl-filters .btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
</style>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-file-earmark-richtext"></i> Templates de mensagem</h5>
            <small class="text-muted">Modelos prontos para a Prospecção E-mail e para os blocos das sequências</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('prospection') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope-paper"></i> Prospecção E-mail</a>
            <button class="btn btn-sm btn-primary" id="btn-new-template" onclick="tplNew()"><i class="bi bi-plus-lg"></i> Novo template</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm tpl-filters" role="group">
                <button class="btn btn-outline-secondary active" data-ch="">Todos</button>
                <button class="btn btn-outline-secondary" data-ch="email"><i class="bi bi-envelope"></i> E-mail</button>
                <button class="btn btn-outline-secondary" data-ch="whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                <button class="btn btn-outline-secondary" data-ch="linkedin"><i class="bi bi-linkedin"></i> LinkedIn</button>
            </div>
            <input type="search" id="tpl-search" class="form-control form-control-sm" style="max-width:280px;" placeholder="Buscar por nome ou assunto...">
            <small class="text-muted ms-auto" id="tpl-count"></small>
        </div>
    </div>

    <div class="row g-3" id="tpl-grid"><div class="col-12 text-center text-muted py-4">Carregando...</div></div>
</div>

<!-- Editor -->
<div class="modal fade" id="tplEditModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="te-title">Novo template</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="te-id">
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small fw-medium">Nome *</label>
                        <input type="text" id="te-name" class="form-control form-control-sm" maxlength="150" placeholder="Ex: Apresentação comercial — clínicas">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Canal *</label>
                        <select id="te-channel" class="form-select form-select-sm" onchange="teChannel()">
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="linkedin">LinkedIn</option>
                        </select>
                    </div>
                    <div class="col-12" id="te-subject-wrap">
                        <label class="form-label small fw-medium">Assunto</label>
                        <input type="text" id="te-subject" class="form-control form-control-sm" maxlength="250" placeholder="Ex: {{primeiro_nome}}, uma ideia para a {{empresa}}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Mensagem *</label>
                        <div id="te-rich">
                            <div id="te-chips"></div>
                            <div id="te-editor"></div>
                        </div>
                        <div id="te-plain" style="display:none;">
                            <div id="te-chips-text"></div>
                            <textarea id="te-text" class="form-control form-control-sm" rows="9" placeholder="Olá {{primeiro_nome}}, ..."></textarea>
                        </div>
                        <small class="text-muted d-block mt-1" id="te-help"></small>
                    </div>
                </div>
                <div id="te-error" class="alert alert-danger py-2 small mt-2 mb-0" style="display:none;"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-primary" id="te-preview" onclick="tePreview()"><i class="bi bi-eye"></i> Ver como chega</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="te-save" onclick="teSave(this)"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/_var_picker.php'; ?>
<?php require APP_PATH . '/views/layouts/_rich_editor.php'; ?>

<script>
const BASE = '<?= baseUrl('') ?>';
let TPLS = [], tplFilter = '', teModal = null, teEditor = null;
const CH = { email: ['E-mail', 'bg-primary', 'envelope'], whatsapp: ['WhatsApp', 'bg-success', 'whatsapp'], linkedin: ['LinkedIn', 'bg-info', 'linkedin'] };
const esc = (s) => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; };
const plain = (html) => { const d = document.createElement('div'); d.innerHTML = String(html || '').replace(/<br\s*\/?>/gi, ' ').replace(/<\/p>/gi, ' '); return (d.textContent || '').replace(/\s+/g, ' ').trim(); };

function tplLoad() {
    fetch(BASE + 'prospection/templateList', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => { TPLS = d.templates || []; tplRender(); })
        .catch(() => { document.getElementById('tpl-grid').innerHTML = '<div class="col-12 text-danger">Erro ao carregar os templates.</div>'; });
}

function tplRender() {
    const q = document.getElementById('tpl-search').value.trim().toLowerCase();
    const list = TPLS.filter(t => (!tplFilter || t.channel === tplFilter)
        && (!q || (t.name || '').toLowerCase().includes(q) || (t.subject || '').toLowerCase().includes(q)));
    document.getElementById('tpl-count').textContent = list.length + ' template(s)';
    const grid = document.getElementById('tpl-grid');
    if (!list.length) {
        grid.innerHTML = `<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-file-earmark-richtext" style="font-size:2rem;"></i>
            <p class="mt-2 mb-2">${TPLS.length ? 'Nenhum template com esse filtro.' : 'Nenhum template ainda.'}</p>
            <button class="btn btn-sm btn-primary" onclick="tplNew()"><i class="bi bi-plus-lg"></i> Criar template</button></div></div></div>`;
        return;
    }
    grid.innerHTML = list.map(t => {
        const c = CH[t.channel] || CH.email;
        return `<div class="col-12 col-md-6 col-xl-4"><div class="tpl-card" data-id="${t.id}">
            <div class="d-flex justify-content-between gap-2 align-items-start">
                <div style="min-width:0;"><h6>${esc(t.name)}</h6>${t.subject ? `<div class="tpl-subj">Assunto: ${esc(t.subject)}</div>` : ''}</div>
                <span class="badge ${c[1]} flex-shrink-0"><i class="bi bi-${c[2]}"></i> ${c[0]}</span>
            </div>
            <div class="tpl-snippet">${esc(plain(t.body).slice(0, 220))}</div>
            <div class="d-flex gap-1 flex-wrap">
                <button class="btn btn-sm btn-outline-primary" onclick="tplEdit(${t.id})"><i class="bi bi-pencil"></i> Editar</button>
                ${t.channel === 'email' ? `<button class="btn btn-sm btn-outline-secondary" onclick="tplPreviewId(${t.id})" title="Ver como chega"><i class="bi bi-eye"></i></button>` : ''}
                <button class="btn btn-sm btn-outline-secondary" onclick="tplDuplicate(${t.id})" title="Duplicar"><i class="bi bi-copy"></i></button>
                <button class="btn btn-sm btn-outline-danger ms-auto" onclick="tplDelete(${t.id}, this)" title="Excluir"><i class="bi bi-trash"></i></button>
            </div></div></div>`;
    }).join('');
}

function teOpen(t, title) {
    if (!teModal) teModal = new bootstrap.Modal(document.getElementById('tplEditModal'));
    if (!teEditor) teEditor = RichEditor.mount(document.getElementById('te-editor'), { chips: document.getElementById('te-chips'), placeholder: 'Olá {{primeiro_nome}}, ...' });
    document.getElementById('te-title').textContent = title;
    document.getElementById('te-id').value = t.id || '';
    document.getElementById('te-name').value = t.name || '';
    document.getElementById('te-channel').value = t.channel || 'email';
    document.getElementById('te-subject').value = t.subject || '';
    document.getElementById('te-error').style.display = 'none';
    teChannel(t.body || '');
    teModal.show();
}

// Troca editor formatado (e-mail) x texto puro (WhatsApp/LinkedIn), levando o conteúdo junto.
let teMode = 'rich';
function teChannel(body) {
    const isEmail = document.getElementById('te-channel').value === 'email';
    const ta = document.getElementById('te-text');
    let current = body !== undefined ? body : (teMode === 'rich' ? teEditor.get() : ta.value);
    document.getElementById('te-subject-wrap').style.display = isEmail ? '' : 'none';
    document.getElementById('te-preview').style.display = isEmail ? '' : 'none';
    document.getElementById('te-rich').style.display = isEmail ? '' : 'none';
    document.getElementById('te-plain').style.display = isEmail ? 'none' : '';
    if (isEmail) { teEditor.set(current); teMode = 'rich'; }
    else { ta.value = /<[a-z][\s\S]*>/i.test(current) ? htmlToText(current) : current; teMode = 'text'; }
    document.getElementById('te-help').textContent = isEmail
        ? 'Use a barra para negrito, itálico, listas e links. O e-mail chega com essa formatação, com a sua assinatura e o layout do sistema. Clique numa variável para inserir.'
        : 'Texto simples (WhatsApp/LinkedIn não aceitam formatação de e-mail). Clique numa variável para inserir.';
}
function htmlToText(html) {
    const d = document.createElement('div');
    d.innerHTML = String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n');
    return (d.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
}
function teBody() {
    return document.getElementById('te-channel').value === 'email' ? teEditor.get() : document.getElementById('te-text').value;
}

function tplNew() { teOpen({ channel: tplFilter || 'email' }, 'Novo template'); }
function tplEdit(id) { const t = TPLS.find(x => +x.id === +id); if (t) teOpen(t, 'Editar template'); }
function tplDuplicate(id) {
    const t = TPLS.find(x => +x.id === +id); if (!t) return;
    teOpen(Object.assign({}, t, { id: '', name: t.name + ' (cópia)' }), 'Duplicar template');
}
function tplPreviewId(id) { const t = TPLS.find(x => +x.id === +id); if (t) openEmailPreview({ subject: t.subject, body: t.body }); }
function tePreview() { openEmailPreview({ subject: document.getElementById('te-subject').value, body: teBody() }); }

function teSave(btn) {
    const err = document.getElementById('te-error');
    err.style.display = 'none';
    const fd = new FormData();
    const id = document.getElementById('te-id').value;
    if (id) fd.append('id', id);
    fd.append('channel', document.getElementById('te-channel').value);
    fd.append('name', document.getElementById('te-name').value.trim());
    fd.append('subject', document.getElementById('te-subject').value.trim());
    fd.append('body', teBody());
    btn.disabled = true;
    fetch(BASE + 'prospection/templateSave', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(d => {
            btn.disabled = false;
            if (d.error) { err.textContent = d.error; err.style.display = ''; return; }
            teModal.hide(); tplLoad();
        })
        .catch(() => { btn.disabled = false; err.textContent = 'Erro ao salvar.'; err.style.display = ''; });
}

function tplDelete(id, btn) {
    const t = TPLS.find(x => +x.id === +id);
    if (!confirm('Excluir o template "' + (t ? t.name : '') + '"? Blocos de sequência que usam este template passam a usar o texto do próprio bloco.')) return;
    btn.disabled = true;
    fetch(BASE + 'prospection/templateDelete/' + id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json()).then(() => tplLoad());
}

document.querySelectorAll('.tpl-filters .btn').forEach(b => b.onclick = () => {
    document.querySelectorAll('.tpl-filters .btn').forEach(x => x.classList.toggle('active', x === b));
    tplFilter = b.dataset.ch; tplRender();
});
document.getElementById('tpl-search').addEventListener('input', tplRender);
// Chips de variável do modo texto (WhatsApp/LinkedIn)
document.getElementById('te-chips-text').innerHTML = varChipsHtml();
bindVarChips(document.getElementById('te-chips-text'), document.getElementById('te-text'));
tplLoad();
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
