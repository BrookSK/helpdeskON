<!-- Modal Gerenciar Lead -->
<div class="modal fade" id="leadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-person-lines-fill"></i> Gerenciar lead</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ld-id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Nome</label>
                        <input type="text" id="ld-contact_name" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-medium">Telefone</label>
                        <input type="text" id="ld-phone" class="form-control form-control-sm" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-medium">E-mail</label>
                        <input type="email" id="ld-lead_email" class="form-control form-control-sm" placeholder="email@empresa.com">
                    </div>
                </div>

                <hr>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-semibold mb-0" style="font-size:0.85rem;"><i class="bi bi-clipboard-data"></i> Briefing comercial</h6>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small mb-1">Necessidade</label>
                        <textarea id="ld-bf-need" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Principal dor</label>
                        <textarea id="ld-bf-main_pain" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Objetivo esperado</label>
                        <textarea id="ld-bf-expected_goal" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Solução atual</label>
                        <input type="text" id="ld-bf-current_solution" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Faixa de investimento</label>
                        <input type="text" id="ld-bf-investment_range" class="form-control form-control-sm" placeholder="R$ 0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Urgência/prazo</label>
                        <select id="ld-bf-urgency" class="form-select form-select-sm">
                            <option value="">Selecione</option>
                            <option value="Baixa">Baixa</option>
                            <option value="Média">Média</option>
                            <option value="Alta">Alta</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Nível de decisão</label>
                        <input type="text" id="ld-bf-decision_level" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Temperatura</label>
                        <select id="ld-bf-lead_temperature" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option value="frio">Frio</option>
                            <option value="morno">Morno</option>
                            <option value="quente">Quente</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Fonte do lead</label>
                        <select id="ld-bf-lead_source" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option value="telefonema">Telefonema</option>
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="instagram">Instagram</option>
                            <option value="facebook">Facebook</option>
                            <option value="apollo">Apollo.io</option>
                            <option value="manual_email">E-mail manual</option>
                            <option value="form">Formulário</option>
                            <option value="freelas99">99Freelas</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small mb-1">Principal objeção</label>
                        <textarea id="ld-bf-main_objection" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Próximo passo</label>
                        <textarea id="ld-bf-next_step" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">Observações</label>
                        <textarea id="ld-bf-notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-success" id="ld-chat-btn" href="#"><i class="bi bi-whatsapp"></i> Iniciar chat</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="ld-call-btn" style="display:none;" onclick="callLeadFromModal(this)"><i class="bi bi-telephone-outbound"></i> Telefonar</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button class="btn btn-sm btn-primary" onclick="saveLead()"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let leadModalInstance = null;
const LD_BF_FIELDS = ['need','main_pain','current_solution','expected_goal','urgency','investment_range','decision_level','lead_temperature','lead_source','main_objection','next_step','notes'];

function getLeadModal() {
    if (!leadModalInstance) leadModalInstance = new bootstrap.Modal(document.getElementById('leadModal'));
    return leadModalInstance;
}

function openLead(id) {
    document.getElementById('ld-id').value = id;
    document.getElementById('ld-contact_name').value = '';
    document.getElementById('ld-phone').value = '';
    document.getElementById('ld-lead_email').value = '';
    LD_BF_FIELDS.forEach(k => { const el = document.getElementById('ld-bf-' + k); if (el) el.value = ''; });
    document.getElementById('ld-chat-btn').href = BASE + 'whatsapp/chat/' + id;

    fetch(BASE + 'crm/leadDetail/' + id, { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            const c = d.contact || {};
            document.getElementById('ld-contact_name').value = c.contact_name || c.push_name || '';
            document.getElementById('ld-phone').value = c.phone || '';
            document.getElementById('ld-lead_email').value = c.lead_email || '';
            // Botão Telefonar só quando há telefone
            const callBtn = document.getElementById('ld-call-btn');
            if (c.phone) { callBtn.style.display = ''; callBtn.dataset.lead = id; }
            else callBtn.style.display = 'none';
            const b = d.briefing;
            if (b) LD_BF_FIELDS.forEach(k => { const el = document.getElementById('ld-bf-' + k); if (el && b[k] != null) el.value = b[k]; });
            getLeadModal().show();
        });
}

function callLeadFromModal(btn) {
    const id = btn.dataset.lead || document.getElementById('ld-id').value;
    if (typeof callLead === 'function') callLead(id, btn);
}

function saveLead() {
    const id = document.getElementById('ld-id').value;
    if (!id) return;
    const fd = new FormData();
    fd.append('contact_name', document.getElementById('ld-contact_name').value.trim());
    fd.append('phone', document.getElementById('ld-phone').value.trim());
    fd.append('lead_email', document.getElementById('ld-lead_email').value.trim());
    LD_BF_FIELDS.forEach(k => fd.append('bf_' + k, document.getElementById('ld-bf-' + k).value));

    fetch(BASE + 'crm/updateLead/' + id, { method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => {
            if (d.error) { alert(d.error); return; }
            location.reload();
        });
}
</script>
