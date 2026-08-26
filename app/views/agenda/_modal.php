<!-- Modal Reunião -->
<div class="modal fade" id="meetingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-calendar2-week"></i> <span id="meeting-modal-title">Nova reunião</span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mt-id">
                <input type="hidden" id="mt-contact-id">
                <input type="hidden" id="mt-google-event-id">
                <input type="hidden" id="mt-meet-link">
                <!-- Urgência e temperatura da reunião: espelham os campos do briefing (campos únicos) -->
                <input type="hidden" id="mt-urgency">
                <input type="hidden" id="mt-temperature">

                <div class="row g-3">
                    <!-- Tipo da reunião -->
                    <div class="col-md-5">
                        <label class="form-label small fw-medium">Tipo de reunião *</label>
                        <select id="mt-type" class="form-select form-select-sm" onchange="onMeetingTypeChange()">
                            <option value="comercial">Comercial</option>
                            <option value="operacional">Operacional</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-medium">Título *</label>
                        <input type="text" id="mt-title" class="form-control form-control-sm" placeholder="Ex: Reunião de apresentação">
                    </div>

                    <!-- Cliente do CRM -->
                    <div class="col-md-8 mt-commercial-only">
                        <label class="form-label small fw-medium">Cliente (CRM) *</label>
                        <select id="mt-client" class="form-select form-select-sm" onchange="onClientChange()">
                            <option value="">Selecione um lead do CRM...</option>
                            <?php foreach ($leads as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= escape($l['contact_name'] ?: ('Contato #' . $l['id'])) ?><?= $l['phone'] ? ' — ' . escape($l['phone']) : '' ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">➕ Cadastrar novo cliente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Data e horário da reunião</label>
                        <input type="datetime-local" id="mt-meeting-at" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 d-flex flex-wrap align-items-center gap-2 mt-commercial-only">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="mt-gen-meet" onclick="generateMeet(this)">
                            <i class="bi bi-camera-video"></i> Gerar link do Meet
                        </button>
                        <span id="mt-meet-hint" style="font-size:0.78rem;"></span>
                    </div>

                    <!-- Email do cliente (para envio do convite) -->
                    <div class="col-md-6 mt-commercial-only">
                        <label class="form-label small fw-medium">E-mail do cliente</label>
                        <input type="email" id="mt-client-email" class="form-control form-control-sm" placeholder="cliente@email.com">
                    </div>

                    <!-- Campos de novo cliente (aparecem ao escolher "Cadastrar novo") -->
                    <div class="col-md-6 mt-new-client" style="display:none;">
                        <label class="form-label small fw-medium">Nome do novo cliente</label>
                        <input type="text" id="mt-new-name" class="form-control form-control-sm" placeholder="Nome do cliente">
                    </div>
                    <div class="col-md-6 mt-new-client" style="display:none;">
                        <label class="form-label small fw-medium">Telefone</label>
                        <input type="text" id="mt-new-phone" class="form-control form-control-sm" placeholder="(00) 00000-0000" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>

                    <div class="col-md-4 mt-commercial-only">
                        <label class="form-label small fw-medium">Responsável</label>
                        <select id="mt-assigned" class="form-select form-select-sm">
                            <?php foreach ($team as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $user['id'] ? 'selected' : '' ?>><?= escape($t['name']) ?> (<?= roleLabel($t['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8 mt-commercial-only">
                        <label class="form-label small fw-medium">Status</label>
                        <select id="mt-status" class="form-select form-select-sm" onchange="onStatusChange()">
                            <option value="a_agendar">A agendar</option>
                            <option value="agendada">Agendada</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="realizada">Realizada</option>
                            <option value="convertida">Convertida</option>
                            <option value="remarcada">Remarcada</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>

                    <!-- Quem fechou (aparece só quando status = convertida) -->
                    <div class="col-md-6" id="closed-by-field" style="display:none;">
                        <label class="form-label small fw-medium">Quem fechou o negócio? *</label>
                        <select id="mt-closed-by" class="form-select form-select-sm">
                            <option value="">Selecione...</option>
                            <?php foreach ($team as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= escape($t['name']) ?> (<?= roleLabel($t['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Quem efetivamente realizou o fechamento comercial</small>
                    </div>

                    <!-- Participantes da equipe (checkboxes) -->
                    <div class="col-12">
                        <label class="form-label small fw-medium">Participantes da equipe</label>
                        <div id="mt-participants" class="border rounded p-2" style="max-height:170px;overflow-y:auto;">
                            <?php foreach ($participants as $role => $users): ?>
                            <div class="mb-1">
                                <div class="fw-semibold text-muted" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.03em;"><?= roleLabel($role) ?></div>
                                <?php foreach ($users as $p): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input mt-participant-cb" type="checkbox" id="mt-pt-<?= $p['id'] ?>" value="<?= $p['id'] ?>">
                                    <label class="form-check-label small" for="mt-pt-<?= $p['id'] ?>"><?= escape($p['name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Marque os usuários que participarão da reunião</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-medium">Descrição</label>
                        <textarea id="mt-notes" class="form-control form-control-sm" rows="2" placeholder="Descrição / notas da reunião..."></textarea>
                    </div>
                </div>

                <!-- Briefing do cliente (editável) -->
                <div class="mt-commercial-only">
                <hr>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-semibold mb-0" style="font-size:0.85rem;"><i class="bi bi-clipboard-data"></i> Briefing do cliente</h6>
                    <small class="text-muted">Salvo junto com a reunião</small>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small mb-1">Necessidade</label>
                        <textarea id="bf-need" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Principal dor</label>
                        <textarea id="bf-main_pain" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Objetivo esperado</label>
                        <textarea id="bf-expected_goal" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Solução atual</label>
                        <input type="text" id="bf-current_solution" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Faixa de investimento</label>
                        <input type="text" id="bf-investment_range" class="form-control form-control-sm" placeholder="R$ 0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Urgência</label>
                        <select id="bf-urgency" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Nível de decisão</label>
                        <input type="text" id="bf-decision_level" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Temperatura</label>
                        <select id="bf-lead_temperature" class="form-select form-select-sm" onchange="document.getElementById('mt-temperature').value = this.value;">
                            <option value="">—</option>
                            <option value="frio">Frio</option>
                            <option value="morno">Morno</option>
                            <option value="quente">Quente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Fonte do lead</label>
                        <select id="bf-lead_source" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option value="telefonema">Telefonema</option>
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="instagram">Instagram</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Principal objeção</label>
                        <textarea id="bf-main_objection" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Próximo passo</label>
                        <textarea id="bf-next_step" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Observações do briefing</label>
                        <textarea id="bf-notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                </div><!-- /.mt-commercial-only (briefing) -->
            </div>
            <div class="modal-footer justify-content-between">
                <button class="btn btn-sm btn-outline-danger" id="mt-delete-btn" onclick="deleteMeeting()" style="display:none;"><i class="bi bi-trash"></i> Excluir</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info" id="mt-resend-btn" onclick="resendNotifications()" style="display:none;"><i class="bi bi-send"></i> Reenviar notificações</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button class="btn btn-sm btn-primary" onclick="saveMeeting()"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let meetingModalInstance = null;
function getMeetingModal() {
    if (!meetingModalInstance) meetingModalInstance = new bootstrap.Modal(document.getElementById('meetingModal'));
    return meetingModalInstance;
}

const BF_FIELDS = ['need','main_pain','current_solution','expected_goal','urgency','investment_range','decision_level','lead_temperature','lead_source','main_objection','next_step','notes'];

function resetMeetingForm() {
    document.getElementById('mt-id').value = '';
    document.getElementById('mt-contact-id').value = '';
    document.getElementById('mt-google-event-id').value = '';
    document.getElementById('mt-meet-link').value = '';
    document.getElementById('mt-meet-hint').innerHTML = '';
    ['mt-title','mt-meeting-at','mt-new-name','mt-new-phone','mt-notes','mt-client-email'].forEach(f => document.getElementById(f).value = '');
    document.getElementById('mt-client').value = '';
    document.getElementById('mt-urgency').value = 'media';
    document.getElementById('mt-temperature').value = '';
    document.getElementById('mt-status').value = 'a_agendar';
    document.getElementById('mt-closed-by').value = '';
    document.getElementById('closed-by-field').style.display = 'none';
    // Tipo de reunião padrão
    const typeSel = document.getElementById('mt-type');
    if (typeSel) typeSel.value = 'comercial';
    // Limpa participantes (checkboxes)
    document.querySelectorAll('.mt-participant-cb').forEach(cb => cb.checked = false);
    const bfTemp = document.getElementById('bf-lead_temperature'); if (bfTemp) bfTemp.value = '';
    const bfUrg = document.getElementById('bf-urgency'); if (bfUrg) bfUrg.value = '';
    document.querySelectorAll('.mt-new-client').forEach(el => el.style.display = 'none');
    document.getElementById('mt-delete-btn').style.display = 'none';
    document.getElementById('mt-resend-btn').style.display = 'none';
    clearBriefing();
    onMeetingTypeChange();
}

// Mostra/oculta os campos comerciais/briefing conforme o tipo de reunião.
// Operacional: só título, descrição, data/horário e participantes.
function onMeetingTypeChange() {
    const isOperational = document.getElementById('mt-type').value === 'operacional';
    document.querySelectorAll('.mt-commercial-only').forEach(el => {
        el.style.display = isOperational ? 'none' : '';
    });
    // Ao voltar para operacional, garante que os campos de "novo cliente" fiquem ocultos
    if (isOperational) {
        document.querySelectorAll('.mt-new-client').forEach(el => el.style.display = 'none');
    }
}
function clearBriefing() {
    BF_FIELDS.forEach(k => { const el = document.getElementById('bf-' + k); if (el) el.value = ''; });
}
function fillBriefing(bf) {
    clearBriefing();
    if (!bf) return;
    BF_FIELDS.forEach(k => { const el = document.getElementById('bf-' + k); if (el && bf[k] != null) el.value = bf[k]; });
}
// Converte a urgência textual do briefing (ex: "Baixa") para o enum da reunião ("baixa").
const URGENCY_TO_ENUM = { 'baixa':'baixa', 'média':'media', 'media':'media', 'alta':'alta', 'urgente':'urgente' };
const URGENCY_TO_LABEL = { 'baixa':'Baixa', 'media':'Média', 'alta':'Alta', 'urgente':'Urgente' };
function urgencyEnum(v) { return URGENCY_TO_ENUM[(v || '').toString().trim().toLowerCase()] || 'media'; }
function urgencyLabel(v) { return URGENCY_TO_LABEL[urgencyEnum(v)] || 'Média'; }

// Urgência e temperatura são campos únicos (dropdowns do briefing) que também alimentam a reunião.
// meetingUrgency vem no enum (baixa/media/...); o dropdown do briefing usa o rótulo (Baixa/Média/...).
function syncInherited(meetingUrgency, meetingTemp) {
    const bfTemp = document.getElementById('bf-lead_temperature');
    const temp = (bfTemp && bfTemp.value) ? bfTemp.value : (meetingTemp || '');
    if (bfTemp) bfTemp.value = temp;
    document.getElementById('mt-temperature').value = temp;

    const bfUrg = document.getElementById('bf-urgency');
    // Prioriza o valor já preenchido no briefing; senão usa o da reunião.
    const label = (bfUrg && bfUrg.value) ? bfUrg.value : urgencyLabel(meetingUrgency);
    if (bfUrg) bfUrg.value = label;
    document.getElementById('mt-urgency').value = urgencyEnum(label);
}

let GOOGLE_READY = null;
function checkGoogleReady() {
    if (GOOGLE_READY !== null) { applyGoogleReady(); return; }
    fetch(`${BASE}agenda/googleStatus`).then(r => r.json()).then(d => {
        GOOGLE_READY = !!d.configured;
        applyGoogleReady();
    }).catch(() => { GOOGLE_READY = false; applyGoogleReady(); });
}
function applyGoogleReady() {
    const btn = document.getElementById('mt-gen-meet');
    if (!btn) return;
    if (GOOGLE_READY) {
        btn.disabled = false; btn.title = '';
    } else {
        btn.disabled = true;
        btn.title = 'Configure a integração Google em Configurações';
        document.getElementById('mt-meet-hint').innerHTML = '<span class="text-muted"><i class="bi bi-info-circle"></i> Google não configurado</span>';
    }
}

function openMeetingModal(id = null, dateStr = null) {
    resetMeetingForm();
    checkGoogleReady();
    if (dateStr) document.getElementById('mt-meeting-at').value = dateStr + 'T09:00';
    if (id) {
        fetch(`${BASE}agenda/get/${id}`).then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            fillMeeting(d.meeting);
            getMeetingModal().show();
        });
    } else {
        document.getElementById('meeting-modal-title').textContent = 'Nova reunião';
        getMeetingModal().show();
    }
}

function fillMeeting(m) {
    document.getElementById('meeting-modal-title').textContent = 'Editar reunião';
    document.getElementById('mt-id').value = m.id;
    document.getElementById('mt-type').value = m.meeting_type || 'comercial';
    onMeetingTypeChange();
    document.getElementById('mt-contact-id').value = m.contact_id || '';
    document.getElementById('mt-title').value = m.title || '';
    document.getElementById('mt-meeting-at').value = m.meeting_at ? m.meeting_at.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('mt-client').value = m.contact_id || '';
    document.getElementById('mt-assigned').value = m.assigned_to || '';
    document.getElementById('mt-status').value = m.status || 'a_agendar';
    document.getElementById('mt-closed-by').value = m.closed_by || '';
    onStatusChange();
    document.getElementById('mt-notes').value = m.notes || '';
    document.getElementById('mt-client-email').value = m.client_email || '';
    document.getElementById('mt-google-event-id').value = m.google_event_id || '';
    document.getElementById('mt-meet-link').value = m.meet_link || '';
    // Preenche participantes selecionados (checkboxes)
    const ids = (m.participants || []).map(p => String(p.id));
    document.querySelectorAll('.mt-participant-cb').forEach(cb => { cb.checked = ids.includes(cb.value); });
    fillBriefing(m.briefing);
    // Urgência e temperatura são campos únicos (briefing). Usa os do briefing; se vazios, cai nos da reunião.
    syncInherited(m.urgency || 'media', m.temperature || '');
    if (m.meet_link) showMeetLink(m.meet_link);
    document.getElementById('mt-delete-btn').style.display = '';
    document.getElementById('mt-resend-btn').style.display = '';
}

// Mostra/oculta campo "Quem fechou" conforme o status
function onStatusChange() {
    const status = document.getElementById('mt-status').value;
    const field = document.getElementById('closed-by-field');
    if (status === 'convertida') {
        field.style.display = '';
    } else {
        field.style.display = 'none';
    }
}

// Ao escolher cliente: carrega o briefing ou mostra campos de novo cliente
function onClientChange() {
    const val = document.getElementById('mt-client').value;
    const newFields = document.querySelectorAll('.mt-new-client');
    if (val === '__new__') {
        newFields.forEach(el => el.style.display = '');
        document.getElementById('mt-contact-id').value = '';
        clearBriefing();
        return;
    }
    newFields.forEach(el => el.style.display = 'none');
    document.getElementById('mt-contact-id').value = val || '';
    if (val) {
        fetch(`${BASE}agenda/briefing/${val}`).then(r => r.json()).then(d => { fillBriefing(d.briefing); syncInherited('media', ''); });
    } else {
        clearBriefing();
        syncInherited('media', '');
    }
}

function collectPayload() {
    const fd = new FormData();
    fd.append('meeting_type', document.getElementById('mt-type').value);
    fd.append('title', document.getElementById('mt-title').value.trim());
    fd.append('meeting_at', document.getElementById('mt-meeting-at').value);
    fd.append('assigned_to', document.getElementById('mt-assigned').value);
    // Urgência e temperatura únicas: sempre vindas dos dropdowns do briefing
    const bfUrg = document.getElementById('bf-urgency');
    const urgVal = urgencyEnum(bfUrg ? bfUrg.value : 'media');
    document.getElementById('mt-urgency').value = urgVal;
    fd.append('urgency', urgVal);

    const bfTemp = document.getElementById('bf-lead_temperature');
    const tempVal = bfTemp ? bfTemp.value : document.getElementById('mt-temperature').value;
    document.getElementById('mt-temperature').value = tempVal;
    fd.append('temperature', tempVal);
    fd.append('status', document.getElementById('mt-status').value);
    fd.append('closed_by', document.getElementById('mt-closed-by').value);
    fd.append('notes', document.getElementById('mt-notes').value);
    fd.append('client_email', document.getElementById('mt-client-email').value.trim());

    const clientVal = document.getElementById('mt-client').value;
    if (clientVal === '__new__') {
        fd.append('new_client_name', document.getElementById('mt-new-name').value.trim());
        fd.append('new_client_phone', document.getElementById('mt-new-phone').value.trim());
    } else if (clientVal) {
        fd.append('contact_id', clientVal);
    }
    // Link do Meet já gerado (evita criar evento duplicado)
    fd.append('google_event_id', document.getElementById('mt-google-event-id').value);
    fd.append('meet_link', document.getElementById('mt-meet-link').value);
    // Participantes da equipe (checkboxes marcados)
    document.querySelectorAll('.mt-participant-cb:checked').forEach(cb => fd.append('participants[]', cb.value));
    // Briefing
    BF_FIELDS.forEach(k => fd.append('bf_' + k, document.getElementById('bf-' + k).value));
    return fd;
}

function showMeetLink(link) {
    const hint = document.getElementById('mt-meet-hint');
    if (link) {
        hint.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Link gerado:</span> '
            + '<a href="' + link + '" target="_blank">' + link + '</a>';
    } else {
        hint.innerHTML = '';
    }
}

// Gera o link do Meet no Google antes de salvar
function generateMeet(btn) {
    const meetingAt = document.getElementById('mt-meeting-at').value;
    if (!meetingAt) { alert('Informe a data e o horário da reunião primeiro.'); return; }
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';

    const fd = new FormData();
    fd.append('title', document.getElementById('mt-title').value.trim() || 'Reunião');
    fd.append('meeting_at', meetingAt);
    fd.append('client_email', document.getElementById('mt-client-email').value.trim());
    fd.append('notes', document.getElementById('mt-notes').value);
    const mid = document.getElementById('mt-id').value;
    if (mid) fd.append('meeting_id', mid);
    // Envia participantes para inclusão no evento Google
    document.querySelectorAll('.mt-participant-cb:checked').forEach(cb => fd.append('participants[]', cb.value));

    fetch(`${BASE}agenda/generateMeet`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            btn.disabled = false; btn.innerHTML = original;
            if (d.error) { alert(d.error); return; }
            document.getElementById('mt-google-event-id').value = d.event_id || '';
            document.getElementById('mt-meet-link').value = d.meet_link || '';
            showMeetLink(d.meet_link);
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = original; alert('Erro ao gerar o link.'); });
}

function saveMeeting() {
    const title = document.getElementById('mt-title').value.trim();
    if (!title) { alert('Informe o título.'); return; }

    const isOperational = document.getElementById('mt-type').value === 'operacional';

    if (!isOperational) {
        // Contato obrigatório (só reunião comercial)
        const clientVal = document.getElementById('mt-client').value;
        if (!clientVal) { alert('Selecione um cliente (CRM) ou cadastre um novo.'); return; }
        if (clientVal === '__new__' && !document.getElementById('mt-new-name').value.trim()) {
            alert('Informe o nome do novo cliente.'); return;
        }
        // Se convertida, exige quem fechou
        const status = document.getElementById('mt-status').value;
        if (status === 'convertida' && !document.getElementById('mt-closed-by').value) {
            alert('Informe quem fechou o negócio.'); return;
        }
    } else {
        // Reunião operacional: exige ao menos um participante
        if (document.querySelectorAll('.mt-participant-cb:checked').length === 0) {
            alert('Selecione ao menos um participante.'); return;
        }
    }

    const id = document.getElementById('mt-id').value;
    const url = id ? `${BASE}agenda/update/${id}` : `${BASE}agenda/create`;

    const fd = collectPayload();

    // Se está editando e mudando status para "cancelada" e há evento Google, pergunta (só comercial)
    if (id && !isOperational) {
        const newStatus = document.getElementById('mt-status').value;
        const hasGoogleEvent = !!document.getElementById('mt-google-event-id').value;
        if (newStatus === 'cancelada' && hasGoogleEvent) {
            const deleteEvent = confirm('A reunião será cancelada. Deseja remover o evento do Google Calendar?');
            if (deleteEvent) {
                fd.append('delete_google_event', '1');
                const notify = confirm('Notificar os participantes sobre o cancelamento?');
                if (notify) fd.append('notify_participants', '1');
            }
        }
    }

    fetch(url, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            location.reload();
        });
}

// Reenvia as notificações (WhatsApp + e-mail) aos participantes/cliente da reunião
function resendNotifications() {
    const id = document.getElementById('mt-id').value;
    if (!id) return;
    if (!confirm('Reenviar as notificações (WhatsApp + e-mail) aos participantes?')) return;

    const btn = document.getElementById('mt-resend-btn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';

    fetch(`${BASE}agenda/resendNotifications/${id}`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            btn.disabled = false; btn.innerHTML = original;
            if (d.error) { alert(d.error); return; }
            alert(d.message || 'Notificações reenviadas.');
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = original; alert('Erro ao reenviar as notificações.'); });
}

function deleteMeeting() {
    const id = document.getElementById('mt-id').value;
    if (!id) return;

    const hasGoogleEvent = !!document.getElementById('mt-google-event-id').value;
    let deleteGoogleEvent = false;
    let notifyParticipants = false;

    if (!confirm('Excluir esta reunião?')) return;

    if (hasGoogleEvent) {
        deleteGoogleEvent = confirm('Deseja também remover o evento do Google Calendar?');
        if (deleteGoogleEvent) {
            notifyParticipants = confirm('Notificar os participantes sobre o cancelamento?');
        }
    }

    const fd = new FormData();
    if (deleteGoogleEvent) fd.append('delete_google_event', '1');
    if (notifyParticipants) fd.append('notify_participants', '1');

    fetch(`${BASE}agenda/delete/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            location.reload();
        });
}
</script>
