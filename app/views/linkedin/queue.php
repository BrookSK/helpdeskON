<?php $pageTitle = 'Minhas Ações - LinkedIn'; $currentPage = 'linkedin_queue'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-linkedin text-primary"></i> Minhas Ações</h5>
            <small class="text-muted">Ações LinkedIn manuais das suas sequências — abrir, colar, enviar, confirmar.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('linkedin/run') ?>" class="btn btn-sm btn-primary" id="btn-run-mode"><i class="bi bi-lightning-charge"></i> Execução rápida</a>
        </div>
    </div>

    <!-- Indicadores -->
    <div class="row g-2 mb-3" id="li-counters">
        <?php
        $c = $counters ?? ['pending'=>0,'today'=>0,'overdue'=>0,'sent'=>0,'skipped'=>0,'replied'=>0];
        $cards = [
            ['key'=>'today','label'=>'Hoje','icon'=>'bi-calendar-check','color'=>'primary'],
            ['key'=>'overdue','label'=>'Atrasadas','icon'=>'bi-exclamation-triangle','color'=>'danger'],
            ['key'=>'pending','label'=>'Pendentes','icon'=>'bi-hourglass-split','color'=>'warning'],
            ['key'=>'sent','label'=>'Enviadas','icon'=>'bi-check2-circle','color'=>'success'],
            ['key'=>'replied','label'=>'Responderam','icon'=>'bi-reply','color'=>'info'],
            ['key'=>'skipped','label'=>'Puladas','icon'=>'bi-skip-forward','color'=>'secondary'],
        ];
        foreach ($cards as $card): ?>
        <div class="col-6 col-md-2">
            <div class="card border-<?= $card['color'] ?>" style="border-left-width:3px !important;">
                <div class="card-body py-2 px-3">
                    <div class="text-muted" style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.4px;"><i class="bi <?= $card['icon'] ?>"></i> <?= $card['label'] ?></div>
                    <div class="fw-bold fs-5" data-counter="<?= $card['key'] ?>"><?= (int)$c[$card['key']] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" id="scope-filter">
                <button class="btn btn-outline-secondary active" data-scope="">Todas</button>
                <button class="btn btn-outline-secondary" data-scope="today">Hoje</button>
                <button class="btn btn-outline-secondary" data-scope="overdue">Atrasadas</button>
            </div>
            <select class="form-select form-select-sm" id="f-action" style="max-width:180px;">
                <option value="">Todas as ações</option>
                <option value="connect">Conexão</option>
                <option value="message">1ª mensagem</option>
                <option value="followup">Follow-up</option>
                <option value="final">Mensagem final</option>
            </select>
            <select class="form-select form-select-sm" id="f-status" style="max-width:170px;">
                <option value="pending">Pendentes</option>
                <option value="sent">Enviadas</option>
                <option value="skipped">Puladas</option>
                <option value="replied">Responderam</option>
            </select>
            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <select class="form-select form-select-sm" id="f-assigned" style="max-width:200px;">
                <option value="all">Todos os responsáveis</option>
                <?php foreach (($team ?? []) as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= escape($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-sm btn-light border ms-auto" onclick="loadTasks()"><i class="bi bi-arrow-clockwise"></i></button>
        </div>
    </div>

    <div id="li-list">
        <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm"></span> Carregando ações...</div>
    </div>
</div>

<!-- Modal editar mensagem -->
<div class="modal fade" id="editMsgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pencil"></i> Editar mensagem</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-task-id">
                <textarea id="edit-task-msg" class="form-control form-control-sm" rows="7"></textarea>
                <small class="text-muted d-block mt-1">A mensagem editada será usada ao copiar e ficará registrada ao confirmar o envio.</small>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-outline-secondary" onclick="regenMsg()"><i class="bi bi-stars"></i> Regenerar com IA</button>
                <button class="btn btn-sm btn-primary" onclick="saveEditedMsg()"><i class="bi bi-check-lg"></i> Aplicar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl('') ?>';
const IS_ADMIN = <?= (($user['role'] ?? '') === 'super_admin') ? 'true' : 'false' ?>;
let editModal = null;
let TASKS = {}; // id -> task

function currentFilters() {
    const scope = document.querySelector('#scope-filter .active')?.dataset.scope || '';
    const p = new URLSearchParams();
    if (scope) p.set('scope', scope);
    p.set('status', document.getElementById('f-status').value);
    const act = document.getElementById('f-action').value; if (act) p.set('action_type', act);
    if (IS_ADMIN) { const a = document.getElementById('f-assigned').value; if (a) p.set('assigned_to', a); }
    return p.toString();
}

function loadTasks() {
    const box = document.getElementById('li-list');
    box.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm"></span></div>';
    fetch(BASE + 'linkedin/list?' + currentFilters(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            if (d.counters) updateCounters(d.counters);
            const tasks = d.tasks || [];
            TASKS = {}; tasks.forEach(t => TASKS[t.id] = t);
            if (!tasks.length) {
                box.innerHTML = '<div class="card"><div class="card-body text-center py-5 text-muted"><i class="bi bi-check2-all" style="font-size:2.5rem;color:#ccc;"></i><p class="mt-2 mb-0">Nenhuma ação nesta visão.</p></div></div>';
                return;
            }
            box.innerHTML = tasks.map(renderCard).join('');
        }).catch(()=>{ box.innerHTML = '<div class="alert alert-danger">Erro ao carregar ações.</div>'; });
}

function updateCounters(c) {
    document.querySelectorAll('[data-counter]').forEach(el => {
        const k = el.dataset.counter; if (c[k] !== undefined) el.textContent = c[k];
    });
}

const ACTION_LABELS = { connect:'Solicitação de conexão', message:'1ª mensagem', followup:'Follow-up', final:'Mensagem final' };

function renderCard(t) {
    const pending = (t.status === 'ready' || t.status === 'opened');
    const meta = [t.title, t.company, t.sector].filter(Boolean).map(escapeHtml).join(' · ');
    const noUrl = !t.has_linkedin;
    return `<div class="card mb-2" id="task-${t.id}">
      <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="fw-semibold">${escapeHtml(t.lead_name)}</span>
              <span class="badge bg-light text-dark border">${ACTION_LABELS[t.action_type]||t.action_type}</span>
              ${statusBadge(t.status)}
              ${t.sequence_name ? `<span class="text-muted small"><i class="bi bi-diagram-3"></i> ${escapeHtml(t.sequence_name)}</span>` : ''}
            </div>
            ${meta ? `<div class="text-muted small mt-1">${meta}</div>` : ''}
            ${t.objective ? `<div class="small mt-1"><i class="bi bi-bullseye text-primary"></i> ${escapeHtml(t.objective)}</div>` : ''}
            <div class="mt-2 p-2 bg-light rounded small" style="white-space:pre-wrap;" id="msg-${t.id}">${escapeHtml(t.message || '(sem mensagem)')}</div>
          </div>
        </div>
        ${pending ? `<div class="d-flex flex-wrap gap-2 mt-2">
            <button class="btn btn-sm btn-primary" onclick="openAndCopy(${t.id})" ${noUrl?'disabled title="Lead sem URL de LinkedIn"':''}>
                <i class="bi bi-box-arrow-up-right"></i> Abrir LinkedIn + Copiar</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="editMsg(${t.id})"><i class="bi bi-pencil"></i> Editar</button>
            <button class="btn btn-sm btn-success" onclick="markSent(${t.id})"><i class="bi bi-check2"></i> Enviei</button>
            <button class="btn btn-sm btn-outline-warning" onclick="skipTask(${t.id})"><i class="bi bi-skip-forward"></i> Pular</button>
            <button class="btn btn-sm btn-outline-info ms-auto" onclick="leadReplied(${t.id})"><i class="bi bi-reply"></i> Lead respondeu</button>
        </div>` : ''}
        <div class="small mt-2" id="fb-${t.id}"></div>
      </div>
    </div>`;
}

function statusBadge(s) {
    const map = { ready:['A fazer','secondary'], opened:['Perfil aberto','primary'], sent:['Enviada','success'], skipped:['Pulada','secondary'], replied:['Respondeu','info'] };
    const v = map[s] || [s,'secondary'];
    return `<span class="badge bg-${v[1]}">${v[0]}</span>`;
}

// ===== ABRIR LINKEDIN + COPIAR (um gesto do usuário) =====
async function openAndCopy(id) {
    const t = TASKS[id]; if (!t) return;
    const msg = t.message || '';
    // 1) copiar para o clipboard (acionado pela interação do usuário)
    let copied = false;
    try { await navigator.clipboard.writeText(msg); copied = true; }
    catch (e) { copied = fallbackCopy(msg); }
    // 2) abrir a URL do perfil em nova aba
    if (t.linkedin_url) window.open(t.linkedin_url, '_blank', 'noopener');
    // 3) registrar abertura no servidor (NÃO conclui a tarefa)
    postAction('open', id).then(()=>{ t.status = 'opened'; });
    // 4) feedback
    feedback(id, copied
        ? '<span class="text-success"><i class="bi bi-clipboard-check"></i> Mensagem copiada! Cole no LinkedIn (Ctrl+V), envie e depois clique em <strong>Enviei</strong>.</span>'
        : '<span class="text-danger">Não consegui copiar automaticamente. Selecione o texto acima e copie manualmente.</span>');
}

function fallbackCopy(text) {
    try {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok;
    } catch (e) { return false; }
}

function markSent(id) {
    const t = TASKS[id];
    const fd = new FormData(); fd.append('id', id);
    if (t && t.message) fd.append('final_message', t.message);
    postAction('markSent', id, fd).then(d=>{ if(d && d.success){ afterDone(id, 'Enviada e sequência retomada.'); } });
}
function skipTask(id) {
    if (!confirm('Pular esta ação? A sequência avança para a próxima etapa.')) return;
    postAction('skip', id).then(d=>{ if(d && d.success){ afterDone(id, 'Ação pulada.'); } });
}
function leadReplied(id) {
    if (!confirm('Registrar que o lead RESPONDEU no LinkedIn? Isso interrompe os follow-ups automáticos.')) return;
    postAction('leadReplied', id).then(d=>{ if(d && d.success){ afterDone(id, 'Resposta registrada. Follow-ups interrompidos.'); } });
}

function afterDone(id, msg) {
    const card = document.getElementById('task-' + id);
    if (card) { card.style.opacity = '0.5'; }
    feedback(id, '<span class="text-success"><i class="bi bi-check2-circle"></i> ' + msg + '</span>');
    setTimeout(loadTasks, 900);
}

function postAction(action, id, fd) {
    if (!fd) { fd = new FormData(); fd.append('id', id); }
    return fetch(BASE + 'linkedin/' + action, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{ if(d && d.error){ alert(d.error); return null; } return d; })
        .catch(()=>{ alert('Erro na ação.'); return null; });
}

function feedback(id, html) { const el = document.getElementById('fb-' + id); if (el) el.innerHTML = html; }

// ===== Editar / regenerar =====
function editMsg(id) {
    const t = TASKS[id]; if (!t) return;
    document.getElementById('edit-task-id').value = id;
    document.getElementById('edit-task-msg').value = t.message || '';
    if (!editModal) editModal = new bootstrap.Modal(document.getElementById('editMsgModal'));
    editModal.show();
}
function saveEditedMsg() {
    const id = document.getElementById('edit-task-id').value;
    const msg = document.getElementById('edit-task-msg').value;
    if (TASKS[id]) TASKS[id].message = msg;
    const box = document.getElementById('msg-' + id); if (box) box.textContent = msg;
    editModal.hide();
}
function regenMsg() {
    const id = document.getElementById('edit-task-id').value;
    const fd = new FormData(); fd.append('id', id);
    fetch(BASE + 'linkedin/regenerate', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            if (d.error) { alert(d.error); return; }
            document.getElementById('edit-task-msg').value = d.message || '';
            if (d.warning) alert(d.warning);
        });
}

function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

// Filtros
document.querySelectorAll('#scope-filter button').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('#scope-filter button').forEach(x=>x.classList.remove('active'));
    b.classList.add('active'); loadTasks();
}));
document.getElementById('f-action').addEventListener('change', loadTasks);
document.getElementById('f-status').addEventListener('change', loadTasks);
if (IS_ADMIN) document.getElementById('f-assigned').addEventListener('change', loadTasks);

loadTasks();
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
