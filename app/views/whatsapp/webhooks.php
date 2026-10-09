<?php $pageTitle = 'Webhooks de entrada - WhatsApp'; $currentPage = 'whatsapp_webhooks'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0"><i class="bi bi-hdd-network text-success"></i> Webhooks de entrada</h5>
            <small class="text-muted">Receba dados de outros sistemas via POST e dispare mensagens no WhatsApp</small>
        </div>
        <a href="<?= baseUrl('whatsapp/chat') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar ao chat</a>
    </div>

    <div class="alert alert-light border small">
        <i class="bi bi-info-circle text-primary"></i>
        Crie um webhook para a empresa, copie a <strong>URL</strong> gerada e cole no seu outro sistema.
        Ele faz um <strong>POST</strong> (JSON) com telefone, nome, e-mail e mensagem. Enquanto o webhook
        estiver em <span class="badge bg-secondary">modo teste</span>, as requisições que chegarem são apenas
        registradas (nada é enviado) — assim você vê o payload real e <strong>mapeia os campos certos</strong>.
        Quando estiver tudo certo, <strong>ative</strong> para começar a enviar no WhatsApp.
    </div>

    <div class="row g-3">
        <!-- ESQUERDA: empresa + lista -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <select id="wh-company" class="form-select form-select-sm">
                            <option value="">Selecione a empresa...</option>
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= escape($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-success btn-sm text-nowrap" onclick="quickCreate()" title="Cria um webhook já com o link pronto">
                            <i class="bi bi-plus-lg"></i> Novo
                        </button>
                    </div>
                    <div id="wh-list"><div class="text-muted small text-center py-3">Selecione uma empresa para ver os webhooks.</div></div>
                </div>
            </div>
        </div>

        <!-- DIREITA: detalhe do webhook -->
        <div class="col-lg-8">
            <div id="wh-detail-empty" class="card">
                <div class="card-body text-muted text-center py-5">
                    <i class="bi bi-hdd-network" style="font-size:2rem;opacity:.5;"></i>
                    <p class="mt-2 mb-0">Crie ou selecione um webhook para configurar.</p>
                </div>
            </div>

            <div id="wh-detail" style="display:none;">
                <!-- URL + status -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <input type="text" id="wh-name" class="form-control form-control-sm fw-medium" style="max-width:280px;" placeholder="Nome do webhook" onchange="saveWebhook(true)">
                            <div class="d-flex align-items-center gap-2">
                                <span id="wh-status-badge" class="badge bg-secondary">modo teste</span>
                                <button id="wh-toggle-btn" class="btn btn-sm btn-outline-success" onclick="toggleActive()"><i class="bi bi-play-fill"></i> Ativar</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteWebhook()" title="Excluir"><i class="bi bi-trash3"></i></button>
                            </div>
                        </div>
                        <label class="form-label small fw-medium mb-1">URL do webhook (cole no seu outro sistema)</label>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text">POST</span>
                            <input type="text" id="wh-url" class="form-control" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyUrl()" title="Copiar"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <small class="text-muted">Content-Type: <code>application/json</code></small>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Mapeamento + template -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-white py-2"><strong class="small">Mapeamento dos campos</strong></div>
                            <div class="card-body">
                                <p class="text-muted" style="font-size:0.72rem;">
                                    Diga em qual campo do JSON está cada dado. Dica: clique num campo do payload
                                    recebido (ao lado) para preencher automaticamente.
                                </p>
                                <input type="hidden" id="wh-id">
                                <input type="hidden" id="wh-company-id">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Campo do telefone *</label>
                                    <input type="text" id="wh-phone-field" class="form-control form-control-sm" placeholder="phone">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Campo do nome</label>
                                    <input type="text" id="wh-name-field" class="form-control form-control-sm" placeholder="name">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Campo do e-mail</label>
                                    <input type="text" id="wh-email-field" class="form-control form-control-sm" placeholder="email">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Campo da mensagem</label>
                                    <input type="text" id="wh-message-field" class="form-control form-control-sm" placeholder="message">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Instância WhatsApp</label>
                                    <select id="wh-instance" class="form-select form-select-sm">
                                        <option value="">Padrão do sistema</option>
                                        <?php foreach ($instances as $i): ?>
                                        <option value="<?= (int)$i['id'] ?>"><?= escape($i['display_name'] ?: $i['instance_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Template da mensagem</label>
                                    <textarea id="wh-template" class="form-control form-control-sm" rows="3" placeholder="Olá {{nome}}! {{mensagem}}"></textarea>
                                    <small class="text-muted" style="font-size:0.68rem;">Variáveis: <code>{{nome}}</code> <code>{{telefone}}</code> <code>{{email}}</code> <code>{{mensagem}}</code>. Vazio = usa o campo da mensagem.</small>
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="saveWebhook(false)"><i class="bi bi-check-lg"></i> Salvar mapeamento</button>
                                <span id="wh-save-msg" class="small ms-2"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Requests recebidas (ao vivo) + testar -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
                                <strong class="small"><i class="bi bi-activity"></i> Requisições recebidas</strong>
                                <div class="d-flex align-items-center gap-2">
                                    <span id="wh-live" class="badge bg-success" style="display:none;">ao vivo</span>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick="sendTest()" title="Dispara um POST de teste neste webhook"><i class="bi bi-send"></i> Testar</button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="wh-requests" style="max-height:460px;overflow-y:auto;">
                                    <div class="text-muted small text-center py-4">Dispare um teste ou envie do seu sistema. As requisições aparecem aqui.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';

let whList = [];            // webhooks da empresa selecionada
let whCurrent = null;       // webhook aberto
let whLastReqId = 0;        // último id de request visto (polling)
let whPoll = null;          // setInterval

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

document.getElementById('wh-company').addEventListener('change', loadList);

function loadList() {
    const companyId = document.getElementById('wh-company').value;
    const box = document.getElementById('wh-list');
    if (!companyId) { box.innerHTML = '<div class="text-muted small text-center py-3">Selecione uma empresa.</div>'; return; }
    box.innerHTML = '<div class="text-muted small text-center py-3">Carregando...</div>';
    fetch(BASE + 'whatsapp/webhooksData?company_id=' + encodeURIComponent(companyId), { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => { whList = d.webhooks || []; renderList(); })
        .catch(() => { box.innerHTML = '<div class="text-danger small text-center py-3">Falha ao carregar.</div>'; });
}

function renderList() {
    const box = document.getElementById('wh-list');
    if (!whList.length) { box.innerHTML = '<div class="text-muted small text-center py-3">Nenhum webhook. Clique em "Novo".</div>'; return; }
    box.innerHTML = whList.map(w => {
        const active = Number(w.active) === 1;
        const sel = (whCurrent && whCurrent.id === w.id) ? 'border-success' : '';
        return `<div class="d-flex align-items-center justify-content-between p-2 mb-1 border rounded ${sel}" style="cursor:pointer;" onclick="openWebhook(${w.id})">
            <div class="text-truncate">
                <i class="bi bi-circle-fill ${active?'text-success':'text-secondary'}" style="font-size:0.5rem;"></i>
                <span class="small fw-medium">${escapeHtml(w.name)}</span>
            </div>
            <span class="badge ${active?'bg-success':'bg-secondary'}" style="font-size:0.6rem;">${active?'ativo':'teste'}</span>
        </div>`;
    }).join('');
}

function quickCreate() {
    const companyId = document.getElementById('wh-company').value;
    if (!companyId) { alert('Selecione a empresa primeiro.'); return; }
    const fd = new FormData();
    fd.append('company_id', companyId);
    fd.append('csrf_token', CSRF);
    fetch(BASE + 'whatsapp/quickCreateWebhook', { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            whList.unshift(d.webhook);
            renderList();
            openWebhookObj(d.webhook); // já abre com a URL pronta
        })
        .catch(() => alert('Falha ao criar o webhook.'));
}

function openWebhook(id) {
    const w = whList.find(x => x.id === id);
    if (w) openWebhookObj(w);
}

function openWebhookObj(w) {
    whCurrent = w;
    whLastReqId = 0;
    document.getElementById('wh-detail-empty').style.display = 'none';
    document.getElementById('wh-detail').style.display = 'block';
    document.getElementById('wh-id').value = w.id;
    document.getElementById('wh-company-id').value = w.company_id;
    document.getElementById('wh-name').value = w.name || '';
    document.getElementById('wh-url').value = w.public_url || '';
    document.getElementById('wh-phone-field').value = w.phone_field || 'phone';
    document.getElementById('wh-name-field').value = w.name_field || '';
    document.getElementById('wh-email-field').value = w.email_field || '';
    document.getElementById('wh-message-field').value = w.message_field || '';
    document.getElementById('wh-template').value = w.message_template || '';
    document.getElementById('wh-instance').value = w.instance_id || '';
    updateStatusUI(Number(w.active) === 1);
    renderList();
    document.getElementById('wh-requests').innerHTML = '<div class="text-muted small text-center py-4">Carregando requisições...</div>';
    loadRequests(true);
    startPolling();
}

function updateStatusUI(active) {
    const badge = document.getElementById('wh-status-badge');
    const btn = document.getElementById('wh-toggle-btn');
    if (active) {
        badge.className = 'badge bg-success'; badge.textContent = 'ativo (enviando)';
        btn.className = 'btn btn-sm btn-outline-warning'; btn.innerHTML = '<i class="bi bi-pause-fill"></i> Desativar';
    } else {
        badge.className = 'badge bg-secondary'; badge.textContent = 'modo teste';
        btn.className = 'btn btn-sm btn-outline-success'; btn.innerHTML = '<i class="bi bi-play-fill"></i> Ativar';
    }
}

function saveWebhook(silent) {
    if (!whCurrent) return;
    const fd = new FormData();
    fd.append('id', whCurrent.id);
    fd.append('company_id', document.getElementById('wh-company-id').value);
    fd.append('name', document.getElementById('wh-name').value.trim());
    fd.append('instance_id', document.getElementById('wh-instance').value);
    fd.append('phone_field', document.getElementById('wh-phone-field').value.trim());
    fd.append('name_field', document.getElementById('wh-name-field').value.trim());
    fd.append('email_field', document.getElementById('wh-email-field').value.trim());
    fd.append('message_field', document.getElementById('wh-message-field').value.trim());
    fd.append('message_template', document.getElementById('wh-template').value);
    fd.append('active', Number(whCurrent.active) === 1 ? '1' : '0');
    fd.append('csrf_token', CSRF);
    fetch(BASE + 'whatsapp/saveWebhook', { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            // Atualiza o nome no cache/lista.
            whCurrent.name = document.getElementById('wh-name').value.trim();
            whCurrent.phone_field = document.getElementById('wh-phone-field').value.trim();
            whCurrent.name_field = document.getElementById('wh-name-field').value.trim();
            whCurrent.email_field = document.getElementById('wh-email-field').value.trim();
            whCurrent.message_field = document.getElementById('wh-message-field').value.trim();
            whCurrent.message_template = document.getElementById('wh-template').value;
            whCurrent.instance_id = document.getElementById('wh-instance').value || null;
            renderList();
            const msg = document.getElementById('wh-save-msg');
            if (!silent) { msg.textContent = '✓ salvo'; msg.className = 'small ms-2 text-success'; setTimeout(()=>msg.textContent='', 2000); }
        })
        .catch(() => { if (!silent) alert('Falha ao salvar.'); });
}

function toggleActive() {
    if (!whCurrent) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + 'whatsapp/toggleWebhook/' + whCurrent.id, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            whCurrent.active = d.active;
            updateStatusUI(Number(d.active) === 1);
            renderList();
        })
        .catch(() => alert('Falha ao alterar o status.'));
}

function deleteWebhook() {
    if (!whCurrent) return;
    if (!confirm('Excluir este webhook? As requisições registradas também serão removidas.')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + 'whatsapp/deleteWebhook/' + whCurrent.id, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            stopPolling();
            whList = whList.filter(x => x.id !== whCurrent.id);
            whCurrent = null;
            document.getElementById('wh-detail').style.display = 'none';
            document.getElementById('wh-detail-empty').style.display = 'block';
            renderList();
        })
        .catch(() => alert('Falha ao excluir.'));
}

function copyUrl() {
    const url = document.getElementById('wh-url').value;
    if (!url) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(()=>flashSave('URL copiada!')).catch(()=>fallbackCopy(url));
    } else fallbackCopy(url);
}
function fallbackCopy(t){ const ta=document.createElement('textarea'); ta.value=t; ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');flashSave('URL copiada!');}catch(e){prompt('Copie a URL:',t);} document.body.removeChild(ta); }
function flashSave(txt){ const m=document.getElementById('wh-save-msg'); m.textContent=txt; m.className='small ms-2 text-success'; setTimeout(()=>m.textContent='',2000); }

function sendTest() {
    if (!whCurrent) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fetch(BASE + 'whatsapp/sendTestPayload/' + whCurrent.id, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            // A request de teste vai aparecer no painel pelo polling.
            loadRequests(false);
        })
        .catch(() => alert('Falha ao enviar o teste.'));
}

