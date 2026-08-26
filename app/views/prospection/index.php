<?php $pageTitle = 'Prospecção por E-mail'; $currentPage = 'prospection'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-paper"></i> Prospecção por E-mail</h5>
            <small class="text-muted">Envie e-mails comerciais vinculados aos leads do CRM</small>
        </div>
        <a href="<?= baseUrl('prospection/history') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history"></i> Histórico</a>
    </div>

    <?php if (empty($accounts)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Nenhuma conta de e-mail vinculada ao seu usuário.
        <?php if ($user['role'] === 'super_admin'): ?>
        <a href="<?= baseUrl('settings/emailAccounts') ?>">Configurar contas</a>
        <?php else: ?>
        Peça ao administrador para vincular uma conta ao seu perfil.
        <?php endif; ?>
    </div>
    <?php else: ?>

    <div class="row g-3">
        <!-- Formulário de envio -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem;"><i class="bi bi-send"></i> Novo E-mail</h6>
                    <span class="badge bg-light text-dark border" id="send-status"></span>
                </div>
                <div class="card-body">
                    <form id="prospection-form" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- Conta de e-mail -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Enviar de *</label>
                                <select id="pf-account" class="form-select form-select-sm" required>
                                    <?php if (count($accounts) > 1): ?>
                                    <option value="">Selecione a conta...</option>
                                    <?php endif; ?>
                                    <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= escape($acc['display_name'] ? $acc['display_name'] . ' <' . $acc['email'] . '>' : $acc['email']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Destinatário -->
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Para (e-mail) *</label>
                                <input type="email" id="pf-recipient-email" class="form-control form-control-sm" placeholder="destinatario@empresa.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Nome do destinatário</label>
                                <input type="text" id="pf-recipient-name" class="form-control form-control-sm" placeholder="Nome da pessoa">
                            </div>

                            <!-- CC / BCC -->
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">CC <small class="text-muted">(separar por vírgula)</small></label>
                                <input type="text" id="pf-cc" class="form-control form-control-sm" placeholder="copia@email.com, outro@email.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">CCO <small class="text-muted">(cópia oculta)</small></label>
                                <input type="text" id="pf-bcc" class="form-control form-control-sm" placeholder="oculto@email.com">
                            </div>

                            <!-- Assunto -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Assunto *</label>
                                <input type="text" id="pf-subject" class="form-control form-control-sm" placeholder="Assunto do e-mail" required>
                            </div>

                            <!-- Body (Rich Text) -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Mensagem *</label>
                                <div id="pf-editor" style="min-height:200px;"></div>
                            </div>

                            <!-- Anexos -->
                            <div class="col-12">
                                <label class="form-label small fw-medium">Anexos</label>
                                <input type="file" id="pf-attachments" class="form-control form-control-sm" multiple>
                                <small class="text-muted">Selecione um ou mais arquivos</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary" id="pf-send-btn">
                                <i class="bi bi-send"></i> Enviar E-mail
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Painel lateral: vínculo com lead -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem;"><i class="bi bi-link-45deg"></i> Vincular a Lead</h6>
                </div>
                <div class="card-body">
                    <label class="form-label small fw-medium">Contato (CRM)</label>
                    <select id="pf-contact" class="form-select form-select-sm" onchange="onContactChange()">
                        <option value="">Nenhum (envio sem vínculo)</option>
                        <?php foreach ($leads as $l): ?>
                        <option value="<?= $l['id'] ?>"><?= escape($l['contact_name'] ?: ('Contato #' . $l['id'])) ?><?= $l['phone'] ? ' — ' . escape($l['phone']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Vincule a um lead do CRM para contabilizar nas métricas</small>

                    <!-- Info do lead selecionado -->
                    <div id="lead-info" class="mt-3" style="display:none;">
                        <hr>
                        <h6 class="small fw-semibold mb-2"><i class="bi bi-clipboard-data"></i> Briefing do Lead</h6>
                        <div id="lead-info-content" style="font-size:0.78rem;"></div>
                    </div>
                </div>
            </div>

            <!-- Últimos envios -->
            <div class="card mt-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold" style="font-size:0.85rem;"><i class="bi bi-clock-history"></i> Últimos Envios</h6>
                </div>
                <div class="card-body p-2" id="recent-sends" style="max-height:300px;overflow-y:auto;">
                    <p class="text-muted small text-center mb-0">Carregando...</p>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Quill Editor CSS/JS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<script>
const BASE = '<?= baseUrl("") ?>';
let quill = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar editor rich text
    const editorEl = document.getElementById('pf-editor');
    if (editorEl) {
        quill = new Quill('#pf-editor', {
            theme: 'snow',
            placeholder: 'Escreva sua mensagem...',
            modules: {
                toolbar: [
                    [{'header': [1, 2, 3, false]}],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{'color': []}, {'background': []}],
                    [{'list': 'ordered'}, {'list': 'bullet'}],
                    [{'align': []}],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });
    }

    // Carregar últimos envios
    loadRecentSends();

    // Form submit
    document.getElementById('prospection-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        sendEmail();
    });
});

function sendEmail() {
    const btn = document.getElementById('pf-send-btn');
    const statusEl = document.getElementById('send-status');

    // Validações
    const accountId = document.getElementById('pf-account').value;
    const recipientEmail = document.getElementById('pf-recipient-email').value.trim();
    const subject = document.getElementById('pf-subject').value.trim();
    const body = quill ? quill.root.innerHTML : '';

    if (!accountId) { alert('Selecione a conta de e-mail.'); return; }
    if (!recipientEmail) { alert('Informe o e-mail do destinatário.'); return; }
    if (!subject) { alert('Informe o assunto.'); return; }
    if (!body || body === '<p><br></p>') { alert('Escreva a mensagem.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    statusEl.textContent = '';

    const fd = new FormData();
    fd.append('email_account_id', accountId);
    fd.append('recipient_email', recipientEmail);
    fd.append('recipient_name', document.getElementById('pf-recipient-name').value.trim());
    fd.append('cc', document.getElementById('pf-cc').value.trim());
    fd.append('bcc', document.getElementById('pf-bcc').value.trim());
    fd.append('subject', subject);
    fd.append('body', body);
    fd.append('contact_id', document.getElementById('pf-contact').value);

    // Anexos
    const files = document.getElementById('pf-attachments').files;
    for (let i = 0; i < files.length; i++) {
        fd.append('attachments[]', files[i]);
    }

    fetch(`${BASE}prospection/send`, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Enviar E-mail';

        if (d.success) {
            statusEl.textContent = '✓ Enviado!';
            statusEl.className = 'badge bg-success';
            // Limpar formulário
            document.getElementById('pf-recipient-email').value = '';
            document.getElementById('pf-recipient-name').value = '';
            document.getElementById('pf-cc').value = '';
            document.getElementById('pf-bcc').value = '';
            document.getElementById('pf-subject').value = '';
            document.getElementById('pf-attachments').value = '';
            if (quill) quill.setContents([]);
            loadRecentSends();
            setTimeout(() => { statusEl.textContent = ''; statusEl.className = 'badge bg-light text-dark border'; }, 4000);
        } else {
            statusEl.textContent = '✗ Falhou';
            statusEl.className = 'badge bg-danger';
            alert(d.error || 'Erro ao enviar.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Enviar E-mail';
        alert('Erro de conexão.');
    });
}

function onContactChange() {
    const contactId = document.getElementById('pf-contact').value;
    const infoDiv = document.getElementById('lead-info');
    const contentDiv = document.getElementById('lead-info-content');

    if (!contactId) {
        infoDiv.style.display = 'none';
        return;
    }

    fetch(`${BASE}prospection/leadInfo/${contactId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.contact) { infoDiv.style.display = 'none'; return; }

            // Preenche automaticamente os dados de contato do destinatário
            const nameField = document.getElementById('pf-recipient-name');
            const emailField = document.getElementById('pf-recipient-email');
            if (d.contact.name && !nameField.value.trim()) nameField.value = d.contact.name;
            if (d.contact.email) {
                emailField.value = d.contact.email;
            } else if (!emailField.value.trim()) {
                // Sem e-mail cadastrado: sinaliza para o usuário preencher
                emailField.placeholder = 'Lead sem e-mail cadastrado — preencha aqui';
            }

            let html = `<p><strong>${d.contact.name}</strong>`;
            if (d.contact.phone) html += ` — ${d.contact.phone}`;
            html += '</p>';
            if (d.contact.email) html += `<p><strong>E-mail:</strong> ${d.contact.email}</p>`;

            if (d.briefing) {
                const bf = d.briefing;
                if (bf.need) html += `<p><strong>Necessidade:</strong> ${bf.need}</p>`;
                if (bf.main_pain) html += `<p><strong>Dor:</strong> ${bf.main_pain}</p>`;
                if (bf.lead_temperature) html += `<p><strong>Temperatura:</strong> ${bf.lead_temperature}</p>`;
                if (bf.investment_range) html += `<p><strong>Investimento:</strong> ${bf.investment_range}</p>`;
                if (bf.next_step) html += `<p><strong>Próximo passo:</strong> ${bf.next_step}</p>`;
            } else {
                html += '<p class="text-muted">Sem briefing cadastrado.</p>';
            }

            contentDiv.innerHTML = html;
            infoDiv.style.display = '';
        });
}

function loadRecentSends() {
    const container = document.getElementById('recent-sends');
    if (!container) return;

    fetch(`${BASE}prospection/history?format=json&limit=5`)
        .then(r => r.text())
        .then(text => {
            // Se retornou HTML (página inteira), usa fallback
            try {
                const d = JSON.parse(text);
                if (d.prospections && d.prospections.length) {
                    container.innerHTML = d.prospections.map(p => `
                        <div class="border-bottom py-2 px-1" style="font-size:0.75rem;">
                            <div class="d-flex justify-content-between">
                                <strong>${p.recipient_email}</strong>
                                <span class="badge ${p.status === 'sent' ? 'bg-success' : 'bg-danger'}" style="font-size:0.6rem;">${p.status === 'sent' ? 'Enviado' : 'Falhou'}</span>
                            </div>
                            <div class="text-muted">${p.subject}</div>
                            <div class="text-muted">${p.sent_at ? new Date(p.sent_at).toLocaleString('pt-BR') : '—'}</div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<p class="text-muted small text-center mb-0">Nenhum envio ainda.</p>';
                }
            } catch(e) {
                container.innerHTML = '<p class="text-muted small text-center mb-0"><a href="' + BASE + 'prospection/history">Ver histórico</a></p>';
            }
        })
        .catch(() => {
            container.innerHTML = '<p class="text-muted small text-center mb-0">—</p>';
        });
}
</script>

<style>
#pf-editor { background: #fff; border-radius: 0 0 6px 6px; }
.ql-toolbar { border-radius: 6px 6px 0 0; }
.ql-container { border-radius: 0 0 6px 6px; min-height: 200px; }
</style>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
