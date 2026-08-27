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
                    <div class="col-sm-6" id="item-approver-wrap">
                        <label class="form-label small fw-medium">Aprovador</label>
                        <select id="item-approver" class="form-select form-select-sm">
                            <option value="">Selecione o aprovador...</option>
                            <?php foreach ($approvers as $ap): ?>
                            <option value="<?= $ap['id'] ?>"><?= escape($ap['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Escolha um administrador. Ele recebe notificação de envio e de ajustes.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-medium">Status</label>
                        <select id="item-status" class="form-select form-select-sm">
                            <option value="rascunho">Rascunho</option>
                            <option value="ideia">Ideia</option>
                            <option value="em_producao">Em produção</option>
                            <option value="aguardando_aprovacao">Aguardando aprovação</option>
                            <option value="aprovado" class="opt-approve">Aprovado</option>
                            <option value="agendado">Agendado</option>
                            <option value="publicado">Publicado</option>
                            <option value="rejeitado" class="opt-reject">Rejeitado</option>
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

                    <!-- Agendar nas redes sociais (Buffer) — item aprovado -->
                    <div class="col-12" id="item-buffer-wrap" style="display:none;">
                        <hr>
                        <label class="form-label small fw-medium"><i class="bi bi-share"></i> Publicar nas redes sociais (Buffer)</label>
                        <div id="item-buffer-channels" class="d-flex flex-wrap gap-2 mb-2"></div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">URL da imagem (opcional)</span>
                            <input type="url" id="item-buffer-image" class="form-control" placeholder="https://... (arte pública)">
                        </div>
                        <button class="btn btn-sm btn-primary" onclick="scheduleToBuffer()"><i class="bi bi-calendar-check"></i> Agendar no Buffer</button>
                        <small class="text-muted d-block mt-1">Usa a data/horário do item. Selecione os canais acima.</small>
                        <div id="item-buffer-result" class="small mt-1"></div>
                    </div>

                    <!-- Anexos -->
                    <div class="col-12" id="item-attachments-wrap">
                        <label class="form-label small fw-medium">Anexos (artes, documentos, materiais)</label>
                        <div id="item-attachments" class="d-flex flex-wrap gap-2 mb-2"></div>
                        <!-- Lista de arquivos selecionados antes de salvar (item novo) -->
                        <div id="item-pending-files" class="d-flex flex-wrap gap-2 mb-2"></div>
                        <div class="d-flex gap-2">
                            <input type="file" id="item-file" class="form-control form-control-sm" multiple onchange="onItemFileChange()">
                            <button class="btn btn-sm btn-outline-primary" id="item-file-btn" onclick="uploadItemFile()" style="display:none;"><i class="bi bi-upload"></i></button>
                        </div>
                        <small class="text-muted" id="item-file-hint">Os anexos serão enviados ao salvar a demanda.</small>
                    </div>

                    <!-- Histórico da demanda -->
                    <div class="col-12" id="item-history-wrap" style="display:none;">
                        <hr>
                        <label class="form-label small fw-medium"><i class="bi bi-clock-history"></i> Histórico</label>
                        <div id="item-history" class="small" style="max-height:200px;overflow-y:auto;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger" id="item-delete-btn" onclick="deleteItem()" style="display:none;"><i class="bi bi-trash"></i> Excluir</button>
                    <button class="btn btn-sm btn-outline-success" id="item-notify-btn" onclick="notifyResponsible()" style="display:none;" title="Reenviar a notificação ao responsável via WhatsApp"><i class="bi bi-whatsapp"></i> Notificar responsável</button>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <!-- Ações de aprovação (admin) -->
                    <button class="btn btn-sm mkt-btn-warning mkt-approval-action" onclick="requestChanges()" style="display:none;"><i class="bi bi-arrow-counterclockwise"></i> Solicitar ajustes</button>
                    <button class="btn btn-sm mkt-btn-danger mkt-approval-action" onclick="rejectItem()" style="display:none;"><i class="bi bi-x-lg"></i> Rejeitar</button>
                    <button class="btn btn-sm btn-success mkt-approval-action" onclick="approveItem()" style="display:none;"><i class="bi bi-check-lg"></i> Aprovar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <!-- Salvar padrão (admin) -->
                    <button class="btn btn-sm btn-primary" id="item-save-btn" onclick="saveItem()"><i class="bi bi-check-lg"></i> Salvar</button>
                    <!-- Salvar (marketing): rascunho ou enviar para revisão -->
                    <button class="btn btn-sm btn-outline-secondary" id="item-save-draft-btn" onclick="saveItemAs('rascunho')" style="display:none;"><i class="bi bi-file-earmark"></i> Salvar como rascunho</button>
                    <button class="btn btn-sm btn-primary" id="item-save-review-btn" onclick="saveItemAs('aguardando_aprovacao')" style="display:none;"><i class="bi bi-send"></i> Salvar e enviar para revisão</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Solicitar ajustes (admin) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="requestChangesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="rc-modal-title"><i class="bi bi-arrow-counterclockwise text-warning"></i> Solicitar ajustes</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rc-item-id">
                <label class="form-label small fw-medium" id="rc-notes-label">Descreva os ajustes necessários</label>
                <textarea id="rc-notes" class="form-control form-control-sm" rows="4" placeholder="Ex: ajustar a chamada, revisar a arte, trocar a data..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm mkt-btn-warning" id="rc-confirm-btn" onclick="confirmRequestChanges()"><i class="bi bi-send"></i> Enviar solicitação</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                <p class="small text-muted">A IA identifica as datas comerciais e comemorativas relevantes do <strong>ano inteiro</strong> e insere no calendário. Ao clicar, novas datas são adicionadas e as já existentes são mantidas. Ela não cria briefing nem copy.</p>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Ano</label>
                    <input type="number" id="holiday-year" class="form-control form-control-sm" value="<?= date('Y') ?>" min="2020" max="2100">
                </div>
                <div id="holiday-result" class="small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-sm btn-primary" id="holiday-gen-btn" onclick="generateHolidays()"><i class="bi bi-stars"></i> Gerar datas do ano</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let itemModalInstance = null;
let currentItem = null;
let pendingFiles = []; // arquivos selecionados antes de salvar um item novo

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
    pendingFiles = [];
    ['item-id','item-holiday-id','item-title','item-scheduled','item-social','item-assigned','item-approver','item-briefing','item-copy'].forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
    document.getElementById('item-status').value = 'ideia';
    const hw = document.getElementById('item-history-wrap'); if (hw) hw.style.display = 'none';
    document.getElementById('item-review-alert').style.display = 'none';
    document.getElementById('item-attachments-wrap').style.display = '';
    document.getElementById('item-attachments').innerHTML = '';
    document.getElementById('item-pending-files').innerHTML = '';
    const bw = document.getElementById('item-buffer-wrap');
    if (bw) bw.style.display = 'none';
    document.getElementById('item-delete-btn').style.display = 'none';
    const dbtn = document.getElementById('item-save-draft-btn'); if (dbtn) dbtn.style.display = 'none';
    const rbtn = document.getElementById('item-save-review-btn'); if (rbtn) rbtn.style.display = 'none';
    const sbtn = document.getElementById('item-save-btn'); if (sbtn) sbtn.style.display = '';
    const nbtn = document.getElementById('item-notify-btn');
    if (nbtn) nbtn.style.display = 'none';
    document.querySelectorAll('.mkt-approval-action').forEach(b => b.style.display = 'none');
    document.getElementById('item-file').value = '';
    document.getElementById('item-file').disabled = false;
    document.getElementById('item-file-btn').style.display = 'none';
    document.getElementById('item-file-hint').style.display = '';
}

// Seleção de arquivos: se item novo, acumula em pendingFiles; se edição, mostra botão de upload
function onItemFileChange() {
    const id = document.getElementById('item-id').value;
    const input = document.getElementById('item-file');
    if (id) {
        // Edição: envia imediatamente
        uploadItemFile();
        return;
    }
    // Novo: acumula arquivos para enviar após salvar
    for (const f of input.files) pendingFiles.push(f);
    input.value = '';
    renderPendingFiles();
}

function renderPendingFiles() {
    const box = document.getElementById('item-pending-files');
    if (!pendingFiles.length) { box.innerHTML = ''; return; }
    box.innerHTML = pendingFiles.map((f, i) => {
        const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(f.name);
        const icon = isImg ? 'bi-file-earmark-image' : 'bi-file-earmark-text';
        return `<div class="d-flex align-items-center gap-2 border rounded p-1 pe-2" style="background:#f0faf8;">
            <i class="bi ${icon}" style="font-size:1.2rem;color:#00997D;"></i>
            <span class="small text-truncate" style="max-width:140px;">${escapeHtml(f.name)}</span>
            <button class="btn btn-sm btn-link text-danger p-0" onclick="removePendingFile(${i})"><i class="bi bi-x-lg"></i></button>
        </div>`;
    }).join('');
}

function removePendingFile(i) {
    pendingFiles.splice(i, 1);
    renderPendingFiles();
}

// UI para novo item conforme papel
function applyRoleUiForNew() {
    const assignedWrap = document.getElementById('item-assigned-wrap');
    const approverWrap = document.getElementById('item-approver-wrap');
    if (IS_ADMIN) {
        assignedWrap.style.display = '';
        document.querySelector('.mkt-marketing-only').style.display = 'none';
    } else {
        // marketing: sempre responsável; oculta seletor
        assignedWrap.style.display = 'none';
        document.querySelector('.mkt-marketing-only').style.display = '';
    }
    // Aprovador (sempre um admin): marketing e admin podem escolher.
    if (approverWrap) {
        approverWrap.style.display = '';
        document.getElementById('item-approver').disabled = false;
    }
    // Status: marketing usa os botões dedicados; select só habilitado p/ admin
    document.getElementById('item-status').disabled = !IS_ADMIN;
    // Botões de salvar por papel
    const saveBtn = document.getElementById('item-save-btn');
    const draftBtn = document.getElementById('item-save-draft-btn');
    const reviewBtn = document.getElementById('item-save-review-btn');
    if (IS_ADMIN) {
        saveBtn.style.display = '';
        if (draftBtn) draftBtn.style.display = 'none';
        if (reviewBtn) reviewBtn.style.display = 'none';
    } else {
        saveBtn.style.display = 'none';
        if (draftBtn) draftBtn.style.display = '';
        if (reviewBtn) reviewBtn.style.display = '';
    }
    toggleApproveOption();
}

// Esconde opções "Aprovado" e "Rejeitado" do select para não-admin (decisão do admin)
function toggleApproveOption() {
    const approve = document.querySelector('#item-status .opt-approve');
    if (approve) approve.style.display = IS_ADMIN ? '' : 'none';
    const reject = document.querySelector('#item-status .opt-reject');
    if (reject) reject.style.display = IS_ADMIN ? '' : 'none';
}

// Mostrar/ocultar seção do Buffer baseado no status selecionado
document.getElementById('item-status').addEventListener('change', function() {
    const bufferWrap = document.getElementById('item-buffer-wrap');
    if (['aprovado', 'agendado', 'publicado'].includes(this.value)) {
        bufferWrap.style.display = '';
        loadBufferChannels();
    } else {
        bufferWrap.style.display = 'none';
    }
});

function fillItemForm(it) {
    currentItem = it;
    document.getElementById('item-modal-title').textContent = 'Editar demanda';
    document.getElementById('item-id').value = it.id;
    document.getElementById('item-holiday-id').value = it.holiday_id || '';
    document.getElementById('item-title').value = it.title || '';
    document.getElementById('item-scheduled').value = it.scheduled_at ? it.scheduled_at.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('item-social').value = it.social_network || '';
    document.getElementById('item-assigned').value = it.assigned_to || '';
    const apprEl = document.getElementById('item-approver'); if (apprEl) apprEl.value = it.approver_id || '';
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

    // Campos de conteúdo: editáveis por quem gerencia (marketing responsável ou admin).
    ['item-title','item-social','item-briefing','item-copy'].forEach(f => document.getElementById(f).disabled = !canManage);
    // Status: admin edita livremente; marketing usa os botões (rascunho/revisão), então o select fica somente leitura.
    document.getElementById('item-status').disabled = !IS_ADMIN;

    // Botões de salvar conforme o papel
    const saveBtn = document.getElementById('item-save-btn');
    const draftBtn = document.getElementById('item-save-draft-btn');
    const reviewBtn = document.getElementById('item-save-review-btn');
    if (IS_ADMIN) {
        saveBtn.style.display = canManage ? '' : 'none';
        if (draftBtn) draftBtn.style.display = 'none';
        if (reviewBtn) reviewBtn.style.display = 'none';
    } else {
        // Marketing: esconde o "Salvar" genérico e usa os dois botões dedicados
        saveBtn.style.display = 'none';
        if (draftBtn) draftBtn.style.display = canManage ? '' : 'none';
        if (reviewBtn) reviewBtn.style.display = canManage ? '' : 'none';
    }
    document.getElementById('item-delete-btn').style.display = canManage ? '' : 'none';

    // Alerta de ajustes
    if (it.review_notes) {
        document.getElementById('item-review-notes').textContent = it.review_notes;
        document.getElementById('item-review-alert').style.display = '';
    }

    // Botão "Notificar responsável" (fallback manual): visível para quem gerencia
    // quando houver um responsável definido na demanda.
    const notifyBtn = document.getElementById('item-notify-btn');
    if (notifyBtn) notifyBtn.style.display = (canManage && it.assigned_to) ? '' : 'none';

    // Ações de aprovação (admin, item aguardando aprovação)
    if (IS_ADMIN && it.status === 'aguardando_aprovacao') {
        document.querySelectorAll('.mkt-approval-action').forEach(b => b.style.display = '');
    }

    // Agendamento no Buffer: disponível quando aprovado/agendado
    const bufferWrap = document.getElementById('item-buffer-wrap');
    if (['aprovado', 'agendado', 'publicado'].includes(it.status)) {
        bufferWrap.style.display = '';
        loadBufferChannels();
        document.getElementById('item-buffer-result').innerHTML = '';
    } else {
        bufferWrap.style.display = 'none';
    }

    // Anexos (item existente: upload direto)
    document.getElementById('item-attachments-wrap').style.display = '';
    document.getElementById('item-pending-files').innerHTML = '';
    document.getElementById('item-file-hint').style.display = 'none';
    document.getElementById('item-file').disabled = !canManage;
    document.getElementById('item-file').parentElement.style.display = canManage ? '' : 'none';
    renderAttachments(it.attachments || []);

    // Aprovador (sempre um admin): quem gerencia a demanda pode escolher.
    const apprWrap = document.getElementById('item-approver-wrap');
    if (apprWrap) {
        apprWrap.style.display = '';
        document.getElementById('item-approver').disabled = !canManage;
    }

    // Histórico da demanda
    renderHistory(it.history || []);
}

const MKT_HISTORY_LABELS = {
    created: '📝 Criada', updated: '✏️ Atualizada', submitted: '📤 Enviada p/ aprovação',
    changes_requested: '🔄 Ajustes solicitados', approved: '✅ Aprovada', rejected: '❌ Rejeitada',
    adjusted: '🛠️ Ajustes realizados'
};

function renderHistory(history) {
    const wrap = document.getElementById('item-history-wrap');
    const box = document.getElementById('item-history');
    if (!wrap || !box) return;
    if (!history.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    box.innerHTML = history.map(h => {
        const label = MKT_HISTORY_LABELS[h.action] || h.action;
        const when = h.created_at ? new Date(h.created_at.replace(' ', 'T')).toLocaleString('pt-BR') : '';
        const who = h.user_name ? ` — ${escapeHtml(h.user_name)}` : '';
        const notes = h.notes ? `<div class="text-muted" style="white-space:pre-wrap;">${escapeHtml(h.notes)}</div>` : '';
        return `<div class="border-start ps-2 mb-2" style="border-width:3px !important;border-color:#00997D !important;">
            <div class="fw-medium">${label}${who}</div>
            <div class="text-muted" style="font-size:0.72rem;">${when}</div>
            ${notes}
        </div>`;
    }).join('');
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
    if (IS_ADMIN) {
        fd.append('assigned_to', document.getElementById('item-assigned').value);
    }
    // Aprovador pode ser definido por quem gerencia (marketing ou admin)
    fd.append('approver_id', document.getElementById('item-approver').value);
    const hid = document.getElementById('item-holiday-id').value;
    if (hid) fd.append('holiday_id', hid);
    return fd;
}

// Salvar do marketing com destino explícito: 'rascunho' ou 'aguardando_aprovacao'.
function saveItemAs(targetStatus) {
    const title = document.getElementById('item-title').value.trim();
    if (!title) { alert('Informe o título.'); return; }

    // Para enviar à revisão é obrigatório ter imagem (existente ou nova).
    if (targetStatus === 'aguardando_aprovacao') {
        const hasExisting = currentItem && currentItem.has_image;
        const hasNewImg = pendingFiles.some(f => /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(f.name));
        if (!hasExisting && !hasNewImg) {
            alert('Anexe ao menos uma imagem para enviar à revisão. Sem imagem, salve como rascunho.');
            return;
        }
    }
    // Define o status desejado e reaproveita o fluxo de salvamento
    document.getElementById('item-status').value = targetStatus;
    saveItem(true);
}

function saveItem(skipDraftGuard) {
    const title = document.getElementById('item-title').value.trim();
    if (!title) { alert('Informe o título.'); return; }
    const id = document.getElementById('item-id').value;
    const statusSel = document.getElementById('item-status');
    const status = statusSel.value;
    if (skipDraftGuard === true) {
        // Chamado por saveItemAs — validação de imagem já feita; segue direto.
        return doSaveItem(id);
    }

    // Regra (marketing): sem imagem, só pode salvar como rascunho.
    const needsImage = ['em_producao','aguardando_aprovacao','aprovado','agendado','publicado'].includes(status);
    if (!IS_ADMIN && needsImage) {
        const hasExisting = currentItem && currentItem.has_image;
        const hasNewImg = pendingFiles.some(f => /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(f.name));
        if (!hasExisting && !hasNewImg) {
            if (!confirm('Esta demanda ainda não tem imagem. Sem imagem só é possível salvar como RASCUNHO. Deseja salvar como rascunho?')) return;
            statusSel.value = 'rascunho';
        }
    }

    doSaveItem(id);
}

// Executa o salvamento (create/update) + upload dos anexos pendentes.
function doSaveItem(id) {
    const url = id ? `${BASE}marketing/update/${id}` : `${BASE}marketing/create`;

    fetch(url, { method: 'POST', body: collectItemPayload(), headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            if (!id && pendingFiles.length) {
                // Envia os anexos selecionados antes de salvar
                uploadPendingFiles(data.item.id).then(() => { getItemModal().hide(); afterItemChange(); });
            } else {
                getItemModal().hide();
                afterItemChange();
            }
        });
}

// Faz upload sequencial dos arquivos para o item (usa pendingFiles se não vier lista)
function uploadPendingFiles(itemId, files = null) {
    if (files === null) { files = pendingFiles.slice(); pendingFiles = []; }
    return files.reduce((chain, f) => chain.then(() => {
        const fd = new FormData();
        fd.append('file', f);
        return fetch(`${BASE}marketing/upload/${itemId}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json());
    }), Promise.resolve());
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

let requestChangesModalInstance = null;
let rcMode = 'ajustes'; // 'ajustes' ou 'rejeitar'

function requestChanges() {
    openRcModal('ajustes');
}

function rejectItem() {
    openRcModal('rejeitar');
}

function openRcModal(mode) {
    const id = document.getElementById('item-id').value;
    if (!id) return;
    rcMode = mode;
    document.getElementById('rc-item-id').value = id;
    document.getElementById('rc-notes').value = '';

    const title = document.getElementById('rc-modal-title');
    const confirmBtn = document.getElementById('rc-confirm-btn');
    const notesLabel = document.getElementById('rc-notes-label');
    if (mode === 'rejeitar') {
        title.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Rejeitar conteúdo';
        confirmBtn.className = 'btn btn-sm mkt-btn-danger';
        confirmBtn.innerHTML = '<i class="bi bi-x-lg"></i> Rejeitar';
        notesLabel.textContent = 'Motivo da rejeição (opcional)';
    } else {
        title.innerHTML = '<i class="bi bi-arrow-counterclockwise text-warning"></i> Solicitar ajustes';
        confirmBtn.className = 'btn btn-sm mkt-btn-warning';
        confirmBtn.innerHTML = '<i class="bi bi-send"></i> Enviar solicitação';
        notesLabel.textContent = 'Descreva os ajustes necessários';
    }

    if (!requestChangesModalInstance) requestChangesModalInstance = new bootstrap.Modal(document.getElementById('requestChangesModal'));
    requestChangesModalInstance.show();
}

function confirmRequestChanges() {
    const id = document.getElementById('rc-item-id').value;
    const notes = document.getElementById('rc-notes').value.trim();
    if (!id) return;
    // Ajustes exige texto; rejeição o motivo é opcional
    if (rcMode === 'ajustes' && !notes) { document.getElementById('rc-notes').focus(); return; }

    const endpoint = rcMode === 'rejeitar' ? 'reject' : 'requestChanges';
    const btn = document.getElementById('rc-confirm-btn');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('review_notes', notes);
    fetch(`${BASE}marketing/${endpoint}/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false;
            if (data.error) { alert(data.error); return; }
            if (requestChangesModalInstance) requestChangesModalInstance.hide();
            getItemModal().hide();
            afterItemChange();
        })
        .catch(() => { btn.disabled = false; });
}

// Reenvia manualmente ao responsável a notificação da demanda via WhatsApp.
// Fallback para quando o disparo automático não ocorreu. A mensagem é montada
// no backend conforme o status atual da demanda.
function notifyResponsible() {
    const id = document.getElementById('item-id').value;
    if (!id) return;
    if (!confirm('Reenviar a notificação desta demanda para o responsável via WhatsApp?')) return;

    const btn = document.getElementById('item-notify-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...'; }
    fetch(`${BASE}marketing/notifyResponsible/${id}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-whatsapp"></i> Notificar responsável'; }
            if (data.error) { alert(data.error); return; }
            alert('Notificação enviada ao responsável via WhatsApp.');
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-whatsapp"></i> Notificar responsável'; }
            alert('Erro ao enviar a notificação.');
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
    if (!id || !input.files.length) return;
    uploadPendingFiles(id, Array.from(input.files)).then(() => {
        input.value = '';
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

// ===== Integração Buffer (agendamento social) =====
let bufferChannelsCache = null;

function loadBufferChannels() {
    const box = document.getElementById('item-buffer-channels');
    const render = (channels) => {
        if (!channels.length) {
            box.innerHTML = '<span class="text-muted small">Nenhum canal. Sincronize em Métricas Sociais.</span>';
            return;
        }
        box.innerHTML = channels.map(c => `
            <label class="d-inline-flex align-items-center gap-1 border rounded px-2 py-1" style="cursor:pointer;font-size:0.78rem;user-select:none;">
                <input type="checkbox" class="form-check-input buffer-channel-cb" value="${c.channel_id}" style="width:16px;height:16px;margin:0;">
                <i class="bi bi-${bufferIcon(c.service)}"></i> ${escapeHtml(c.name || c.service)}
            </label>`).join('');
    };
    if (bufferChannelsCache) { render(bufferChannelsCache); return; }
    fetch(`${BASE}buffer/channels`).then(r => r.json()).then(data => {
        bufferChannelsCache = data.channels || [];
        render(bufferChannelsCache);
    });
}

function bufferIcon(service) {
    const map = { instagram:'instagram', facebook:'facebook', linkedin:'linkedin', twitter:'twitter-x', youtube:'youtube', tiktok:'tiktok', pinterest:'pinterest' };
    return map[service] || 'share';
}

function scheduleToBuffer() {
    const id = document.getElementById('item-id').value;
    const channels = Array.from(document.querySelectorAll('.buffer-channel-cb:checked')).map(cb => cb.value);
    const result = document.getElementById('item-buffer-result');
    const btn = document.querySelector('[onclick="scheduleToBuffer()"]');
    if (!channels.length) { result.innerHTML = '<span class="text-danger">Selecione ao menos um canal.</span>'; return; }

    // Se é nova demanda (sem ID), avisar que precisa salvar primeiro
    if (!id) {
        result.innerHTML = '<span class="text-danger">Salve a demanda primeiro antes de agendar no Buffer.</span>';
        return;
    }

    const text = document.getElementById('item-copy').value.trim() || document.getElementById('item-title').value.trim();
    const dueAt = document.getElementById('item-scheduled').value;
    const imageUrl = document.getElementById('item-buffer-image').value.trim();

    const fd = new FormData();
    fd.append('marketing_item_id', id);
    fd.append('text', text);
    fd.append('channel_ids', channels.join(','));
    if (dueAt) fd.append('due_at', dueAt.replace('T', ' '));
    if (imageUrl) fd.append('image_url', imageUrl);

    // Desabilitar botão para evitar cliques duplos
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Agendando...'; }
    result.innerHTML = '<span class="text-muted">Agendando no Buffer, aguarde...</span>';
    fetch(`${BASE}buffer/schedule`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            if (data.error) { result.innerHTML = `<span class="text-danger">${data.error}</span>`; return; }
            if (data.message) {
                result.innerHTML = `<div class="alert alert-success small py-2 px-3 mb-0 mt-1"><i class="bi bi-check-circle"></i> ${data.message}</div>`;
            } else {
                result.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> ${data.created} publicação(ões) agendada(s) no Buffer com sucesso!</span>`;
            }
            // Marca o item como agendado
            const fd2 = new FormData();
            fd2.append('status', 'agendado');
            fetch(`${BASE}marketing/update/${id}`, { method: 'POST', body: fd2, headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(() => afterItemChange());
        })
        .catch(() => { result.innerHTML = '<span class="text-danger">Erro na requisição.</span>'; })
        .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-calendar-check"></i> Agendar no Buffer'; } });
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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando ano inteiro...';
    result.innerHTML = '<span class="text-muted">Consultando a IA, isso pode levar alguns segundos...</span>';
    const fd = new FormData();
    fd.append('year', document.getElementById('holiday-year').value);
    fetch(`${BASE}marketing/generateHolidays`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stars"></i> Gerar datas do ano';
            if (data.error) { result.innerHTML = `<span class="text-danger">${data.error}</span>`; return; }
            const parts = [`<i class="bi bi-check-circle"></i> ${data.inserted} nova(s) data(s) adicionada(s) em ${data.year}.`];
            if (data.skipped) parts.push(`${data.skipped} já existiam.`);
            result.innerHTML = `<span class="text-success">${parts.join(' ')}</span>`;
            loadCalendar();
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-stars"></i> Gerar datas do ano'; result.innerHTML = '<span class="text-danger">Erro na requisição.</span>'; });
}
<?php endif; ?>
</script>