// ===== Requisições recebidas =====
function loadRequests(initial) {
    if (!whCurrent) return;
    const after = initial ? 0 : whLastReqId;
    fetch(BASE + 'whatsapp/webhookRequests/' + whCurrent.id + '?after_id=' + after, { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            const rows = d.requests || [];
            if (initial) {
                renderRequests(rows, true);
                if (rows.length) whLastReqId = Math.max(...rows.map(r => Number(r.id)));
            } else if (rows.length) {
                // vem ASC; atualiza o último id e redesenha tudo (simples/consistente)
                whLastReqId = Math.max(whLastReqId, ...rows.map(r => Number(r.id)));
                refreshRequests();
            }
        })
        .catch(() => {});
}

// Redesenha a lista completa (DESC) — usada no polling para refletir mudança de status.
function refreshRequests() {
    if (!whCurrent) return;
    fetch(BASE + 'whatsapp/webhookRequests/' + whCurrent.id + '?after_id=0', { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            const rows = d.requests || [];
            renderRequests(rows, true);
            if (rows.length) whLastReqId = Math.max(...rows.map(r => Number(r.id)));
        })
        .catch(() => {});
}

function renderRequests(rows, isDesc) {
    const box = document.getElementById('wh-requests');
    if (!rows.length) {
        box.innerHTML = '<div class="text-muted small text-center py-4">Nenhuma requisição ainda. Clique em "Testar" ou envie do seu sistema.</div>';
        return;
    }
    box.innerHTML = rows.map(renderRequest).join('');
}

