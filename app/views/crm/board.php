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
            <a href="<?= baseUrl('crm/dashboard') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i> Dashboard</a>
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
                    <?php
                    $displayName = $card['contact_name'] ?: $card['title'];
                    $tempColors = ['frio' => '#3b82f6', 'morno' => '#f59e0b', 'quente' => '#ef4444'];
                    $tempColor = $tempColors[$card['lead_temperature'] ?? ''] ?? null;
                    $outcome = $card['lead_outcome'] ?? 'open';
                    ?>
                    <div class="crm-card outcome-<?= $outcome ?>" data-card-id="<?= $card['id'] ?>" onclick="openCardDetail(<?= $card['id'] ?>)">
                        <div class="crm-card-title">
                            <?php if ($tempColor): ?>
                            <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:<?= $tempColor ?>;" title="Lead <?= escape($card['lead_temperature']) ?>"></span>
                            <?php endif; ?>
                            <?= escape($displayName) ?>
                        </div>
                        <div class="d-flex gap-1 flex-wrap mb-1">
                            <?php if (!empty($card['label_name'])): ?>
                            <span class="badge" style="background:<?= escape($card['label_color'] ?? '#6c757d') ?>;font-size:0.62rem;"><?= escape($card['label_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($outcome === 'converted'): ?>
                            <span class="badge bg-success" style="font-size:0.62rem;"><i class="bi bi-check-circle"></i> Convertido</span>
                            <?php elseif ($outcome === 'lost'): ?>
                            <span class="badge bg-danger" style="font-size:0.62rem;"><i class="bi bi-x-circle"></i> Perdido</span>
                            <?php endif; ?>
                            <?php if (!empty($card['follow_up_at'])): ?>
                            <span class="badge bg-warning text-dark" style="font-size:0.62rem;"><i class="bi bi-clock"></i> Retomar <?= date('d/m', strtotime($card['follow_up_at'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($card['in_recovery'])): ?>
                            <span class="badge" style="font-size:0.62rem;background:#7e57c2;color:#fff;"><i class="bi bi-arrow-repeat"></i> Em recuperação</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($card['phone']): ?>
                        <div class="crm-card-phone"><i class="bi bi-telephone"></i> <?= escape($card['phone']) ?></div>
                        <?php endif; ?>
                        <div class="crm-card-footer">
                            <?php if (!empty($card['investment_range'])): ?>
                            <span class="text-success fw-medium"><i class="bi bi-cash-coin"></i> <?= escape($card['investment_range']) ?></span>
                            <?php elseif ($card['value']): ?>
                            <span class="text-success fw-medium">R$ <?= number_format($card['value'], 2, ',', '.') ?></span>
                            <?php else: ?>
                            <span class="text-muted">Sem investimento</span>
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
                        <input type="text" id="new-card-value" class="form-control form-control-sm" placeholder="R$ 0,00" oninput="formatCardCurrency(this)">
                        <small class="text-muted" style="font-size:0.68rem">Sincroniza com a Faixa de investimento do briefing.</small>
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
                <div class="row g-2">
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Etiqueta (opcional)</label>
                        <select id="new-card-label" class="form-select form-select-sm">
                            <option value="">Nenhuma</option>
                            <?php foreach (($labels ?? []) as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= escape($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label class="form-label small fw-medium">Status (opcional)</label>
                        <select id="new-card-status" class="form-select form-select-sm">
                            <option value="">Nenhum</option>
                            <option value="novo">Novo</option>
                            <option value="em_atendimento">Em atendimento</option>
                            <option value="aguardando">Aguardando</option>
                            <option value="concluido">Concluído</option>
                            <option value="perdido">Perdido</option>
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
                <div class="mb-3">
                    <label class="form-label small fw-medium">Etiqueta (opcional)</label>
                    <select id="new-col-label" class="form-select form-select-sm">
                        <option value="">Nenhuma</option>
                        <?php foreach (($labels ?? []) as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= escape($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Status (opcional)</label>
                    <select id="new-col-status" class="form-select form-select-sm">
                        <option value="">Nenhum</option>
                        <option value="novo">Novo</option>
                        <option value="em_atendimento">Em atendimento</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="concluido">Concluído</option>
                        <option value="perdido">Perdido</option>
                    </select>
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
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-card-details" type="button"><i class="bi bi-card-text"></i> Detalhes</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-card-briefing" type="button"><i class="bi bi-clipboard-data"></i> Briefing Comercial</button></li>
                </ul>
                <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-card-details">
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
                            <label class="form-label small fw-medium mb-0">Valor (R$)</label>
                            <span class="text-muted d-block" style="font-weight:400;font-size:0.68rem">Faixa de investimento do briefing</span>
                            <input type="text" id="card-value" class="form-control form-control-sm" placeholder="R$ 0,00" oninput="formatCardCurrency(this)">
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
                        <div class="mb-3 p-2 rounded" style="background:#fff8e1;">
                            <label class="form-label small fw-medium mb-1"><i class="bi bi-clock-history"></i> Retomar contato em</label>
                            <div class="d-flex gap-1 mb-1">
                                <input type="number" min="1" id="card-followup-amount" class="form-control form-control-sm" placeholder="Ex: 7" style="max-width:80px;">
                                <select id="card-followup-unit" class="form-select form-select-sm">
                                    <option value="minutes">Minutos</option>
                                    <option value="hours">Horas</option>
                                    <option value="days" selected>Dias</option>
                                </select>
                            </div>
                            <label class="form-label small fw-medium mb-1" style="font-size:0.72rem;">Mover para a coluna:</label>
                            <select id="card-followup-column" class="form-select form-select-sm mb-2">
                                <option value="">Primeira coluna (padrão)</option>
                                <?php foreach ($columns as $col): ?>
                                <option value="<?= $col['id'] ?>"><?= escape($col['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-warning w-100" onclick="setFollowUp()"><i class="bi bi-clock"></i> Agendar retomada</button>
                            <small id="card-followup-info" class="text-muted d-block mt-1" style="font-size:0.68rem;"></small>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button class="btn btn-sm btn-success flex-fill" onclick="convertLead()"><i class="bi bi-check-circle"></i> Convertido</button>
                            <button class="btn btn-sm btn-danger flex-fill" onclick="lostLead()"><i class="bi bi-x-circle"></i> Perdido</button>
                        </div>
                        <a href="#" id="card-open-chat" class="btn btn-sm btn-outline-success w-100 mb-2" style="display:none;">
                            <i class="bi bi-whatsapp"></i> Abrir no Chat
                        </a>
                        <button class="btn btn-sm btn-primary w-100 mb-2" onclick="saveCardDetail()">Salvar</button>
                        <button class="btn btn-sm btn-outline-danger w-100" onclick="deleteCard()"><i class="bi bi-trash"></i> Excluir</button>
                    </div>
                </div>
                </div><!-- /tab-card-details -->

                <div class="tab-pane fade" id="tab-card-briefing">
                    <div id="card-briefing-content">
                        <div class="text-muted small text-center py-4">Carregando briefing...</div>
                    </div>
                </div>
                </div><!-- /tab-content -->
            </div>
        </div>
    </div>
</div>

<style>
.crm-kanban-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 10px; }
.briefing-view-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.briefing-view-item:last-child { border-bottom: none; }
.briefing-view-label { font-size: 0.72rem; color: #888; text-transform: uppercase; letter-spacing: 0.3px; }
.briefing-view-value { font-size: 0.88rem; color: #333; margin-top: 2px; white-space: pre-wrap; }
.crm-column { background: #f8f9fa; border-radius: 12px; padding: 0; display: flex; flex-direction: column; max-height: calc(100vh - 180px); }
.crm-column-header { padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; flex-shrink: 0; }
.crm-col-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.crm-cards-list { padding: 8px; flex: 1; overflow-y: auto; min-height: 80px; }
.crm-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
.crm-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.crm-card.outcome-converted { border-left: 3px solid #2e7d32; }
.crm-card.outcome-lost { border-left: 3px solid #c62828; opacity: 0.75; }
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
    fd.append('value', parseCurrency(document.getElementById('new-card-value').value));
    fd.append('assigned_to', document.getElementById('new-card-assigned').value);
    fd.append('label_id', document.getElementById('new-card-label').value);
    fd.append('status', document.getElementById('new-card-status').value);
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
        // Título do lead = nome atual do contato do WhatsApp (se vinculado), senão o título do card
        const displayTitle = card.contact_name || card.title || '';
        const titleEl = document.getElementById('card-detail-title');
        titleEl.textContent = displayTitle;
        if (card.in_recovery == 1) {
            titleEl.insertAdjacentHTML('beforeend', ' <span class="badge align-middle" style="font-size:0.6rem;background:#7e57c2;color:#fff;"><i class="bi bi-arrow-repeat"></i> Em recuperação</span>');
        }
        document.getElementById('card-title').value = displayTitle;
        document.getElementById('card-description').value = card.description || '';
        document.getElementById('card-phone').value = card.phone || '';
        // Valor: prioriza a faixa de investimento do briefing; senão usa o value numérico
        let displayValue = '';
        if (card.investment_range) {
            displayValue = card.investment_range;
        } else if (card.value) {
            displayValue = 'R$ ' + parseFloat(card.value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        document.getElementById('card-value').value = displayValue;
        document.getElementById('card-assigned').value = card.assigned_to || '';

        // Botão "Abrir no Chat" — leva direto à conversa do contato vinculado
        const openChatBtn = document.getElementById('card-open-chat');
        if (openChatBtn) {
            if (card.contact_id) {
                openChatBtn.href = BASE + 'whatsapp/chat/' + card.contact_id;
                openChatBtn.style.display = '';
            } else {
                openChatBtn.style.display = 'none';
            }
        }
        document.getElementById('card-followup-amount').value = '';
        document.getElementById('card-followup-unit').value = 'days';
        document.getElementById('card-followup-column').value = card.follow_up_column_id || '';
        const fuInfo = document.getElementById('card-followup-info');
        if (fuInfo) {
            if (card.follow_up_at) {
                const dt = new Date(card.follow_up_at.replace(' ', 'T'));
                fuInfo.textContent = 'Retomada agendada para ' + dt.toLocaleString('pt-BR');
            } else {
                fuInfo.textContent = '';
            }
        }

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

        renderBriefing(data.briefing);

        new bootstrap.Modal(document.getElementById('cardDetailModal')).show();
    });
}

function renderBriefing(b) {
    const container = document.getElementById('card-briefing-content');
    if (!container) return;
    if (!b) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-clipboard-x fs-3"></i><p class="mt-2 mb-0 small">Nenhum briefing comercial cadastrado para este contato.</p><p class="small text-muted">Preencha o briefing no chat do WhatsApp (Detalhes do contato).</p></div>';
        return;
    }
    const tempLabels = { frio: '🔵 Frio', morno: '🟠 Morno', quente: '🔴 Quente' };
    const fields = [
        ['need', 'Necessidade do lead'],
        ['main_pain', 'Principal dor/problema'],
        ['current_solution', 'Solução atual utilizada'],
        ['expected_goal', 'Objetivo esperado'],
        ['urgency', 'Urgência/prazo'],
        ['investment_range', 'Faixa de investimento'],
        ['decision_level', 'Nível de decisão do contato'],
        ['lead_temperature', 'Temperatura do lead'],
        ['main_objection', 'Principal objeção'],
        ['next_step', 'Próximo passo combinado'],
        ['next_contact_date', 'Data do próximo contato'],
        ['notes', 'Observações importantes'],
    ];
    let html = '';
    fields.forEach(([key, label]) => {
        let val = b[key];
        if (!val) return;
        if (key === 'lead_temperature') val = tempLabels[val] || val;
        if (key === 'next_contact_date') val = String(val).split('-').reverse().join('/');
        html += `<div class="briefing-view-item">
            <div class="briefing-view-label">${label}</div>
            <div class="briefing-view-value">${escapeHtmlSafe(val)}</div>
        </div>`;
    });
    if (!html) {
        html = '<div class="text-center text-muted py-4">Briefing sem informações preenchidas.</div>';
    }
    container.innerHTML = html;
}

function escapeHtmlSafe(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function saveCardDetail() {
    if (!currentCardId) return;
    const fd = new FormData();
    fd.append('title', document.getElementById('card-title').value);
    fd.append('description', document.getElementById('card-description').value);
    fd.append('phone', document.getElementById('card-phone').value);
    fd.append('value', parseCurrency(document.getElementById('card-value').value));
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

function convertLead() {
    if (!currentCardId || !confirm('Marcar este lead como CONVERTIDO?')) return;
    fetch(BASE + 'crm/convertLead/' + currentCardId, { method: 'POST', body: new FormData(), headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert(data.error || 'Erro'); });
}

function lostLead() {
    if (!currentCardId || !confirm('Marcar este lead como PERDIDO?')) return;
    fetch(BASE + 'crm/lostLead/' + currentCardId, { method: 'POST', body: new FormData(), headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert(data.error || 'Erro'); });
}

function setFollowUp() {
    if (!currentCardId) return;
    const amount = parseInt(document.getElementById('card-followup-amount').value, 10);
    const unit = document.getElementById('card-followup-unit').value;
    const targetColumn = document.getElementById('card-followup-column').value;
    if (!amount || amount <= 0) { alert('Informe um valor válido.'); return; }
    const fd = new FormData();
    fd.append('amount', amount);
    fd.append('unit', unit);
    fd.append('target_column_id', targetColumn);
    fetch(BASE + 'crm/setFollowUp/' + currentCardId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const dt = new Date(data.follow_up_at.replace(' ', 'T'));
            document.getElementById('card-followup-info').textContent = 'Retomada agendada para ' + dt.toLocaleString('pt-BR');
            showToast && showToast('Retomada de contato agendada!');
        } else alert(data.error || 'Erro');
    });
}

// Formata o campo de valor do card como moeda BRL enquanto digita
function formatCardCurrency(el) {
    let digits = (el.value || '').replace(/\D/g, '');
    if (!digits) { el.value = ''; return; }
    const value = (parseInt(digits, 10) / 100);
    el.value = 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Extrai número de uma string monetária "R$ 1.234,56" -> 1234.56
function parseCurrency(str) {
    if (!str) return '';
    const digits = String(str).replace(/\D/g, '');
    if (!digits) return '';
    return (parseInt(digits, 10) / 100).toFixed(2);
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
    fd.append('label_id', document.getElementById('new-col-label').value);
    fd.append('status', document.getElementById('new-col-status').value);

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
