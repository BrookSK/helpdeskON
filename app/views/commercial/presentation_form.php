<?php $pageTitle = 'Apresentação - ON Solutions Helpdesk'; $currentPage = 'presentations'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php $isPublished = $presentation['status'] === 'published'; ?>
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
            <?php elseif (Permissions::canAccess($user['role'] ?? null, 'proposals')): ?>
            <button class="btn btn-sm btn-dark" onclick="toProposal()"><i class="bi bi-arrow-right-circle"></i> Avançar para proposta</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Dados</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-12"><label class="form-label small">Título (interno)</label><input id="a-title" class="form-control form-control-sm" value="<?= escape($presentation['title']) ?>"></div>
                        <div class="col-md-5"><label class="form-label small">Cliente / empresa</label><input id="a-client-name" class="form-control form-control-sm" value="<?= escape($presentation['client_name'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">E-mail</label><input id="a-client-email" class="form-control form-control-sm" value="<?= escape($presentation['client_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small">Telefone</label><input id="a-client-phone" class="form-control form-control-sm" value="<?= escape($presentation['client_phone'] ?? '') ?>"></div>
                    </div>
                    <small class="text-muted d-block mt-2">Nos textos, use <code>{{cliente}}</code> para inserir o nome do cliente e <code>{{empresa}}</code> para o nome da sua empresa.</small>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Seções da apresentação</strong>
                    <div class="d-flex gap-2">
                        <select id="new-section-type" class="form-select form-select-sm" style="width:auto;">
                            <?php foreach ($sectionTypes as $type => $label): ?>
                            <option value="<?= $type ?>"><?= escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addSection()"><i class="bi bi-plus"></i> Seção</button>
                    </div>
                </div>
                <div class="card-body" id="sections-box"></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Link público</strong></div>
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

            <div class="card">
                <div class="card-header py-2"><strong>Modelo</strong></div>
                <div class="card-body">
                    <p class="small text-muted">Novas apresentações nascem a partir do modelo padrão.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="saveAsTemplate()"><i class="bi bi-bookmark-check"></i> Salvar estas seções como modelo padrão</button>
                        <button class="btn btn-sm btn-outline-warning" onclick="restoreDefault()"><i class="bi bi-arrow-counterclockwise"></i> Restaurar conteúdo original (Gamma)</button>
                    </div>
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
const DEFAULT_SECTIONS = <?= json_encode($defaultSections, JSON_UNESCAPED_UNICODE) ?>;
let SECTIONS = <?= json_encode($sections, JSON_UNESCAPED_UNICODE) ?>;

// Rótulos dos dois campos de cada item, conforme o layout da seção.
const ITEM_LABELS = {
    stats:   ['Valor (ex.: 100+)', 'Rótulo'],
    steps:   ['Etapa', 'Descrição'],
    cards:   ['Título', 'Descrição'],
    compare: ['Mercado (low-code / freelancer)', 'ON Solutions'],
    list:    ['Item', 'Detalhe (opcional)'],
    cta:     ['Rótulo (ex.: E-mail)', 'Valor'],
};

function esc(v) {
    return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderSections() {
    const box = document.getElementById('sections-box');
    if (!SECTIONS.length) { box.innerHTML = '<p class="text-muted small mb-0">Nenhuma seção. Adicione uma acima.</p>'; return; }
    box.innerHTML = SECTIONS.map((s, i) => {
        const labels = ITEM_LABELS[s.type];
        const items = labels ? `
            <label class="form-label small mt-2 mb-1">Itens</label>
            ${(s.items || []).map((it, j) => `
                <div class="d-flex gap-2 mb-1 align-items-start">
                    <input class="form-control form-control-sm" style="max-width:40%;" placeholder="${esc(labels[0])}" value="${esc(it.title)}" oninput="setItem(${i},${j},'title',this.value)">
                    <textarea class="form-control form-control-sm" rows="1" placeholder="${esc(labels[1])}" oninput="setItem(${i},${j},'text',this.value)">${esc(it.text)}</textarea>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${i},${j})" title="Remover item"><i class="bi bi-x"></i></button>
                </div>`).join('')}
            <button class="btn btn-sm btn-link px-0" onclick="addItem(${i})"><i class="bi bi-plus"></i> Item</button>` : '';
        return `
        <div class="border rounded mb-3 ${s.visible ? '' : 'opacity-50'}">
            <div class="d-flex align-items-center gap-2 px-3 py-2 bg-light border-bottom">
                <span class="badge bg-secondary">${esc(SECTION_TYPES[s.type] || s.type)}</span>
                <strong class="small flex-grow-1 text-truncate">${esc(s.title || '(sem título)')}</strong>
                <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},-1)" ${i === 0 ? 'disabled' : ''} title="Subir"><i class="bi bi-arrow-up"></i></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="moveSection(${i},1)" ${i === SECTIONS.length - 1 ? 'disabled' : ''} title="Descer"><i class="bi bi-arrow-down"></i></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleSection(${i})" title="${s.visible ? 'Ocultar do cliente' : 'Mostrar ao cliente'}"><i class="bi ${s.visible ? 'bi-eye' : 'bi-eye-slash'}"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="removeSection(${i})" title="Remover seção"><i class="bi bi-trash"></i></button>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small mb-1">Título</label><input class="form-control form-control-sm" value="${esc(s.title)}" oninput="setField(${i},'title',this.value)"></div>
                    <div class="col-md-6"><label class="form-label small mb-1">Subtítulo</label><input class="form-control form-control-sm" value="${esc(s.subtitle)}" oninput="setField(${i},'subtitle',this.value)"></div>
                    <div class="col-12"><label class="form-label small mb-1">Texto</label><textarea class="form-control form-control-sm" rows="3" oninput="setField(${i},'body',this.value)">${esc(s.body)}</textarea></div>
                </div>
                ${items}
            </div>
        </div>`;
    }).join('');
}

function setField(i, key, value) { SECTIONS[i][key] = value; }
function setItem(i, j, key, value) { SECTIONS[i].items[j][key] = value; }
function addItem(i) { (SECTIONS[i].items = SECTIONS[i].items || []).push({ title: '', text: '' }); renderSections(); }
function removeItem(i, j) { SECTIONS[i].items.splice(j, 1); renderSections(); }
function toggleSection(i) { SECTIONS[i].visible = !SECTIONS[i].visible; renderSections(); }
function moveSection(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= SECTIONS.length) return;
    [SECTIONS[i], SECTIONS[j]] = [SECTIONS[j], SECTIONS[i]];
    renderSections();
}
function removeSection(i) {
    if (!confirm('Remover esta seção?')) return;
    SECTIONS.splice(i, 1);
    renderSections();
}
function addSection() {
    const type = document.getElementById('new-section-type').value;
    SECTIONS.push({ type, title: SECTION_TYPES[type] || '', subtitle: '', body: '', visible: true, items: [] });
    renderSections();
}
function restoreDefault() {
    if (!confirm('Substituir as seções atuais pelo conteúdo original (Gamma)? As alterações só valem depois de salvar.')) return;
    SECTIONS = JSON.parse(JSON.stringify(DEFAULT_SECTIONS));
    renderSections();
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

async function toProposal() {
    if (!await savePresentation()) return;
    if (!confirm('Criar a proposta para este cliente e seguir na esteira?')) return;
    const r = await postAction('toProposal');
    if (r.error) { alert(r.error); return; }
    location.href = `${PRES_BASE}proposal/edit/${r.id}`;
}

renderSections();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
