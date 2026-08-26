<?php $pageTitle = 'Agenda - ON Solutions Helpdesk'; $currentPage = 'agenda'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusMeta = [
    'a_agendar'  => ['A agendar', '#607d8b'],
    'agendada'   => ['Agendada', '#1565c0'],
    'confirmada' => ['Confirmada', '#00897b'],
    'realizada'  => ['Realizada', '#2e7d32'],
    'convertida' => ['Convertida', '#6a1b9a'],
    'remarcada'  => ['Remarcada', '#e65100'],
    'cancelada'  => ['Cancelada', '#c62828'],
];
$urgencyMeta = [
    'baixa' => ['Baixa', '#607d8b'], 'media' => ['Média', '#1565c0'],
    'alta' => ['Alta', '#e65100'], 'urgente' => ['Urgente', '#c62828'],
];
$tempMeta = ['frio' => ['Frio', '#1565c0'], 'morno' => ['Morno', '#e65100'], 'quente' => ['Quente', '#c62828']];
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar2-week"></i> Agenda Comercial</h5>
            <small class="text-muted">Reuniões vinculadas aos leads do CRM</small>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group btn-group-sm" id="view-toggle">
                <button type="button" class="btn btn-outline-primary active" data-view="kanban"><i class="bi bi-kanban"></i> Kanban</button>
                <button type="button" class="btn btn-outline-primary" data-view="calendar"><i class="bi bi-calendar3"></i> Calendário</button>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openMeetingModal()"><i class="bi bi-plus-lg"></i> Nova reunião</button>
        </div>
    </div>

    <!-- KANBAN -->
    <div id="kanban-view">
        <div class="agenda-kanban">
            <?php foreach ($statusMeta as $key => $meta): ?>
            <div class="agenda-col" data-status="<?= $key ?>">
                <div class="agenda-col-head" style="border-top:3px solid <?= $meta[1] ?>">
                    <span class="agenda-col-title"><?= $meta[0] ?></span>
                    <span class="agenda-col-count"><?= count($grouped[$key] ?? []) ?></span>
                </div>
                <div class="agenda-col-body" data-status="<?= $key ?>">
                    <?php foreach (($grouped[$key] ?? []) as $m): ?>
                    <?php require APP_PATH . '/views/agenda/_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CALENDÁRIO -->
    <div id="calendar-view" style="display:none;">
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
</div>

<?php require APP_PATH . '/views/agenda/_modal.php'; ?>

