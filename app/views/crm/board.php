<?php $pageTitle = escape($board['name']) . ' - CRM'; $currentPage = 'crm'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-kanban"></i> <?= escape($board['name']) ?></h5>
            <small class="text-muted"><?= escape($board['description'] ?? 'Board CRM') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Boards</a>
            <button class="btn btn-sm btn-outline-primary" onclick="openAddColumnModal()"><i class="bi bi-plus-lg"></i> Coluna</button>
            <button class="btn btn-sm btn-primary" onclick="openAddCardModal()"><i class="bi bi-plus-lg"></i> Card</button>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="crm-kanban-scroll">
        <div class="d-flex gap-3" id="kanban-board" style="min-width:max-content;">
            <?php foreach ($columns as $col): ?>
            <div class="crm-column" data-column-id="<?= $col['id'] ?>" style="width:280px;flex-shrink:0;">
                <div class="crm-column-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="crm-col-dot" style="background:<?= escape($col['color']) ?>;"></span>
                        <span class="fw-bold" style="font-size:0.85rem;"><?= escape($col['name']) ?></span>
                        <span class="badge rounded-pill bg-light text-dark" style="font-size:0.65rem;"><?= count($col['cards']) ?></span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm p-0 text-muted" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end" style="font-size:0.8rem;">
                            <li><a class="dropdown-item" href="#" onclick="renameColumn(<?= $col['id'] ?>,'<?= escape($col['name']) ?>')">Renomear</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteColumn(<?= $col['id'] ?>)">Excluir</a></li>
                        </ul>
                    </div>
                </div>
                <div class="crm-cards-list" data-column-id="<?= $col['id'] ?>">
                    <?php foreach ($col['cards'] as $card): ?>
                    <div class="crm-card" data-card-id="<?= $card['id'] ?>" onclick="openCardDetail(<?= $card['id'] ?>)">
                        <div class="crm-card-title"><?= escape($card['title']) ?></div>
                        <?php if ($card['phone']): ?>
                        <div class="crm-card-phone"><i class="bi bi-telephone"></i> <?= escape($card['phone']) ?></div>
                        <?php endif; ?>
                        <div class="crm-card-footer">
                            <?php if ($card['value']): ?>
                            <span class="text-success fw-medium">R$ <?= number_format($card['value'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                            <?php if ($card['assigned_name']): ?>
                            <span class="text-muted"><i class="bi bi-person"></i> <?= escape($card['assigned_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Adicionar Card -->
<div class="modal fade" id="addCardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Novo Card</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Título / Nome *</label>
                    <input type="text" id="new-card-title" class="form-control form-control-sm" required>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Telefone</label>
                        <input type="text" id="new-card-phone" class="form-control form-control-sm" placeholder="(11) 99999-9999">
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Valor (R$)</label>
                        <input type="number" step="0.01" id="new-card-value" class="form-control form-control-sm" placeholder="0,00">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Coluna</label>
                        <select id="new-card-column" class="form-select form-select-sm">
                            <?php foreach ($columns as $col): ?>
                            <option value="<?= $col['id'] ?>"><?= escape($col['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Responsável</label>
                        <select id="new-card-assigned" class="form-select form-select-sm">
                            <option value="">Ninguém</option>
                            <?php foreach ($teamMembers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Descrição</label>
                    <textarea id="new-card-description" class="form-control form-control-sm" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="createCard()">Criar Card</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adicionar Coluna -->
<div class="modal fade" id="addColumnModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Nova Coluna</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome *</label>
                    <input type="text" id="new-col-name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Cor</label>
                    <input type="color" id="new-col-color" class="form-control form-control-sm form-control-color" value="#6c757d">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="createColumn()">Criar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhe do Card -->
<div class="modal fade" id="cardDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="card-detail-title">Card</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Título</label>
                            <input type="text" id="card-title" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Descrição</label>
                            <textarea id="card-description" class="form-control form-control-sm" rows="4"></textarea>
                        </div>
                        <hr>
                        <label class="form-label small fw-medium"><i class="bi bi-clock-history"></i> Atividades</label>
                        <div id="card-activities" style="max-height:200px;overflow-y:auto;" class="mb-3"></div>
                        <div class="d-flex gap-2">
                            <input type="text" id="card-note-input" class="form-control form-control-sm" placeholder="Adicionar nota..." onkeypress="if(event.key==='Enter')addCardNote()">
                            <button class="btn btn-sm btn-outline-primary" onclick="addCardNote()"><i class="bi bi-send"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Telefone</label>
                            <input type="text" id="card-phone" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Valor (R$)</label>
                            <input type="number" step="0.01" id="card-value" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Responsável</label>
                            <select id="card-assigned" class="form-select form-select-sm">
                                <option value="">Ninguém</option>
                                <?php foreach ($teamMembers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (isset($card['contact_phone']) && $card['contact_phone']): ?>
                        <a href="<?= baseUrl('whatsapp/chat') ?>" class="btn btn-sm btn-outline-success w-100 mb-2">
                            <i class="bi bi-whatsapp"></i> Abrir no Chat
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary w-100 mb-2" onclick="saveCardDetail()">Salvar</button>
                        <button class="btn btn-sm btn-outline-danger w-100" onclick="deleteCard()"><i class="bi bi-trash"></i> Excluir</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.crm-kanban-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 10px; }
.crm-column { background: #f8f9fa; border-radius: 12px; padding: 0; display: flex; flex-direction: column; max-height: calc(100vh - 180px); }
.crm-column-header { padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; flex-shrink: 0; }
.crm-col-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.crm-cards-list { padding: 8px; flex: 1; overflow-y: auto; min-height: 80px; }
.crm-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
.crm-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.crm-card-title { font-size: 0.85rem; font-weight: 500; margin-bottom: 4px; }
.crm-card-phone { font-size: 0.72rem; color: #666; margin-bottom: 4px; }
.crm-card-footer { display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; margin-top: 6px; }
.crm-ghost { opacity: 0.4; background: #E0F7F4 !important; border: 2px dashed var(--primary) !important; }
.crm-drag { box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; transform: rotate(2deg); }
.activity-item { padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.78rem; }
.activity-item:last-child { border-bottom: none; }
.activity-note { background: #fffde7; padding: 6px 10px; border-radius: 6px; margin: 4px 0; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const BASE = '<?= baseUrl("") ?>';
const BOARD_ID = <?= $board['id'] ?>;
let currentCardId = null;

// =========================================
// DRAG AND DROP
// =========================================
document.querySelectorAll('.crm-cards-list').forEach(list => {
    new Sortable(list, {
        group: 'crm-cards',
        animation: 200,
        ghostClass: 'crm-ghost',
        dragClass: 'crm-drag',
        onEnd: function(evt) {
            const cardId = evt.item.dataset.cardId;
            const newColumnId = evt.to.dataset.columnId;
            const position = [...evt.to.children].indexOf(evt.item);

            const fd = new FormData();
            fd.append('card_id', cardId);
            fd.append('column_id', newColumnId);
            fd.append('position', position);

            fetch(BASE + 'crm/moveCard', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                }
                updateColumnCounts();
            });
        }
    });
});

function updateColumnCounts() {
    document.querySelectorAll('.crm-column').forEach(col => {
        const list = col.querySelector('.crm-cards-list');
        const badge = col.querySelector('.badge');
        if (list && badge) badge.textContent = list.querySelectorAll('.crm-card').length;
    });
}

// =========================================
// CARDS CRUD
// =========================================
function openAddCardModal() {
    document.getElementById('new-card-title').value = '';
    document.getElementById('new-card-phone').value = '';
    document.getElementById('new-card-value').value = '';
    document.getElementById('new-card-description').value = '';
    new bootstrap.Modal(document.getElementById('addCardModal')).show();
}

function createCard() {
    const fd = new FormData();
    fd.append('column_id', document.getElementById('new-card-column').value);
    fd.append('title', document.getElementById('new-card-title').value);
    fd.append('phone', document.getElementById('new-card-phone').value);
    fd.append('value', document.getElementById('new-card-value').value);
    fd.append('assigned_to', document.getElementById('new-card-assigned').value);
    fd.append('description', document.getElementById('new-card-description').value);

    fetch(BASE + 'crm/createCard', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao criar card');
        }
    });
}

function openCardDetail(cardId) {
    event.stopPropagation();
    currentCardId = cardId;

    fetch(BASE + 'crm/cardDetail/' + cardId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        const card = data.card;
        document.getElementById('card-detail-title').textContent = card.title;
        document.getElementById('card-title').value = card.title || '';
        document.getElementById('card-description').value = card.description || '';
        document.getElementById('card-phone').value = card.phone || '';
        document.getElementById('card-value').value = card.value || '';
        document.getElementById('card-assigned').value = card.assigned_to || '';

        // Atividades
        const actDiv = document.getElementById('card-activities');
        if (data.activities && data.activities.length) {
            actDiv.innerHTML = data.activities.map(a => {
                const icon = a.activity_type === 'note' ? 'bi-sticky' : (a.activity_type === 'move' ? 'bi-arrows-move' : 'bi-plus-circle');
                const cls = a.activity_type === 'note' ? 'activity-note' : '';
                return `<div class="activity-item ${cls}"><i class="bi ${icon} me-1"></i><strong>${a.user_name || 'Sistema'}</strong>: ${a.description}<br><small class="text-muted">${formatTime(a.created_at)}</small></div>`;
            }).join('');
        } else {
            actDiv.innerHTML = '<div class="text-muted small">Nenhuma atividade</div>';
        }

        new bootstrap.Modal(document.getElementById('cardDetailModal')).show();
    });
}

function saveCardDetail() {
    if (!currentCardId) return;
    const fd = new FormData();
    fd.append('title', document.getElementById('card-title').value);
    fd.append('description', document.getElementById('card-description').value);
    fd.append('phone', document.getElementById('card-phone').value);
    fd.append('value', document.getElementById('card-value').value);
    fd.append('assigned_to', document.getElementById('card-assigned').value);

    fetch(BASE + 'crm/updateCard/' + currentCardId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function deleteCard() {
    if (!currentCardId || !confirm('Excluir este card?')) return;
    const fd = new FormData();
    fetch(BASE + 'crm/deleteCard/' + currentCardId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

function addCardNote() {
    if (!currentCardId) return;
    const input = document.getElementById('card-note-input');
    const text = input.value.trim();
    if (!text) return;

    const fd = new FormData();
    fd.append('description', text);

    fetch(BASE + 'crm/addNote/' + currentCardId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const actDiv = document.getElementById('card-activities');
            actDiv.insertAdjacentHTML('afterbegin',
                `<div class="activity-item activity-note"><i class="bi bi-sticky me-1"></i><strong>${data.activity.user_name}</strong>: ${data.activity.description}<br><small class="text-muted">agora</small></div>`
            );
            input.value = '';
        }
    });
}
</script>

<script>
// =========================================
// COLUNAS
// =========================================
function openAddColumnModal() {
    document.getElementById('new-col-name').value = '';
    document.getElementById('new-col-color').value = '#6c757d';
    new bootstrap.Modal(document.getElementById('addColumnModal')).show();
}

function createColumn() {
    const fd = new FormData();
    fd.append('board_id', BOARD_ID);
    fd.append('name', document.getElementById('new-col-name').value);
    fd.append('color', document.getElementById('new-col-color').value);

    fetch(BASE + 'crm/createColumn', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'Erro');
    });
}

function renameColumn(colId, currentName) {
    const newName = prompt('Novo nome da coluna:', currentName);
    if (!newName || newName === currentName) return;

    const fd = new FormData();
    fd.append('name', newName);

    fetch(BASE + 'crm/updateColumn/' + colId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

function deleteColumn(colId) {
    if (!confirm('Excluir esta coluna e todos os cards nela?')) return;

    const fd = new FormData();
    fetch(BASE + 'crm/deleteColumn/' + colId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

// =========================================
// UTILS
// =========================================
function formatTime(datetime) {
    if (!datetime) return '';
    const d = new Date(datetime.replace(' ', 'T'));
    return d.getDate().toString().padStart(2,'0') + '/' + (d.getMonth()+1).toString().padStart(2,'0') + ' ' + d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