const WH_STATUS = {
    received:  ['secondary','Recebido (teste)'],
    queued:    ['info','Na fila'],
    processing:['warning','Enviando'],
    sent:      ['success','Enviado'],
    failed:    ['danger','Falhou'],
    skipped:   ['secondary','Ignorado'],
};

function renderRequest(r) {
    const st = WH_STATUS[r.status] || ['secondary', r.status];
    let phones = []; try { phones = JSON.parse(r.parsed_phones || '[]'); } catch(e){}
    const when = r.created_at ? r.created_at.replace('T',' ').substring(0,19) : '';
    const rawId = 'raw-' + r.id;
    const treeId = 'tree-' + r.id;
    // Monta a árvore de campos clicáveis a partir do payload cru.
    let tree = '';
    try {
        const obj = JSON.parse(r.raw_payload || '{}');
        tree = buildFieldTree(obj, '');
    } catch(e) { tree = '<span class="text-muted">payload não é JSON</span>'; }
    return `<div class="p-2 border-bottom">
        <div class="d-flex align-items-center justify-content-between">
            <span class="badge bg-${st[0]}">${st[1]}</span>
            <span class="text-muted" style="font-size:0.66rem;">#${r.id} · ${when} · ${escapeHtml(r.source_ip||'')}</span>
        </div>
        <div class="small mt-1"><i class="bi bi-telephone"></i> ${phones.length ? phones.map(escapeHtml).join(', ') : '<em class="text-muted">—</em>'}</div>
        ${r.parsed_message ? `<div class="small text-muted">${escapeHtml(r.parsed_message)}</div>` : ''}
        <div class="mt-1" style="font-size:0.7rem;">
            <strong>Campos recebidos</strong> <span class="text-muted">(clique para mapear)</span>
            <div class="mt-1">${tree}</div>
        </div>
        <div><a href="#" style="font-size:0.66rem;" onclick="event.preventDefault();document.getElementById('${rawId}').classList.toggle('d-none');">ver JSON cru</a></div>
        <pre id="${rawId}" class="d-none bg-light rounded p-2 mt-1 mb-0" style="font-size:0.66rem;max-height:160px;overflow:auto;white-space:pre-wrap;">${escapeHtml(r.raw_payload||'')}</pre>
    </div>`;
}

