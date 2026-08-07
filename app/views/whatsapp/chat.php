<?php $pageTitle = 'WhatsApp Chat'; $currentPage = 'whatsapp_chat'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content p-0" style="height:100vh;display:flex;flex-direction:column;">
    <!-- Top bar compacta -->
    <div class="wpp-topbar">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-whatsapp text-success fs-5"></i>
            <span class="fw-medium">WhatsApp Chat</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('whatsapp') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i></a>
            <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-kanban"></i> CRM</a>
        </div>
    </div>

    <!-- Layout principal 3 colunas -->
    <div class="wpp-layout">
        <!-- COLUNA ESQUERDA: Lista de contatos -->
        <div class="wpp-contacts-panel" id="contacts-panel">
            <div class="wpp-contacts-header">
                <input type="text" class="form-control form-control-sm" id="contact-search" placeholder="Buscar contato...">
                <div class="d-flex gap-1 mt-2 flex-wrap">
                    <select class="form-select form-select-sm" id="filter-assigned" style="font-size:0.72rem;max-width:130px;">
                        <option value="">Todos</option>
                        <option value="unassigned">Sem dono</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="filter-label" style="font-size:0.72rem;max-width:120px;">
                        <option value="">Etiquetas</option>
                        <?php foreach ($labels as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= escape($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="wpp-contacts-list" id="contacts-list">
                <div class="text-center py-4 text-muted small">Carregando contatos...</div>
            </div>
        </div>

        <!-- COLUNA CENTRAL: Mensagens -->
        <div class="wpp-chat-panel" id="chat-panel">
            <div class="wpp-chat-empty" id="chat-empty">
                <i class="bi bi-chat-text" style="font-size:4rem;color:#ccc;"></i>
                <p class="text-muted mt-2">Selecione um contato para iniciar</p>
            </div>
            <!-- Header do chat ativo -->
            <div class="wpp-chat-header" id="chat-header" style="display:none;">
                <div class="d-flex align-items-center gap-2 cursor-pointer" onclick="toggleDetailPanel()">
                    <div class="wpp-avatar-sm" id="chat-avatar">?</div>
                    <div>
                        <div class="fw-medium" style="font-size:0.88rem;" id="chat-contact-name">—</div>
                        <small class="text-muted" id="chat-contact-phone"></small>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetailPanel()" title="Detalhes">
                        <i class="bi bi-person-lines-fill"></i>
                    </button>
                </div>
            </div>
            <!-- Área de mensagens -->
            <div class="wpp-messages" id="messages-area" style="display:none;"></div>
            <!-- Input de mensagem -->
            <div class="wpp-input-area" id="input-area" style="display:none;">
                <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('media-input').click()" title="Enviar arquivo">
                    <i class="bi bi-paperclip"></i>
                </button>
                <input type="file" id="media-input" style="display:none;" onchange="sendMediaFile()">
                <input type="text" class="form-control form-control-sm" id="message-input" placeholder="Digite uma mensagem..." onkeypress="if(event.key==='Enter')sendMessage()">
                <button class="btn btn-sm btn-success" onclick="sendMessage()"><i class="bi bi-send"></i></button>
            </div>
        </div>

        <!-- COLUNA DIREITA: Detalhes do contato -->
        <div class="wpp-detail-panel" id="detail-panel">
            <div class="wpp-detail-header">
                <span class="fw-medium">Detalhes do Contato</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetailPanel()"><i class="bi bi-x"></i></button>
            </div>
            <div class="wpp-detail-body" id="detail-body">
                <div class="text-center py-4">
                    <div class="wpp-avatar-lg mx-auto" id="detail-avatar">?</div>
                    <h6 class="mt-2" id="detail-name">—</h6>
                    <small class="text-muted" id="detail-phone">—</small>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome</label>
                    <input type="text" class="form-control form-control-sm" id="detail-name-input">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Atribuído a</label>
                    <select class="form-select form-select-sm" id="detail-assigned">
                        <option value="">Ninguém</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Observações Internas</label>
                    <textarea class="form-control form-control-sm" id="detail-notes" rows="4" placeholder="Notas sobre este contato..."></textarea>
                </div>
                <button class="btn btn-sm btn-primary w-100 mb-3" onclick="saveContactDetails()">
                    <i class="bi bi-check-lg"></i> Salvar
                </button>
                <hr>
                <!-- Etiquetas -->
                <div class="mb-3">
                    <label class="form-label small fw-medium">Etiquetas</label>
                    <div id="detail-labels" class="d-flex flex-wrap gap-1 mb-2"></div>
                    <div class="d-flex gap-1">
                        <select class="form-select form-select-sm" id="add-label-select" style="font-size:0.75rem;">
                            <option value="">+ Adicionar</option>
                            <?php foreach ($labels as $l): ?>
                            <option value="<?= $l['id'] ?>" data-color="<?= $l['color'] ?>"><?= escape($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addLabelToContact()" style="font-size:0.72rem;">+</button>
                    </div>
                </div>
                <hr>
                <!-- Adicionar ao CRM -->
                <div class="mb-3">
                    <label class="form-label small fw-medium">CRM</label>
                    <div class="d-flex gap-1">
                        <select class="form-select form-select-sm" id="crm-board-select" style="font-size:0.75rem;" onchange="loadBoardColumns()">
                            <option value="">Selecione board</option>
                        </select>
                        <select class="form-select form-select-sm" id="crm-column-select" style="font-size:0.75rem;display:none;"></select>
                    </div>
                    <button class="btn btn-sm btn-outline-success w-100 mt-2" onclick="addContactToCrm()">
                        <i class="bi bi-kanban"></i> Adicionar ao CRM
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wpp-topbar { padding: 10px 20px; background: #fff; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.wpp-layout { display: flex; flex: 1; overflow: hidden; }
.wpp-contacts-panel { width: 320px; min-width: 320px; border-right: 1px solid #e9ecef; display: flex; flex-direction: column; background: #fff; }
.wpp-contacts-header { padding: 10px; border-bottom: 1px solid #f0f0f0; }
.wpp-contacts-list { flex: 1; overflow-y: auto; }
.wpp-contact-item { padding: 10px 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; border-bottom: 1px solid #f8f8f8; transition: background 0.15s; }
.wpp-contact-item:hover { background: #f0faf8; }
.wpp-contact-item.active { background: #e0f7f4; border-left: 3px solid var(--primary); }
.wpp-avatar-sm { width: 38px; height: 38px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; color: #555; flex-shrink: 0; font-weight: 600; }
.wpp-avatar-lg { width: 70px; height: 70px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #555; font-weight: 600; }
.wpp-contact-info { flex: 1; min-width: 0; }
.wpp-contact-name { font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpp-contact-last { font-size: 0.72rem; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wpp-contact-meta { text-align: right; flex-shrink: 0; }
.wpp-contact-time { font-size: 0.65rem; color: #999; }
.wpp-unread { background: var(--primary); color: #fff; font-size: 0.6rem; padding: 2px 6px; border-radius: 10px; display: inline-block; margin-top: 2px; }
.wpp-chat-panel { flex: 1; display: flex; flex-direction: column; background: #efeae2; position: relative; }
.wpp-chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.wpp-chat-header { padding: 10px 16px; background: #fff; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
.wpp-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 4px; }
.wpp-input-area { padding: 10px 16px; background: #fff; border-top: 1px solid #e9ecef; display: flex; gap: 8px; align-items: center; }
.wpp-detail-panel { width: 320px; min-width: 320px; background: #fff; border-left: 1px solid #e9ecef; display: none; flex-direction: column; overflow-y: auto; }
.wpp-detail-panel.open { display: flex; }
.wpp-detail-header { padding: 12px 16px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
.wpp-detail-body { padding: 16px; overflow-y: auto; flex: 1; }
.wpp-msg { max-width: 65%; padding: 8px 12px; border-radius: 8px; font-size: 0.84rem; word-wrap: break-word; position: relative; }
.wpp-msg.mine { align-self: flex-end; background: #d9fdd3; border-bottom-right-radius: 2px; }
.wpp-msg.other { align-self: flex-start; background: #fff; border-bottom-left-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
.wpp-msg-time { font-size: 0.62rem; color: #888; margin-top: 3px; text-align: right; }
.wpp-msg-media img { max-width: 220px; border-radius: 6px; cursor: pointer; }
.wpp-msg-media audio { max-width: 220px; }
.wpp-label-badge { font-size: 0.65rem; padding: 2px 8px; border-radius: 10px; color: #fff; display: inline-block; }
.cursor-pointer { cursor: pointer; }
@media (max-width: 768px) {
    .wpp-contacts-panel { width: 100%; min-width: 100%; }
    .wpp-chat-panel { display: none; }
    .wpp-chat-panel.active { display: flex; width: 100%; }
    .wpp-detail-panel { position: absolute; right: 0; top: 0; bottom: 0; z-index: 10; width: 100%; }
}
</style>

<script>
const BASE = '<?= baseUrl("") ?>';
let activeContactId = <?= $activeContactId ? intval($activeContactId) : 'null' ?>;
let pollInterval = null;
let lastMessageId = 0;

// =========================================
// CONTATOS
// =========================================
function loadContacts() {
    const search = document.getElementById('contact-search').value;
    const assigned = document.getElementById('filter-assigned').value;
    const label = document.getElementById('filter-label').value;

    let url = BASE + 'whatsapp/contacts?';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (assigned) url += 'assigned_to=' + encodeURIComponent(assigned) + '&';
    if (label) url += 'label_id=' + encodeURIComponent(label) + '&';

    fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(contacts => {
        const list = document.getElementById('contacts-list');
        if (!contacts.length) {
            list.innerHTML = '<div class="text-center py-4 text-muted small">Nenhum contato encontrado</div>';
            return;
        }
        list.innerHTML = contacts.map(c => {
            const initials = (c.contact_name || c.push_name || c.phone || '?').substring(0, 2).toUpperCase();
            const name = c.contact_name || c.push_name || c.phone || 'Desconhecido';
            const time = c.last_message_at ? formatTime(c.last_message_at) : '';
            const isActive = activeContactId == c.id ? 'active' : '';
            const unread = c.unread_count > 0 ? `<span class="wpp-unread">${c.unread_count}</span>` : '';
            const assignedBadge = c.assigned_name ? `<small style="font-size:0.6rem;color:var(--primary);">${c.assigned_name}</small>` : '';
            return `<div class="wpp-contact-item ${isActive}" onclick="openChat(${c.id})" data-id="${c.id}">
                <div class="wpp-avatar-sm">${initials}</div>
                <div class="wpp-contact-info">
                    <div class="wpp-contact-name">${escapeHtml(name)}</div>
                    <div class="wpp-contact-last">${assignedBadge}</div>
                </div>
                <div class="wpp-contact-meta">
                    <div class="wpp-contact-time">${time}</div>
                    ${unread}
                </div>
            </div>`;
        }).join('');
    });
}

// =========================================
// CHAT
// =========================================
function openChat(contactId) {
    activeContactId = contactId;

    // Highlight
    document.querySelectorAll('.wpp-contact-item').forEach(el => el.classList.remove('active'));
    const active = document.querySelector(`.wpp-contact-item[data-id="${contactId}"]`);
    if (active) active.classList.add('active');

    // Show chat UI
    document.getElementById('chat-empty').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('messages-area').style.display = 'flex';
    document.getElementById('input-area').style.display = 'flex';

    // Load messages
    document.getElementById('messages-area').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';

    fetch(BASE + 'whatsapp/messages/' + contactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(messages => {
        renderMessages(messages);
        scrollToBottom();
        startPolling();
    });

    // Load contact detail
    fetch(BASE + 'whatsapp/contactDetail/' + contactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(contact => {
        const name = contact.contact_name || contact.push_name || contact.phone || 'Desconhecido';
        const initials = name.substring(0, 2).toUpperCase();
        document.getElementById('chat-contact-name').textContent = name;
        document.getElementById('chat-contact-phone').textContent = contact.phone || '';
        document.getElementById('chat-avatar').textContent = initials;
        // Detail panel
        document.getElementById('detail-name').textContent = name;
        document.getElementById('detail-phone').textContent = contact.phone || '';
        document.getElementById('detail-avatar').textContent = initials;
        document.getElementById('detail-name-input').value = contact.contact_name || '';
        document.getElementById('detail-assigned').value = contact.assigned_to || '';
        document.getElementById('detail-notes').value = contact.internal_notes || '';
        // Labels
        renderContactLabels(contact.labels || []);
    });

    // Load CRM boards
    loadCrmBoards();
}

function renderMessages(messages) {
    const area = document.getElementById('messages-area');
    if (!messages.length) {
        area.innerHTML = '<div class="text-center text-muted small py-4">Nenhuma mensagem ainda</div>';
        return;
    }
    area.innerHTML = messages.map(m => {
        lastMessageId = Math.max(lastMessageId, m.id);
        return renderSingleMessage(m);
    }).join('');
}

function renderSingleMessage(m) {
    const cls = m.from_me == 1 ? 'mine' : 'other';
    let content = '';
    if (m.message_type === 'image' && m.media_url) {
        content = `<div class="wpp-msg-media"><img src="${BASE + m.media_url}" onclick="window.open(this.src)"></div>`;
        if (m.message_text) content += `<div>${escapeHtml(m.message_text)}</div>`;
    } else if (m.message_type === 'audio' && m.media_url) {
        content = `<audio controls src="${BASE + m.media_url}" style="max-width:200px;"></audio>`;
    } else if (m.message_type === 'document' && m.media_url) {
        content = `<a href="${BASE + m.media_url}" target="_blank" class="text-decoration-none"><i class="bi bi-file-earmark"></i> ${escapeHtml(m.media_filename || 'Documento')}</a>`;
    } else if (m.message_type === 'video' && m.media_url) {
        content = `<video controls src="${BASE + m.media_url}" style="max-width:220px;border-radius:6px;"></video>`;
    } else {
        content = escapeHtml(m.message_text || '').replace(/\n/g, '<br>');
    }
    const time = m.timestamp ? formatTime(m.timestamp) : '';
    return `<div class="wpp-msg ${cls}">${content}<div class="wpp-msg-time">${time}</div></div>`;
}
</script>

<script>
// =========================================
// ENVIO DE MENSAGEM
// =========================================
function sendMessage() {
    const input = document.getElementById('message-input');
    const text = input.value.trim();
    if (!text || !activeContactId) return;

    input.value = '';
    // Otimistic UI
    const area = document.getElementById('messages-area');
    const tempHtml = `<div class="wpp-msg mine">${escapeHtml(text).replace(/\n/g, '<br>')}<div class="wpp-msg-time">agora</div></div>`;
    area.insertAdjacentHTML('beforeend', tempHtml);
    scrollToBottom();

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('message', text);

    fetch(BASE + 'whatsapp/send', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.message) {
            lastMessageId = Math.max(lastMessageId, data.message.id || 0);
        }
    });
}

function sendMediaFile() {
    const fileInput = document.getElementById('media-input');
    if (!fileInput.files[0] || !activeContactId) return;

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('file', fileInput.files[0]);
    fd.append('caption', '');

    fetch(BASE + 'whatsapp/sendMedia', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.message) {
            const area = document.getElementById('messages-area');
            area.insertAdjacentHTML('beforeend', renderSingleMessage(data.message));
            scrollToBottom();
            lastMessageId = Math.max(lastMessageId, data.message.id || 0);
        }
    });

    fileInput.value = '';
}

// =========================================
// POLLING
// =========================================
function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => {
        if (!activeContactId) return;
        fetch(BASE + 'whatsapp/poll/' + activeContactId + '?after_id=' + lastMessageId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(messages => {
            if (messages.length) {
                const area = document.getElementById('messages-area');
                messages.forEach(m => {
                    lastMessageId = Math.max(lastMessageId, m.id);
                    area.insertAdjacentHTML('beforeend', renderSingleMessage(m));
                });
                scrollToBottom();
                // Refresh contact list
                loadContacts();
            }
        });
    }, 3000);
}

// =========================================
// DETALHES DO CONTATO
// =========================================
function toggleDetailPanel() {
    document.getElementById('detail-panel').classList.toggle('open');
}

function saveContactDetails() {
    if (!activeContactId) return;
    const fd = new FormData();
    fd.append('contact_name', document.getElementById('detail-name-input').value);
    fd.append('assigned_to', document.getElementById('detail-assigned').value);
    fd.append('internal_notes', document.getElementById('detail-notes').value);

    fetch(BASE + 'whatsapp/updateContact/' + activeContactId, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Atualizar header
            const name = document.getElementById('detail-name-input').value || document.getElementById('detail-phone').textContent;
            document.getElementById('chat-contact-name').textContent = name;
            document.getElementById('detail-name').textContent = name;
            loadContacts();
            showToast('Contato atualizado!');
        }
    });
}

function renderContactLabels(labels) {
    const container = document.getElementById('detail-labels');
    container.innerHTML = labels.map(l =>
        `<span class="wpp-label-badge" style="background:${l.color};">${escapeHtml(l.name)} <i class="bi bi-x-circle" style="cursor:pointer;font-size:0.6rem;" onclick="removeLabelFromContact(${l.id})"></i></span>`
    ).join('');
}

function addLabelToContact() {
    const select = document.getElementById('add-label-select');
    const labelId = select.value;
    if (!labelId || !activeContactId) return;

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('label_id', labelId);
    fd.append('action', 'add');

    fetch(BASE + 'whatsapp/toggleLabel', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => {
        // Reload labels
        fetch(BASE + 'whatsapp/contactDetail/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(c => renderContactLabels(c.labels || []));
        select.value = '';
    });
}

function removeLabelFromContact(labelId) {
    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('label_id', labelId);
    fd.append('action', 'remove');

    fetch(BASE + 'whatsapp/toggleLabel', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => {
        fetch(BASE + 'whatsapp/contactDetail/' + activeContactId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(c => renderContactLabels(c.labels || []));
    });
}
</script>

<script>
// =========================================
// CRM INTEGRATION
// =========================================
function loadCrmBoards() {
    fetch(BASE + 'crm/listBoards', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(boards => {
        const select = document.getElementById('crm-board-select');
        select.innerHTML = '<option value="">Selecione board</option>';
        boards.forEach(b => {
            select.innerHTML += `<option value="${b.id}" data-columns='${JSON.stringify(b.columns)}'>${escapeHtml(b.name)}</option>`;
        });
    });
}

function loadBoardColumns() {
    const select = document.getElementById('crm-board-select');
    const opt = select.options[select.selectedIndex];
    const colSelect = document.getElementById('crm-column-select');

    if (!opt.value) {
        colSelect.style.display = 'none';
        return;
    }

    const columns = JSON.parse(opt.dataset.columns || '[]');
    colSelect.innerHTML = columns.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    colSelect.style.display = 'block';
}

function addContactToCrm() {
    if (!activeContactId) return;
    const boardId = document.getElementById('crm-board-select').value;
    const columnId = document.getElementById('crm-column-select').value;

    if (!boardId) { alert('Selecione um board'); return; }

    const fd = new FormData();
    fd.append('contact_id', activeContactId);
    fd.append('board_id', boardId);
    if (columnId) fd.append('column_id', columnId);

    fetch(BASE + 'whatsapp/addToCrm', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Contato adicionado ao CRM!');
        } else {
            alert(data.error || 'Erro');
        }
    });
}

// =========================================
// UTILIDADES
// =========================================
function scrollToBottom() {
    const area = document.getElementById('messages-area');
    area.scrollTop = area.scrollHeight;
}

function formatTime(datetime) {
    if (!datetime) return '';
    const d = new Date(datetime.replace(' ', 'T'));
    const now = new Date();
    const diff = now - d;
    if (diff < 86400000 && d.getDate() === now.getDate()) {
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }
    if (diff < 604800000) {
        const dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        return dias[d.getDay()];
    }
    return d.getDate().toString().padStart(2,'0') + '/' + (d.getMonth()+1).toString().padStart(2,'0');
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success py-2 px-3 shadow';
    toast.style.zIndex = 9999;
    toast.style.fontSize = '0.82rem';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// =========================================
// INIT
// =========================================
document.addEventListener('DOMContentLoaded', () => {
    loadContacts();

    // Debounce search
    let searchTimer;
    document.getElementById('contact-search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadContacts, 400);
    });
    document.getElementById('filter-assigned').addEventListener('change', loadContacts);
    document.getElementById('filter-label').addEventListener('change', loadContacts);

    // Se tem um contato ativo na URL
    if (activeContactId) {
        setTimeout(() => openChat(activeContactId), 500);
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
