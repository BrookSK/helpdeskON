<!-- Modal: Item / Demanda -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-megaphone"></i> <span id="item-modal-title">Nova demanda</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="item-id">
                <input type="hidden" id="item-holiday-id">

                <div id="item-review-alert" class="alert alert-warning py-2 px-3 small" style="display:none;">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Ajustes solicitados:</strong> <span id="item-review-notes"></span>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-medium">Título *</label>
                        <input type="text" id="item-title" class="form-control form-control-sm" placeholder="Ex: Post Dia do Cliente">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-medium">Data e horário</label>
                        <input type="datetime-local" id="item-scheduled" class="form-control form-control-sm">
                        <small class="text-muted mkt-admin-only" style="display:none;">Datas podem ser alteradas pelo admin.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-medium">Rede social</label>
                        <select id="item-social" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <?php foreach ($socialNetworks as $sn): ?>
                            <option value="<?= escape($sn) ?>"><?= escape($sn) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6" id="item-assigned-wrap">
                        <label class="form-label small fw-medium">Responsável</label>
                        <select id="item-assigned" class="form-select form-select-sm">
                            <option value="">Sem responsável</option>
                            <?php foreach ($team as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= escape($t['name']) ?> (<?= roleLabel($t['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted mkt-marketing-only" style="display:none;">Você será o responsável por esta demanda.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-medium">Status</label>
                        <select id="item-status" class="form-select form-select-sm">
                            <option value="ideia">Ideia</option>
                            <option value="em_producao">Em produção</option>
                            <option value="aguardando_aprovacao">Aguardando aprovação</option>
                            <option value="aprovado" class="opt-approve">Aprovado</option>
                            <option value="agendado">Agendado</option>
                            <option value="publicado">Publicado</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Briefing</label>
                        <textarea id="item-briefing" class="form-control form-control-sm" rows="3" placeholder="Objetivo, público, referências..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-medium">Copy</label>
                        <textarea id="item-copy" class="form-control form-control-sm" rows="4" placeholder="Texto do conteúdo..."></textarea>
                    </div>

                    <!-- Anexos (apenas ao editar item existente) -->
                    <div class="col-12" id="item-attachments-wrap" style="display:none;">
                        <label class="form-label small fw-medium">Anexos (artes, documentos, materiais)</label>
                        <div id="item-attachments" class="d-flex flex-wrap gap-2 mb-2"></div>
                        <div class="d-flex gap-2">
                            <input type="file" id="item-file" class="form-control form-control-sm">
                            <button class="btn btn-sm btn-outline-primary" onclick="uploadItemFile()"><i class="bi bi-upload"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button class="btn btn-sm btn-outline-danger" id="item-delete-btn" onclick="deleteItem()" style="display:none;"><i class="bi bi-trash"></i> Excluir</button>
                </div>
                <div class="d-flex gap-2">
                    <!-- Ações de aprovação (admin) -->
                    <button class="btn btn-sm btn-outline-warning mkt-approval-action" onclick="requestChanges()" style="display:none;"><i class="bi bi-arrow-counterclockwise"></i> Solicitar ajustes</button>
                    <button class="btn btn-sm btn-success mkt-approval-action" onclick="approveItem()" style="display:none;"><i class="bi bi-check-lg"></i> Aprovar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button class="btn btn-sm btn-primary" id="item-save-btn" onclick="saveItem()"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Datas comemorativas com IA (admin) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="holidaysModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-stars text-warning"></i> Datas comemorativas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">A IA identifica datas comerciais e comemorativas relevantes e insere no calendário. Ela não cria briefing nem copy.</p>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Mês</label>
                    <select id="holiday-month" class="form-select form-select-sm">
                        <?php $mn=['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro']; foreach($mn as $i=>$m): ?>
                        <option value="<?= $i+1 ?>" <?= ($i+1)==date('n')?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Ano</label>
                    <input type="number" id="holiday-year" class="form-control form-control-sm" value="<?= date('Y') ?>">
                </div>
                <div id="holiday-result" class="small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-sm btn-primary" id="holiday-gen-btn" onclick="generateHolidays()"><i class="bi bi-stars"></i> Buscar datas</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let itemModalInstance = null;
let currentItem = null;

function getItemModal() {
    if (!itemModalInstance) itemModalInstance = new bootstrap.Modal(document.getElementById('itemModal'));
    return itemModalInstance;
}

// Abre modal: novo (id null) ou edição. dateStr opcional pré-preenche a data.
function openItemModal(id = null, dateStr = null) {
    resetItemForm();
    if (dateStr) {
        document.getElementById('item-scheduled').value = dateStr + 'T09:00';
    }
    if (id) {
        fetch(`${BASE}marketing/get/${id}`).then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            fillItemForm(data.item);
            getItemModal().show();
        });
    } else {
        document.getElementById('item-modal-title').textContent = 'Nova demanda';
        applyRoleUiForNew();
        getItemModal().show();
    }
}

// Abre criação de conteúdo já vinculado a uma data comemorativa
function openHolidayCreate(holidayId, title, dateStr) {
    resetItemForm();
    document.getElementById('item-modal-title').textContent = 'Criar conteúdo para data comemorativa';
    document.getElementById('item-holiday-id').value = holidayId;
    document.getElementById('item-title').value = title;
    document.getElementById('item-scheduled').value = dateStr + 'T09:00';
    applyRoleUiForNew();
    getItemModal().show();
}

function resetItemForm() {
    currentItem = null;
    ['item-id','item-holiday-id','item-title','item-scheduled','item-social','item-assigned','item-briefing','item-copy'].forEach(f => document.getElementById(f).value = '');
    document.getElementById('item-status').value = 'ideia';
    document.getElementById('item-review-alert').style.display = 'none';
    document.getElementById('item-attachments-wrap').style.display = 'none';
    document.getElementById('item-attachments').innerHTML = '';
    document.getElementById('item-delete-btn').style.display = 'none';
    document.querySelectorAll('.mkt-approval-action').forEach(b => b.style.display = 'none');
    document.getElementById('item-file').value = '';
}

// UI para novo item conforme papel
function applyRoleUiForNew() {
    const assignedWrap = document.getElementById('item-assigned-wrap');
    if (IS_ADMIN) {
        assignedWrap.style.display = '';
        document.querySelector('.mkt-marketing-only').style.display = 'none';
    } else {
        // marketing: sempre responsável; oculta seletor
        assignedWrap.style.display = 'none';
        document.querySelector('.mkt-marketing-only').style.display = '';
    }
    toggleApproveOption();
}

// Esconde opção "Aprovado" do select para não-admin
function toggleApproveOption() {
    const opt = document.querySelector('#item-status .opt-approve');
    if (opt) opt.style.display = IS_ADMIN ? '' : 'none';
}

function fillItemForm(it) {
    currentItem = it;
    document.getElementById('item-modal-title').textContent = 'Editar demanda';
    document.getElementById('item-id').value = it.id;
    document.getElementById('item-holiday-id').value = it.holiday_id || '';
    document.getElementById('item-title').value = it.title || '';
    document.getElementById('item-scheduled').value = it.scheduled_at ? it.scheduled_at.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('item-social').value = it.social_network || '';
    document.getElementById('item-assigned').value = it.assigned_to || '';
    document.getElementById('item-briefing').value = it.briefing || '';
    document.getElementById('item-copy').value = it.copy || '';
    document.getElementById('item-status').value = it.status || 'ideia';
    toggleApproveOption();

    const canManage = it.can_manage;
    // Responsável: só admin altera
    document.getElementById('item-assigned-wrap').style.display = IS_ADMIN ? '' : 'none';
    document.querySelector('.mkt-marketing-only').style.display = 'none';
    // Data: só admin altera
    document.getElementById('item-scheduled').disabled = !IS_ADMIN;

    // Campos editáveis apenas por quem gerencia
    ['item-title','item-social','item-briefing','item-copy','item-status'].forEach(f => document.getElementById(f).disabled = !canManage);
    document.getElementById('item-save-btn').style.display = canManage ? '' : 'none';
    document.getElementById('item-delete-btn').style.display = canManage ? '' : 'none';

    // Alerta de ajustes
    if (it.review_notes) {
        document.getElementById('item-review-notes').textContent = it.review_notes;
        document.getElementById('item-review-alert').style.display = '';
    }

    // Ações de aprovação (admin, item aguardando aprovação)
    if (IS_ADMIN && it.status === 'aguardando_aprovacao') {
        document.querySelectorAll('.mkt-approval-action').forEach(b => b.style.display = '');
    }

    // Anexos
    document.getElementById('item-attachments-wrap').style.display = '';
    renderAttachments(it.attachments || []);
}

function renderAttachments(atts) {
    const box = document.getElementById('item-attachments');
    if (!atts.length) { box.innerHTML = '<span class="text-muted small">Nenhum anexo.</span>'; return; }
    box.innerHTML = atts.map(a => {
        const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(a.file_name);
        const thumb = isImg
            ? `<img src="${BASE + a.file_path}" style="width:46px;height:46px;object-fit:cover;border-radius:6px;">`
            : `<i class="bi bi-file-earmark-text" style="font-size:1.4rem;color:#00997D;"></i>`;
        const canDel = currentItem && currentItem.can_manage;
        return `<div class="d-flex align-items-center gap-2 border rounded p-1 pe-2" style="background:#fafbfc;">
            <a href="${BASE + a.file_path}" target="_blank">${thumb}</a>
            <span class="small text-truncate" style="max-width:120px;">${escapeHtml(a.file_name)}</span>
            ${canDel ? `<button class="btn btn-sm btn-link text-danger p-0" onclick="deleteAttachment(${a.id})"><i class="bi bi-x-lg"></i></button>` : ''}
        </div>`;
    }).join('');
}

function collectItemPayload() {
    const fd = new FormData();
    fd.append('title', document.getElementById('item-title').value.trim());
    fd.append('scheduled_at', document.getElementById('item-scheduled').value.replace('T', ' '));
    fd.append('social_network', document.getElementById('item-social').value);
    fd.append('briefing', document.getElementById('item-briefing').value);
    fd.append('copy', document.getElementById('item-copy').value);
    fd.append('status', document.getElementById('item-status').value);
    if (IS_ADMIN) fd.append('assigned_to', document.getElementById('item-assigned').value);
    const hid = document.getElementById('item-holiday-id').value;
    if (hid) fd.append('holiday_id', hid);
    return fd;
}

function saveItem() {
    const title = document.getElementById('item-title').value.trim();
    if (!title) { alert('Informe o título.'); return; }
    const id = document.getElementById('item-id').value;
    const url = id ? `${BASE}marketing/update/${id}` : `${BASE}marketing/create`;

    fetch(url, { method: 'POST', body: collectItemPayload(), headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            if (!id) {
                // Reabrir em modo edição para permitir anexos
                fillItemForm({ ...data.item, can_manage: true, is_admin: IS_ADMIN });
            } else {
                getItemModal().hide();
            }
            afterItemChange();
        });
}

function approveItem() {
    const id = document.getElementById('item-id').value;
    if (!id) return;
    fetch(`${BASE}marketing/approve/${id}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            getItemModal().hide();
            afterItemChange();
        });
}

function requestChanges() {
    const id = document.getElementById('item-id').value;
    if (!id) return;
    const notes = prompt('Descreva os ajustes necessários:');
    if (notes === null) return;
    const fd = new FormData();
    fd.append('review_notes', notes);
    fetch(`${BASE}marketing/requestChanges/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            getItemModal().hide();
            afterItemChange();
        });
}

function deleteItem() {
    const id = document.getElementById('item-id').value;
    if (!id || !confirm('Excluir esta demanda?')) return;
    fetch(`${BASE}marketing/delete/${id}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            getItemModal().hide();
            afterItemChange();
        });
}

function uploadItemFile() {
    const id = document.getElementById('item-id').value;
    const input = document.getElementById('item-file');
    if (!id || !input.files[0]) return;
    const fd = new FormData();
    fd.append('file', input.files[0]);
    fetch(`${BASE}marketing/upload/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            input.value = '';
            // Recarrega anexos
            fetch(`${BASE}marketing/get/${id}`).then(r => r.json()).then(d => { currentItem = d.item; renderAttachments(d.item.attachments || []); });
        });
}