// Gera a árvore de caminhos (dot-notation) com botões para mapear cada campo.
function buildFieldTree(obj, prefix) {
    if (obj === null || typeof obj !== 'object') return '';
    let out = '';
    for (const k in obj) {
        const path = prefix ? (prefix + '.' + k) : k;
        const v = obj[k];
        if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
            out += `<div class="ms-2"><span class="text-muted">${escapeHtml(path)}:</span>${buildFieldTree(v, path)}</div>`;
        } else {
            const preview = Array.isArray(v) ? ('[' + v.join(', ') + ']') : String(v);
            out += `<div class="ms-2 d-flex align-items-center gap-1 flex-wrap">
                <code style="font-size:0.68rem;">${escapeHtml(path)}</code>
                <span class="text-muted" style="font-size:0.66rem;">= ${escapeHtml(preview.length>40?preview.substring(0,40)+'…':preview)}</span>
                <span class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary py-0 px-1" style="font-size:0.6rem;" onclick="mapField('phone','${escapeHtml(path)}')" title="Usar como telefone">tel</button>
                    <button class="btn btn-outline-secondary py-0 px-1" style="font-size:0.6rem;" onclick="mapField('name','${escapeHtml(path)}')" title="Usar como nome">nome</button>
                    <button class="btn btn-outline-secondary py-0 px-1" style="font-size:0.6rem;" onclick="mapField('email','${escapeHtml(path)}')" title="Usar como e-mail">email</button>
                    <button class="btn btn-outline-secondary py-0 px-1" style="font-size:0.6rem;" onclick="mapField('message','${escapeHtml(path)}')" title="Usar como mensagem">msg</button>
                </span>
            </div>`;
        }
    }
    return out;
}

// Preenche o input de mapeamento correspondente com o caminho clicado.
function mapField(kind, path) {
    const map = { phone:'wh-phone-field', name:'wh-name-field', email:'wh-email-field', message:'wh-message-field' };
    const el = document.getElementById(map[kind]);
    if (el) { el.value = path; el.focus(); flashSave('Mapeado "' + path + '" como ' + kind + ' — lembre de salvar'); }
}

function startPolling() {
    stopPolling();
    document.getElementById('wh-live').style.display = '';
    whPoll = setInterval(() => refreshRequests(), 2500);
}
function stopPolling() {
    if (whPoll) { clearInterval(whPoll); whPoll = null; }
    const live = document.getElementById('wh-live'); if (live) live.style.display = 'none';
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
