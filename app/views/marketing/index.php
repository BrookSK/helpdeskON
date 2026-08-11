<?php $pageTitle = 'Marketing - ON Solutions Helpdesk'; $currentPage = 'marketing'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusMeta = [
    'ideia' => ['Ideia', '#607d8b'],
    'em_producao' => ['Em produção', '#e65100'],
    'aguardando_aprovacao' => ['Aguardando aprovação', '#7b1fa2'],
    'aprovado' => ['Aprovado', '#2e7d32'],
    'agendado' => ['Agendado', '#1565c0'],
    'publicado' => ['Publicado', '#00897b'],
    'rejeitado' => ['Rejeitado', '#d32f2f'],
];
$socialNetworks = ['Instagram', 'Facebook', 'LinkedIn', 'TikTok', 'YouTube', 'X (Twitter)', 'WhatsApp', 'Blog', 'Outro'];
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-megaphone"></i> Marketing</h5>
            <small class="text-muted">Calendário editorial e produção de conteúdo</small>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isAdmin): ?>
            <button class="btn btn-outline-secondary btn-sm" onclick="openHolidaysModal()" title="Buscar datas comemorativas com IA"><i class="bi bi-stars"></i> Datas com IA</button>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" onclick="openItemModal()"><i class="bi bi-plus-lg"></i> Nova demanda</button>
        </div>
    </div>

    <!-- Abas principais -->
    <ul class="nav nav-pills mkt-tabs mb-3" id="mkt-tabs">
        <li class="nav-item"><button class="nav-link active" data-tab="calendario" onclick="switchMktTab('calendario')"><i class="bi bi-calendar3"></i> Calendário</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="pendencias" onclick="switchMktTab('pendencias')"><i class="bi bi-list-check"></i> Pendências</button></li>
        <?php if ($isAdmin): ?>
        <li class="nav-item"><button class="nav-link" data-tab="aprovacoes" onclick="switchMktTab('aprovacoes')"><i class="bi bi-check2-circle"></i> Aprovações <span class="badge bg-danger ms-1" id="approval-count" style="display:none;">0</span></button></li>
        <?php endif; ?>
    </ul>

    <!-- ===== CALENDÁRIO ===== -->
    <div id="tab-calendario" class="mkt-tab-panel">
        <div class="card">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="cal-prev"><i class="bi bi-chevron-left"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" id="cal-today">Hoje</button>
                        <button class="btn btn-sm btn-outline-secondary" id="cal-next"><i class="bi bi-chevron-right"></i></button>
                        <h6 class="mb-0 ms-2 fw-semibold" id="cal-title" style="min-width:170px;"></h6>
                    </div>
                    <div class="btn-group btn-group-sm" id="cal-mode-toggle">
                        <button type="button" class="btn btn-outline-primary active" data-mode="month">Mês</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="week">Semana</button>
                    </div>
                </div>
                <div id="calendar-container"></div>
            </div>
        </div>
    </div>

    <!-- ===== PENDÊNCIAS ===== -->
    <div id="tab-pendencias" class="mkt-tab-panel" style="display:none;">
        <div id="pendencias-list">
            <div class="text-muted small py-4 text-center">Carregando...</div>
        </div>
    </div>

    <!-- ===== APROVAÇÕES ===== -->
    <?php if ($isAdmin): ?>
    <div id="tab-aprovacoes" class="mkt-tab-panel" style="display:none;">
        <div id="aprovacoes-list">
            <div class="text-muted small py-4 text-center">Carregando...</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/views/marketing/_modals.php'; ?>

