<?php $pageTitle = 'Relatório Diário - ON Solutions Helpdesk'; $currentPage = 'rdo'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-journal-text"></i> Relatório Diário</h5>
            <small class="text-muted">Registro e acompanhamento das atividades do dia</small>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" id="rdo-view-toggle">
                <button type="button" class="btn btn-outline-primary active" data-view="calendar"><i class="bi bi-calendar3"></i> Calendário</button>
                <button type="button" class="btn btn-outline-primary" data-view="kanban"><i class="bi bi-kanban"></i> Kanban</button>
                <button type="button" class="btn btn-outline-primary" data-view="list"><i class="bi bi-list-ul"></i> Lista</button>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openRdoModal()"><i class="bi bi-plus-lg"></i> Novo relatório</button>
        </div>
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

    <!-- ===== VISÃO: CALENDÁRIO (padrão) ===== -->
    <div id="rdo-calendar-view">
        <div class="card border-0 shadow-sm"><div class="card-body p-2 p-md-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="rdo-cal-prev"><i class="bi bi-chevron-left"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" id="rdo-cal-today">Hoje</button>
                    <button class="btn btn-sm btn-outline-secondary" id="rdo-cal-next"><i class="bi bi-chevron-right"></i></button>
                    <h6 class="mb-0 ms-2 fw-semibold" id="rdo-cal-title" style="min-width:170px;"></h6>
                </div>
                <div class="btn-group btn-group-sm" id="rdo-cal-mode-toggle">
                    <button type="button" class="btn btn-outline-primary active" data-mode="month">Mês</button>
                    <button type="button" class="btn btn-outline-primary" data-mode="week">Semana</button>
                </div>
            </div>
            <div id="rdo-calendar-container"></div>
        </div></div>
    </div>

    <!-- ===== VISÃO: KANBAN (colunas por empresa) ===== -->
    <div id="rdo-kanban-view" style="display:none;">
        <div class="rdo-kanban" id="rdo-kanban-board">
            <div class="text-muted small py-4">Carregando...</div>
        </div>
    </div>

    <!-- ===== VISÃO: LISTA ===== -->
    <div id="rdo-list-view" style="display:none;">
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
</div>

