<!-- Modal Reunião -->
<style>
    /* Opções de reunião: cartões quadrados separados, com borda suave */
    .mt-mode-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .mt-mode-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .mt-mode-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-align: center;
        padding: 14px 10px;
        border: 1px solid #e2e5ec;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 500;
        color: #4b5266;
        min-height: 76px;
        transition: border-color 0.15s, box-shadow 0.15s, background 0.15s, color 0.15s;
    }
    .mt-mode-card i { font-size: 1.25rem; color: #8a90a2; transition: color 0.15s; }
    .mt-mode-card:hover {
        border-color: #c9cedb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    /* Selecionado */
    .mt-mode-input:checked + .mt-mode-card {
        border-color: var(--primary, #00BFA6);
        background: #f2fbf9;
        color: #0b7f70;
        box-shadow: 0 2px 10px rgba(0, 191, 166, 0.15);
    }
    .mt-mode-input:checked + .mt-mode-card i { color: var(--primary, #00BFA6); }
    /* Desabilitado (ex.: Google não configurado) */
    .mt-mode-input:disabled + .mt-mode-card {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f6f7f9;
    }
    /* Foco por teclado (acessibilidade) */
    .mt-mode-input:focus-visible + .mt-mode-card {
        outline: 2px solid var(--primary, #00BFA6);
        outline-offset: 2px;
    }
    @media (max-width: 575.98px) {
        .mt-mode-grid { grid-template-columns: 1fr; }
    }

    /* ===== Combobox unificado (Empresa / Contato): busca + seleção num só campo ===== */
    .mt-combo { position: relative; }
    .mt-combo-list {
        position: absolute;
        top: calc(100% + 2px);
        left: 0;
        right: 0;
        z-index: 30;
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e2e5ec;
        border-radius: 10px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.10);
        padding: 4px;
        display: none;
    }
    .mt-combo-list.open { display: block; }
    .mt-combo-item {
        padding: 7px 10px;
        border-radius: 7px;
        font-size: 0.85rem;
        color: #3a3f51;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mt-combo-item:hover,
    .mt-combo-item.active { background: #f2fbf9; color: #0b7f70; }
    .mt-combo-item.selected { font-weight: 600; }
    .mt-combo-empty {
        padding: 8px 10px;
        font-size: 0.8rem;
        color: #9aa0b3;
    }
</style>

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
                <!-- Snapshot do contato escolhido via Empresa → Contato (não altera contact_id do CRM) -->
                <input type="hidden" id="mt-client-name">
                <input type="hidden" id="mt-client-phone">
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
                            <option value="externo">Convite externo</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-medium">Título *</label>
                        <input type="text" id="mt-title" class="form-control form-control-sm" placeholder="Ex: Reunião de apresentação">
                    </div>

                    <!-- Origem do cliente: CRM (lead) ou Empresa → Contato -->
                    <div class="col-md-4 mt-commercial-only">
                        <label class="form-label small fw-medium">Buscar cliente por *</label>
                        <select id="mt-client-source" class="form-select form-select-sm" onchange="onClientSourceChange()">
                            <option value="crm">Cliente (CRM)</option>
                            <option value="empresa">Empresa</option>
                        </select>
                    </div>

                    <!-- Origem = CRM: lead do CRM -->
                    <div class="col-md-8 mt-commercial-only mt-source-crm">
                        <label class="form-label small fw-medium">Cliente (CRM) *</label>
                        <select id="mt-client" class="form-select form-select-sm" onchange="onClientChange()">
                            <option value="">Selecione um lead do CRM...</option>
                            <?php foreach ($leads as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= escape($l['contact_name'] ?: ('Contato #' . $l['id'])) ?><?= $l['phone'] ? ' — ' . escape($l['phone']) : '' ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">➕ Cadastrar novo cliente</option>
                        </select>
                    </div>

                    <!-- Origem = Empresa: Empresa → Contato (reutiliza users.company_id) -->
                    <!-- Empresa: combobox unificado (buscar + selecionar num único campo) -->
                    <div class="col-md-4 mt-commercial-only mt-source-empresa" style="display:none;">
                        <label class="form-label small fw-medium">Empresa *</label>
                        <div class="mt-combo" id="mt-company-combo">
                            <input type="hidden" id="mt-company" value="">
                            <input type="text" id="mt-company-search" class="form-control form-control-sm mt-combo-input"
                                   placeholder="Buscar e selecionar empresa..." autocomplete="off"
                                   onfocus="openCompanyList()" oninput="filterCompanyOptions()">
                            <div class="mt-combo-list" id="mt-company-list">
                                <?php foreach ($companies as $co): ?>
                                <div class="mt-combo-item" data-value="<?= $co['id'] ?>" data-name="<?= escape(mb_strtolower($co['name'])) ?>"><?= escape($co['name']) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Contato: combobox unificado (carrega os contatos da empresa escolhida) -->
                    <div class="col-md-4 mt-commercial-only mt-source-empresa" style="display:none;">
                        <label class="form-label small fw-medium">Contato *</label>
                        <div class="mt-combo" id="mt-contact-combo">
                            <input type="hidden" id="mt-contact" value="">
                            <input type="text" id="mt-contact-search" class="form-control form-control-sm mt-combo-input"
                                   placeholder="Selecione uma empresa primeiro..." autocomplete="off" disabled
                                   onfocus="openContactList()" oninput="filterContactOptions()">
                            <div class="mt-combo-list" id="mt-contact-list"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-medium">Data e horário da reunião</label>
                        <input type="datetime-local" id="mt-meeting-at" class="form-control form-control-sm">
                    </div>
                    <!-- ===== Opções de reunião (uma OU outra — mutuamente exclusivas) ===== -->
                    <div class="col-12">
                        <label class="form-label small fw-medium mb-1"><i class="bi bi-camera-video"></i> Opções de reunião</label>
                        <div class="mt-mode-grid" role="group" aria-label="Opções de reunião">
                            <input type="radio" class="mt-mode-input" name="mt-meeting-mode" id="mt-mode-none" value="" checked onchange="onMeetingModeChange()">
                            <label class="mt-mode-card" for="mt-mode-none">
                                <i class="bi bi-slash-circle"></i>
                                <span>Nenhuma</span>
                            </label>

                            <input type="radio" class="mt-mode-input" name="mt-meeting-mode" id="mt-mode-meet" value="meet" onchange="onMeetingModeChange()">
                            <label class="mt-mode-card" for="mt-mode-meet">
                                <i class="bi bi-camera-video"></i>
                                <span>Google Meet</span>
                            </label>

                            <input type="radio" class="mt-mode-input" name="mt-meeting-mode" id="mt-mode-room" value="room" onchange="onMeetingModeChange()">
                            <label class="mt-mode-card" for="mt-mode-room">
                                <i class="bi bi-camera-reels"></i>
                                <span>Sala de vídeo do sistema</span>
                            </label>
                        </div>
                        <div class="mt-1"><span id="mt-meet-hint" style="font-size:0.78rem;"></span></div>
                    </div>

                    <!-- Opções da sala de vídeo do sistema (privacidade + administradores) -->
                    <!-- Só aparece quando a opção "Sala de vídeo do sistema" está selecionada. -->
                    <div class="col-12" id="mt-room-options" style="display:none;">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="mt-room-visibility" id="mt-rv-public" value="public" checked onchange="onRoomVisibilityChange()">
                                <label class="form-check-label small" for="mt-rv-public"><i class="bi bi-globe"></i> Pública</label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="radio" name="mt-room-visibility" id="mt-rv-private" value="private" onchange="onRoomVisibilityChange()">
                                <label class="form-check-label small" for="mt-rv-private"><i class="bi bi-shield-lock"></i> Privada (entrada aprovada)</label>
                            </div>
                        </div>
                        <div id="mt-room-admins-block" class="mt-2" style="display:none;">
                            <label class="form-label small fw-medium mb-1">Administradores da sala (aprovam a entrada)</label>
                            <select id="mt-room-admins" class="form-select form-select-sm" multiple size="4">
                                <?php foreach (($team ?? []) as $tm): ?>
                                <option value="<?= (int)$tm['id'] ?>"><?= escape($tm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Você já é admin. Segure Ctrl/Cmd para escolher mais de um.</small>
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" id="mt-gen-room-private" onclick="generateVideoRoom(this)">
                                <i class="bi bi-camera-reels"></i> Criar sala privada
                            </button>
                        </div>
                    </div>

                    <!-- ===== Convite externo (demanda #210) ===== -->
                    <!-- Convidados que NÃO fazem parte do sistema: nome + e-mail e/ou telefone. -->
                    <div class="col-12 mt-external-only" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small fw-medium mb-0"><i class="bi bi-person-plus"></i> Convidados externos *</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="addExternalGuestRow()">
                                <i class="bi bi-plus-lg"></i> Adicionar convidado
                            </button>
                        </div>
                        <div id="mt-external-guests"></div>
                        <small class="text-muted">Informe o nome e ao menos um contato (e-mail ou telefone) por convidado.</small>
                    </div>

                    <!-- Registrar agendamento: gera o link "Adicionar ao Google Agenda" enviado no convite. -->
                    <div class="col-12 mt-external-only" style="display:none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="mt-register-google">
                            <label class="form-check-label small fw-medium" for="mt-register-google">
                                <i class="bi bi-calendar-plus"></i> Registrar agendamento (gerar link do Google Agenda)
                            </label>
                        </div>
                        <small class="text-muted">Um link "Adicionar ao Google Agenda" será enviado junto com o convite para o cliente salvar o compromisso.</small>
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

                    <!-- Status para reunião operacional (sem estados comerciais) -->
                    <div class="col-md-6 mt-operational-only" style="display:none;">
                        <label class="form-label small fw-medium">Status</label>
                        <select id="mt-status-op" class="form-select form-select-sm">
                            <option value="a_agendar">A agendar</option>
                            <option value="agendada">Agendada</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="realizada">Realizada</option>
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

                    <!-- Participantes da equipe (dropdown com multisseleção) -->
                    <div class="col-12">
                        <label class="form-label small fw-medium">Participantes da equipe</label>
                        <!-- Select real (fonte de verdade dos dados; mantém participants[] intacto) -->
                        <select id="mt-participants" multiple class="d-none">
                            <?php foreach ($participants as $role => $users): ?>
                            <optgroup label="<?= roleLabel($role) ?>">
                                <?php foreach ($users as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= escape($p['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <!-- UI do dropdown -->
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 d-flex justify-content-between align-items-center"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <span id="mt-participants-label">0 selecionados</span>
                            </button>
                            <div class="dropdown-menu w-100 p-2" style="max-height:240px; overflow-y:auto;">
                                <?php foreach ($participants as $role => $users): ?>
                                <h6 class="dropdown-header px-1 py-1"><?= roleLabel($role) ?></h6>
                                <?php foreach ($users as $p): ?>
                                <label class="dropdown-item d-flex align-items-center gap-2 px-1 py-1" style="cursor:pointer;">
                                    <input type="checkbox" class="form-check-input mt-0 mt-participant-check" value="<?= $p['id'] ?>"
                                           onchange="onParticipantToggle(this)">
                                    <span class="small"><?= escape($p['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Selecione uma ou mais pessoas da equipe</small>
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
                        <input type="text" id="bf-investment_range" class="form-control form-control-sm" placeholder="R$ 0,00" inputmode="numeric" oninput="maskCurrency(this)">
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
    // Opções de reunião: volta para "Nenhuma" e esconde os controles da sala do sistema.
    const modeNone = document.getElementById('mt-mode-none');
    if (modeNone) modeNone.checked = true;
    const roomOpts = document.getElementById('mt-room-options');
    if (roomOpts) roomOpts.style.display = 'none';
    const rvPublic = document.getElementById('mt-rv-public');
    if (rvPublic) rvPublic.checked = true;
    const roomAdminsBlk = document.getElementById('mt-room-admins-block');
    if (roomAdminsBlk) roomAdminsBlk.style.display = 'none';
    ['mt-title','mt-meeting-at','mt-new-name','mt-new-phone','mt-notes','mt-client-email','mt-client-name','mt-client-phone'].forEach(f => document.getElementById(f).value = '');
    document.getElementById('mt-client').value = '';
    // Reseta o combobox de Empresa → Contato
    const coHidden = document.getElementById('mt-company'); if (coHidden) coHidden.value = '';
    const coSearch = document.getElementById('mt-company-search'); if (coSearch) coSearch.value = '';
    document.getElementById('mt-company-list')?.querySelectorAll('.mt-combo-item').forEach(i => { i.classList.remove('selected'); i.style.display = ''; });
    closeCompanyList();
    const ctHidden = document.getElementById('mt-contact'); if (ctHidden) ctHidden.value = '';
    const ctSearch = document.getElementById('mt-contact-search');
    if (ctSearch) { ctSearch.value = ''; ctSearch.disabled = true; ctSearch.placeholder = 'Selecione uma empresa primeiro...'; }
    mtContactCache = [];
    const ctList = document.getElementById('mt-contact-list'); if (ctList) ctList.innerHTML = '';
    closeContactList();
    document.getElementById('mt-urgency').value = 'media';
    document.getElementById('mt-temperature').value = '';
    document.getElementById('mt-status').value = 'a_agendar';
    document.getElementById('mt-status-op').value = 'a_agendar';
    document.getElementById('mt-closed-by').value = '';
    document.getElementById('closed-by-field').style.display = 'none';
    // Tipo de reunião padrão
    const typeSel = document.getElementById('mt-type');
    if (typeSel) typeSel.value = 'comercial';
    // Reseta o bloco de convite externo (convidados + registrar agendamento)
    const extWrap = document.getElementById('mt-external-guests');
    if (extWrap) extWrap.innerHTML = '';
    const regChk = document.getElementById('mt-register-google');
    if (regChk) regChk.checked = false;
    // Origem do cliente padrão: CRM
    const srcSel = document.getElementById('mt-client-source');
    if (srcSel) srcSel.value = 'crm';
    // Limpa participantes (select fonte de verdade + checkboxes do dropdown)
    const ptSel = document.getElementById('mt-participants');
    if (ptSel) Array.from(ptSel.options).forEach(o => o.selected = false);
    syncParticipantChecks();
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
// Externo: título, descrição, data/horário, convidados externos e registrar agendamento.
function onMeetingTypeChange() {
    const type = document.getElementById('mt-type').value;
    const isOperational = type === 'operacional';
    const isExternal = type === 'externo';
    const isCommercial = !isOperational && !isExternal;

    // Campos comerciais (cliente CRM/Empresa + briefing) só na reunião comercial.
    document.querySelectorAll('.mt-commercial-only').forEach(el => {
        el.style.display = isCommercial ? '' : 'none';
    });
    // Status operacional (sem estados comerciais): operacional e externo.
    document.querySelectorAll('.mt-operational-only').forEach(el => {
        el.style.display = isCommercial ? 'none' : '';
    });
    // Blocos exclusivos do convite externo.
    document.querySelectorAll('.mt-external-only').forEach(el => {
        el.style.display = isExternal ? '' : 'none';
    });

    if (isExternal) {
        // Garante ao menos uma linha de convidado ao entrar no modo externo.
        const wrap = document.getElementById('mt-external-guests');
        if (wrap && wrap.children.length === 0) addExternalGuestRow();
    }

    if (isCommercial) {
        // Em reunião comercial, respeita a origem escolhida (CRM ou Empresa)
        onClientSourceChange();
    } else {
        // Nos demais tipos, esconde os campos de "novo cliente".
        document.querySelectorAll('.mt-new-client').forEach(el => el.style.display = 'none');
    }
}

// ===== Convidados externos =====

// Adiciona uma linha (nome / e-mail / telefone) ao bloco de convidados externos.
function addExternalGuestRow(guest) {
    const wrap = document.getElementById('mt-external-guests');
    if (!wrap) return;
    const g = guest || {};
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 mt-external-guest-row';
    row.innerHTML =
        '<div class="col-md-4"><input type="text" class="form-control form-control-sm mt-ext-name" placeholder="Nome *"></div>' +
        '<div class="col-md-4"><input type="email" class="form-control form-control-sm mt-ext-email" placeholder="E-mail"></div>' +
        '<div class="col-md-3"><input type="text" class="form-control form-control-sm mt-ext-phone" placeholder="Telefone" inputmode="numeric" oninput="this.value=this.value.replace(/\\D/g,\'\')"></div>' +
        '<div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="removeExternalGuestRow(this)"><i class="bi bi-x-lg"></i></button></div>';
    wrap.appendChild(row);
    row.querySelector('.mt-ext-name').value = g.name || '';
    row.querySelector('.mt-ext-email').value = g.email || '';
    row.querySelector('.mt-ext-phone').value = (g.phone || '').toString().replace(/\D/g, '');
}

// Remove uma linha de convidado externo (mantém sempre ao menos uma).
function removeExternalGuestRow(btn) {
    const wrap = document.getElementById('mt-external-guests');
    const row = btn.closest('.mt-external-guest-row');
    if (row) row.remove();
    if (wrap && wrap.children.length === 0) addExternalGuestRow();
}

// Limpa e repopula o bloco de convidados externos (usado ao abrir/editar).
function fillExternalGuests(list) {
    const wrap = document.getElementById('mt-external-guests');
    if (!wrap) return;
    wrap.innerHTML = '';
    const guests = Array.isArray(list) ? list : [];
    if (guests.length === 0) { addExternalGuestRow(); return; }
    guests.forEach(g => addExternalGuestRow(g));
}
function clearBriefing() {
    BF_FIELDS.forEach(k => { const el = document.getElementById('bf-' + k); if (el) el.value = ''; });
}
function fillBriefing(bf) {
    clearBriefing();
    if (!bf) return;
    BF_FIELDS.forEach(k => { const el = document.getElementById('bf-' + k); if (el && bf[k] != null) el.value = bf[k]; });
    // Normaliza a faixa de investimento para o formato monetário (BR), inclusive
    // valores antigos salvos "crus" (ex.: "50000" -> "R$ 50.000,00").
    const inv = document.getElementById('bf-investment_range');
    if (inv && inv.value) maskCurrency(inv);
}

// Máscara de moeda (Real): formata o que é digitado a partir dos centavos.
// Ex.: digitar 5000000 -> "R$ 50.000,00". Guarda apenas os dígitos e reconstrói.
function maskCurrency(el) {
    let digits = (el.value || '').replace(/\D/g, '');
    if (digits === '') { el.value = ''; return; }
    // Limita para evitar números absurdos (até 999.999.999,99)
    digits = digits.slice(0, 11);
    const cents = parseInt(digits, 10);
    const formatted = (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    el.value = 'R$ ' + formatted;
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
    // A opção "Google Meet" só fica disponível se a integração Google estiver configurada.
    const meetRadio = document.getElementById('mt-mode-meet');
    const meetLabel = document.querySelector('label[for="mt-mode-meet"]');
    if (!meetRadio) return;
    if (GOOGLE_READY) {
        meetRadio.disabled = false;
        if (meetLabel) { meetLabel.classList.remove('disabled'); meetLabel.title = ''; }
    } else {
        meetRadio.disabled = true;
        if (meetLabel) {
            meetLabel.classList.add('disabled');
            meetLabel.title = 'Configure a integração Google em Configurações';
        }
    }
}

// Define o "min" do seletor de data como o instante atual (formato datetime-local),
// reforçando visualmente que reunião nova não pode ser no passado.
function setMeetingAtMinNow() {
    const el = document.getElementById('mt-meeting-at');
    if (!el) return;
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    el.min = now.toISOString().slice(0, 16);
}

function openMeetingModal(id = null, dateStr = null) {
    resetMeetingForm();
    checkGoogleReady();
    if (dateStr) document.getElementById('mt-meeting-at').value = dateStr + 'T09:00';
    if (id) {
        // Edição: permite data retroativa (registro de reunião já ocorrida).
        document.getElementById('mt-meeting-at').removeAttribute('min');
        fetch(`${BASE}agenda/get/${id}`).then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            fillMeeting(d.meeting);
            getMeetingModal().show();
        });
    } else {
        // Nova reunião: bloqueia datas passadas no próprio seletor.
        setMeetingAtMinNow();
        document.getElementById('meeting-modal-title').textContent = 'Nova reunião';
        getMeetingModal().show();
    }
}

function fillMeeting(m) {
    document.getElementById('meeting-modal-title').textContent = 'Editar reunião';
    document.getElementById('mt-id').value = m.id;
    const mtType = (m.meeting_type || 'comercial').toString().trim().toLowerCase();
    document.getElementById('mt-type').value = ['operacional','externo'].includes(mtType) ? mtType : 'comercial';
    onMeetingTypeChange();
    document.getElementById('mt-contact-id').value = m.contact_id || '';
    document.getElementById('mt-title').value = m.title || '';
    document.getElementById('mt-meeting-at').value = m.meeting_at ? m.meeting_at.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('mt-client').value = m.contact_id || '';
    document.getElementById('mt-assigned').value = m.assigned_to || '';
    document.getElementById('mt-status').value = m.status || 'a_agendar';
    document.getElementById('mt-closed-by').value = m.closed_by || '';
    // Status do seletor operacional (estados comerciais caem em "a_agendar")
    const opStatus = ['a_agendar','agendada','confirmada','realizada','cancelada'].includes(m.status) ? m.status : 'a_agendar';
    document.getElementById('mt-status-op').value = opStatus;
    onStatusChange();
    document.getElementById('mt-notes').value = m.notes || '';
    document.getElementById('mt-client-email').value = m.client_email || '';
    document.getElementById('mt-google-event-id').value = m.google_event_id || '';
    document.getElementById('mt-meet-link').value = m.meet_link || '';
    // Reflete a opção de reunião já existente (sem regenerar o link).
    // Sala do sistema: link aponta para o próprio site (videocall). Caso contrário, Google Meet.
    (function() {
        const link = m.meet_link || '';
        if (!link) { showMeetLink(''); return; }
        const isSystemRoom = link.indexOf('videocall') !== -1 || (link.indexOf(BASE) === 0);
        const modeRadio = document.getElementById(isSystemRoom ? 'mt-mode-room' : 'mt-mode-meet');
        if (modeRadio && !modeRadio.disabled) modeRadio.checked = true;
        const roomOpts = document.getElementById('mt-room-options');
        if (roomOpts) roomOpts.style.display = isSystemRoom ? '' : 'none';
        showMeetLink(link);
    })();
    // Preenche participantes selecionados (select fonte de verdade + checkboxes do dropdown)
    const ptSel = document.getElementById('mt-participants');
    if (ptSel && m.participants) {
        const ids = m.participants.map(p => String(p.id));
        Array.from(ptSel.options).forEach(o => o.selected = ids.includes(o.value));
    }
    syncParticipantChecks();
    // Convite externo: convidados externos + registrar agendamento.
    if (mtType === 'externo') {
        let guests = m.external_guests || [];
        if (typeof guests === 'string') { try { guests = JSON.parse(guests); } catch (e) { guests = []; } }
        fillExternalGuests(guests);
        const regChk = document.getElementById('mt-register-google');
        if (regChk) regChk.checked = String(m.register_google) === '1' || m.register_google === 1 || m.register_google === true;
    }
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

// Alterna a origem do cliente: CRM (lead) ou Empresa → Contato.
function onClientSourceChange() {
    const isEmpresa = document.getElementById('mt-client-source').value === 'empresa';
    document.querySelectorAll('.mt-source-crm').forEach(el => el.style.display = isEmpresa ? 'none' : '');
    document.querySelectorAll('.mt-source-empresa').forEach(el => el.style.display = isEmpresa ? '' : 'none');
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

// ===== Empresa → Contato (reutiliza users.company_id via Company::getUsers) =====

// ===== Combobox de Empresa =====
function openCompanyList() {
    filterCompanyOptions();
    document.getElementById('mt-company-list').classList.add('open');
}
function closeCompanyList() {
    document.getElementById('mt-company-list').classList.remove('open');
}

// Filtra os itens da lista de empresa pelo texto digitado e mantém a lista aberta.
function filterCompanyOptions() {
    const term = (document.getElementById('mt-company-search').value || '').trim().toLowerCase();
    const listEl = document.getElementById('mt-company-list');
    listEl.classList.add('open');
    let visible = 0;
    listEl.querySelectorAll('.mt-combo-item').forEach(item => {
        const name = item.getAttribute('data-name') || item.textContent.toLowerCase();
        const show = term === '' || name.includes(term);
        item.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    let empty = listEl.querySelector('.mt-combo-empty');
    if (visible === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'mt-combo-empty';
            empty.textContent = 'Nenhuma empresa encontrada';
            listEl.appendChild(empty);
        }
        empty.style.display = '';
    } else if (empty) {
        empty.style.display = 'none';
    }
}

// Seleciona uma empresa a partir do id (usado no clique e ao editar).
function selectCompany(id, name) {
    document.getElementById('mt-company').value = id || '';
    document.getElementById('mt-company-search').value = name || '';
    document.getElementById('mt-company-list').querySelectorAll('.mt-combo-item').forEach(i => {
        i.classList.toggle('selected', i.getAttribute('data-value') === String(id));
    });
    closeCompanyList();
    onCompanyChange();
}

// ===== Combobox de Contato =====
let mtContactCache = []; // contatos carregados da empresa atual

function openContactList() {
    const input = document.getElementById('mt-contact-search');
    if (input.disabled) return;
    filterContactOptions();
    document.getElementById('mt-contact-list').classList.add('open');
}
function closeContactList() {
    document.getElementById('mt-contact-list').classList.remove('open');
}

// (Re)constrói a lista de contatos a partir do cache carregado.
function renderContactList() {
    const listEl = document.getElementById('mt-contact-list');
    listEl.innerHTML = '';
    mtContactCache.forEach(c => {
        const item = document.createElement('div');
        item.className = 'mt-combo-item';
        item.setAttribute('data-value', c.id);
        item.setAttribute('data-name', (c.name || '').toLowerCase());
        item.setAttribute('data-email', c.email || '');
        item.setAttribute('data-phone', c.phone || '');
        item.setAttribute('data-contact-name', c.name || '');
        item.textContent = c.name;
        listEl.appendChild(item);
    });
}

function filterContactOptions() {
    const term = (document.getElementById('mt-contact-search').value || '').trim().toLowerCase();
    const listEl = document.getElementById('mt-contact-list');
    listEl.classList.add('open');
    let visible = 0;
    listEl.querySelectorAll('.mt-combo-item').forEach(item => {
        const name = item.getAttribute('data-name') || item.textContent.toLowerCase();
        const show = term === '' || name.includes(term);
        item.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    let empty = listEl.querySelector('.mt-combo-empty');
    if (visible === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'mt-combo-empty';
            listEl.appendChild(empty);
        }
        empty.textContent = mtContactCache.length ? 'Nenhum contato encontrado' : 'Nenhum contato nesta empresa';
        empty.style.display = '';
    } else if (empty) {
        empty.style.display = 'none';
    }
}

// Ao trocar a empresa: carrega os contatos (users) daquela empresa e reseta o contato.
function onCompanyChange() {
    const companyId = document.getElementById('mt-company').value;
    const contactInput = document.getElementById('mt-contact-search');
    // Reseta o contato atual
    document.getElementById('mt-contact').value = '';
    contactInput.value = '';
    mtContactCache = [];
    document.getElementById('mt-contact-list').innerHTML = '';

    if (!companyId) {
        contactInput.disabled = true;
        contactInput.placeholder = 'Selecione uma empresa primeiro...';
        return;
    }
    contactInput.disabled = true;
    contactInput.placeholder = 'Carregando contatos...';
    fetch(`${BASE}agenda/companyContacts/${companyId}`)
        .then(r => r.json())
        .then(d => {
            mtContactCache = (d && d.contacts) ? d.contacts : [];
            renderContactList();
            if (mtContactCache.length === 0) {
                contactInput.disabled = true;
                contactInput.placeholder = 'Nenhum contato nesta empresa';
                return;
            }
            contactInput.disabled = false;
            contactInput.placeholder = 'Buscar e selecionar contato...';
        })
        .catch(() => {
            contactInput.disabled = true;
            contactInput.placeholder = 'Erro ao carregar contatos';
        });
}

// Seleciona um contato: guarda o id e preenche o snapshot (nome/telefone/email).
// NÃO altera o contact_id do CRM (agenda_meetings.contact_id continua apontando p/ whatsapp_contacts).
function selectContact(item) {
    const id = item.getAttribute('data-value');
    const name  = item.getAttribute('data-contact-name') || '';
    const email = item.getAttribute('data-email') || '';
    const phone = item.getAttribute('data-phone') || '';
    document.getElementById('mt-contact').value = id || '';
    document.getElementById('mt-contact-search').value = name;
    document.getElementById('mt-contact-list').querySelectorAll('.mt-combo-item').forEach(i => {
        i.classList.toggle('selected', i === item);
    });
    document.getElementById('mt-client-name').value = name;
    document.getElementById('mt-client-phone').value = phone;
    if (email) document.getElementById('mt-client-email').value = email;
    closeContactList();
}

// Clique nos itens das listas (delegação) + fechar ao clicar fora.
document.addEventListener('click', function(e) {
    const compItem = e.target.closest('#mt-company-list .mt-combo-item');
    if (compItem) { selectCompany(compItem.getAttribute('data-value'), compItem.textContent); return; }

    const contItem = e.target.closest('#mt-contact-list .mt-combo-item');
    if (contItem) { selectContact(contItem); return; }

    // Fecha as listas se o clique foi fora dos respectivos comboboxes
    if (!e.target.closest('#mt-company-combo')) closeCompanyList();
    if (!e.target.closest('#mt-contact-combo')) closeContactList();
});

// ===== Participantes (dropdown com multisseleção) =====

// Sincroniza o checkbox marcado/desmarcado com o <select multiple> (fonte de verdade).
function onParticipantToggle(cb) {
    const sel = document.getElementById('mt-participants');
    const opt = Array.from(sel.options).find(o => o.value === cb.value);
    if (opt) opt.selected = cb.checked;
    updateParticipantsLabel();
}

// Atualiza o texto do botão: "0 selecionados", "1 selecionado", "3 selecionados".
function updateParticipantsLabel() {
    const sel = document.getElementById('mt-participants');
    const n = Array.from(sel.selectedOptions).length;
    const label = document.getElementById('mt-participants-label');
    if (label) label.textContent = n === 1 ? '1 selecionado' : n + ' selecionados';
}

// Reflete o estado do <select> nos checkboxes (usado ao abrir/editar reunião).
function syncParticipantChecks() {
    const sel = document.getElementById('mt-participants');
    const selectedIds = Array.from(sel.selectedOptions).map(o => o.value);
    document.querySelectorAll('.mt-participant-check').forEach(cb => {
        cb.checked = selectedIds.includes(cb.value);
    });
    updateParticipantsLabel();
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
    // Operacional e externo usam o seletor de status simplificado (sem estados comerciais).
    const typePayload = document.getElementById('mt-type').value;
    const usesOpStatus = (typePayload === 'operacional' || typePayload === 'externo');
    const statusVal = usesOpStatus
        ? document.getElementById('mt-status-op').value
        : document.getElementById('mt-status').value;
    fd.append('status', statusVal);
    fd.append('closed_by', document.getElementById('mt-closed-by').value);
    fd.append('notes', document.getElementById('mt-notes').value);
    fd.append('client_email', document.getElementById('mt-client-email').value.trim());
    // Snapshot do contato escolhido via Empresa → Contato (campos já persistidos hoje)
    fd.append('client_name', document.getElementById('mt-client-name').value.trim());
    fd.append('client_phone', document.getElementById('mt-client-phone').value.trim());

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
    // Participantes da equipe (select fonte de verdade)
    const ptSelPayload = document.getElementById('mt-participants');
    if (ptSelPayload) Array.from(ptSelPayload.selectedOptions).forEach(o => fd.append('participants[]', o.value));
    // Convite externo: convidados externos + registrar agendamento.
    if (document.getElementById('mt-type').value === 'externo') {
        document.querySelectorAll('#mt-external-guests .mt-external-guest-row').forEach(row => {
            const name  = row.querySelector('.mt-ext-name').value.trim();
            const email = row.querySelector('.mt-ext-email').value.trim();
            const phone = row.querySelector('.mt-ext-phone').value.trim();
            if (!name && !email && !phone) return; // ignora linhas vazias
            fd.append('external_name[]', name);
            fd.append('external_email[]', email);
            fd.append('external_phone[]', phone);
        });
        fd.append('register_google', document.getElementById('mt-register-google').checked ? '1' : '0');
    }
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

// ===== Opções de reunião (uma OU outra) =====
// Selecionar "Google Meet" gera o link do Meet imediatamente.
// Selecionar "Sala de vídeo do sistema" cria a sala imediatamente (quando pública);
// se for privada, gera após escolher os administradores.
// Como Meet e Sala compartilham o mesmo campo (mt-meet-link), trocar de opção
// SEMPRE limpa o link anterior — garantindo que nunca existam os dois ao mesmo tempo.
function clearMeetingLink() {
    document.getElementById('mt-meet-link').value = '';
    document.getElementById('mt-google-event-id').value = '';
    document.getElementById('mt-meet-hint').innerHTML = '';
}

function onMeetingModeChange() {
    const mode = document.querySelector('input[name="mt-meeting-mode"]:checked')?.value || '';
    const roomOptions = document.getElementById('mt-room-options');

    // Toda troca de opção descarta o link gerado anteriormente (exclusividade).
    clearMeetingLink();

    // Mostra os controles da sala do sistema apenas no modo "room".
    if (roomOptions) roomOptions.style.display = (mode === 'room') ? '' : 'none';

    if (mode === 'meet') {
        generateMeet();
    } else if (mode === 'room') {
        onRoomVisibilityChange();
        // Sala pública: gera com 1 clique. Sala privada: aguarda escolher admins.
        const visibility = document.querySelector('input[name="mt-room-visibility"]:checked')?.value || 'public';
        if (visibility === 'public') generateVideoRoom();
    }
}

// Gera o link do Meet no Google antes de salvar.
// Pode ser chamada sem botão (a partir do seletor de opções).
function generateMeet(btn) {
    const meetingAt = document.getElementById('mt-meeting-at').value;
    if (!meetingAt) {
        alert('Informe a data e o horário da reunião primeiro.');
        // Reverte a seleção, pois não foi possível gerar.
        document.getElementById('mt-mode-none').checked = true;
        onMeetingModeChange();
        return;
    }
    const hint = document.getElementById('mt-meet-hint');
    hint.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Gerando link do Meet...</span>';
    if (btn) { btn.disabled = true; }

    const fd = new FormData();
    fd.append('title', document.getElementById('mt-title').value.trim() || 'Reunião');
    fd.append('meeting_at', meetingAt);
    fd.append('client_email', document.getElementById('mt-client-email').value.trim());
    fd.append('notes', document.getElementById('mt-notes').value);
    const mid = document.getElementById('mt-id').value;
    if (mid) fd.append('meeting_id', mid);
    // Envia participantes para inclusão no evento Google
    const ptSelMeet = document.getElementById('mt-participants');
    if (ptSelMeet) Array.from(ptSelMeet.selectedOptions).forEach(o => fd.append('participants[]', o.value));

    fetch(`${BASE}agenda/generateMeet`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (btn) { btn.disabled = false; }
            if (d.error) { alert(d.error); clearMeetingLink(); return; }
            document.getElementById('mt-google-event-id').value = d.event_id || '';
            document.getElementById('mt-meet-link').value = d.meet_link || '';
            showMeetLink(d.meet_link);
        })
        .catch(() => { if (btn) { btn.disabled = false; } clearMeetingLink(); alert('Erro ao gerar o link.'); });
}

// Mostra o seletor de administradores quando a sala de vídeo é privada.
function onRoomVisibilityChange() {
    const v = document.querySelector('input[name="mt-room-visibility"]:checked')?.value || 'public';
    const blk = document.getElementById('mt-room-admins-block');
    if (blk) blk.style.display = (v === 'private') ? '' : 'none';
    // Trocar entre pública/privada invalida o link anterior da sala.
    clearMeetingLink();
    // Pública gera direto; privada espera a escolha dos admins.
    if (v === 'public') generateVideoRoom();
}

// Gera uma SALA DE VÍDEO nativa do sistema (WebRTC em grupo, sem API externa).
// O link público entra no campo do Meet (mt-meet-link), então já será enviado
// nos convites por e-mail/WhatsApp e serve para o Fathom entrar e gravar.
// Pode ser chamada sem botão (a partir do seletor de opções).
function generateVideoRoom(btn) {
    const hint = document.getElementById('mt-meet-hint');
    hint.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Criando sala de vídeo...</span>';
    if (btn) { btn.disabled = true; }

    const fd = new FormData();
    fd.append('title', document.getElementById('mt-title').value.trim() || 'Videochamada');
    const mid = document.getElementById('mt-id').value;
    if (mid) fd.append('meeting_id', mid);
    const visibility = document.querySelector('input[name="mt-room-visibility"]:checked')?.value || 'public';
    fd.append('visibility', visibility);
    if (visibility === 'private') {
        Array.from(document.getElementById('mt-room-admins').selectedOptions).forEach(o => fd.append('admins[]', o.value));
    }

    fetch(`${BASE}videocall/create`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (btn) { btn.disabled = false; }
            if (d.error) { alert(d.error); clearMeetingLink(); return; }
            // Reaproveita o campo do Meet para o link da chamada (vai nos convites).
            document.getElementById('mt-meet-link').value = d.url || '';
            document.getElementById('mt-google-event-id').value = '';
            showMeetLink(d.url);
        })
        .catch(() => { if (btn) { btn.disabled = false; } clearMeetingLink(); alert('Erro ao criar a sala de vídeo.'); });
}

function saveMeeting() {
    const title = document.getElementById('mt-title').value.trim();
    if (!title) { alert('Informe o título.'); return; }

    // Data no passado: bloqueia apenas em reunião NOVA (sem id). Ao editar uma reunião
    // já existente permitimos data anterior a agora (registro retroativo).
    const meetingId = document.getElementById('mt-id').value;
    const meetingAtVal = document.getElementById('mt-meeting-at').value;
    if (!meetingId && meetingAtVal) {
        const when = new Date(meetingAtVal);
        if (!isNaN(when.getTime()) && when.getTime() < Date.now()) {
            alert('A data e o horário da reunião não podem estar no passado.');
            document.getElementById('mt-meeting-at').focus();
            return;
        }
    }

    const meetingType = document.getElementById('mt-type').value;
    const isOperational = meetingType === 'operacional';
    const isExternal = meetingType === 'externo';

    if (isExternal) {
        // Convite externo: exige ao menos um convidado com nome + (e-mail ou telefone).
        let validGuests = 0;
        document.querySelectorAll('#mt-external-guests .mt-external-guest-row').forEach(row => {
            const name  = row.querySelector('.mt-ext-name').value.trim();
            const email = row.querySelector('.mt-ext-email').value.trim();
            const phone = row.querySelector('.mt-ext-phone').value.trim();
            if (name && (email || phone)) validGuests++;
        });
        if (validGuests === 0) {
            alert('Informe ao menos um convidado externo com nome e e-mail ou telefone.'); return;
        }
    } else if (isOperational) {
        // Reunião operacional: exige ao menos um participante
        const ptSelOp = document.getElementById('mt-participants');
        if (!ptSelOp || ptSelOp.selectedOptions.length === 0) {
            alert('Selecione ao menos um participante.'); return;
        }
    } else {
        // Reunião comercial: valida conforme a origem do cliente (CRM ou Empresa)
        const source = document.getElementById('mt-client-source').value;
        if (source === 'empresa') {
            if (!document.getElementById('mt-company').value) { alert('Selecione uma empresa.'); return; }
            if (!document.getElementById('mt-contact').value) { alert('Selecione um contato da empresa.'); return; }
        } else {
            const clientVal = document.getElementById('mt-client').value;
            if (!clientVal) { alert('Selecione um cliente (CRM) ou cadastre um novo.'); return; }
            if (clientVal === '__new__' && !document.getElementById('mt-new-name').value.trim()) {
                alert('Informe o nome do novo cliente.'); return;
            }
        }
        // Se convertida, exige quem fechou
        const status = document.getElementById('mt-status').value;
        if (status === 'convertida' && !document.getElementById('mt-closed-by').value) {
            alert('Informe quem fechou o negócio.'); return;
        }
    }

    const id = document.getElementById('mt-id').value;
    const url = id ? `${BASE}agenda/update/${id}` : `${BASE}agenda/create`;

    const fd = collectPayload();

    // Se está editando e mudando status para "cancelada" e há evento Google, pergunta (só comercial)
    if (id && !isOperational && !isExternal) {
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