<style>
.mkt-tabs .nav-link { color: #555; font-size: 0.85rem; border-radius: 8px; }
.mkt-tabs .nav-link.active { background: var(--primary); color: #fff; }
.mkt-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.mkt-status-group { margin-bottom: 22px; }
.mkt-status-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #eef0f2; }
.mkt-status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.mkt-status-title { font-size: 0.82rem; font-weight: 600; color: #445; text-transform: uppercase; letter-spacing: .4px; }
.mkt-status-count { font-size: 0.68rem; font-weight: 600; background: #eef0f2; color: #667; border-radius: 20px; padding: 1px 8px; }
.mkt-btn-warning { background: #f5a623; border: 1px solid #f5a623; color: #fff; }
.mkt-btn-warning:hover, .mkt-btn-warning:focus { background: #e0951a; border-color: #e0951a; color: #fff; }
.mkt-btn-danger { background: #d32f2f; border: 1px solid #d32f2f; color: #fff; }
.mkt-btn-danger:hover, .mkt-btn-danger:focus { background: #b71c1c; border-color: #b71c1c; color: #fff; }
.mkt-card { background: #fff; border: 1px solid #eef0f2; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: box-shadow .15s, transform .15s; }
.mkt-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); transform: translateY(-1px); }
.mkt-card h6 { font-size: 0.9rem; margin-bottom: 6px; }
.mkt-badge { font-size: 0.66rem; font-weight: 600; padding: 2px 9px; border-radius: 20px; color: #fff; display: inline-block; }
.mkt-meta { font-size: 0.72rem; color: #888; margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px 10px; }

/* Calendário */
.cal-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
.cal-grid th { background: #f8f9fa; text-align: center; font-weight: 600; font-size: 0.72rem; padding: 6px 4px; border: 1px solid #eef0f2; color: #667; text-transform: uppercase; letter-spacing: .3px; }
.cal-grid td { border: 1px solid #eef0f2; vertical-align: top; height: 110px; padding: 4px; position: relative; }
.cal-grid td.other-month { background: #fafbfc; }
.cal-grid td.today { background: #f0faf8; }
.cal-daynum { font-size: 0.72rem; font-weight: 600; color: #556; display: inline-block; margin-bottom: 2px; }
.cal-cell-add { position: absolute; top: 3px; right: 4px; opacity: 0; font-size: 0.7rem; color: var(--primary); cursor: pointer; transition: opacity .15s; }
.cal-grid td:hover .cal-cell-add { opacity: 1; }
.cal-event { font-size: 0.68rem; padding: 2px 6px; border-radius: 5px; margin-bottom: 2px; color: #fff; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-holiday { font-size: 0.66rem; padding: 1px 6px; border-radius: 5px; margin-bottom: 2px; background: #fff3e0; color: #e65100; border: 1px dashed #ffb74d; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-holiday:hover { background: #ffe0b2; }
.cal-week td { height: 300px; }
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const CURRENT_USER_ID = <?= intval($user['id']) ?>;
const STATUS_META = <?= json_encode($statusMeta) ?>;
let calDate = new Date();
let calMode = 'month';
let calData = { events: [], holidays: [] };

// ===== Tabs =====
function switchMktTab(tab) {
    document.querySelectorAll('#mkt-tabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.mkt-tab-panel').forEach(p => p.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = '';
    if (tab === 'calendario') loadCalendar();
    else if (tab === 'pendencias') loadItems('pendencias');
    else if (tab === 'aprovacoes') loadItems('aprovacoes');
}

// ===== Calendário =====
document.getElementById('cal-prev').addEventListener('click', () => navCal(-1));
document.getElementById('cal-next').addEventListener('click', () => navCal(1));
document.getElementById('cal-today').addEventListener('click', () => { calDate = new Date(); loadCalendar(); });
document.querySelectorAll('#cal-mode-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#cal-mode-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        calMode = this.dataset.mode;
        loadCalendar();
    });
});

function navCal(dir) {
    if (calMode === 'month') calDate.setMonth(calDate.getMonth() + dir);
    else calDate.setDate(calDate.getDate() + 7 * dir);
    loadCalendar();
}

function fmtDate(d) { return d.toISOString().slice(0, 10); }

function loadCalendar() {
    let start, end;
    if (calMode === 'month') {
        start = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        end = new Date(calDate.getFullYear(), calDate.getMonth() + 1, 0);
    } else {
        start = new Date(calDate);
        start.setDate(start.getDate() - start.getDay());
        end = new Date(start);
        end.setDate(end.getDate() + 6);
    }
    // Amplia a janela para pegar dias visíveis de outros meses
    const qStart = new Date(start); qStart.setDate(qStart.getDate() - 7);
    const qEnd = new Date(end); qEnd.setDate(qEnd.getDate() + 7);

    fetch(`${BASE}marketing/calendar?start=${fmtDate(qStart)}&end=${fmtDate(qEnd)}`)
        .then(r => r.json())
        .then(data => { calData = data; renderCalendar(start, end); });
}

const MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const WEEKDAYS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

function eventsForDay(dayStr) {
    return calData.events.filter(e => (e.scheduled_at || '').slice(0, 10) === dayStr);
}
function holidaysForDay(dayStr) {
    return calData.holidays.filter(h => (h.holiday_date || '').slice(0, 10) === dayStr);
}

function renderCalendar(start, end) {
    const container = document.getElementById('calendar-container');
    const title = document.getElementById('cal-title');

    if (calMode === 'month') {
        title.textContent = MONTHS[calDate.getMonth()] + ' ' + calDate.getFullYear();
        const first = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        const gridStart = new Date(first);
        gridStart.setDate(gridStart.getDate() - first.getDay());
        const todayStr = fmtDate(new Date());

        let html = '<table class="cal-grid"><thead><tr>' + WEEKDAYS.map(d => `<th>${d}</th>`).join('') + '</tr></thead><tbody>';
        let cur = new Date(gridStart);
        for (let w = 0; w < 6; w++) {
            html += '<tr>';
            for (let d = 0; d < 7; d++) {
                const dayStr = fmtDate(cur);
                const isOther = cur.getMonth() !== calDate.getMonth();
                const isToday = dayStr === todayStr;
                html += `<td class="${isOther ? 'other-month' : ''} ${isToday ? 'today' : ''}">
                    <span class="cal-daynum">${cur.getDate()}</span>
                    <i class="bi bi-plus-circle-fill cal-cell-add" onclick="openItemModal(null,'${dayStr}')" title="Criar demanda"></i>
                    <div class="cal-cell-body">${renderDayContent(dayStr)}</div>
                </td>`;
                cur.setDate(cur.getDate() + 1);
            }
            html += '</tr>';
            if (cur > end && cur.getDay() === 0) break;
        }
        html += '</tbody></table>';
        container.innerHTML = html;
    } else {
        const ws = new Date(calDate); ws.setDate(ws.getDate() - ws.getDay());
        const we = new Date(ws); we.setDate(we.getDate() + 6);
        title.textContent = `${ws.getDate()} ${MONTHS[ws.getMonth()].slice(0,3)} - ${we.getDate()} ${MONTHS[we.getMonth()].slice(0,3)}`;
        const todayStr = fmtDate(new Date());
        let html = '<table class="cal-grid cal-week"><thead><tr>';
        let cur = new Date(ws);
        for (let d = 0; d < 7; d++) { html += `<th>${WEEKDAYS[d]} ${cur.getDate()}</th>`; cur.setDate(cur.getDate() + 1); }
        html += '</tr></thead><tbody><tr>';
        cur = new Date(ws);
        for (let d = 0; d < 7; d++) {
            const dayStr = fmtDate(cur);
            const isToday = dayStr === todayStr;
            html += `<td class="${isToday ? 'today' : ''}">
                <i class="bi bi-plus-circle-fill cal-cell-add" onclick="openItemModal(null,'${dayStr}')" title="Criar demanda"></i>
                <div class="cal-cell-body">${renderDayContent(dayStr)}</div>
            </td>`;
            cur.setDate(cur.getDate() + 1);
        }
        html += '</tr></tbody></table>';
        container.innerHTML = html;
    }
}

function renderDayContent(dayStr) {
    let out = '';
    holidaysForDay(dayStr).forEach(h => {
        out += `<div class="cal-holiday" onclick="event.stopPropagation();openHolidayCreate(${h.id}, '${escapeAttr(h.title)}', '${dayStr}')" title="${escapeAttr(h.title)} — clique para criar conteúdo"><i class="bi bi-star-fill"></i> ${escapeHtml(h.title)}</div>`;
    });
    eventsForDay(dayStr).forEach(e => {
        const color = (STATUS_META[e.status] || ['', '#888'])[1];
        out += `<div class="cal-event" style="background:${color}" onclick="event.stopPropagation();openItemModal(${e.id})" title="${escapeAttr(e.title)}">${escapeHtml(e.title)}</div>`;
    });
    return out;
}

// ===== Listas (Pendências / Aprovações) =====
function loadItems(section) {
    const listId = section === 'aprovacoes' ? 'aprovacoes-list' : 'pendencias-list';
    const list = document.getElementById(listId);
    list.innerHTML = '<div class="text-muted small py-4 text-center">Carregando...</div>';
    fetch(`${BASE}marketing/items?section=${section}`)
        .then(r => r.json())
        .then(data => {
            const items = data.items || [];
            if (!items.length) {
                list.innerHTML = '<div class="text-muted small py-4 text-center">Nenhum item.</div>';
                return;
            }
            list.innerHTML = renderGroupedByStatus(items);
        });
}

// Agrupa os itens por status em seções separadas (evita misturar aprovado com aguardando, etc.)
function renderGroupedByStatus(items) {
    // Ordem de exibição dos status
    const order = ['ideia','em_producao','aguardando_aprovacao','aprovado','agendado','publicado','rejeitado'];
    const groups = {};
    items.forEach(it => { (groups[it.status] = groups[it.status] || []).push(it); });

    let html = '';
    order.forEach(status => {
        const list = groups[status];
        if (!list || !list.length) return;
        const meta = STATUS_META[status] || ['', '#888'];
        html += `<div class="mkt-status-group">
            <div class="mkt-status-head">
                <span class="mkt-status-dot" style="background:${meta[1]}"></span>
                <span class="mkt-status-title">${meta[0]}</span>
                <span class="mkt-status-count">${list.length}</span>
            </div>
            <div class="mkt-cards-grid">${list.map(renderCardHtml).join('')}</div>
        </div>`;
    });
    return html;
}

function renderCardHtml(it) {
    const meta = STATUS_META[it.status] || ['', '#888'];
    const when = it.scheduled_at ? new Date(it.scheduled_at.replace(' ', 'T')).toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : 'Sem data';
    return `<div class="mkt-card" onclick="openItemModal(${it.id})">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <h6 class="fw-semibold mb-0">${escapeHtml(it.title)}</h6>
            <span class="mkt-badge" style="background:${meta[1]}">${meta[0]}</span>
        </div>
        <div class="mkt-meta">
            <span><i class="bi bi-calendar-event"></i> ${when}</span>
            ${it.social_network ? `<span><i class="bi bi-share"></i> ${escapeHtml(it.social_network)}</span>` : ''}
            <span><i class="bi bi-person"></i> ${escapeHtml(it.assigned_name || 'Sem responsável')}</span>
        </div>
    </div>`;
}

// ===== Contador de aprovações pendentes =====
function refreshApprovalCount() {
    if (!IS_ADMIN) return;
    fetch(`${BASE}marketing/items?section=aprovacoes`).then(r => r.json()).then(data => {
        const n = (data.items || []).length;
        const badge = document.getElementById('approval-count');
        if (badge) { badge.textContent = n; badge.style.display = n > 0 ? 'inline-block' : 'none'; }
    });
}

// ===== Utils =====
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function escapeAttr(s) { return (s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

document.addEventListener('DOMContentLoaded', () => {
    loadCalendar();
    refreshApprovalCount();
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
