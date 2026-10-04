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

    <!-- Abas: Relatórios | Pendências (admin) -->
    <ul class="nav nav-tabs mb-3" id="rdoTabs">
        <li class="nav-item">
            <button class="nav-link active" id="tab-relatorios-btn" onclick="switchTab('relatorios')">
                <i class="bi bi-journal-text"></i> Relatórios
            </button>
        </li>
        <?php if ($isReviewer): ?>
        <li class="nav-item">
            <button class="nav-link" id="tab-pendencias-btn" onclick="switchTab('pendencias')">
                <i class="bi bi-hourglass-split"></i> Pendências de Revisão
                <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger ms-1" id="badge-pendencias"><?= (int) $pendingCount ?></span>
                <?php else: ?>
                <span class="badge bg-secondary ms-1" id="badge-pendencias" style="display:none">0</span>
                <?php endif; ?>
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <!-- ===== ABA: Relatórios ===== -->
    <div id="tab-relatorios">

        <!-- Filtros -->
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Buscar por descrição</label>
                    <input type="text" id="f-search" class="form-control form-control-sm" placeholder="Palavra na descrição, pendências, ocorrência...">
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
                <div class="col-md-3">
                    <label class="form-label small mb-1">Cliente / Empresa</label>
                    <select id="f-company" class="form-select form-select-sm">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= (int) $co['id'] ?>"><?= escape($co['name']) ?></option>
                        <?php endforeach; ?>
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
    </div><!-- /#tab-relatorios -->

    <!-- ===== ABA: Pendências de Revisão (admin) ===== -->
    <?php if ($isReviewer): ?>
    <div id="tab-pendencias" style="display:none">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div id="pendencias-loading" class="text-center text-muted py-5">
                    <div class="spinner-border spinner-border-sm me-2"></div> Carregando pendências...
                </div>
                <div id="pendencias-list" style="display:none"></div>
                <div id="pendencias-empty" class="text-center text-muted py-5" style="display:none">
                    <i class="bi bi-check2-circle fs-2 text-success"></i>
                    <p class="mt-2 mb-0">Nenhuma pendência de revisão.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.main-content -->

