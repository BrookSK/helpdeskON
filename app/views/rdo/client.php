<?php $pageTitle = 'Relatório Diário - ON Solutions Helpdesk'; $currentPage = 'rdo'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-journal-text"></i> Relatório Diário</h5>
            <small class="text-muted">Acompanhamento das atividades da sua empresa</small>
        </div>
    </div>

    <?php if (!$hasCompany): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-info-circle"></i>
        <div>Seu cadastro ainda não está vinculado a uma empresa, portanto não há relatórios para exibir. Fale com o suporte caso precise de acesso.</div>
    </div>
    <?php else: ?>

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

    <!-- Filtros (somente leitura: busca, período, status) -->
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
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
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()" title="Limpar"><i class="bi bi-eraser"></i></button>
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
                        <th>Resumo / Atividades</th>
                        <th>Status</th>
                        <th class="text-center">Ocor.</th>
                        <th class="text-center">Anexos</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="rdo-tbody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div></div>
    <?php endif; ?>
</div>

<!-- ===== Modal de visualização (somente leitura) ===== -->
<div class="modal fade" id="rdoViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-text"></i> Relatório Diário</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
          <div id="view-tab-dados"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<?php if ($hasCompany): ?>
<script>
const RDO = {
    base:         '<?= baseUrl('rdo') ?>',
    root:         '<?= rtrim(baseUrl(''), '/') ?>',
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
};

const KIND_LABELS = { colaborador: 'Colaborador', prestador: 'Prestador' };

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function statusBadge(st) {
    const lbl = RDO.statusLabels[st] || st;
    const cls = st === 'finalizado' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${escapeHtml(lbl)}</span>`;
}
// Formata report_date (YYYY-MM-DD[ HH:MM:SS]) como DD/MM/AAAA.
function rdoDateBR(reportDate) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(reportDate || ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

// =========================================================================
// Listagem (o escopo por empresa é aplicado no servidor; o cliente não
// envia company_id nem user_id — eles seriam ignorados de qualquer forma).
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

    const res  = await fetch(RDO.base + '/list?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    document.getElementById('stat-total').textContent       = data.stats.total;
    document.getElementById('stat-finalizado').textContent  = data.stats.finalizado;
    document.getElementById('stat-andamento').textContent   = data.stats.em_andamento;
    document.getElementById('stat-ocorrencias').textContent = data.stats.ocorrencias;

    renderList(data.items || []);
}

function renderList(items) {
    const tb = document.getElementById('rdo-tbody');
    if (!items.length) {
        tb.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Nenhum relatório encontrado.</td></tr>`;
        return;
    }
    tb.innerHTML = items.map(it => {
        const dateBR = rdoDateBR(it.report_date);
        const resumo = escapeHtml((it.title || it.activities || '').substring(0, 80));
        const occ = Number(it.has_occurrence) ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' : '<span class="text-muted">—</span>';
        return `<tr>
            <td>${dateBR}</td>
            <td>${resumo || '<span class="text-muted">—</span>'}</td>
            <td>${statusBadge(it.status)}</td>
            <td class="text-center">${occ}</td>
            <td class="text-center">${Number(it.attachment_count) || 0}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" title="Visualizar" onclick="viewRdo(${it.id})"><i class="bi bi-eye"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function clearFilters() {
    ['f-search','f-date-from','f-date-to','f-status'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    loadRdos();
}

['f-date-from','f-date-to','f-status'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', loadRdos);
});
document.getElementById('f-search').addEventListener('input', () => {
    clearTimeout(window._rdoSearchT); window._rdoSearchT = setTimeout(loadRdos, 400);
});

// =========================================================================
// Modal de visualização (somente leitura)
// =========================================================================
let rdoViewModal;
async function viewRdo(id) {
    const res  = await fetch(RDO.base + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }
    const it   = data.item;

    const dateBR  = it.report_date ? rdoDateBR(it.report_date) : '—';
    const project = it.company_name
        ? escapeHtml(it.company_name)
        : '<span class="fst-italic text-muted">Sem cliente</span>';

    const collabs    = it.collaborators || [];
    const collabHtml = collabs.length
        ? `<ul class="mb-0 ps-3">` + collabs.map(c => {
            const kind  = KIND_LABELS[c.kind] || c.kind || '';
            const notes = c.notes ? ' — ' + escapeHtml(c.notes) : '';
            return `<li>${escapeHtml(c.collaborator_name)} <span class="badge bg-light text-dark border">${escapeHtml(kind)}</span>${notes}</li>`;
        }).join('') + `</ul>`
        : '<span class="text-muted">Nenhum colaborador informado.</span>';

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

    document.getElementById('view-tab-dados').innerHTML = `
        <div class="row g-3">
            <div class="col-sm-6"><div class="text-muted small">Empresa</div><div class="fw-medium"><i class="bi bi-building"></i> ${project}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Data</div><div class="fw-medium">${dateBR}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Status</div><div>${statusBadge(it.status)}</div></div>
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

    rdoViewModal = rdoViewModal || new bootstrap.Modal(document.getElementById('rdoViewModal'));
    rdoViewModal.show();
}

// Carrega ao abrir a tela.
loadRdos();
</script>
<?php endif; ?>
