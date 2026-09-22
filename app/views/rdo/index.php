<?php $pageTitle = 'Relatório Diário - ON Solutions Helpdesk'; $currentPage = 'rdo'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-journal-text"></i> Relatório Diário</h5>
            <small class="text-muted">Registro e acompanhamento das atividades do dia</small>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openRdoModal()"><i class="bi bi-plus-lg"></i> Novo relatório</button>
    </div>

    <!-- Cards de resumo -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Total de RDO</div>
                <div class="fs-4 fw-bold" id="stat-total">0</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Finalizados</div>
                <div class="fs-4 fw-bold text-success" id="stat-finalizado">0</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Em andamento</div>
                <div class="fs-4 fw-bold text-warning" id="stat-andamento">0</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small">Com ocorrência</div>
                <div class="fs-4 fw-bold text-danger" id="stat-ocorrencias">0</div>
            </div></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Buscar por descrição</label>
                <input type="text" id="f-search" class="form-control form-control-sm" placeholder="Palavra na descrição/ocorrência...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">De</label>
                <input type="date" id="f-date-from" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Até</label>
                <input type="date" id="f-date-to" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select id="f-status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $val => $lbl): ?>
                    <option value="<?= escape($val) ?>"><?= escape($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Ocorrência</label>
                <select id="f-occ" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="1">Com ocorrência</option>
                    <option value="0">Sem ocorrência</option>
                </select>
            </div>
            <?php if ($isGlobal): ?>
            <div class="col-md-3">
                <label class="form-label small mb-1">Pessoa</label>
                <select id="f-user" class="form-select form-select-sm">
                    <option value="">Todas as pessoas</option>
                    <?php foreach ($team as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= escape($t['name']) ?> — <?= roleLabel($t['role']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="clearFilters()"><i class="bi bi-eraser"></i> Limpar</button>
            </div>
        </div>
    </div></div>

    <!-- Listagem -->
    <div class="card border-0 shadow-sm"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <?php if ($isGlobal): ?><th>Quem</th><?php endif; ?>
                        <th>Resumo / Atividades</th>
                        <th>Status</th>
                        <th class="text-center">Ocor.</th>
                        <th class="text-center">Anexos</th>
                        <th>Criado em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="rdo-tbody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<!-- Modal criar/editar -->
<div class="modal fade" id="rdoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="rdoModalTitle">Novo relatório</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rdo-id">
        <input type="hidden" id="rdo-transcription">

        <!-- Gravação por voz -->
        <div class="mb-3 p-3 border rounded-3 bg-light">
            <h6 class="mb-2" style="font-size:0.9rem"><i class="bi bi-mic"></i> Gravação por Voz</h6>
            <p class="text-muted small mb-3">Clique no microfone, relate seu dia e o sistema preencherá as atividades automaticamente.</p>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <button type="button" id="btn-record" class="btn btn-lg btn-outline-danger rounded-circle flex-shrink-0" style="width:56px;height:56px">
                    <i class="bi bi-mic-fill fs-5"></i>
                </button>
                <div>
                    <span id="record-status" class="text-muted small">Clique para gravar</span>
                    <div id="record-timer" class="fw-bold" style="display:none">00:00</div>
                </div>
                <div id="record-loading" style="display:none" class="ms-auto">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="text-muted ms-1 small">Processando...</span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-sm-4">
                <label class="form-label fw-medium">Data *</label>
                <input type="date" id="rdo-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-sm-8">
                <label class="form-label fw-medium">Título / resumo</label>
                <input type="text" id="rdo-title" class="form-control" placeholder="Resumo do dia (opcional)">
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Atividades realizadas *</label>
                <textarea id="rdo-activities" class="form-control" rows="4" placeholder="O que você fez hoje..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Ocorrências (se houver)</label>
                <textarea id="rdo-occurrences" class="form-control" rows="2" placeholder="Impedimentos, problemas, atrasos..."></textarea>
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Status</label>
                <select id="rdo-status" class="form-select">
                    <?php foreach ($statusLabels as $val => $lbl): ?>
                    <option value="<?= escape($val) ?>"><?= escape($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Colaboradores -->
            <div class="col-12">
                <label class="form-label fw-medium mb-1">Colaboradores / Prestadores</label>
                <div id="collab-list"></div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="addCollabRow()"><i class="bi bi-plus"></i> Adicionar</button>
            </div>

            <!-- Anexos -->
            <div class="col-12">
                <label class="form-label fw-medium">Anexos (áudio / imagem / arquivo)</label>
                <input type="file" id="rdo-file" class="form-control" accept="image/*,audio/*,video/*,.pdf,.doc,.docx">
                <small class="text-muted">Máx. 25MB. O anexo é enviado após salvar o relatório.</small>
                <div id="rdo-attachments" class="mt-2 d-flex flex-wrap gap-2"></div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btn-save-rdo" onclick="saveRdo()"><i class="bi bi-check-lg"></i> Salvar</button>
      </div>
    </div>
  </div>
</div>

<script>
const RDO = {
    base: '<?= baseUrl('rdo') ?>',
    isGlobal: <?= $isGlobal ? 'true' : 'false' ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
};

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function statusBadge(st) {
    const lbl = RDO.statusLabels[st] || st;
    const cls = st === 'finalizado' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${escapeHtml(lbl)}</span>`;
}

async function loadRdos() {
    const params = new URLSearchParams();
    const s = document.getElementById('f-search').value.trim();
    if (s) params.set('search', s);
    const df = document.getElementById('f-date-from').value;
    if (df) params.set('date_from', df);
    const dt = document.getElementById('f-date-to').value;
    if (dt) params.set('date_to', dt);
    const st = document.getElementById('f-status').value;
    if (st) params.set('status', st);
    const occ = document.getElementById('f-occ').value;
    if (occ !== '') params.set('has_occurrence', occ);
    const uEl = document.getElementById('f-user');
    if (uEl && uEl.value) params.set('user_id', uEl.value);

    const res = await fetch(RDO.base + '/list?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    document.getElementById('stat-total').textContent = data.stats.total;
    document.getElementById('stat-finalizado').textContent = data.stats.finalizado;
    document.getElementById('stat-andamento').textContent = data.stats.em_andamento;
    document.getElementById('stat-ocorrencias').textContent = data.stats.ocorrencias;

    const tb = document.getElementById('rdo-tbody');
    if (!data.items.length) {
        tb.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">Nenhum relatório encontrado.</td></tr>`;
        return;
    }
    tb.innerHTML = data.items.map(it => {
        const dateBR = it.report_date ? it.report_date.split('-').reverse().join('/') : '';
        const created = it.created_at ? it.created_at.replace('T', ' ').substring(0, 16) : '';
        const resumo = escapeHtml((it.title || it.activities || '').substring(0, 80));
        const occ = Number(it.has_occurrence) ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' : '<span class="text-muted">—</span>';
        const who = RDO.isGlobal ? `<td>${escapeHtml(it.user_name || '')}</td>` : '';
        return `<tr>
            <td>${dateBR}</td>
            ${who}
            <td>${resumo || '<span class="text-muted">—</span>'}</td>
            <td>${statusBadge(it.status)}</td>
            <td class="text-center">${occ}</td>
            <td class="text-center">${Number(it.attachment_count) || 0}</td>
            <td class="small text-muted">${created}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="editRdo(${it.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteRdo(${it.id})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function clearFilters() {
    document.getElementById('f-search').value = '';
    document.getElementById('f-date-from').value = '';
    document.getElementById('f-date-to').value = '';
    document.getElementById('f-status').value = '';
    document.getElementById('f-occ').value = '';
    const u = document.getElementById('f-user'); if (u) u.value = '';
    loadRdos();
}

['f-search','f-date-from','f-date-to','f-status','f-occ','f-user'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', loadRdos);
});
document.getElementById('f-search').addEventListener('input', () => {
    clearTimeout(window._rdoSearchT); window._rdoSearchT = setTimeout(loadRdos, 400);
});

let rdoModal;
function openRdoModal() {
    document.getElementById('rdo-id').value = '';
    document.getElementById('rdo-transcription').value = '';
    document.getElementById('rdo-date').value = new Date().toISOString().substring(0, 10);
    document.getElementById('rdo-title').value = '';
    document.getElementById('rdo-activities').value = '';
    document.getElementById('rdo-occurrences').value = '';
    document.getElementById('rdo-status').value = 'em_andamento';
    document.getElementById('collab-list').innerHTML = '';
    document.getElementById('rdo-attachments').innerHTML = '';
    document.getElementById('rdo-file').value = '';
    document.getElementById('rdoModalTitle').textContent = 'Novo relatório';
    document.getElementById('record-status').textContent = 'Clique para gravar';
    document.getElementById('record-status').className = 'text-muted small';
    rdoModal = rdoModal || new bootstrap.Modal(document.getElementById('rdoModal'));
    rdoModal.show();
}

async function editRdo(id) {
    const res = await fetch(RDO.base + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }
    const it = data.item;
    openRdoModal();
    document.getElementById('rdoModalTitle').textContent = 'Editar relatório';
    document.getElementById('rdo-id').value = it.id;
    document.getElementById('rdo-date').value = it.report_date;
    document.getElementById('rdo-title').value = it.title || '';
    document.getElementById('rdo-activities').value = it.activities || '';
    document.getElementById('rdo-occurrences').value = it.occurrences || '';
    document.getElementById('rdo-status').value = it.status;
    document.getElementById('rdo-transcription').value = it.transcription || '';
    (it.collaborators || []).forEach(c => addCollabRow(c.collaborator_name, c.kind, c.notes));
    renderAttachments(it.attachments || [], it.id);
}

function renderAttachments(list, reportId) {
    const box = document.getElementById('rdo-attachments');
    box.innerHTML = list.map(a => `
        <span class="badge bg-light text-dark border">
            <i class="bi bi-paperclip"></i> ${escapeHtml(a.file_name)}
            <a href="#" class="text-danger ms-1" onclick="delAttachment(${a.id});return false;">&times;</a>
        </span>`).join('');
}

async function delAttachment(attId) {
    if (!confirm('Remover este anexo?')) return;
    await fetch(RDO.base + '/deleteAttachment/' + attId, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const id = document.getElementById('rdo-id').value;
    if (id) editRdo(id);
}

function addCollabRow(name = '', kind = 'colaborador', notes = '') {
    const wrap = document.createElement('div');
    wrap.className = 'row g-1 mb-1 collab-row';
    wrap.innerHTML = `
        <div class="col-5"><input type="text" class="form-control form-control-sm c-name" placeholder="Nome" value="${escapeHtml(name)}"></div>
        <div class="col-3"><select class="form-select form-select-sm c-kind">
            <option value="colaborador"${kind==='colaborador'?' selected':''}>Colaborador</option>
            <option value="prestador"${kind==='prestador'?' selected':''}>Prestador</option>
        </select></div>
        <div class="col-3"><input type="text" class="form-control form-control-sm c-notes" placeholder="Papel/obs" value="${escapeHtml(notes||'')}"></div>
        <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.collab-row').remove()">&times;</button></div>`;
    document.getElementById('collab-list').appendChild(wrap);
}

async function saveRdo() {
    const id = document.getElementById('rdo-id').value;
    const activities = document.getElementById('rdo-activities').value.trim();
    const date = document.getElementById('rdo-date').value;
    if (!date) { alert('Informe a data.'); return; }
    if (!activities) { alert('Descreva as atividades do dia.'); return; }

    const fd = new FormData();
    fd.append('report_date', date);
    fd.append('title', document.getElementById('rdo-title').value.trim());
    fd.append('activities', activities);
    fd.append('occurrences', document.getElementById('rdo-occurrences').value.trim());
    fd.append('status', document.getElementById('rdo-status').value);
    fd.append('transcription', document.getElementById('rdo-transcription').value);
    document.querySelectorAll('.collab-row').forEach(row => {
        const n = row.querySelector('.c-name').value.trim();
        if (!n) return;
        fd.append('collaborator_name[]', n);
        fd.append('collaborator_kind[]', row.querySelector('.c-kind').value);
        fd.append('collaborator_notes[]', row.querySelector('.c-notes').value.trim());
    });

    const btn = document.getElementById('btn-save-rdo');
    btn.disabled = true;
    const url = id ? (RDO.base + '/update/' + id) : (RDO.base + '/create');
    const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    if (data.error) { alert(data.error); btn.disabled = false; return; }

    const reportId = id || data.id;
    // Envia anexo, se houver.
    const fileEl = document.getElementById('rdo-file');
    if (fileEl.files.length) {
        const af = new FormData();
        af.append('file', fileEl.files[0]);
        await fetch(RDO.base + '/upload/' + reportId, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: af });
    }
    btn.disabled = false;
    rdoModal.hide();
    loadRdos();
}

async function deleteRdo(id) {
    if (!confirm('Excluir este relatório?')) return;
    await fetch(RDO.base + '/delete/' + id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    loadRdos();
}

// ===== Gravação de áudio (MediaRecorder -> base64 -> rdo/transcribe) =====
let mediaRecorder, audioChunks = [], recordingTimer, seconds = 0;
const btnRecord = document.getElementById('btn-record');
const recordStatus = document.getElementById('record-status');
const recordTimer = document.getElementById('record-timer');
const recordLoading = document.getElementById('record-loading');

btnRecord.addEventListener('click', async () => {
    if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
    else startRecording();
});

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
        audioChunks = [];
        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = processAudio;
        mediaRecorder.start();
        btnRecord.classList.replace('btn-outline-danger', 'btn-danger');
        btnRecord.innerHTML = '<i class="bi bi-stop-fill fs-5"></i>';
        recordStatus.textContent = 'Gravando...';
        recordStatus.className = 'text-danger small fw-medium';
        recordTimer.style.display = 'block';
        seconds = 0;
        recordingTimer = setInterval(() => {
            seconds++;
            recordTimer.textContent = String(Math.floor(seconds/60)).padStart(2,'0') + ':' + String(seconds%60).padStart(2,'0');
        }, 1000);
    } catch (e) { alert('Não foi possível acessar o microfone.'); }
}

function stopRecording() {
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach(t => t.stop());
    clearInterval(recordingTimer);
    btnRecord.classList.replace('btn-danger', 'btn-outline-danger');
    btnRecord.innerHTML = '<i class="bi bi-mic-fill fs-5"></i>';
    recordStatus.textContent = 'Processando...';
    recordStatus.className = 'text-muted small';
    recordTimer.style.display = 'none';
    recordLoading.style.display = 'flex';
}

async function processAudio() {
    const blob = new Blob(audioChunks, { type: 'audio/webm' });
    const reader = new FileReader();
    reader.onload = async function() {
        const base64 = reader.result.split(',')[1];
        try {
            const res = await fetch(RDO.base + '/transcribe', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ audio: base64 })
            });
            const data = await res.json();
            if (data.success && data.organized) {
                if (data.organized.title) document.getElementById('rdo-title').value = data.organized.title;
                document.getElementById('rdo-activities').value = data.organized.activities || data.transcription;
                if (data.organized.occurrences) document.getElementById('rdo-occurrences').value = data.organized.occurrences;
                document.getElementById('rdo-transcription').value = data.transcription || '';
                recordStatus.textContent = '✓ Campos preenchidos!';
                recordStatus.className = 'text-success small fw-medium';
            } else {
                recordStatus.textContent = data.error || 'Erro na transcrição';
                recordStatus.className = 'text-danger small';
            }
        } catch (e) {
            recordStatus.textContent = 'Erro ao processar áudio.';
            recordStatus.className = 'text-danger small';
        }
        recordLoading.style.display = 'none';
    };
    reader.readAsDataURL(blob);
}

loadRdos();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