<!-- ===== Modal criar/editar ===== -->
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

        <!-- Aviso de bloqueio -->
        <div id="rdo-locked-alert" class="alert alert-warning d-flex align-items-center gap-2 mb-3" style="display:none!important">
            <i class="bi bi-lock-fill"></i>
            <div>
                <strong>Relatório bloqueado.</strong> O prazo de preenchimento já encerrou.
                Suas alterações serão enviadas ao administrador para aprovação.
                <br><span id="rdo-locked-reason" class="small text-muted"></span>
            </div>
        </div>

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
                <label class="form-label fw-medium">Cliente / Empresa</label>
                <select id="rdo-company" class="form-select">
                    <option value="">— Sem cliente —</option>
                    <?php foreach ($companies as $co): ?>
                    <option value="<?= (int) $co['id'] ?>"><?= escape($co['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Título / resumo</label>
                <input type="text" id="rdo-title" class="form-control" placeholder="Resumo do dia (opcional)">
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Atividades realizadas *</label>
                <textarea id="rdo-activities" class="form-control" rows="4" placeholder="O que você fez hoje..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Pendências / em andamento</label>
                <textarea id="rdo-pending-tasks" class="form-control" rows="2" placeholder="O que ficou em andamento ou pendente para os próximos dias..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Impedimentos / ocorrências</label>
                <textarea id="rdo-occurrences" class="form-control" rows="2" placeholder="Bloqueios, problemas, atrasos..."></textarea>
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Plano para o próximo dia</label>
                <textarea id="rdo-next-day-plan" class="form-control" rows="2" placeholder="Prioridades e plano para amanhã..."></textarea>
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

<!-- ===== Modal de visualização (somente leitura) ===== -->
<div class="modal fade" id="rdoViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-text"></i> Relatório Diário</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <!-- Abas: Dados | Histórico -->
      <ul class="nav nav-tabs px-3 pt-2" id="viewTabs">
          <li class="nav-item"><button class="nav-link active" onclick="switchViewTab('dados')"><i class="bi bi-file-text"></i> Dados</button></li>
          <li class="nav-item"><button class="nav-link" onclick="switchViewTab('historico')"><i class="bi bi-clock-history"></i> Histórico</button></li>
      </ul>
      <div class="modal-body">
          <div id="view-tab-dados"></div>
          <div id="view-tab-historico" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-outline-warning" id="rdo-view-unlock" style="display:none"><i class="bi bi-unlock"></i> Solicitar desbloqueio</button>
        <button type="button" class="btn btn-primary" id="rdo-view-edit"><i class="bi bi-pencil"></i> Editar</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal de revisão (diff — apenas admin) ===== -->
<?php if ($isReviewer): ?>
<div class="modal fade" id="rdoReviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-hourglass-split"></i> Revisão de alteração</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="review-modal-body"></div>
      <div class="modal-footer">
        <div class="me-auto">
            <input type="text" id="review-notes" class="form-control form-control-sm" placeholder="Observação (opcional)" style="min-width:240px">
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btn-reject-review" onclick="resolveReview('reject')"><i class="bi bi-x-lg"></i> Recusar</button>
        <button type="button" class="btn btn-success" id="btn-approve-review" onclick="resolveReview('approve')"><i class="bi bi-check-lg"></i> Aprovar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const RDO = {
    base:         '<?= baseUrl('rdo') ?>',
    root:         '<?= rtrim(baseUrl(''), '/') ?>',
    isGlobal:     <?= $isGlobal    ? 'true' : 'false' ?>,
    isReviewer:   <?= $isReviewer  ? 'true' : 'false' ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
    deadline:     '<?= escape($deadline) ?>',
};

const KIND_LABELS = { colaborador: 'Colaborador', prestador: 'Prestador' };

const ACTION_LABELS = {
    created:   { lbl: 'Criado',               cls: 'bg-primary' },
    updated:   { lbl: 'Editado',              cls: 'bg-info text-dark' },
    submitted: { lbl: 'Enviado p/ aprovação', cls: 'bg-warning text-dark' },
    approved:  { lbl: 'Aprovado',             cls: 'bg-success' },
    rejected:  { lbl: 'Recusado',             cls: 'bg-danger' },
    locked:    { lbl: 'Bloqueado',            cls: 'bg-secondary' },
    unlocked:  { lbl: 'Desbloqueado',         cls: 'bg-success' },
};

const REVIEW_TYPE_LABELS = {
    late_fill:      'Preenchimento tardio (mesmo dia, após o prazo)',
    post_deadline:  'Preenchimento retroativo (data anterior)',
    edit_request:   'Solicitação de alteração',
    unlock_request: 'Solicitação de desbloqueio',
};

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function statusBadge(st) {
    const lbl = RDO.statusLabels[st] || st;
    const cls = st === 'finalizado' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${escapeHtml(lbl)}</span>`;
}
function reviewBadge(rs) {
    if (rs === 'pending_review') return `<span class="badge bg-warning text-dark ms-1"><i class="bi bi-hourglass-split"></i> Pendente</span>`;
    return '';
}
function lockBadge(isLocked) {
    if (Number(isLocked)) return `<span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill"></i> Bloqueado</span>`;
    return '';
}

// =========================================================================
// Alternância de abas principais
// =========================================================================
function switchTab(tab) {
    document.getElementById('tab-relatorios').style.display    = tab === 'relatorios' ? '' : 'none';
    <?php if ($isReviewer): ?>
    document.getElementById('tab-pendencias').style.display    = tab === 'pendencias' ? '' : 'none';
    <?php endif; ?>
    document.getElementById('tab-relatorios-btn').classList.toggle('active', tab === 'relatorios');
    <?php if ($isReviewer): ?>
    document.getElementById('tab-pendencias-btn').classList.toggle('active', tab === 'pendencias');
    if (tab === 'pendencias') loadPendencias();
    <?php endif; ?>
}

// =========================================================================
// Listagem de relatórios
// =========================================================================
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
    const co = document.getElementById('f-company').value;
    if (co) params.set('company_id', co);
    const uEl = document.getElementById('f-user');
    if (uEl && uEl.value) params.set('user_id', uEl.value);

    const res  = await fetch(RDO.base + '/list?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    document.getElementById('stat-total').textContent       = data.stats.total;
    document.getElementById('stat-finalizado').textContent  = data.stats.finalizado;
    document.getElementById('stat-andamento').textContent   = data.stats.em_andamento;
    document.getElementById('stat-ocorrencias').textContent = data.stats.ocorrencias;

    const tb       = document.getElementById('rdo-tbody');
    const colCount = RDO.isGlobal ? 8 : 7;
    if (!data.items.length) {
        tb.innerHTML = `<tr><td colspan="${colCount}" class="text-center text-muted py-4">Nenhum relatório encontrado.</td></tr>`;
        return;
    }

    let html = '', lastGroup = null;
    data.items.forEach(it => {
        const groupKey = it.company_id ? ('c' + it.company_id) : 'none';
        if (groupKey !== lastGroup) {
            lastGroup = groupKey;
            const projName = it.company_name
                ? escapeHtml(it.company_name)
                : '<span class="fst-italic text-muted">Sem cliente</span>';
            html += `<tr class="table-secondary"><td colspan="${colCount}" class="fw-semibold"><i class="bi bi-building"></i> ${projName}</td></tr>`;
        }
        const dateBR  = it.report_date ? it.report_date.split('-').reverse().join('/') : '';
        const created = it.created_at  ? it.created_at.replace('T', ' ').substring(0, 16) : '';
        const resumo  = escapeHtml((it.title || it.activities || '').substring(0, 80));
        const occ     = Number(it.has_occurrence)
            ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>'
            : '<span class="text-muted">—</span>';
        const who = RDO.isGlobal ? `<td>${escapeHtml(it.user_name || '')}</td>` : '';
        const statusCol = statusBadge(it.status) + reviewBadge(it.review_status) + lockBadge(it.is_locked);
        html += `<tr>
            <td>${dateBR}</td>
            ${who}
            <td>${resumo || '<span class="text-muted">—</span>'}</td>
            <td>${statusCol}</td>
            <td class="text-center">${occ}</td>
            <td class="text-center">${Number(it.attachment_count) || 0}</td>
            <td class="small text-muted">${created}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" title="Visualizar" onclick="viewRdo(${it.id})"><i class="bi bi-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary"   title="Editar"     onclick="editRdo(${it.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger"    title="Excluir"    onclick="deleteRdo(${it.id})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    });
    tb.innerHTML = html;
}

function clearFilters() {
    ['f-search','f-date-from','f-date-to','f-status','f-occ','f-company'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    const u = document.getElementById('f-user'); if (u) u.value = '';
    loadRdos();
}

['f-search','f-date-from','f-date-to','f-status','f-occ','f-company','f-user'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', loadRdos);
});
document.getElementById('f-search').addEventListener('input', () => {
    clearTimeout(window._rdoSearchT); window._rdoSearchT = setTimeout(loadRdos, 400);
});

// =========================================================================
// Modal criar/editar
// =========================================================================
let rdoModal;
function openRdoModal() {
    document.getElementById('rdo-id').value             = '';
    document.getElementById('rdo-transcription').value  = '';
    document.getElementById('rdo-date').value           = new Date().toISOString().substring(0, 10);
    document.getElementById('rdo-company').value        = '';
    document.getElementById('rdo-title').value          = '';
    document.getElementById('rdo-activities').value     = '';
    document.getElementById('rdo-pending-tasks').value  = '';
    document.getElementById('rdo-occurrences').value    = '';
    document.getElementById('rdo-next-day-plan').value  = '';
    document.getElementById('rdo-status').value         = 'em_andamento';
    document.getElementById('collab-list').innerHTML    = '';
    document.getElementById('rdo-attachments').innerHTML= '';
    document.getElementById('rdo-file').value           = '';
    document.getElementById('rdoModalTitle').textContent= 'Novo relatório';
    document.getElementById('record-status').textContent= 'Clique para gravar';
    document.getElementById('record-status').className  = 'text-muted small';
    document.getElementById('rdo-locked-alert').style.display = 'none';
    rdoModal = rdoModal || new bootstrap.Modal(document.getElementById('rdoModal'));
    rdoModal.show();
}

async function editRdo(id) {
    const res  = await fetch(RDO.base + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }
    const it = data.item;
    openRdoModal();
    document.getElementById('rdoModalTitle').textContent    = 'Editar relatório';
    document.getElementById('rdo-id').value                 = it.id;
    document.getElementById('rdo-date').value               = it.report_date;
    document.getElementById('rdo-company').value            = it.company_id || '';
    document.getElementById('rdo-title').value              = it.title || '';
    document.getElementById('rdo-activities').value         = it.activities || '';
    document.getElementById('rdo-pending-tasks').value      = it.pending_tasks || '';
    document.getElementById('rdo-occurrences').value        = it.occurrences || '';
    document.getElementById('rdo-next-day-plan').value      = it.next_day_plan || '';
    document.getElementById('rdo-status').value             = it.status;
    document.getElementById('rdo-transcription').value      = it.transcription || '';

    // Mostra alerta se bloqueado
    if (Number(it.is_locked)) {
        const alert = document.getElementById('rdo-locked-alert');
        alert.style.display = '';
        const today     = new Date().toISOString().substring(0, 10);
        const isOldDate = it.report_date < today;
        document.getElementById('rdo-locked-reason').textContent = isOldDate
            ? 'Este relatório é de um dia anterior. Alterações requerem aprovação do administrador.'
            : 'O prazo de preenchimento deste dia já encerrou. A alteração será enviada para aprovação.';
    }

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
    const id         = document.getElementById('rdo-id').value;
    const activities = document.getElementById('rdo-activities').value.trim();
    const date       = document.getElementById('rdo-date').value;
    if (!date)       { alert('Informe a data.'); return; }
    if (!activities) { alert('Descreva as atividades do dia.'); return; }

    const fd = new FormData();
    fd.append('report_date',   date);
    fd.append('company_id',    document.getElementById('rdo-company').value);
    fd.append('title',         document.getElementById('rdo-title').value.trim());
    fd.append('activities',    activities);
    fd.append('pending_tasks', document.getElementById('rdo-pending-tasks').value.trim());
    fd.append('occurrences',   document.getElementById('rdo-occurrences').value.trim());
    fd.append('next_day_plan', document.getElementById('rdo-next-day-plan').value.trim());
    fd.append('status',        document.getElementById('rdo-status').value);
    fd.append('transcription', document.getElementById('rdo-transcription').value);
    document.querySelectorAll('.collab-row').forEach(row => {
        const n = row.querySelector('.c-name').value.trim();
        if (!n) return;
        fd.append('collaborator_name[]',  n);
        fd.append('collaborator_kind[]',  row.querySelector('.c-kind').value);
        fd.append('collaborator_notes[]', row.querySelector('.c-notes').value.trim());
    });

    const btn = document.getElementById('btn-save-rdo');
    btn.disabled = true;
    const url = id ? (RDO.base + '/update/' + id) : (RDO.base + '/create');
    const res  = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();

    // Relatório bloqueado (423)
    if (res.status === 423) {
        alert(data.error || 'Este relatório está bloqueado. Solicite desbloqueio ao administrador.');
        btn.disabled = false;
        return;
    }
    if (data.error) { alert(data.error); btn.disabled = false; return; }

    const reportId = id || data.id;
    const fileEl   = document.getElementById('rdo-file');
    if (fileEl.files.length) {
        const af = new FormData();
        af.append('file', fileEl.files[0]);
        await fetch(RDO.base + '/upload/' + reportId, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: af });
    }
    btn.disabled = false;
    rdoModal.hide();

    if (data.pending) {
        alert('✓ Alteração enviada para aprovação do administrador.');
    }

    loadRdos();
}

async function deleteRdo(id) {
    if (!confirm('Excluir este relatório?')) return;
    await fetch(RDO.base + '/delete/' + id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    loadRdos();
}

// =========================================================================
// Modal de visualização
// =========================================================================
let rdoViewModal, _currentViewId = null;

function switchViewTab(tab) {
    document.getElementById('view-tab-dados').style.display    = tab === 'dados'    ? '' : 'none';
    document.getElementById('view-tab-historico').style.display= tab === 'historico'? '' : 'none';
    document.querySelectorAll('#viewTabs .nav-link').forEach((btn, i) => {
        btn.classList.toggle('active', (i === 0 && tab === 'dados') || (i === 1 && tab === 'historico'));
    });
}

async function viewRdo(id) {
    _currentViewId = id;
    const res  = await fetch(RDO.base + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }
    const it   = data.item;

    const dateBR  = it.report_date ? it.report_date.split('-').reverse().join('/') : '—';
    const project = it.company_name
        ? escapeHtml(it.company_name)
        : '<span class="fst-italic text-muted">Sem cliente</span>';

    // Colaboradores
    const collabs    = it.collaborators || [];
    const collabHtml = collabs.length
        ? `<ul class="mb-0 ps-3">` + collabs.map(c => {
            const kind  = KIND_LABELS[c.kind] || c.kind || '';
            const notes = c.notes ? ' — ' + escapeHtml(c.notes) : '';
            return `<li>${escapeHtml(c.collaborator_name)} <span class="badge bg-light text-dark border">${escapeHtml(kind)}</span>${notes}</li>`;
        }).join('') + `</ul>`
        : '<span class="text-muted">Nenhum colaborador informado.</span>';

    // Anexos
    const atts    = it.attachments || [];
    const attHtml = atts.length
        ? atts.map(a => `<a href="${RDO.root}/${escapeHtml(a.file_path)}" target="_blank" rel="noopener"
                class="badge bg-light text-dark border text-decoration-none">
                <i class="bi bi-paperclip"></i> ${escapeHtml(a.file_name)}</a>`).join(' ')
        : '<span class="text-muted">Nenhum anexo.</span>';

    const occHtml = (it.occurrences && it.occurrences.trim())
        ? `<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(it.occurrences).replace(/\n/g,'<br>')}</div>`
        : '<span class="text-muted">Sem ocorrências.</span>';

    const pendHtml = (it.pending_tasks && it.pending_tasks.trim())
        ? escapeHtml(it.pending_tasks).replace(/\n/g,'<br>')
        : '<span class="text-muted">Nada pendente informado.</span>';

    const nextHtml = (it.next_day_plan && it.next_day_plan.trim())
        ? escapeHtml(it.next_day_plan).replace(/\n/g,'<br>')
        : '<span class="text-muted">Não informado.</span>';

    const who = RDO.isGlobal && it.user_name
        ? `<div class="col-sm-6"><div class="text-muted small">Autor</div><div class="fw-medium">${escapeHtml(it.user_name)}</div></div>`
        : '';

    const lockedBadge = Number(it.is_locked)
        ? `<span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill"></i> Bloqueado</span>` : '';
    const reviewBadgeHtml = it.review_status === 'pending_review'
        ? `<span class="badge bg-warning text-dark ms-1"><i class="bi bi-hourglass-split"></i> Pendente</span>` : '';

    document.getElementById('view-tab-dados').innerHTML = `
        <div class="row g-3">
            <div class="col-sm-6"><div class="text-muted small">Cliente / Empresa</div><div class="fw-medium"><i class="bi bi-building"></i> ${project}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Data</div><div class="fw-medium">${dateBR}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Status</div><div>${statusBadge(it.status)}${lockedBadge}${reviewBadgeHtml}</div></div>
            ${who}
            ${it.title ? `<div class="col-12"><div class="text-muted small">Título / resumo</div><div class="fw-medium">${escapeHtml(it.title)}</div></div>` : ''}
            <div class="col-12">
                <div class="text-muted small">Atividades realizadas</div>
                <div>${it.activities ? escapeHtml(it.activities).replace(/\n/g,'<br>') : '<span class="text-muted">—</span>'}</div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Pendências / em andamento</div>
                <div>${pendHtml}</div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Impedimentos / ocorrências</div>
                ${occHtml}
            </div>
            <div class="col-12">
                <div class="text-muted small">Plano para o próximo dia</div>
                <div>${nextHtml}</div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Colaboradores / Prestadores</div>
                ${collabHtml}
            </div>
            <div class="col-12">
                <div class="text-muted small">Anexos</div>
                <div class="d-flex flex-wrap gap-2">${attHtml}</div>
            </div>
        </div>`;

    // Histórico
    renderHistoricoTab(it.history || []);

    // Botão editar e desbloqueio
    const editBtn   = document.getElementById('rdo-view-edit');
    const unlockBtn = document.getElementById('rdo-view-unlock');
    editBtn.onclick = () => { rdoViewModal.hide(); editRdo(it.id); };

    if (Number(it.is_locked)) {
        unlockBtn.style.display = '';
        unlockBtn.onclick = () => requestUnlock(it.id);
    } else {
        unlockBtn.style.display = 'none';
    }

    switchViewTab('dados');
    rdoViewModal = rdoViewModal || new bootstrap.Modal(document.getElementById('rdoViewModal'));
    rdoViewModal.show();
}

function renderHistoricoTab(history) {
    const el = document.getElementById('view-tab-historico');
    if (!history.length) {
        el.innerHTML = '<p class="text-muted text-center py-4">Nenhum evento registrado.</p>';
        return;
    }
    const rows = history.map(h => {
        const meta  = ACTION_LABELS[h.action] || { lbl: h.action, cls: 'bg-secondary' };
        const when  = (h.changed_at || '').substring(0, 16).replace('T', ' ');
        const actor = escapeHtml(h.actor_name || '—');
        const notes = h.notes ? `<div class="small text-muted mt-1">${escapeHtml(h.notes)}</div>` : '';
        return `<tr>
            <td class="text-nowrap small">${when}</td>
            <td><span class="badge ${meta.cls}">${meta.lbl}</span></td>
            <td class="small">${actor}</td>
            <td class="small">${notes}</td>
        </tr>`;
    }).join('');
    el.innerHTML = `<table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>Quando</th><th>Ação</th><th>Por</th><th>Obs.</th></tr></thead>
        <tbody>${rows}</tbody>
    </table>`;
}

// =========================================================================
// Solicitar desbloqueio
// =========================================================================
async function requestUnlock(id) {
    if (!confirm('Solicitar desbloqueio deste relatório ao administrador?')) return;
    const res  = await fetch(RDO.base + '/requestUnlock/' + id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    alert(data.message || (data.success ? 'Solicitação enviada.' : 'Erro ao solicitar.'));
    if (data.success) { rdoViewModal && rdoViewModal.hide(); loadRdos(); }
}

// =========================================================================
// Gravação de áudio (MediaRecorder → base64 → rdo/transcribe)
// =========================================================================
let mediaRecorder, audioChunks = [], recordingTimer, seconds = 0;
const btnRecord    = document.getElementById('btn-record');
const recordStatus = document.getElementById('record-status');
const recordTimer  = document.getElementById('record-timer');
const recordLoading= document.getElementById('record-loading');

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
        recordStatus.className   = 'text-danger small fw-medium';
        recordTimer.style.display= 'block';
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
    btnRecord.innerHTML      = '<i class="bi bi-mic-fill fs-5"></i>';
    recordStatus.textContent = 'Processando...';
    recordStatus.className   = 'text-muted small';
    recordTimer.style.display= 'none';
    recordLoading.style.display = 'flex';
}

async function processAudio() {
    const blob   = new Blob(audioChunks, { type: 'audio/webm' });
    const reader = new FileReader();
    reader.onload = async function() {
        const base64 = reader.result.split(',')[1];
        try {
            const res  = await fetch(RDO.base + '/transcribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ audio: base64 }),
            });
            const data = await res.json();
            if (data.success && data.organized) {
                if (data.organized.title)         document.getElementById('rdo-title').value         = data.organized.title;
                document.getElementById('rdo-activities').value                                       = data.organized.activities || data.transcription;
                if (data.organized.pending_tasks) document.getElementById('rdo-pending-tasks').value  = data.organized.pending_tasks;
                if (data.organized.occurrences)   document.getElementById('rdo-occurrences').value    = data.organized.occurrences;
                if (data.organized.next_day_plan) document.getElementById('rdo-next-day-plan').value  = data.organized.next_day_plan;
                document.getElementById('rdo-transcription').value = data.transcription || '';
                recordStatus.textContent = '✓ Campos preenchidos!';
                recordStatus.className   = 'text-success small fw-medium';
            } else {
                recordStatus.textContent = data.error || 'Erro na transcrição';
                recordStatus.className   = 'text-danger small';
            }
        } catch (e) {
            recordStatus.textContent = 'Erro ao processar áudio.';
            recordStatus.className   = 'text-danger small';
        }
        recordLoading.style.display = 'none';
    };
    reader.readAsDataURL(blob);
}

// =========================================================================
// Aba de pendências (admin)
// =========================================================================
<?php if ($isReviewer): ?>
let _currentReviewId = null;
let rdoReviewModal;

async function loadPendencias() {
    document.getElementById('pendencias-loading').style.display = '';
    document.getElementById('pendencias-list').style.display    = 'none';
    document.getElementById('pendencias-empty').style.display   = 'none';

    const res  = await fetch(RDO.base + '/pendingReviews', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    document.getElementById('pendencias-loading').style.display = 'none';

    // Atualiza badge
    const badge = document.getElementById('badge-pendencias');
    if (data.total > 0) {
        badge.textContent    = data.total;
        badge.className      = 'badge bg-danger ms-1';
        badge.style.display  = '';
    } else {
        badge.style.display  = 'none';
    }

    if (!data.reviews || !data.reviews.length) {
        document.getElementById('pendencias-empty').style.display = '';
        return;
    }

    const html = data.reviews.map(r => {
        const typeLbl  = REVIEW_TYPE_LABELS[r.type] || r.type;
        const dateBR   = r.report_date ? r.report_date.split('-').reverse().join('/') : '—';
        const when     = (r.requested_at || '').substring(0, 16).replace('T', ' ');
        const owner    = escapeHtml(r.owner_name || '—');
        const requester= escapeHtml(r.requester_name || '—');

        let diffHtml = '';
        if (r.type === 'edit_request' && r.payload) {
            const current  = r.payload.current  || {};
            const proposed = r.payload.proposed || {};
            const fieldLabels = {
                title: 'Título', activities: 'Atividades', occurrences: 'Ocorrências',
                pending_tasks: 'Pendências', next_day_plan: 'Plano p/ amanhã',
                status: 'Status', company_id: 'Empresa', report_date: 'Data',
            };
            const changed = Object.keys(proposed).filter(k => k !== 'has_occurrence');
            if (changed.length) {
                diffHtml = `<div class="mt-2 small">
                    <div class="fw-semibold mb-1">Campos alterados:</div>
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Campo</th><th class="text-danger">Atual</th><th class="text-success">Proposto</th></tr></thead>
                        <tbody>` +
                    changed.map(k => {
                        const lbl  = fieldLabels[k] || k;
                        const cur  = current[k]  ?? '—';
                        const prop = proposed[k] ?? '—';
                        return `<tr>
                            <td class="text-muted">${escapeHtml(lbl)}</td>
                            <td class="text-danger">${escapeHtml(String(cur)).replace(/\n/g,'<br>') || '<em>vazio</em>'}</td>
                            <td class="text-success">${escapeHtml(String(prop)).replace(/\n/g,'<br>') || '<em>vazio</em>'}</td>
                        </tr>`;
                    }).join('') +
                    `</tbody></table></div>`;
            }
        }

        return `<div class="border-bottom p-3">
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <div class="flex-grow-1">
                    <div class="fw-semibold">${owner} — <span class="text-muted fw-normal">${dateBR}</span></div>
                    <div class="small text-muted">Tipo: <strong>${escapeHtml(typeLbl)}</strong> · Solicitado por ${requester} em ${when}</div>
                    ${r.report_status ? `<div class="small mt-1">Status do relatório: ${statusBadge(r.report_status)}</div>` : ''}
                    ${diffHtml}
                </div>
                <div class="d-flex gap-2 flex-shrink-0 align-items-start mt-1">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewRdo(${r.report_id})"><i class="bi bi-eye"></i> Ver</button>
                    <button class="btn btn-sm btn-success"  onclick="openReviewModal(${r.id})"><i class="bi bi-check-lg"></i> Aprovar</button>
                    <button class="btn btn-sm btn-danger"   onclick="openReviewModal(${r.id})"><i class="bi bi-x-lg"></i> Recusar</button>
                </div>
            </div>
        </div>`;
    }).join('');

    document.getElementById('pendencias-list').innerHTML = html;
    document.getElementById('pendencias-list').style.display = '';
}

async function openReviewModal(reviewId) {
    _currentReviewId = reviewId;
    document.getElementById('review-notes').value = '';

    // Busca o item novamente para mostrar o diff completo no modal
    const res  = await fetch(RDO.base + '/pendingReviews', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    const r    = (data.reviews || []).find(x => x.id == reviewId);
    if (!r) return;

    const typeLbl = REVIEW_TYPE_LABELS[r.type] || r.type;
    const dateBR  = r.report_date ? r.report_date.split('-').reverse().join('/') : '—';

    let bodyHtml = `<p class="mb-2"><strong>Profissional:</strong> ${escapeHtml(r.owner_name || '—')} · Data: ${dateBR}</p>
        <p class="mb-2"><strong>Tipo:</strong> ${escapeHtml(typeLbl)}</p>`;

    if (r.type === 'edit_request' && r.payload) {
        const current  = r.payload.current  || {};
        const proposed = r.payload.proposed || {};
        const fieldLabels = {
            title: 'Título', activities: 'Atividades', occurrences: 'Ocorrências',
            pending_tasks: 'Pendências', next_day_plan: 'Plano p/ amanhã',
            status: 'Status', company_id: 'Empresa', report_date: 'Data',
        };
        const changed = Object.keys(proposed).filter(k => k !== 'has_occurrence');
        if (changed.length) {
            bodyHtml += `<table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Campo</th><th class="text-danger">Atual</th><th class="text-success">Proposto</th></tr></thead>
                <tbody>` +
                changed.map(k => {
                    const lbl  = fieldLabels[k] || k;
                    const cur  = current[k]  ?? '—';
                    const prop = proposed[k] ?? '—';
                    return `<tr>
                        <td>${escapeHtml(lbl)}</td>
                        <td class="text-danger small">${escapeHtml(String(cur)).replace(/\n/g,'<br>') || '<em>vazio</em>'}</td>
                        <td class="text-success small">${escapeHtml(String(prop)).replace(/\n/g,'<br>') || '<em>vazio</em>'}</td>
                    </tr>`;
                }).join('') +
                `</tbody></table>`;
        }
    } else {
        bodyHtml += `<p class="text-muted small">Nenhum dado de alteração para comparar.</p>`;
    }

    document.getElementById('review-modal-body').innerHTML = bodyHtml;
    rdoReviewModal = rdoReviewModal || new bootstrap.Modal(document.getElementById('rdoReviewModal'));
    rdoReviewModal.show();
}

async function resolveReview(action) {
    if (!_currentReviewId) return;
    const notes = document.getElementById('review-notes').value.trim();
    const url   = action === 'approve'
        ? RDO.base + '/approveEdit/' + _currentReviewId
        : RDO.base + '/rejectEdit/' + _currentReviewId;

    const fd = new FormData();
    if (notes) fd.append('notes', notes);

    const res  = await fetch(url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }

    rdoReviewModal.hide();
    loadPendencias();
    loadRdos();
}
<?php endif; ?>

// Carrega ao iniciar
loadRdos();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