<style>
/* ===== Kanban RDO (colunas por empresa) ===== */
.rdo-kanban { display: flex; gap: 14px; overflow-x: auto; padding-bottom: 8px; align-items: flex-start; }
.rdo-col { flex: 0 0 290px; max-width: 290px; background: #f4f6f8; border-radius: 12px; display: flex; flex-direction: column; }
.rdo-col-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; background: #fff; border-radius: 12px 12px 0 0; border-top: 3px solid #00BFA6; }
.rdo-col-title { font-size: 0.78rem; font-weight: 700; color: #445; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rdo-col-count { font-size: 0.66rem; font-weight: 600; background: #eef0f2; color: #667; border-radius: 20px; padding: 1px 8px; flex-shrink: 0; }
.rdo-col-body { padding: 10px; display: flex; flex-direction: column; gap: 10px; min-height: 60px; }
.rdo-card { background: #fff; border: 1px solid #eef0f2; border-left: 3px solid #f59e0b; border-radius: 10px; padding: 10px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: box-shadow .15s; }
.rdo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.rdo-card.done { border-left-color: #2e7d32; }
.rdo-card h6 { font-size: 0.85rem; margin-bottom: 4px; }
.rdo-card .rc-meta { font-size: 0.7rem; color: #888; display: flex; flex-wrap: wrap; gap: 3px 8px; margin-top: 4px; }
.rdo-col-empty { color: #99a; font-size: .72rem; text-align: center; padding: 8px 0; }

/* ===== Calendário RDO (grade HTML, mesmo padrão da Agenda) ===== */
.rdo-cal-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
.rdo-cal-grid th { background: #f8f9fa; text-align: center; font-weight: 600; font-size: 0.72rem; padding: 6px 4px; border: 1px solid #eef0f2; color: #667; text-transform: uppercase; }
.rdo-cal-grid td { border: 1px solid #eef0f2; vertical-align: top; height: 110px; padding: 4px; position: relative; }
.rdo-cal-grid td.other-month { background: #fafbfc; }
.rdo-cal-grid td.today { background: #eef7ff; }
.rdo-cal-daynum { font-size: 0.72rem; font-weight: 600; color: #556; }
.rdo-cal-cell-add { position: absolute; top: 3px; right: 4px; opacity: 0; font-size: 0.7rem; color: var(--primary,#00BFA6); cursor: pointer; }
.rdo-cal-grid td:hover .rdo-cal-cell-add { opacity: 1; }
.rdo-cal-event { font-size: 0.68rem; padding: 2px 6px 2px 7px; border-radius: 4px; margin-bottom: 2px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
.rdo-cal-week td { height: 300px; }
</style>

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
    root: '<?= rtrim(baseUrl(''), '/') ?>',
    isGlobal: <?= $isGlobal ? 'true' : 'false' ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>,
};

const KIND_LABELS = { colaborador: 'Colaborador', prestador: 'Prestador' };

// Visão ativa (calendário é o padrão ao abrir a tela) e cache dos itens
// carregados, para que a troca de visão não exija nova chamada ao servidor.
let rdoView = 'calendar';
let rdoItems = [];
let rdoCalDate = new Date();
let rdoCalMode = 'month';

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function statusBadge(st) {
    const lbl = RDO.statusLabels[st] || st;
    const cls = st === 'finalizado' ? 'bg-success' : 'bg-warning text-dark';
    return `<span class="badge ${cls}">${escapeHtml(lbl)}</span>`;
}
// Formata um report_date (YYYY-MM-DD ou "YYYY-MM-DD HH:MM:SS") como DD/MM/AAAA.
// Usa só o prefixo de data, evitando embaralhar quando vem com hora.
function rdoDateBR(reportDate) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(reportDate || ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
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
    const co = document.getElementById('f-company').value;
    if (co) params.set('company_id', co);
    const uEl = document.getElementById('f-user');
    if (uEl && uEl.value) params.set('user_id', uEl.value);

    const res = await fetch(RDO.base + '/list?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();

    document.getElementById('stat-total').textContent = data.stats.total;
    document.getElementById('stat-finalizado').textContent = data.stats.finalizado;
    document.getElementById('stat-andamento').textContent = data.stats.em_andamento;
    document.getElementById('stat-ocorrencias').textContent = data.stats.ocorrencias;

    rdoItems = data.items || [];
    renderActiveView();
}

// Renderiza apenas a visão atualmente ativa, usando os itens já em memória.
function renderActiveView() {
    if (rdoView === 'kanban') renderKanban();
    else if (rdoView === 'list') renderList();
    else renderCalendar();
}

// ===== Alternância de visão (Calendário / Kanban / Lista) =====
document.querySelectorAll('#rdo-view-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#rdo-view-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        rdoView = this.dataset.view;
        document.getElementById('rdo-calendar-view').style.display = rdoView === 'calendar' ? '' : 'none';
        document.getElementById('rdo-kanban-view').style.display   = rdoView === 'kanban'   ? '' : 'none';
        document.getElementById('rdo-list-view').style.display     = rdoView === 'list'     ? '' : 'none';
        renderActiveView();
    });
});

// ===== VISÃO LISTA ===== (tabela agrupada por empresa, como antes)
function renderList() {
    const data = { items: rdoItems };
    const tb = document.getElementById('rdo-tbody');
    // colspan total da tabela (varia se a coluna "Quem" aparece p/ super_admin).
    const colCount = RDO.isGlobal ? 8 : 7;
    if (!data.items.length) {
        tb.innerHTML = `<tr><td colspan="${colCount}" class="text-center text-muted py-4">Nenhum relatório encontrado.</td></tr>`;
        return;
    }

    // Os itens já vêm ordenados por cliente (empresa) pelo backend. Emitimos um
    // cabeçalho de grupo sempre que o cliente muda, separando visualmente os RDO.
    let html = '';
    let lastGroup = null;
    data.items.forEach(it => {
        const groupKey = it.company_id ? ('c' + it.company_id) : 'none';
        if (groupKey !== lastGroup) {
            lastGroup = groupKey;
            const projName = it.company_name
                ? escapeHtml(it.company_name)
                : '<span class="fst-italic text-muted">Sem cliente</span>';
            html += `<tr class="table-secondary">
                <td colspan="${colCount}" class="fw-semibold">
                    <i class="bi bi-building"></i> ${projName}
                </td>
            </tr>`;
        }

        const dateBR = rdoDateBR(it.report_date);
        const created = it.created_at ? it.created_at.replace('T', ' ').substring(0, 16) : '';
        const resumo = escapeHtml((it.title || it.activities || '').substring(0, 80));
        const occ = Number(it.has_occurrence) ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' : '<span class="text-muted">—</span>';
        const who = RDO.isGlobal ? `<td>${escapeHtml(it.user_name || '')}</td>` : '';
        html += `<tr>
            <td>${dateBR}</td>
            ${who}
            <td>${resumo || '<span class="text-muted">—</span>'}</td>
            <td>${statusBadge(it.status)}</td>
            <td class="text-center">${occ}</td>
            <td class="text-center">${Number(it.attachment_count) || 0}</td>
            <td class="small text-muted">${created}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" title="Visualizar" onclick="viewRdo(${it.id})"><i class="bi bi-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" title="Editar" onclick="editRdo(${it.id})"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Excluir" onclick="deleteRdo(${it.id})"><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    });
    tb.innerHTML = html;
}

// ===== VISÃO KANBAN ===== (colunas por empresa — opção B)
// Espelha RdoViewRules::groupByCompany no cliente: uma coluna por empresa,
// "Sem cliente" sempre por último, empresas em ordem alfabética.
function rdoCompanyKey(it) {
    const id = it.company_id;
    if (id === null || id === undefined || id === '' || isNaN(id) || Number(id) <= 0) return 'none';
    return 'c' + Number(id);
}
function groupByCompany(items) {
    const map = new Map();
    items.forEach(it => {
        const key = rdoCompanyKey(it);
        if (!map.has(key)) {
            const isNone = key === 'none';
            map.set(key, {
                key,
                company_id: isNone ? null : Number(it.company_id),
                company_name: isNone ? 'Sem cliente' : (it.company_name || 'Sem cliente'),
                items: [],
            });
        }
        map.get(key).items.push(it);
    });
    const cols = Array.from(map.values());
    cols.sort((a, b) => {
        const aNone = a.key === 'none', bNone = b.key === 'none';
        if (aNone !== bNone) return aNone ? 1 : -1;
        return String(a.company_name).localeCompare(String(b.company_name), 'pt', { sensitivity: 'base' });
    });
    return cols;
}

function renderKanban() {
    const board = document.getElementById('rdo-kanban-board');
    if (!rdoItems.length) {
        board.innerHTML = '<div class="text-muted small py-4">Nenhum relatório encontrado.</div>';
        return;
    }
    const cols = groupByCompany(rdoItems);
    board.innerHTML = cols.map(col => {
        const cards = col.items.map(it => {
            const done = it.status === 'finalizado';
            const dateBR = rdoDateBR(it.report_date);
            const resumo = escapeHtml((it.title || it.activities || '').substring(0, 70)) || '<span class="text-muted">—</span>';
            const occ = Number(it.has_occurrence)
                ? '<span title="Com ocorrência"><i class="bi bi-exclamation-triangle-fill text-danger"></i></span>' : '';
            const att = Number(it.attachment_count)
                ? `<span><i class="bi bi-paperclip"></i> ${Number(it.attachment_count)}</span>` : '';
            const who = (RDO.isGlobal && it.user_name) ? `<span><i class="bi bi-person"></i> ${escapeHtml(it.user_name)}</span>` : '';
            return `<div class="rdo-card ${done ? 'done' : ''}" onclick="viewRdo(${it.id})">
                <h6 class="fw-semibold">${resumo}</h6>
                <div>${statusBadge(it.status)} ${occ}</div>
                <div class="rc-meta">
                    <span><i class="bi bi-calendar-event"></i> ${dateBR}</span>
                    ${who}
                    ${att}
                </div>
            </div>`;
        }).join('');
        return `<div class="rdo-col" data-company="${col.company_id ?? ''}">
            <div class="rdo-col-head">
                <span class="rdo-col-title"><i class="bi bi-building"></i> ${escapeHtml(col.company_name)}</span>
                <span class="rdo-col-count">${col.items.length}</span>
            </div>
            <div class="rdo-col-body">
                ${cards || '<div class="rdo-col-empty">Nenhum RDO</div>'}
            </div>
        </div>`;
    }).join('');
}

// ===== VISÃO CALENDÁRIO ===== (grade HTML, dia = report_date)
const RDO_MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const RDO_WEEKDAYS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
function rdoFmtDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function rdoDayKey(reportDate) {
    const m = /^(\d{4}-\d{2}-\d{2})/.exec(String(reportDate || ''));
    return m ? m[1] : null;
}
function rdoEventsForDay(dayStr) {
    return rdoItems.filter(it => rdoDayKey(it.report_date) === dayStr);
}
function rdoDayContent(dayStr) {
    return rdoEventsForDay(dayStr).map(it => {
        const done = it.status === 'finalizado';
        const bg = done ? 'rgba(129,199,132,0.35)' : '#fef7d6';
        const border = done ? 'rgba(76,175,80,0.7)' : '#f59e0b';
        const text = done ? '#2e5b31' : '#7a5900';
        const label = escapeHtml((it.company_name || it.title || it.activities || 'RDO').substring(0, 24));
        const occ = Number(it.has_occurrence) ? '⚠ ' : '';
        return `<div class="rdo-cal-event" style="background:${bg};border-left:3px solid ${border};color:${text};" onclick="event.stopPropagation();viewRdo(${it.id})" title="${escapeHtml(it.title || it.company_name || '')}">${occ}${label}</div>`;
    }).join('');
}
function rdoNavCal(dir) {
    if (rdoCalMode === 'month') rdoCalDate.setMonth(rdoCalDate.getMonth() + dir);
    else rdoCalDate.setDate(rdoCalDate.getDate() + 7 * dir);
    renderCalendar();
}
function renderCalendar() {
    const container = document.getElementById('rdo-calendar-container');
    const title = document.getElementById('rdo-cal-title');
    const todayStr = rdoFmtDate(new Date());
    if (rdoCalMode === 'month') {
        title.textContent = RDO_MONTHS[rdoCalDate.getMonth()] + ' ' + rdoCalDate.getFullYear();
        const first = new Date(rdoCalDate.getFullYear(), rdoCalDate.getMonth(), 1);
        const gridStart = new Date(first); gridStart.setDate(gridStart.getDate() - first.getDay());
        let html = '<table class="rdo-cal-grid"><thead><tr>' + RDO_WEEKDAYS.map(d => `<th>${d}</th>`).join('') + '</tr></thead><tbody>';
        let cur = new Date(gridStart);
        for (let w = 0; w < 6; w++) {
            html += '<tr>';
            for (let d = 0; d < 7; d++) {
                const dayStr = rdoFmtDate(cur);
                const isOther = cur.getMonth() !== rdoCalDate.getMonth();
                html += `<td class="${isOther ? 'other-month' : ''} ${dayStr === todayStr ? 'today' : ''}">
                    <span class="rdo-cal-daynum">${cur.getDate()}</span>
                    <i class="bi bi-plus-circle-fill rdo-cal-cell-add" onclick="openRdoModalOn('${dayStr}')"></i>
                    <div>${rdoDayContent(dayStr)}</div></td>`;
                cur.setDate(cur.getDate() + 1);
            }
            html += '</tr>';
            const monthEnd = new Date(rdoCalDate.getFullYear(), rdoCalDate.getMonth() + 1, 0);
            if (cur > monthEnd && cur.getDay() === 0) break;
        }
        container.innerHTML = html + '</tbody></table>';
    } else {
        const ws = new Date(rdoCalDate); ws.setDate(ws.getDate() - ws.getDay());
        const we = new Date(ws); we.setDate(we.getDate() + 6);
        title.textContent = `${ws.getDate()} ${RDO_MONTHS[ws.getMonth()].slice(0,3)} - ${we.getDate()} ${RDO_MONTHS[we.getMonth()].slice(0,3)}`;
        let html = '<table class="rdo-cal-grid rdo-cal-week"><thead><tr>';
        let cur = new Date(ws);
        for (let d = 0; d < 7; d++) { html += `<th>${RDO_WEEKDAYS[d]} ${cur.getDate()}</th>`; cur.setDate(cur.getDate() + 1); }
        html += '</tr></thead><tbody><tr>';
        cur = new Date(ws);
        for (let d = 0; d < 7; d++) {
            const dayStr = rdoFmtDate(cur);
            html += `<td class="${dayStr === todayStr ? 'today' : ''}">
                <i class="bi bi-plus-circle-fill rdo-cal-cell-add" onclick="openRdoModalOn('${dayStr}')"></i>
                <div>${rdoDayContent(dayStr)}</div></td>`;
            cur.setDate(cur.getDate() + 1);
        }
        container.innerHTML = html + '</tr></tbody></table>';
    }
}
// Abre o modal de novo RDO já com a data clicada no calendário.
function openRdoModalOn(dayStr) {
    openRdoModal();
    if (dayStr) document.getElementById('rdo-date').value = dayStr;
}

// Controles do calendário
document.getElementById('rdo-cal-prev').addEventListener('click', () => rdoNavCal(-1));
document.getElementById('rdo-cal-next').addEventListener('click', () => rdoNavCal(1));
document.getElementById('rdo-cal-today').addEventListener('click', () => { rdoCalDate = new Date(); renderCalendar(); });
document.querySelectorAll('#rdo-cal-mode-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#rdo-cal-mode-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        rdoCalMode = this.dataset.mode;
        renderCalendar();
    });
});

function clearFilters() {
    document.getElementById('f-search').value = '';
    document.getElementById('f-date-from').value = '';
    document.getElementById('f-date-to').value = '';
    document.getElementById('f-status').value = '';
    document.getElementById('f-occ').value = '';
    document.getElementById('f-company').value = '';
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

let rdoModal;
function openRdoModal() {
    document.getElementById('rdo-id').value = '';
    document.getElementById('rdo-transcription').value = '';
    document.getElementById('rdo-date').value = new Date().toISOString().substring(0, 10);
    document.getElementById('rdo-company').value = '';
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
    document.getElementById('rdo-company').value = it.company_id || '';
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
    fd.append('company_id', document.getElementById('rdo-company').value);
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

// ===== Visualização somente-leitura (sem entrar no modo de edição) =====
let rdoViewModal;
async function viewRdo(id) {
    const res = await fetch(RDO.base + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json();
    if (data.error) { alert(data.error); return; }
    const it = data.item;

    const dateBR = rdoDateBR(it.report_date) || '—';
    const project = it.company_name
        ? escapeHtml(it.company_name)
        : '<span class="fst-italic text-muted">Sem cliente</span>';

    // Colaboradores
    const collabs = (it.collaborators || []);
    const collabHtml = collabs.length
        ? `<ul class="mb-0 ps-3">` + collabs.map(c => {
            const kind = KIND_LABELS[c.kind] || c.kind || '';
            const notes = c.notes ? ' — ' + escapeHtml(c.notes) : '';
            return `<li>${escapeHtml(c.collaborator_name)} <span class="badge bg-light text-dark border">${escapeHtml(kind)}</span>${notes}</li>`;
        }).join('') + `</ul>`
        : '<span class="text-muted">Nenhum colaborador informado.</span>';

    // Anexos (com link para abrir/baixar)
    const atts = (it.attachments || []);
    const attHtml = atts.length
        ? atts.map(a => `<a href="${RDO.root}/${escapeHtml(a.file_path)}" target="_blank" rel="noopener"
                class="badge bg-light text-dark border text-decoration-none">
                <i class="bi bi-paperclip"></i> ${escapeHtml(a.file_name)}</a>`).join(' ')
        : '<span class="text-muted">Nenhum anexo.</span>';

    const occHtml = (it.occurrences && it.occurrences.trim() !== '')
        ? `<div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(it.occurrences).replace(/\n/g, '<br>')}</div>`
        : '<span class="text-muted">Sem ocorrências.</span>';

    const who = RDO.isGlobal && it.user_name
        ? `<div class="col-sm-6"><div class="text-muted small">Autor</div><div class="fw-medium">${escapeHtml(it.user_name)}</div></div>`
        : '';

    document.getElementById('rdo-view-body').innerHTML = `
        <div class="row g-3">
            <div class="col-sm-6"><div class="text-muted small">Cliente / Empresa</div><div class="fw-medium"><i class="bi bi-building"></i> ${project}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Data</div><div class="fw-medium">${dateBR}</div></div>
            <div class="col-sm-3"><div class="text-muted small">Status</div><div>${statusBadge(it.status)}</div></div>
            ${who}
            ${it.title ? `<div class="col-12"><div class="text-muted small">Título / resumo</div><div class="fw-medium">${escapeHtml(it.title)}</div></div>` : ''}
            <div class="col-12">
                <div class="text-muted small">Atividades realizadas</div>
                <div>${it.activities ? escapeHtml(it.activities).replace(/\n/g, '<br>') : '<span class="text-muted">—</span>'}</div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Ocorrências</div>
                ${occHtml}
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

    // Botão "Editar" dentro da visualização
    const editBtn = document.getElementById('rdo-view-edit');
    editBtn.onclick = () => { rdoViewModal.hide(); editRdo(it.id); };

    rdoViewModal = rdoViewModal || new bootstrap.Modal(document.getElementById('rdoViewModal'));
    rdoViewModal.show();
}

loadRdos();
</script>

<!-- Modal de visualização (somente leitura) -->
<div class="modal fade" id="rdoViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-text"></i> Relatório Diário</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="rdo-view-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" id="rdo-view-edit"><i class="bi bi-pencil"></i> Editar</button>
      </div>
    </div>
  </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
