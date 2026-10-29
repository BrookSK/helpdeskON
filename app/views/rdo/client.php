<?php $pageTitle = 'Relatório Diário - ON Solutions Helpdesk'; $currentPage = 'rdo'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-journal-text"></i> Relatório Diário</h5>
            <small class="text-muted">Acompanhamento das atividades da sua empresa</small>
        </div>
        <?php if ($hasCompany): ?>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" id="rdo-view-toggle">
                <button type="button" class="btn btn-outline-primary active" data-view="calendar"><i class="bi bi-calendar3"></i> Calendário</button>
                <button type="button" class="btn btn-outline-primary" data-view="kanban"><i class="bi bi-kanban"></i> Kanban</button>
                <button type="button" class="btn btn-outline-primary" data-view="list"><i class="bi bi-list-ul"></i> Lista</button>
            </div>
        </div>
        <?php endif; ?>
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
            <!-- Legenda de empresas (cores) — preenchida via JS com as empresas presentes no período -->
            <div id="rdo-company-legend" class="rdo-legend"></div>
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
    </div>
    <?php endif; ?>
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
.rdo-cal-event { font-size: 0.68rem; padding: 2px 6px 2px 7px; border-radius: 4px; margin-bottom: 2px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
.rdo-cal-event.done { opacity: 0.6; }
.rdo-cal-week td { height: 300px; }

/* ===== Legenda de empresas (cores por empresa) ===== */
.rdo-legend { display: flex; flex-wrap: wrap; gap: 6px 12px; padding: 4px 2px 10px; }
.rdo-legend:empty { display: none; }
.rdo-legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 0.72rem; color: #556; cursor: pointer; user-select: none; padding: 1px 4px; border-radius: 6px; }
.rdo-legend-item.muted { opacity: 0.4; }
.rdo-legend-item:hover { background: #f1f3f5; }
.rdo-legend-dot { width: 11px; height: 11px; border-radius: 3px; flex-shrink: 0; }

/* ponto de cor da empresa usado no kanban/lista */
.rdo-company-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: middle; flex-shrink: 0; }
</style>

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

// Visão ativa (calendário é o padrão) e cache dos itens carregados, para que a
// troca de visão não exija nova chamada ao servidor.
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
// Formata report_date (YYYY-MM-DD[ HH:MM:SS]) como DD/MM/AAAA.
function rdoDateBR(reportDate) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(reportDate || ''));
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

// ===== Cor por empresa (identidade visual estável) =====
// Mesma regra da visão interna: cor estável derivada do company_id.
const RDO_COMPANY_PALETTE = [
    '#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed',
    '#db2777', '#0891b2', '#ca8a04', '#4f46e5', '#059669',
    '#e11d48', '#0d9488', '#9333ea', '#ea580c', '#2563eb',
];
const RDO_NO_COMPANY_COLOR = '#9aa5b1';

function rdoCompanyId(it) {
    const id = it && it.company_id;
    if (id === null || id === undefined || id === '' || isNaN(id) || Number(id) <= 0) return null;
    return Number(id);
}
function rdoCompanyColor(companyId) {
    if (companyId === null || companyId === undefined) return RDO_NO_COMPANY_COLOR;
    return RDO_COMPANY_PALETTE[Number(companyId) % RDO_COMPANY_PALETTE.length];
}

// Conjunto de empresas atualmente ocultas pela legenda (filtro visual local).
let rdoHiddenCompanies = new Set();
function rdoLegendKey(it) {
    const id = rdoCompanyId(it);
    return id === null ? 'none' : id;
}
function rdoVisibleItems() {
    if (rdoHiddenCompanies.size === 0) return rdoItems;
    return rdoItems.filter(it => !rdoHiddenCompanies.has(rdoLegendKey(it)));
}

