<?php $pageTitle = 'Planejamento - ON Solutions Helpdesk'; $currentPage = 'planning'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusLabels = [
    'open' => ['Aberto', '#1565c0'],
    'in_progress' => ['Em andamento', '#e65100'],
    'waiting_client' => ['Aguardando', '#7b1fa2'],
    'completed' => ['Concluído', '#2e7d32'],
    'denied' => ['Negado', '#d84315'],
    'archived' => ['Arquivado', '#546e7a'],
];
$priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
?>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Planejamento</h5>
            <small class="text-muted">Gerencie cards da equipe</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="btn-group btn-group-sm" id="view-toggle">
                <button type="button" class="btn btn-outline-primary active" data-view="kanban"><i class="bi bi-kanban"></i> Kanban</button>
                <button type="button" class="btn btn-outline-primary" data-view="calendar"><i class="bi bi-calendar3"></i> Calendário</button>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openCreateModal()"><i class="bi bi-plus-lg"></i> Novo Card</button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center" id="filters-form">
                <div class="col-6 col-md-auto">
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">Todas Empresas</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filters['company_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="">Todos Responsáveis</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($filters['assigned_to'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= escape($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('planning') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- KANBAN VIEW -->
    <div id="kanban-view">
        <div class="kanban-scroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:10px;">
            <div class="d-flex gap-3" style="min-width:max-content;">
                <?php foreach ($statusLabels as $status => $info): ?>
                <div style="width:260px;flex-shrink:0;">
                    <div class="kanban-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold" style="color:<?= $info[1] ?>;font-size:0.85rem;"><?= $info[0] ?></h6>
                            <span class="badge rounded-pill" style="background:<?= $info[1] ?>;color:#fff;font-size:0.7rem"><?= count($grouped[$status] ?? []) ?></span>
                        </div>
                        <div class="kanban-list" data-status="<?= $status ?>" style="min-height:60px;">
                            <?php foreach (($grouped[$status] ?? []) as $card): ?>
                            <div class="kanban-card planning-card" data-id="<?= $card['id'] ?>" onclick="openCardModal(<?= $card['id'] ?>)">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="text-muted" style="font-size:0.7rem">#<?= $card['id'] ?></span>
                                    <span class="priority-<?= $card['priority'] ?>" style="font-size:0.7rem"><?= $priorityLabels[$card['priority']] ?? '' ?></span>
                                </div>
                                <div class="fw-medium" style="font-size:0.82rem"><?= escape($card['title']) ?></div>
                                <div class="text-muted mt-2" style="font-size:0.7rem">
                                    <?php if ($card['company_name']): ?>
                                    <span><i class="bi bi-building"></i> <?= escape($card['company_name']) ?></span><br>
                                    <?php endif; ?>
                                    <span><i class="bi bi-person"></i> <?= escape($card['assigned_name'] ?? 'Não atribuído') ?></span>
                                    <?php if ($card['due_date']): ?>
                                    <span class="float-end"><i class="bi bi-clock"></i> <?= date('d/m H:i', strtotime($card['due_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- CALENDAR VIEW -->
    <div id="calendar-view" style="display:none;">
        <div class="card">
            <div class="card-body p-2 p-md-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="btn-group btn-group-sm" id="cal-mode-toggle">
                        <button type="button" class="btn btn-outline-secondary active" data-mode="month">Mês</button>
                        <button type="button" class="btn btn-outline-secondary" data-mode="week">Semana</button>
                        <button type="button" class="btn btn-outline-secondary" data-mode="day">Dia</button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="cal-prev"><i class="bi bi-chevron-left"></i></button>
                        <span class="fw-medium" id="cal-title" style="font-size:0.9rem;min-width:140px;text-align:center;"></span>
                        <button class="btn btn-sm btn-outline-secondary" id="cal-next"><i class="bi bi-chevron-right"></i></button>
                        <button class="btn btn-sm btn-outline-primary" id="cal-today">Hoje</button>
                    </div>
                </div>
                <div id="calendar-container"></div>
            </div>
        </div>
    </div>

    <!-- MODAL CRIAR CARD -->
    <div class="modal fade" id="createCardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Novo Card</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="<?= baseUrl('planning/create') ?>" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Título *</label>
                            <input type="text" name="title" class="form-control form-control-sm" required>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Empresa</label>
                                <select name="company_id" class="form-select form-select-sm">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label small fw-medium">Responsável</label>
                                <select name="assigned_to" class="form-select form-select-sm">
                                    <option value="">Não atribuído</option>
                                    <?php foreach ($teamMembers as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Prioridade</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="low">Baixa</option>
                                    <option value="medium" selected>Média</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <?php foreach ($statusLabels as $s => $info): ?>
                                    <option value="<?= $s ?>"><?= $info[0] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <label class="form-label small fw-medium">Prazo</label>
                                <input type="datetime-local" name="due_date" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary">Criar Card</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHE DO CARD -->
    <div class="modal fade" id="cardDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="detail-title">Card</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:75vh;overflow-y:auto;">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium">Título</label>
                            <input type="text" id="detail-title-input" class="form-control form-control-sm">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-medium">Responsável</label>
                            <select id="detail-assigned" class="form-select form-select-sm">
                                <option value="">Não atribuído</option>
                                <?php foreach ($teamMembers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label small fw-medium">Empresa</label>
                            <select id="detail-company" class="form-select form-select-sm">
                                <option value="">Nenhuma</option>
                                <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-medium">Prioridade</label>
                            <select id="detail-priority" class="form-select form-select-sm">
                                <option value="low">Baixa</option>
                                <option value="medium">Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-medium">Status</label>
                            <select id="detail-status" class="form-select form-select-sm">
                                <?php foreach ($statusLabels as $s => $info): ?>
                                <option value="<?= $s ?>"><?= $info[0] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-medium">Prazo</label>
                            <input type="datetime-local" id="detail-due-date" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="detail-meta"></small>
                        <button class="btn btn-sm btn-primary" onclick="saveCard()">Salvar</button>
                    </div>
                    <hr>

                    <!-- Editor Rich Text -->
                    <label class="form-label small fw-medium">Descrição</label>
                    <div id="quill-editor" style="min-height:200px;background:#fff;border-radius:0 0 6px 6px;"></div>
                    <hr>

                    <!-- Anexos -->
                    <div class="mb-3">
                        <label class="form-label small fw-medium"><i class="bi bi-paperclip"></i> Anexos</label>
                        <div id="detail-attachments" class="mb-2"></div>
                        <div class="d-flex gap-2">
                            <input type="file" id="detail-file-input" class="form-control form-control-sm" style="max-width:250px;">
                            <button class="btn btn-sm btn-outline-primary" onclick="uploadCardFile()"><i class="bi bi-upload"></i></button>
                        </div>
                    </div>
                    <hr>

                    <!-- Comentários -->
                    <label class="form-label small fw-medium"><i class="bi bi-chat-dots"></i> Comentários</label>
                    <div id="detail-comments" class="mb-3" style="max-height:250px;overflow-y:auto;"></div>
                    <div class="d-flex gap-2">
                        <input type="text" id="comment-input" class="form-control form-control-sm" placeholder="Escreva um comentário..." onkeypress="if(event.key==='Enter')addComment()">
                        <button class="btn btn-sm btn-primary" onclick="addComment()"><i class="bi bi-send"></i></button>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCard()"><i class="bi bi-trash"></i> Excluir</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div><!-- end main-content -->

<!-- Quill Editor -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<!-- SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
.planning-card { cursor: pointer; transition: box-shadow 0.2s; }
.planning-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.kanban-ghost { opacity: 0.4; background: var(--primary-50) !important; border: 2px dashed var(--primary) !important; }
.kanban-drag { box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; transform: rotate(1deg); }
#calendar-container table { width: 100%; border-collapse: collapse; }
#calendar-container th, #calendar-container td { border: 1px solid #e9ecef; padding: 4px; vertical-align: top; font-size: 0.8rem; }
#calendar-container th { background: #f8f9fa; text-align: center; font-weight: 600; }
#calendar-container td { min-height: 80px; height: 80px; }
#calendar-container .cal-day { cursor: pointer; transition: background 0.15s; position: relative; }
#calendar-container .cal-day:hover { background: #f0faf8; }
#calendar-container .cal-day-num { font-weight: 600; font-size: 0.75rem; color: #666; }
#calendar-container .cal-day.today .cal-day-num { background: var(--primary); color: #fff; border-radius: 50%; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; }
#calendar-container .cal-day.other-month { opacity: 0.4; }
#calendar-container .cal-event { font-size: 0.68rem; padding: 2px 4px; border-radius: 3px; margin-top: 2px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff; }
/* Week/Day view */
.cal-time-slot { height: 50px; border-bottom: 1px solid #eee; position: relative; }
.cal-time-label { font-size: 0.7rem; color: #999; width: 50px; text-align: right; padding-right: 8px; }
.cal-time-event { position: absolute; left: 55px; right: 4px; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; color: #fff; cursor: pointer; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
@media (max-width: 768px) {
    #calendar-container td { font-size: 0.7rem; padding: 2px; min-height: 50px; height: 50px; }
    #calendar-container .cal-event { font-size: 0.6rem; }
    .cal-time-label { width: 35px; font-size: 0.6rem; }
}
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
let currentCardId = null;
let quill = null;
let calendarEvents = [];
let calDate = new Date();
let calMode = 'month';

const priorityColors = {low:'#6b7280',medium:'#f59e0b',high:'#ef4444',urgent:'#dc2626'};
const statusColors = {open:'#1565c0',in_progress:'#e65100',waiting_client:'#7b1fa2',completed:'#2e7d32',denied:'#d84315',archived:'#546e7a'};

// === VIEW TOGGLE ===
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

// === KANBAN DRAG & DROP ===
document.querySelectorAll('#kanban-view .kanban-list').forEach(list => {
    new Sortable(list, {
        group: 'planning',
        animation: 200,
        ghostClass: 'kanban-ghost',
        dragClass: 'kanban-drag',
        filter: 'a',
        onEnd: function(evt) {
            const cardId = evt.item.dataset.id;
            const newStatus = evt.to.dataset.status;
            const position = evt.newIndex;
            const formData = new FormData();
            formData.append('status', newStatus);
            formData.append('position', position);
            fetch(BASE + 'planning/updateStatus/' + cardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(r => r.json()).then(data => { if (!data.success) evt.from.appendChild(evt.item); updateKanbanCounts(); })
                .catch(() => { evt.from.appendChild(evt.item); updateKanbanCounts(); });
        }
    });
});
function updateKanbanCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const list = col.querySelector('.kanban-list');
        const badge = col.querySelector('.badge');
        if (list && badge) badge.textContent = list.querySelectorAll('.planning-card').length;
    });
}

// === MODALS ===
function openCreateModal() {
    new bootstrap.Modal(document.getElementById('createCardModal')).show();
}

function openCardModal(id) {
    currentCardId = id;
    fetch(BASE + 'planning/get/' + id).then(r => r.json()).then(data => {
        const c = data.card;
        document.getElementById('detail-title').textContent = '#' + c.id + ' ' + c.title;
        document.getElementById('detail-title-input').value = c.title;
        document.getElementById('detail-assigned').value = c.assigned_to || '';
        document.getElementById('detail-company').value = c.company_id || '';
        document.getElementById('detail-priority').value = c.priority;
        document.getElementById('detail-status').value = c.status;
        document.getElementById('detail-due-date').value = c.due_date ? c.due_date.slice(0,16) : '';
        document.getElementById('detail-meta').textContent = 'Criado por ' + c.created_by_name + ' em ' + new Date(c.created_at).toLocaleString('pt-BR') + (c.ticket_id ? ' | Vinculado à demanda #'+c.ticket_id : '');

        // Quill editor
        if (quill) quill.root.innerHTML = c.description || '';

        // Attachments
        renderAttachments(data.attachments);
        // Comments
        renderComments(data.comments);

        new bootstrap.Modal(document.getElementById('cardDetailModal')).show();
    });
}

function saveCard() {
    const formData = new FormData();
    formData.append('title', document.getElementById('detail-title-input').value);
    formData.append('description', quill ? quill.root.innerHTML : '');
    formData.append('assigned_to', document.getElementById('detail-assigned').value);
    formData.append('company_id', document.getElementById('detail-company').value);
    formData.append('priority', document.getElementById('detail-priority').value);
    formData.append('status', document.getElementById('detail-status').value);
    formData.append('due_date', document.getElementById('detail-due-date').value);

    fetch(BASE + 'planning/update/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
}

function deleteCard() {
    if (!confirm('Tem certeza que deseja excluir este card?')) return;
    const formData = new FormData();
    fetch(BASE + 'planning/delete/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
        });
}

// === COMMENTS ===
function renderComments(comments) {
    const container = document.getElementById('detail-comments');
    if (!comments.length) { container.innerHTML = '<p class="text-muted small">Nenhum comentário.</p>'; return; }
    container.innerHTML = comments.map(c => `
        <div class="d-flex gap-2 mb-2">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">
                <i class="bi bi-person text-white" style="font-size:0.7rem;"></i>
            </div>
            <div class="flex-grow-1">
                <div class="small"><strong>${c.user_name}</strong> <span class="text-muted" style="font-size:0.7rem;">${new Date(c.created_at).toLocaleString('pt-BR')}</span></div>
                <div class="small">${c.message}</div>
            </div>
        </div>
    `).join('');
    container.scrollTop = container.scrollHeight;
}

function addComment() {
    const input = document.getElementById('comment-input');
    const msg = input.value.trim();
    if (!msg) return;
    const formData = new FormData();
    formData.append('message', msg);
    fetch(BASE + 'planning/comment/' + currentCardId, { method: 'POST', body: formData, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.success) {
                input.value = '';
                const container = document.getElementById('detail-comments');
                if (container.querySelector('.text-muted')) container.innerHTML = '';
                container.innerHTML += `
                    <div class="d-flex gap-2 mb-2">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">
                            <i class="bi bi-person text-white" style="font-size:0.7rem;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small"><strong>${data.comment.user_name}</strong> <span class="text-muted" style="font-size:0.7rem;">agora</span></div>
                            <div class="small">${data.comment.message}</div>
                        </div>
                    </div>`;
                container.scrollTop = container.scrollHeight;
            }
        });
}

// === ATTACHMENTS ===
function renderAttachments(attachments) {
    const container = document.getElementById('detail-attachments');
    if (!attachments.length) { container.innerHTML = '<p class="text-muted small">Nenhum anexo.</p>'; return; }
    container.innerHTML = attachments.map(a => `
        <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-1" style="font-size:0.8rem;">
            <a href="${BASE}${a.file_path}" target="_blank" class="text-decoration-none text-truncate">${a.file_name}</a>
            <button class="btn btn-sm btn-outline-danger p-0 px-1" onclick="deleteAttachment(${a.id})"><i class="bi bi-x"></i></button>
        </div>
    `).join('');
}

function uploadCardFile() {
    const input = document.getElementById('detail-file-input');
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('file', input.files[0]);
    fetch(BASE + 'planning/upload/' + currentCardId, { method: 'POST', body: formData })
        .then(r => r.json()).then(data => {
            if (data.success) { input.value = ''; openCardModal(currentCardId); }
            else alert(data.error || 'Erro no upload');
        });
}

function deleteAttachment(attId) {
    if (!confirm('Remover anexo?')) return;
    fetch(BASE + 'planning/deleteAttachment/' + attId, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => { if (data.success) openCardModal(currentCardId); });
}

// === CALENDAR ===
document.getElementById('cal-prev').addEventListener('click', () => { navCalendar(-1); });
document.getElementById('cal-next').addEventListener('click', () => { navCalendar(1); });
document.getElementById('cal-today').addEventListener('click', () => { calDate = new Date(); loadCalendar(); });
document.querySelectorAll('#cal-mode-toggle button').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#cal-mode-toggle button').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        calMode = this.dataset.mode;
        loadCalendar();
    });
});

function navCalendar(dir) {
    if (calMode === 'month') calDate.setMonth(calDate.getMonth() + dir);
    else if (calMode === 'week') calDate.setDate(calDate.getDate() + (7 * dir));
    else calDate.setDate(calDate.getDate() + dir);
    loadCalendar();
}

function loadCalendar() {
    let start, end;
    if (calMode === 'month') {
        start = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        end = new Date(calDate.getFullYear(), calDate.getMonth() + 1, 0, 23, 59, 59);
    } else if (calMode === 'week') {
        const d = new Date(calDate); d.setDate(d.getDate() - d.getDay());
        start = new Date(d); end = new Date(d); end.setDate(end.getDate() + 6); end.setHours(23,59,59);
    } else {
        start = new Date(calDate); start.setHours(0,0,0);
        end = new Date(calDate); end.setHours(23,59,59);
    }
    const params = new URLSearchParams(window.location.search);
    params.set('start', fmt(start)); params.set('end', fmt(end));
    fetch(BASE + 'planning/calendar?' + params.toString())
        .then(r => r.json()).then(events => { calendarEvents = events; renderCalendar(start, end); });
}

function fmt(d) { return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')+':00'; }

function renderCalendar(start, end) {
    const container = document.getElementById('calendar-container');
    const title = document.getElementById('cal-title');
    const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    const today = new Date(); today.setHours(0,0,0,0);

    if (calMode === 'month') {
        title.textContent = months[calDate.getMonth()] + ' ' + calDate.getFullYear();
        let html = '<table><thead><tr>';
        days.forEach(d => html += '<th>'+d+'</th>');
        html += '</tr></thead><tbody>';
        const first = new Date(calDate.getFullYear(), calDate.getMonth(), 1);
        const startDay = first.getDay();
        const totalDays = new Date(calDate.getFullYear(), calDate.getMonth()+1, 0).getDate();
        let day = 1 - startDay;
        for (let w = 0; w < 6; w++) {
            html += '<tr>';
            for (let d = 0; d < 7; d++, day++) {
                const cellDate = new Date(calDate.getFullYear(), calDate.getMonth(), day);
                const isOther = day < 1 || day > totalDays;
                const isToday = cellDate.getTime() === today.getTime();
                html += `<td class="cal-day ${isOther?'other-month':''} ${isToday?'today':''}">`;
                html += `<div class="cal-day-num">${cellDate.getDate()}</div>`;
                const dayEvents = calendarEvents.filter(e => { const ed = new Date(e.start); return ed.getDate()===cellDate.getDate() && ed.getMonth()===cellDate.getMonth() && ed.getFullYear()===cellDate.getFullYear(); });
                dayEvents.slice(0,3).forEach(e => { html += `<div class="cal-event" style="background:${priorityColors[e.priority]||'#666'}" onclick="openCardModal(${e.id})" title="${e.title}">${e.title}</div>`; });
                if (dayEvents.length > 3) html += `<div style="font-size:0.65rem;color:#999;">+${dayEvents.length-3} mais</div>`;
                html += '</td>';
            }
            html += '</tr>';
            if (day > totalDays) break;
        }
        html += '</tbody></table>';
        container.innerHTML = html;
    } else if (calMode === 'week') {
        const weekStart = new Date(calDate); weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        title.textContent = `${weekStart.getDate()}/${weekStart.getMonth()+1} - ${new Date(weekStart.getTime()+6*86400000).getDate()}/${new Date(weekStart.getTime()+6*86400000).getMonth()+1}/${weekStart.getFullYear()}`;
        renderTimeGrid(container, weekStart, 7);
    } else {
        title.textContent = `${calDate.getDate()}/${calDate.getMonth()+1}/${calDate.getFullYear()} (${days[calDate.getDay()]})`;
        renderTimeGrid(container, calDate, 1);
    }
}

function renderTimeGrid(container, startDate, numDays) {
    const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    let html = '<div style="overflow-x:auto;"><table style="min-width:'+(numDays>1?'700px':'100%')+'"><thead><tr><th style="width:50px;"></th>';
    for (let d = 0; d < numDays; d++) {
        const dd = new Date(startDate); dd.setDate(dd.getDate() + d);
        html += `<th>${days[dd.getDay()]} ${dd.getDate()}/${dd.getMonth()+1}</th>`;
    }
    html += '</tr></thead><tbody>';
    for (let h = 6; h <= 22; h++) {
        html += '<tr>';
        html += `<td class="cal-time-label">${String(h).padStart(2,'0')}:00</td>`;
        for (let d = 0; d < numDays; d++) {
            const dd = new Date(startDate); dd.setDate(dd.getDate() + d);
            html += '<td class="cal-time-slot" style="position:relative;">';
            const hourEvents = calendarEvents.filter(e => {
                const ed = new Date(e.start);
                return ed.getDate()===dd.getDate() && ed.getMonth()===dd.getMonth() && ed.getFullYear()===dd.getFullYear() && ed.getHours()===h;
            });
            hourEvents.forEach(e => {
                html += `<div class="cal-time-event" style="background:${priorityColors[e.priority]||'#666'}" onclick="openCardModal(${e.id})" title="${e.title}">${e.title}</div>`;
            });
            html += '</td>';
        }
        html += '</tr>';
    }
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// === INIT QUILL ===
document.getElementById('cardDetailModal').addEventListener('shown.bs.modal', function() {
    if (!quill) {
        quill = new Quill('#quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{'header':[1,2,3,false]}],
                    ['bold','italic','underline','strike'],
                    [{'list':'ordered'},{'list':'bullet'}],
                    ['blockquote','code-block'],
                    ['link','image'],
                    [{'color':[]},{'background':[]}],
                    ['clean']
                ]
            },
            placeholder: 'Escreva aqui... (texto, imagens, tabelas, listas...)'
        });
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