<style>
.agenda-kanban { display: flex; gap: 12px; align-items: flex-start; overflow-x: auto; padding-bottom: 8px; }
.agenda-col { flex: 1 1 0; min-width: 250px; background: #f4f6f8; border-radius: 12px; display: flex; flex-direction: column; }
.agenda-col-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; background: #fff; border-radius: 12px 12px 0 0; }
.agenda-col-title { font-size: 0.78rem; font-weight: 700; color: #445; text-transform: uppercase; letter-spacing: .3px; }
.agenda-col-count { font-size: 0.66rem; font-weight: 600; background: #eef0f2; color: #667; border-radius: 20px; padding: 1px 8px; }
.agenda-col-body { padding: 10px; display: flex; flex-direction: column; gap: 10px; min-height: 60px; }
.agenda-card { background: #fff; border: 1px solid #eef0f2; border-radius: 10px; padding: 10px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: box-shadow .15s; }
.agenda-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.agenda-card.dragging { opacity: 0.5; }
.agenda-card-op { border-left: 3px solid #455a64; }
.agenda-col-body.drag-over { background: #e3f2fd; outline: 2px dashed #90caf9; outline-offset: -4px; border-radius: 8px; }
.agenda-col-body.drag-blocked { background: #ffebee; outline: 2px dashed #ef9a9a; outline-offset: -4px; border-radius: 8px; }
.agenda-card h6 { font-size: 0.85rem; margin-bottom: 4px; }
.agenda-card .ac-meta { font-size: 0.7rem; color: #888; display: flex; flex-wrap: wrap; gap: 3px 8px; margin-top: 4px; }
.agenda-badge { font-size: 0.62rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; color: #fff; display: inline-block; }
/* Calendário */
.cal-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
.cal-grid th { background: #f8f9fa; text-align: center; font-weight: 600; font-size: 0.72rem; padding: 6px 4px; border: 1px solid #eef0f2; color: #667; text-transform: uppercase; }
.cal-grid td { border: 1px solid #eef0f2; vertical-align: top; height: 110px; padding: 4px; position: relative; }
.cal-grid td.other-month { background: #fafbfc; }
.cal-grid td.today { background: #eef7ff; }
.cal-daynum { font-size: 0.72rem; font-weight: 600; color: #556; }
.cal-cell-add { position: absolute; top: 3px; right: 4px; opacity: 0; font-size: 0.7rem; color: var(--primary); cursor: pointer; }
.cal-grid td:hover .cal-cell-add { opacity: 1; }
.cal-event { font-size: 0.68rem; padding: 2px 6px; border-radius: 5px; margin-bottom: 2px; color: #fff; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-week td { height: 300px; }
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
const STATUS_META = <?= json_encode($statusMeta) ?>;
let calDate = new Date();
let calMode = 'month';
let calEvents = [];

// ===== Alternância de visão =====
document.querySelectorAll('#view-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#view-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const view = this.dataset.view;
        document.getElementById('kanban-view').style.display = view === 'kanban' ? '' : 'none';
        document.getElementById('calendar-view').style.display = view === 'calendar' ? '' : 'none';
        if (view === 'calendar') loadCalendar();
    });
});

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
        start = new Date(calDate); start.setDate(start.getDate() - start.getDay());
        end = new Date(start); end.setDate(end.getDate() + 6);
    }
    const qs = new Date(start); qs.setDate(qs.getDate() - 7);
    const qe = new Date(end); qe.setDate(qe.getDate() + 7);
    fetch(`${BASE}agenda/calendar?start=${fmtDate(qs)}&end=${fmtDate(qe)}`)
        .then(r => r.json()).then(d => { calEvents = d.events || []; renderCalendar(start, end); });
}

const MONTHS = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const WEEKDAYS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
function eventsForDay(dayStr) { return calEvents.filter(e => (e.meeting_at || '').slice(0, 10) === dayStr); }

function renderCalendar(start, end) {
    const container = document.getElementById('calendar-container');
    const title = document.getElementById('cal-title');
    const todayStr = fmtDate(new Date());
    if (calMode === 'month') {
        title.textContent = MONTHS[calDate.getMonth()] + ' ' + calDate.getFullYear();
        const first = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        const gridStart = new Date(first); gridStart.setDate(gridStart.getDate() - first.getDay());
        let html = '<table class="cal-grid"><thead><tr>' + WEEKDAYS.map(d => `<th>${d}</th>`).join('') + '</tr></thead><tbody>';
        let cur = new Date(gridStart);
        for (let w = 0; w < 6; w++) {
            html += '<tr>';
            for (let d = 0; d < 7; d++) {
                const dayStr = fmtDate(cur);
                const isOther = cur.getMonth() !== calDate.getMonth();
                html += `<td class="${isOther ? 'other-month' : ''} ${dayStr === todayStr ? 'today' : ''}">
                    <span class="cal-daynum">${cur.getDate()}</span>
                    <i class="bi bi-plus-circle-fill cal-cell-add" onclick="openMeetingModal(null,'${dayStr}')"></i>
                    <div>${dayContent(dayStr)}</div></td>`;
                cur.setDate(cur.getDate() + 1);
            }
            html += '</tr>';
            if (cur > end && cur.getDay() === 0) break;
        }
        container.innerHTML = html + '</tbody></table>';
    } else {
        const ws = new Date(calDate); ws.setDate(ws.getDate() - ws.getDay());
        const we = new Date(ws); we.setDate(we.getDate() + 6);
        title.textContent = `${ws.getDate()} ${MONTHS[ws.getMonth()].slice(0,3)} - ${we.getDate()} ${MONTHS[we.getMonth()].slice(0,3)}`;
        let html = '<table class="cal-grid cal-week"><thead><tr>';
        let cur = new Date(ws);
        for (let d = 0; d < 7; d++) { html += `<th>${WEEKDAYS[d]} ${cur.getDate()}</th>`; cur.setDate(cur.getDate() + 1); }
        html += '</tr></thead><tbody><tr>';
        cur = new Date(ws);
        for (let d = 0; d < 7; d++) {
            const dayStr = fmtDate(cur);
            html += `<td class="${dayStr === todayStr ? 'today' : ''}">
                <i class="bi bi-plus-circle-fill cal-cell-add" onclick="openMeetingModal(null,'${dayStr}')"></i>
                <div>${dayContent(dayStr)}</div></td>`;
            cur.setDate(cur.getDate() + 1);
        }
        container.innerHTML = html + '</tr></tbody></table>';
    }
}
function dayContent(dayStr) {
    return eventsForDay(dayStr).map(e => {
        const color = (STATUS_META[e.status] || ['', '#888'])[1];
        const time = (e.meeting_at || '').slice(11, 16);
        return `<div class="cal-event" style="background:${color}" onclick="event.stopPropagation();openMeetingModal(${e.id})" title="${escapeAttr(e.title)}">${time ? time + ' ' : ''}${escapeHtml(e.title)}</div>`;
    }).join('');
}

function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function escapeAttr(s) { return (s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;'); }

// ===== Drag & Drop no Kanban =====
// Reuniões operacionais só podem ir para estes status (sem convertida/remarcada).
const OP_ALLOWED_STATUS = ['a_agendar', 'agendada', 'confirmada', 'realizada', 'cancelada'];
let draggedCard = null;

// Verifica se um card pode ser solto em uma coluna de destino.
function canDrop(card, targetStatus) {
    if (!card) return false;
    const type = card.dataset.type;
    // "convertida" nunca via arrasto (exige informar quem fechou pelo modal).
    if (targetStatus === 'convertida') return false;
    if (type === 'operacional' && !OP_ALLOWED_STATUS.includes(targetStatus)) return false;
    return true;
}

function initKanbanDnD() {
    // Cards arrastáveis
    document.querySelectorAll('.agenda-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', card.dataset.id); } catch (_) {}
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            draggedCard = null;
            document.querySelectorAll('.agenda-col-body').forEach(b => b.classList.remove('drag-over', 'drag-blocked'));
        });
    });

    // Colunas como alvos de drop
    document.querySelectorAll('.agenda-col-body').forEach(body => {
        const targetStatus = body.dataset.status;

        body.addEventListener('dragover', (e) => {
            if (!draggedCard) return;
            e.preventDefault();
            if (canDrop(draggedCard, targetStatus)) {
                e.dataTransfer.dropEffect = 'move';
                body.classList.add('drag-over');
                body.classList.remove('drag-blocked');
            } else {
                e.dataTransfer.dropEffect = 'none';
                body.classList.add('drag-blocked');
                body.classList.remove('drag-over');
            }
        });
        body.addEventListener('dragleave', () => {
            body.classList.remove('drag-over', 'drag-blocked');
        });
        body.addEventListener('drop', (e) => {
            e.preventDefault();
            body.classList.remove('drag-over', 'drag-blocked');
            if (!draggedCard) return;

            const card = draggedCard;
            const id = card.dataset.id;
            const sourceStatus = card.closest('.agenda-col-body')?.dataset.status;
            if (sourceStatus === targetStatus) return; // mesma coluna, nada a fazer

            if (!canDrop(card, targetStatus)) {
                if (targetStatus === 'convertida') {
                    alert('Para marcar como convertida, abra a reunião e informe quem fechou o negócio.');
                } else {
                    alert('Este status não é permitido para reuniões operacionais.');
                }
                return;
            }

            // Move o card na tela de forma otimista
            body.appendChild(card);
            updateColumnCounts();

            const fd = new FormData();
            fd.append('status', targetStatus);
            fetch(`${BASE}agenda/updateStatus/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(r => r.json()).then(d => {
                    if (d.error) { alert(d.error); location.reload(); }
                })
                .catch(() => { alert('Erro ao mover a reunião.'); location.reload(); });
        });
    });
}

// Atualiza os contadores no topo de cada coluna.
function updateColumnCounts() {
    document.querySelectorAll('.agenda-col').forEach(col => {
        const status = col.dataset.status;
        const count = col.querySelectorAll('.agenda-col-body .agenda-card').length;
        const badge = col.querySelector('.agenda-col-count');
        if (badge) badge.textContent = count;
    });
}

initKanbanDnD();
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
