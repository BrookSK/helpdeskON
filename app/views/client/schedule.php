<?php $pageTitle = 'Cronograma - ON Solutions Helpdesk'; $currentPage = 'schedule'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusLabels = [
    'open' => ['Aberto', 'primary'],
    'in_progress' => ['Em andamento', 'warning'],
    'em_revisao_interna' => ['Em Revisão Interna', 'info'],
    'waiting_client' => ['Aguardando', 'secondary'],
    'em_homologacao' => ['Em Homologação', 'info'],
    'aprovado_producao' => ['Aprov. Produção', 'success'],
    'completed' => ['Concluído', 'success'],
    'denied' => ['Negado', 'danger'],
    'archived' => ['Arquivado', 'secondary'],
];
?>

<style>
    .view-tabs .btn { border-radius:20px; }

    /* ===== GANTT (HTML/CSS próprio) ===== */
    .gantt { --row-h:52px; --label-w:260px; border:1px solid #e9edf2; border-radius:14px; overflow:hidden; background:#fff; }
    .gantt-scroll { overflow-x:auto; }
    .gantt-inner { position:relative; min-width:100%; }

    /* Cabeçalho (duas camadas: grupo + unidade) */
    .gantt-head { display:flex; position:sticky; top:0; z-index:5; background:#f8fafc; border-bottom:1px solid #e9edf2; }
    .gantt-head-label { width:var(--label-w); min-width:var(--label-w); flex-shrink:0; position:sticky; left:0; z-index:6;
        background:#f8fafc; display:flex; align-items:center; padding:0 16px; font-size:0.72rem; font-weight:700;
        text-transform:uppercase; letter-spacing:.4px; color:#64748b; border-right:1px solid #e9edf2; }
    .gantt-head-track { position:relative; }
    .gantt-head-groups, .gantt-head-units { display:flex; }
    .gantt-head-groups { border-bottom:1px solid #eef2f6; }
    .gantt-head-group { text-align:center; font-size:0.7rem; font-weight:700; color:#475569; padding:5px 0;
        border-right:1px solid #eef2f6; text-transform:capitalize; white-space:nowrap; }
    .gantt-head-unit { text-align:center; font-size:0.68rem; color:#94a3b8; padding:5px 0; border-right:1px solid #f1f5f9; white-space:nowrap; }
    .gantt-head-unit.is-weekend { background:#fbfcfe; color:#cbd5e1; }
    .gantt-head-unit.is-today { color:#0f766e; font-weight:800; }

    /* Corpo */
    .gantt-body { position:relative; }
    .gantt-row { display:flex; height:var(--row-h); }
    .gantt-row:nth-child(even) { background:#fcfdfe; }
    .gantt-row:hover { background:#f2fbfa; }
    .gantt-row:not(:last-child) { border-bottom:1px solid #f1f5f9; }
    .gantt-row-label { width:var(--label-w); min-width:var(--label-w); flex-shrink:0; position:sticky; left:0; z-index:2;
        background:inherit; padding:8px 16px; display:flex; flex-direction:column; justify-content:center; gap:4px;
        border-right:1px solid #e9edf2; }
    .gantt-row:nth-child(even) .gantt-row-label { background:#fcfdfe; }
    .gantt-row:hover .gantt-row-label { background:#f2fbfa; }
    .gantt-row-title { font-size:0.82rem; font-weight:600; color:#1e293b; line-height:1.2;
        overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; }
    .gantt-row-title .g-id { color:#94a3b8; font-weight:700; margin-right:5px; }
    .gantt-row-meta { display:flex; align-items:center; gap:6px; }
    .g-badge { font-size:0.62rem; font-weight:700; padding:2px 8px; border-radius:20px; color:#fff; white-space:nowrap; }
    .g-dates { font-size:0.66rem; color:#94a3b8; }

    .gantt-row-track { position:relative; flex:1; }
    .gantt-grid-line { position:absolute; top:0; bottom:0; width:1px; background:#f1f5f9; }
    .gantt-grid-line.is-weekend-bg { background:#fafbfd; width:var(--col-w,64px); }
    .gantt-today-line { position:absolute; top:0; bottom:0; width:2px; background:#00BFA6; z-index:3; }
    .gantt-today-line::before { content:''; position:absolute; top:-1px; left:-4px; width:10px; height:10px; border-radius:50%; background:#00BFA6; }

    .gantt-bar { position:absolute; top:50%; transform:translateY(-50%); height:26px; border-radius:8px;
        display:flex; align-items:center; padding:0 10px; z-index:2; box-shadow:0 2px 6px rgba(15,23,42,.16);
        cursor:pointer; transition:filter .15s, box-shadow .15s; }
    .gantt-bar-out { cursor:pointer; }
    .gantt-row { cursor:pointer; }
    .gantt-bar:hover { filter:brightness(1.06); box-shadow:0 4px 12px rgba(15,23,42,.24); }
    .gantt-bar-label { font-size:0.72rem; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .gantt-bar-progress { position:absolute; left:0; top:0; bottom:0; background:rgba(255,255,255,.28); border-radius:8px 0 0 8px; }
    /* rótulo por fora quando a barra é muito curta */
    .gantt-bar-out { position:absolute; top:50%; transform:translateY(-50%); font-size:0.72rem; font-weight:600;
        color:#475569; white-space:nowrap; z-index:2; }
    .gantt-milestone { width:20px !important; height:20px !important; border-radius:50%; padding:0;
        transform:translateY(-50%) rotate(45deg); border:2px solid #fff; }

    .g-legend { display:flex; flex-wrap:wrap; gap:14px; }
    .g-legend span { display:inline-flex; align-items:center; gap:6px; font-size:0.72rem; color:#64748b; }
    .g-legend i { width:12px; height:12px; border-radius:4px; display:inline-block; }

    /* ===== CALENDÁRIO ===== */
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
    .cal-weekday { text-align:center; font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; padding:4px 0; }
    .cal-cell { min-height:104px; border:1px solid #eef2f6; border-radius:10px; padding:6px; background:#fff; transition:border-color .15s; }
    .cal-cell:hover { border-color:#d7e3ea; }
    .cal-cell.other-month { background:#f8fafc; }
    .cal-cell.today { border-color:var(--primary); box-shadow:0 0 0 2px rgba(0,191,166,.18); }
    .cal-daynum { font-size:0.74rem; color:#94a3b8; font-weight:700; }
    .cal-cell.today .cal-daynum { color:var(--primary); }
    .cal-event { font-size:0.66rem; padding:3px 7px; border-radius:6px; margin-top:3px; color:#fff; font-weight:600;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; cursor:pointer; }
    .cal-event:hover { filter:brightness(1.08); }
    @media (max-width: 575px) {
        .gantt { --label-w:150px; }
        .cal-cell { min-height:72px; }
    }
</style>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Cronograma</h5>
            <small class="text-muted">Acompanhe quando cada demanda começa e termina</small>
        </div>
        <div class="view-tabs btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-primary active" id="btn-view-gantt"><i class="bi bi-bar-chart-steps"></i> Gantt</button>
            <button type="button" class="btn btn-outline-primary" id="btn-view-calendar"><i class="bi bi-calendar3"></i> Calendário</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-6 col-md-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos Status</option>
                        <?php foreach ($statusLabels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $info[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="hide_completed" value="1" id="hideCompleted" <?= !empty($filters['hide_completed']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hideCompleted">Ocultar concluídos</label>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('planning/clientSchedule') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
                <div class="col-12 col-md-auto ms-md-auto" id="gantt-mode-wrap">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Escala do Gantt">
                        <button type="button" class="btn btn-outline-secondary gantt-mode" data-mode="day">Dia</button>
                        <button type="button" class="btn btn-outline-secondary gantt-mode active" data-mode="week">Semana</button>
                        <button type="button" class="btn btn-outline-secondary gantt-mode" data-mode="month">Mês</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Gantt -->
    <div class="card mb-3" id="view-gantt">
        <div class="card-body">
            <div id="empty-gantt" class="text-center text-muted py-5" style="display:none;">
                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                Nenhuma demanda com cronograma definido ainda.
            </div>
            <div id="gantt-content">
                <div class="gantt">
                    <div class="gantt-scroll">
                        <div class="gantt-inner" id="gantt-inner"></div>
                    </div>
                </div>
                <div class="g-legend mt-3" id="gantt-legend"></div>
            </div>
        </div>
    </div>

    <!-- Calendário -->
    <div class="card mb-3" id="view-calendar" style="display:none;">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cal-prev"><i class="bi bi-chevron-left"></i></button>
                <h6 class="mb-0 fw-bold" id="cal-title"></h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cal-next"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="cal-grid" id="cal-weekdays"></div>
            <div class="cal-grid mt-1" id="cal-days"></div>
        </div>
    </div>
</div>

<!-- MODAL DETALHE DA DEMANDA (cliente) -->
<div class="modal fade" id="scheduleDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" id="sd-id">#</span>
                    <h6 class="modal-title mb-0 fw-bold" id="sd-title">Demanda</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="sd-loading" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <span class="ms-2">Carregando...</span>
                </div>
                <div id="sd-error" class="alert alert-warning py-2 small" style="display:none;"></div>
                <div id="sd-content" style="display:none;">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="g-badge" id="sd-status"></span>
                        <span class="badge rounded-pill" id="sd-priority"></span>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted" style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.4px;">Início</div>
                                <div class="fw-semibold" id="sd-start">-</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-2 h-100">
                                <div class="text-muted" style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.4px;">Previsão de término</div>
                                <div class="fw-semibold" id="sd-end">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-1 text-muted" style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.4px;">Descrição</div>
                    <div class="border rounded-3 p-3" id="sd-description" style="font-size:0.88rem;max-height:320px;overflow-y:auto;"></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const STATUS_LABELS = <?= json_encode(array_map(fn($v) => $v[0], $statusLabels)) ?>;
const STATUS_COLORS = {
    open:'#2563eb', in_progress:'#ea580c', em_revisao_interna:'#6366f1',
    waiting_client:'#db2777', em_homologacao:'#0891b2', aprovado_producao:'#16a34a',
    completed:'#15803d', denied:'#dc2626', archived:'#64748b'
};
const MS_DAY = 86400000;

let scheduleEvents = [];
let ganttMode = 'week';
let calDate = new Date();

function fetchSchedule() {
    const params = new URLSearchParams(window.location.search);
    params.delete('url');
    return fetch(BASE + 'planning/clientScheduleData?' + params.toString())
        .then(r => r.json())
        .then(events => { scheduleEvents = Array.isArray(events) ? events : []; })
        .catch(() => { scheduleEvents = []; });
}

function parseDate(d) {
    if (!d) return null;
    const p = String(d).slice(0, 10).split('-');
    if (p.length < 3) return null;
    const y = parseInt(p[0], 10), m = parseInt(p[1], 10), day = parseInt(p[2], 10);
    if (!y || !m || !day) return null;
    const dt = new Date(y, m - 1, day); dt.setHours(0, 0, 0, 0);
    return dt;
}
function formatBr(dt) {
    if (!dt) return '-';
    return String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0') + '/' + dt.getFullYear();
}
function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
function startOfToday() { const t = new Date(); t.setHours(0, 0, 0, 0); return t; }

function normalizedEvents() {
    return scheduleEvents.map(e => {
        const start = parseDate(e.start_date);
        let end = parseDate(e.end_date) || start;
        if (start && end && end < start) end = start;
        return { id: e.id, title: e.title || '', status: e.status, priority: e.priority, start, end };
    }).filter(e => e.start);
}

const MONTHS_LONG = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const MONTHS_SHORT = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];

// ===== GANTT =====
function renderGantt() {
    const empty = document.getElementById('empty-gantt');
    const content = document.getElementById('gantt-content');
    const events = normalizedEvents();

    if (!events.length) { empty.style.display = 'block'; content.style.display = 'none'; return; }
    empty.style.display = 'none'; content.style.display = '';

    let min = events[0].start, max = events[0].end;
    events.forEach(e => { if (e.start < min) min = e.start; if (e.end > max) max = e.end; });

    // Configuração por modo: colWidth por unidade + montagem das colunas
    let colW, gridStart, gridEnd, units = [], groups = [];

    if (ganttMode === 'day') {
        colW = 46;
        gridStart = new Date(min); gridStart.setDate(gridStart.getDate() - 2);
        gridEnd = new Date(max); gridEnd.setDate(gridEnd.getDate() + 2);
        for (let d = new Date(gridStart); d <= gridEnd; d.setDate(d.getDate() + 1)) {
            const cur = new Date(d);
            units.push({ date: cur, label: String(cur.getDate()).padStart(2,'0'),
                weekend: cur.getDay() === 0 || cur.getDay() === 6 });
        }
        groups = groupByMonth(units, colW);
    } else if (ganttMode === 'month') {
        colW = 104;
        gridStart = new Date(min.getFullYear(), min.getMonth(), 1);
        gridEnd = new Date(max.getFullYear(), max.getMonth() + 1, 0);
        for (let d = new Date(gridStart); d <= gridEnd; d.setMonth(d.getMonth() + 1)) {
            const cur = new Date(d);
            units.push({ date: cur, label: MONTHS_SHORT[cur.getMonth()], weekend: false });
        }
        groups = groupByYear(units, colW);
    } else { // week
        colW = 58;
        gridStart = new Date(min); gridStart.setDate(gridStart.getDate() - min.getDay() - 7);
        gridEnd = new Date(max); gridEnd.setDate(gridEnd.getDate() + (6 - max.getDay()) + 7);
        for (let d = new Date(gridStart); d <= gridEnd; d.setDate(d.getDate() + 1)) {
            const cur = new Date(d);
            units.push({ date: cur, label: String(cur.getDate()).padStart(2,'0'),
                weekend: cur.getDay() === 0 || cur.getDay() === 6 });
        }
        groups = groupByMonth(units, colW);
    }

    const trackW = units.length * colW;
    const today = startOfToday();

    function xForDate(date) {
        if (ganttMode === 'month') {
            const monthsFromStart = (date.getFullYear() - gridStart.getFullYear()) * 12 + (date.getMonth() - gridStart.getMonth());
            const dim = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
            return (monthsFromStart + (date.getDate() - 1) / dim) * colW;
        }
        return ((date - gridStart) / MS_DAY) * colW;
    }

    const idx = today >= gridStart && today <= gridEnd;
    const todayX = xForDate(today);

    // Cabeçalho
    let headUnits = '';
    units.forEach(u => {
        const cls = (ganttMode !== 'month' && u.weekend ? 'is-weekend ' : '') +
            (sameDay(u.date, today) ? 'is-today' : '');
        headUnits += `<div class="gantt-head-unit ${cls}" style="width:${colW}px;min-width:${colW}px">${u.label}</div>`;
    });
    let headGroups = groups.map(g => `<div class="gantt-head-group" style="width:${g.width}px;min-width:${g.width}px">${g.label}</div>`).join('');

    // Linhas de grade (verticais) reutilizadas em cada track via background — geramos uma vez
    let gridLines = '';
    if (ganttMode !== 'month') {
        units.forEach((u, i) => {
            if (u.weekend) gridLines += `<div class="gantt-grid-line is-weekend-bg" style="left:${i*colW}px;--col-w:${colW}px"></div>`;
        });
    }
    for (let i = 1; i < units.length; i++) gridLines += `<div class="gantt-grid-line" style="left:${i*colW}px"></div>`;
    const todayLine = idx ? `<div class="gantt-today-line" style="left:${todayX}px" title="Hoje"></div>` : '';

    // Corpo
    let rows = '';
    events.forEach(e => {
        const color = STATUS_COLORS[e.status] || '#64748b';
        const statusLabel = STATUS_LABELS[e.status] || e.status || '';
        const left = Math.max(0, xForDate(e.start));
        const endPlus = new Date(e.end); endPlus.setDate(endPlus.getDate() + 1);
        const rawW = xForDate(endPlus) - left;
        const isMilestone = e.start.getTime() === e.end.getTime();
        const minW = ganttMode === 'day' ? colW * 0.7 : 16;
        const w = Math.max(minW, rawW);
        const tip = `#${e.id} ${e.title} — ${statusLabel} — ${formatBr(e.start)} a ${formatBr(e.end)}`;
        const durationDays = Math.round((e.end - e.start) / MS_DAY) + 1;

        let barInner;
        if (isMilestone) {
            barInner = `<div class="gantt-bar gantt-milestone" style="left:${left}px;background:${color}" title="${escapeHtml(tip)}"></div>
                        <div class="gantt-bar-out" style="left:${left + 20}px">${escapeHtml(e.title)}</div>`;
        } else if (w < 70) {
            // barra curta: rótulo do lado de fora
            barInner = `<div class="gantt-bar" style="left:${left}px;width:${w}px;background:${color}" title="${escapeHtml(tip)}"></div>
                        <div class="gantt-bar-out" style="left:${left + w + 8}px">${escapeHtml(e.title)}</div>`;
        } else {
            barInner = `<div class="gantt-bar" style="left:${left}px;width:${w}px;background:${color}" title="${escapeHtml(tip)}">
                            <span class="gantt-bar-label">${escapeHtml(e.title)}</span>
                        </div>`;
        }

        rows += `<div class="gantt-row" data-card-id="${e.id}">
            <div class="gantt-row-label">
                <div class="gantt-row-title"><span class="g-id">#${e.id}</span>${escapeHtml(e.title)}</div>
                <div class="gantt-row-meta">
                    <span class="g-badge" style="background:${color}">${statusLabel}</span>
                    <span class="g-dates">${formatBr(e.start)}${durationDays > 1 ? ' – ' + formatBr(e.end) : ''}</span>
                </div>
            </div>
            <div class="gantt-row-track">${gridLines}${todayLine}${barInner}</div>
        </div>`;
    });

    document.getElementById('gantt-inner').innerHTML = `
        <div class="gantt-head">
            <div class="gantt-head-label">Demanda</div>
            <div class="gantt-head-track" style="width:${trackW}px">
                <div class="gantt-head-groups">${headGroups}</div>
                <div class="gantt-head-units">${headUnits}</div>
            </div>
        </div>
        <div class="gantt-body" style="--track-w:${trackW}px">${rows}</div>`;

    // Ajusta a largura das tracks do corpo para casar com o cabeçalho
    document.querySelectorAll('.gantt-row-track').forEach(t => { t.style.width = trackW + 'px'; t.style.flex = 'none'; });

    renderLegend(events);
}

function groupByMonth(units, colW) {
    const groups = []; let cur = null;
    units.forEach(u => {
        const key = u.date.getFullYear() + '-' + u.date.getMonth();
        if (!cur || cur.key !== key) {
            cur = { key, label: MONTHS_LONG[u.date.getMonth()] + ' ' + u.date.getFullYear(), count: 0 };
            groups.push(cur);
        }
        cur.count++;
    });
    groups.forEach(g => g.width = g.count * colW);
    return groups;
}
function groupByYear(units, colW) {
    const groups = []; let cur = null;
    units.forEach(u => {
        const key = u.date.getFullYear();
        if (!cur || cur.key !== key) { cur = { key, label: String(key), count: 0 }; groups.push(cur); }
        cur.count++;
    });
    groups.forEach(g => g.width = g.count * colW);
    return groups;
}
function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

function renderLegend(events) {
    const used = [...new Set(events.map(e => e.status))];
    document.getElementById('gantt-legend').innerHTML = used.map(s =>
        `<span><i style="background:${STATUS_COLORS[s] || '#64748b'}"></i>${STATUS_LABELS[s] || s}</span>`
    ).join('');
}

// ===== CALENDÁRIO =====
const WEEKDAYS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
function renderCalendar() {
    document.getElementById('cal-weekdays').innerHTML = WEEKDAYS.map(d => `<div class="cal-weekday">${d}</div>`).join('');
    document.getElementById('cal-title').textContent = MONTHS_LONG[calDate.getMonth()] + ' ' + calDate.getFullYear();

    const year = calDate.getFullYear(), month = calDate.getMonth();
    const startDay = new Date(year, month, 1).getDay();
    const today = startOfToday();
    const events = normalizedEvents();

    const cells = [];
    let day = 1 - startDay;
    for (let i = 0; i < 42; i++, day++) {
        const cellDate = new Date(year, month, day); cellDate.setHours(0, 0, 0, 0);
        const isOther = cellDate.getMonth() !== month;
        const isToday = cellDate.getTime() === today.getTime();
        const evHtml = events.filter(e => cellDate >= e.start && cellDate <= e.end).map(e => {
            const color = STATUS_COLORS[e.status] || '#64748b';
            return `<div class="cal-event" data-card-id="${e.id}" style="background:${color}" title="#${e.id} ${escapeHtml(e.title)}">#${e.id} ${escapeHtml(e.title)}</div>`;
        }).join('');
        cells.push(`<div class="cal-cell ${isOther ? 'other-month' : ''} ${isToday ? 'today' : ''}">
            <div class="cal-daynum">${cellDate.getDate()}</div>${evHtml}</div>`);
    }
    document.getElementById('cal-days').innerHTML = cells.join('');
}

// ===== TROCA DE VIEW =====
function showView(which) {
    const gantt = document.getElementById('view-gantt');
    const cal = document.getElementById('view-calendar');
    const modeWrap = document.getElementById('gantt-mode-wrap');
    const bg = document.getElementById('btn-view-gantt');
    const bc = document.getElementById('btn-view-calendar');
    if (which === 'gantt') {
        gantt.style.display = ''; cal.style.display = 'none'; modeWrap.style.display = '';
        bg.classList.add('btn-primary','active'); bg.classList.remove('btn-outline-primary');
        bc.classList.add('btn-outline-primary'); bc.classList.remove('btn-primary','active');
        renderGantt();
    } else {
        gantt.style.display = 'none'; cal.style.display = ''; modeWrap.style.display = 'none';
        bc.classList.add('btn-primary','active'); bc.classList.remove('btn-outline-primary');
        bg.classList.add('btn-outline-primary'); bg.classList.remove('btn-primary','active');
        renderCalendar();
    }
}

document.getElementById('btn-view-gantt').addEventListener('click', () => showView('gantt'));
document.getElementById('btn-view-calendar').addEventListener('click', () => showView('calendar'));
document.querySelectorAll('.gantt-mode').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.gantt-mode').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        ganttMode = this.dataset.mode;
        renderGantt();
    });
});
document.getElementById('cal-prev').addEventListener('click', () => { calDate.setMonth(calDate.getMonth() - 1); renderCalendar(); });
document.getElementById('cal-next').addEventListener('click', () => { calDate.setMonth(calDate.getMonth() + 1); renderCalendar(); });

// ===== DETALHE DA DEMANDA =====
const PRIORITY_LABELS = { low:'Baixa', medium:'Média', high:'Alta', urgent:'Urgente' };
const PRIORITY_CLASSES = { low:'bg-success', medium:'bg-warning text-dark', high:'bg-danger', urgent:'bg-dark' };
let detailModal = null;

function openDetail(cardId) {
    if (!cardId) return;
    if (!detailModal) detailModal = new bootstrap.Modal(document.getElementById('scheduleDetailModal'));

    const loading = document.getElementById('sd-loading');
    const content = document.getElementById('sd-content');
    const errorBox = document.getElementById('sd-error');
    loading.style.display = ''; content.style.display = 'none'; errorBox.style.display = 'none';
    document.getElementById('sd-id').textContent = '#' + cardId;
    document.getElementById('sd-title').textContent = 'Demanda';
    detailModal.show();

    fetch(BASE + 'planning/clientCardDetail/' + encodeURIComponent(cardId))
        .then(r => r.json())
        .then(res => {
            loading.style.display = 'none';
            if (!res || res.error || !res.card) {
                errorBox.textContent = (res && res.error) ? res.error : 'Não foi possível carregar a demanda.';
                errorBox.style.display = '';
                return;
            }
            const c = res.card;
            document.getElementById('sd-id').textContent = '#' + c.id;
            document.getElementById('sd-title').textContent = c.title || 'Demanda';

            const sBadge = document.getElementById('sd-status');
            sBadge.textContent = STATUS_LABELS[c.status] || c.status || '';
            sBadge.style.background = STATUS_COLORS[c.status] || '#64748b';

            const pBadge = document.getElementById('sd-priority');
            pBadge.textContent = PRIORITY_LABELS[c.priority] || c.priority || '';
            pBadge.className = 'badge rounded-pill ' + (PRIORITY_CLASSES[c.priority] || 'bg-secondary');

            document.getElementById('sd-start').textContent = formatBr(parseDate(c.client_start_date));
            document.getElementById('sd-end').textContent = c.client_end_date ? formatBr(parseDate(c.client_end_date)) : 'A definir';

            const desc = document.getElementById('sd-description');
            const raw = (c.description || '').trim();
            desc.innerHTML = raw ? sanitizeHtml(raw) : '<span class="text-muted">Sem descrição.</span>';

            content.style.display = '';
        })
        .catch(() => {
            loading.style.display = 'none';
            errorBox.textContent = 'Erro de conexão ao carregar a demanda.';
            errorBox.style.display = '';
        });
}

// Sanitização leve: remove scripts/estilos/eventos inline da descrição (que
// pode conter HTML do editor interno) antes de injetar no modal.
function sanitizeHtml(html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    tpl.content.querySelectorAll('script,style,iframe,object,embed,link').forEach(el => el.remove());
    tpl.content.querySelectorAll('*').forEach(el => {
        [...el.attributes].forEach(attr => {
            const n = attr.name.toLowerCase();
            if (n.startsWith('on') || (n === 'href' && attr.value.trim().toLowerCase().startsWith('javascript:'))) {
                el.removeAttribute(attr.name);
            }
        });
    });
    return tpl.innerHTML;
}

// Clique no Gantt (linha inteira) e no calendário (evento), via delegação.
document.getElementById('gantt-inner').addEventListener('click', function(ev) {
    const row = ev.target.closest('.gantt-row');
    if (row && row.dataset.cardId) openDetail(row.dataset.cardId);
});
document.getElementById('cal-days').addEventListener('click', function(ev) {
    const item = ev.target.closest('.cal-event');
    if (item && item.dataset.cardId) openDetail(item.dataset.cardId);
});

fetchSchedule().then(() => { showView('gantt'); });
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