function deleteAttachment(attId) {
    if (!confirm('Remover anexo?')) return;
    fetch(`${BASE}marketing/deleteAttachment/${attId}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); return; }
            const id = document.getElementById('item-id').value;
            fetch(`${BASE}marketing/get/${id}`).then(r => r.json()).then(d => { currentItem = d.item; renderAttachments(d.item.attachments || []); });
        });
}

// Recarrega a aba ativa após mudanças
function afterItemChange() {
    const active = document.querySelector('#mkt-tabs .nav-link.active');
    const tab = active ? active.dataset.tab : 'calendario';
    if (tab === 'calendario') loadCalendar();
    else loadItems(tab);
    refreshApprovalCount();
}

<?php if ($isAdmin): ?>
function openHolidaysModal() {
    document.getElementById('holiday-result').innerHTML = '';
    new bootstrap.Modal(document.getElementById('holidaysModal')).show();
}

function generateHolidays() {
    const btn = document.getElementById('holiday-gen-btn');
    const result = document.getElementById('holiday-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Buscando...';
    const fd = new FormData();
    fd.append('month', document.getElementById('holiday-month').value);
    fd.append('year', document.getElementById('holiday-year').value);
    fetch(`${BASE}marketing/generateHolidays`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stars"></i> Buscar datas';
            if (data.error) { result.innerHTML = `<span class="text-danger">${data.error}</span>`; return; }
            result.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> ${data.inserted} data(s) inserida(s) no calendário.</span>`;
            loadCalendar();
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-stars"></i> Buscar datas'; result.innerHTML = '<span class="text-danger">Erro na requisição.</span>'; });
}
<?php endif; ?>
</script>