function renderCompanyLegend() {
    const box = document.getElementById('rdo-company-legend');
    if (!box) return;
    const seen = new Map();
    rdoItems.forEach(it => {
        const key = rdoLegendKey(it);
        if (!seen.has(key)) {
            const id = rdoCompanyId(it);
            seen.set(key, {
                key,
                name: id === null ? 'Sem cliente' : (it.company_name || 'Sem cliente'),
                color: rdoCompanyColor(id),
                isNone: id === null,
            });
        }
    });
    const list = Array.from(seen.values()).sort((a, b) => {
        if (a.isNone !== b.isNone) return a.isNone ? 1 : -1;
        return String(a.name).localeCompare(String(b.name), 'pt', { sensitivity: 'base' });
    });
    if (!list.length) { box.innerHTML = ''; return; }
    box.innerHTML = list.map(c => {
        const muted = rdoHiddenCompanies.has(c.key) ? ' muted' : '';
        return `<span class="rdo-legend-item${muted}" onclick="toggleCompanyLegend('${c.key}')" title="Mostrar/ocultar">
            <span class="rdo-legend-dot" style="background:${c.color}"></span>${escapeHtml(c.name)}</span>`;
    }).join('');
}
function toggleCompanyLegend(key) {
    const k = key === 'none' ? 'none' : Number(key);
    if (rdoHiddenCompanies.has(k)) rdoHiddenCompanies.delete(k);
    else rdoHiddenCompanies.add(k);
    renderCompanyLegend();
    renderCalendar();
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

    rdoItems = data.items || [];
    // Remove da seleção de "ocultos" empresas que não existem mais nos itens.
    const presentKeys = new Set(rdoItems.map(rdoLegendKey));
    rdoHiddenCompanies.forEach(k => { if (!presentKeys.has(k)) rdoHiddenCompanies.delete(k); });
    renderCompanyLegend();
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

// ===== VISÃO LISTA ===== (tabela agrupada por empresa)
function renderList() {
    const tb = document.getElementById('rdo-tbody');
    const items = rdoItems;
    if (!items.length) {
        tb.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">Nenhum relatório encontrado.</td></tr>`;
        return;
    }

    let html = '', lastGroup = null;
    items.forEach(it => {
        const groupKey = it.company_id ? ('c' + it.company_id) : 'none';
        if (groupKey !== lastGroup) {
            lastGroup = groupKey;
            const projName = it.company_name
                ? escapeHtml(it.company_name)
                : '<span class="fst-italic text-muted">Sem cliente</span>';
            const grpColor = rdoCompanyColor(rdoCompanyId(it));
            html += `<tr class="table-secondary">
                <td colspan="6" class="fw-semibold">
                    <span class="rdo-company-dot" style="background:${grpColor}"></span> ${projName}
                </td>
            </tr>`;
        }

        const dateBR = rdoDateBR(it.report_date);
        const resumo = escapeHtml((it.title || it.activities || '').substring(0, 80));
        const occ = Number(it.has_occurrence) ? '<i class="bi bi-exclamation-triangle-fill text-danger"></i>' : '<span class="text-muted">—</span>';
        html += `<tr>
            <td>${dateBR}</td>
            <td>${resumo || '<span class="text-muted">—</span>'}</td>
            <td>${statusBadge(it.status)}</td>
            <td class="text-center">${occ}</td>
            <td class="text-center">${Number(it.attachment_count) || 0}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-secondary" title="Visualizar" onclick="viewRdo(${it.id})"><i class="bi bi-eye"></i></button>
            </td>
        </tr>`;
    });
    tb.innerHTML = html;
}

// ===== VISÃO KANBAN ===== (colunas por empresa)
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
            const cardColor = rdoCompanyColor(rdoCompanyId(it));
            return `<div class="rdo-card ${done ? 'done' : ''}" style="border-left-color:${cardColor}" onclick="viewRdo(${it.id})">
                <h6 class="fw-semibold">${resumo}</h6>
                <div>${statusBadge(it.status)} ${occ}</div>
                <div class="rc-meta">
                    <span><i class="bi bi-calendar-event"></i> ${dateBR}</span>
                    ${att}
                </div>
            </div>`;
        }).join('');
        const colColor = rdoCompanyColor(col.company_id ?? null);
        return `<div class="rdo-col" data-company="${col.company_id ?? ''}">
            <div class="rdo-col-head" style="border-top-color:${colColor}">
                <span class="rdo-col-title"><span class="rdo-company-dot" style="background:${colColor}"></span>${escapeHtml(col.company_name)}</span>
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
    return rdoVisibleItems().filter(it => rdoDayKey(it.report_date) === dayStr);
}
function rdoDayContent(dayStr) {
    return rdoEventsForDay(dayStr).map(it => {
        const done = it.status === 'finalizado';
        const color = rdoCompanyColor(rdoCompanyId(it));
        const label = escapeHtml((it.company_name || it.title || it.activities || 'RDO').substring(0, 24));
        const occ = Number(it.has_occurrence) ? '⚠ ' : '';
        const titleAttr = escapeHtml(((it.company_name ? it.company_name + ' — ' : '') + (it.title || it.activities || '')).trim());
        return `<div class="rdo-cal-event ${done ? 'done' : ''}" style="background:${color}1f;border-left:3px solid ${color};color:${color};" onclick="event.stopPropagation();viewRdo(${it.id})" title="${titleAttr}">${occ}${label}</div>`;
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
                <div>${rdoDayContent(dayStr)}</div></td>`;
            cur.setDate(cur.getDate() + 1);
        }
        container.innerHTML = html + '</tr></tbody></table>';
    }
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
