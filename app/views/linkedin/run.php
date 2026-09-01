<?php $pageTitle = 'Execução Rápida - LinkedIn'; $currentPage = 'linkedin_run'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-lightning-charge text-primary"></i> Execução Rápida</h5>
            <small class="text-muted">Abrir → Colar → Enviar → Confirmar. Uma ação por vez.</small>
        </div>
        <a href="<?= baseUrl('linkedin/queue') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> Ver lista</a>
    </div>

    <div class="d-flex justify-content-center">
        <div style="width:100%;max-width:620px;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small" id="progress-label">Carregando…</span>
                <div class="progress flex-grow-1 mx-3" style="height:6px;"><div class="progress-bar" id="progress-bar" style="width:0%;"></div></div>
            </div>

            <div class="card" id="action-card">
                <div class="card-body text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm"></span> Carregando ações…
                </div>
            </div>

            <div class="text-center mt-3" id="done-box" style="display:none;">
                <i class="bi bi-check2-all text-success" style="font-size:3rem;"></i>
                <h6 class="mt-2">Tudo em dia!</h6>
                <p class="text-muted">Não há mais ações LinkedIn pendentes.</p>
                <a href="<?= baseUrl('linkedin/queue') ?>" class="btn btn-sm btn-outline-primary">Voltar à lista</a>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
const ACTION_LABELS = { connect:'Solicitação de conexão', message:'1ª mensagem', followup:'Follow-up', final:'Mensagem final' };
let QUEUE = [];
let idx = 0;

function loadQueue() {
    fetch(BASE + 'linkedin/list?status=pending', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            QUEUE = d.tasks || [];
            idx = 0;
            renderCurrent();
        });
}

function renderCurrent() {
    const card = document.getElementById('action-card');
    if (idx >= QUEUE.length) {
        card.style.display = 'none';
        document.getElementById('done-box').style.display = '';
        document.getElementById('progress-label').textContent = QUEUE.length ? (QUEUE.length + ' de ' + QUEUE.length) : '';
        document.getElementById('progress-bar').style.width = '100%';
        return;
    }
    const t = QUEUE[idx];
    document.getElementById('progress-label').textContent = 'Ação ' + (idx + 1) + ' de ' + QUEUE.length;
    document.getElementById('progress-bar').style.width = Math.round((idx / QUEUE.length) * 100) + '%';

    const meta = [t.title, t.company, t.sector].filter(Boolean).map(escapeHtml).join(' · ');
    const noUrl = !t.has_linkedin;
    card.style.display = '';
    card.innerHTML = `<div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <span class="badge bg-primary">${ACTION_LABELS[t.action_type]||t.action_type}</span>
            ${t.sequence_name ? `<span class="text-muted small"><i class="bi bi-diagram-3"></i> ${escapeHtml(t.sequence_name)}</span>` : ''}
        </div>
        <h5 class="mb-0">${escapeHtml(t.lead_name)}</h5>
        ${meta ? `<div class="text-muted small">${meta}</div>` : ''}
        ${t.objective ? `<div class="small mt-2"><i class="bi bi-bullseye text-primary"></i> <strong>Objetivo:</strong> ${escapeHtml(t.objective)}</div>` : ''}
        <label class="form-label small text-muted mt-3 mb-1">Mensagem</label>
        <textarea class="form-control form-control-sm" id="run-msg" rows="7">${escapeHtml(t.message||'')}</textarea>
        <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary" onclick="openAndCopy()" ${noUrl?'disabled title="Lead sem URL de LinkedIn"':''}>
                <i class="bi bi-box-arrow-up-right"></i> Abrir LinkedIn + Copiar</button>
            <div id="run-fb" class="small text-center"></div>
            <div class="d-flex gap-2">
                <button class="btn btn-success flex-grow-1" onclick="confirmSent()"><i class="bi bi-check2"></i> Enviei</button>
                <button class="btn btn-outline-warning" onclick="skipCurrent()"><i class="bi bi-skip-forward"></i> Pular</button>
                <button class="btn btn-outline-info" onclick="replied()" title="Lead respondeu"><i class="bi bi-reply"></i></button>
            </div>
        </div>
    </div>`;
}

async function openAndCopy() {
    const t = QUEUE[idx]; if (!t) return;
    const msg = document.getElementById('run-msg').value;
    t.message = msg;
    let copied = false;
    try { await navigator.clipboard.writeText(msg); copied = true; }
    catch (e) { copied = fallbackCopy(msg); }
    if (t.linkedin_url) window.open(t.linkedin_url, '_blank', 'noopener');
    postAction('open', t.id);
    document.getElementById('run-fb').innerHTML = copied
        ? '<span class="text-success"><i class="bi bi-clipboard-check"></i> Copiado! Cole no LinkedIn, envie e clique em Enviei.</span>'
        : '<span class="text-danger">Copie o texto manualmente.</span>';
}

function fallbackCopy(text) {
    try { const ta=document.createElement('textarea'); ta.value=text; ta.style.position='fixed'; ta.style.opacity='0'; document.body.appendChild(ta); ta.select(); const ok=document.execCommand('copy'); document.body.removeChild(ta); return ok; } catch(e){ return false; }
}

function confirmSent() {
    const t = QUEUE[idx];
    const fd = new FormData(); fd.append('id', t.id); fd.append('final_message', document.getElementById('run-msg').value);
    postAction('markSent', t.id, fd).then(d=>{ if(d&&d.success) next(); });
}
function skipCurrent() {
    const t = QUEUE[idx];
    postAction('skip', t.id).then(d=>{ if(d&&d.success) next(); });
}
function replied() {
    const t = QUEUE[idx];
    if (!confirm('Registrar resposta do lead e interromper follow-ups?')) return;
    postAction('leadReplied', t.id).then(d=>{ if(d&&d.success) next(); });
}
function next() { idx++; renderCurrent(); }

function postAction(action, id, fd) {
    if (!fd) { fd = new FormData(); fd.append('id', id); }
    return fetch(BASE + 'linkedin/' + action, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{ if(d&&d.error){ alert(d.error); return null; } return d; })
        .catch(()=>{ alert('Erro na ação.'); return null; });
}

function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

loadQueue();
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
