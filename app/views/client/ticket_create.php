<?php $pageTitle = 'Nova Demanda - ON Solutions Helpdesk'; $currentPage = 'create'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Nova Demanda</h5>
            <small class="text-muted">Descreva por texto ou áudio</small>
        </div>
    </div>

    <?php if (!empty($canShareExternal)): ?>
    <!-- Painel compacto do link de acesso externo -->
    <div class="border border-success-subtle rounded-3 bg-success bg-opacity-10 px-3 py-2 mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <i class="bi bi-link-45deg text-success"></i>
            <span class="fw-semibold small">Link de acesso externo</span>
            <span class="text-muted small flex-grow-1" style="min-width:200px">
                Envie um link para o cliente abrir uma demanda sem ter acesso ao sistema.
                Ele acessa com o PIN que você repassar e a demanda cai direto na sua fila.
            </span>
            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                <button type="button" class="btn btn-success btn-sm"
                        id="btn-share-external"
                        data-has-pin="<?= !empty($hasExternalPin) ? '1' : '0' ?>"
                        data-link="<?= escape($externalLink ?? '') ?>"
                        data-invite-url="<?= escape(baseUrl('tickets/sendExternalInvite')) ?>"
                        onclick="openShareExternal(this)">
                    <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
                </button>
                <button type="button" class="btn btn-outline-success btn-sm"
                        id="btn-copy-external"
                        data-has-pin="<?= !empty($hasExternalPin) ? '1' : '0' ?>"
                        data-link="<?= escape($externalLink ?? '') ?>"
                        onclick="copyExternalLink(this)">
                    <i class="bi bi-clipboard"></i> Copiar link
                </button>
                <span id="share-external-msg" class="small"></span>
            </div>
        </div>
        <?php if (empty($hasExternalPin)): ?>
        <div class="text-warning-emphasis small mt-2">
            <i class="bi bi-exclamation-triangle"></i>
            Você ainda não tem um PIN de acesso externo cadastrado. Cadastre um PIN
            nas suas configurações para liberar este canal.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($canShareExternal)): ?>
    <!-- Modal: enviar o link de acesso externo direto pelo WhatsApp do cliente -->
    <div class="modal fade" id="shareExternalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-whatsapp text-success"></i> Enviar link de acesso externo</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Enviaremos o link de acesso pelo WhatsApp do cliente. Escolha um cliente
                        já cadastrado (o número é preenchido sozinho) ou digite o WhatsApp manualmente.
                    </p>
                    <div class="alert alert-info small py-2 px-3 mb-3">
                        <i class="bi bi-key"></i>
                        O cliente precisa do seu PIN para acessar. Repasse-o por outro meio, ou
                        marque a opção abaixo para enviá-lo junto com a mensagem.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Cliente cadastrado</label>
                        <select id="invite-client" class="form-select">
                            <option value="">Selecione (ou digite o número abaixo)</option>
                            <?php foreach (($clients ?? []) as $client): ?>
                            <option value="<?= (int)$client['id'] ?>"
                                    data-phone="<?= escape(preg_replace('/\D/', '', $client['phone'] ?? '')) ?>"
                                    data-name="<?= escape($client['name']) ?>">
                                <?= escape($client['name']) ?><?= !empty($client['company_name']) ? ' — ' . escape($client['company_name']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Puxa o WhatsApp cadastrado do cliente automaticamente.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">WhatsApp do cliente *</label>
                        <input type="tel" id="invite-phone" class="form-control" placeholder="(11) 99999-9999"
                               inputmode="numeric" autocomplete="off" maxlength="16">
                        <small class="text-muted">Com DDD. Ex.: (11) 99999-8888</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">Nome do cliente</label>
                        <input type="text" id="invite-name" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="invite-include-pin">
                        <label class="form-check-label small" for="invite-include-pin">
                            Enviar meu PIN junto à mensagem
                        </label>
                    </div>
                    <p class="text-muted small mb-3" style="font-size:0.78rem">
                        Por padrão o PIN não vai na mensagem. Marque só se quiser mais praticidade —
                        lembre que qualquer pessoa com acesso a esta conversa verá o PIN.
                    </p>
                    <div id="invite-feedback" class="small mb-2"></div>
                    <div class="d-grid">
                        <button type="button" id="invite-send" class="btn btn-success btn-sm">
                            <i class="bi bi-send"></i> Enviar convite
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Abre o modal de compartilhamento. Só permite se o usuário logado tem PIN.
    let shareExternalLinkValue = '';
    let shareExternalInviteUrl = '';
    function openShareExternal(btn) {
        const msg = document.getElementById('share-external-msg');
        if (btn.getAttribute('data-has-pin') !== '1') {
            msg.textContent = 'Você não possui um PIN cadastrado';
            msg.className = 'small text-danger';
            return;
        }
        msg.textContent = '';
        shareExternalLinkValue = btn.getAttribute('data-link') || '';
        shareExternalInviteUrl = btn.getAttribute('data-invite-url') || '';
        const modal = new bootstrap.Modal(document.getElementById('shareExternalModal'));
        modal.show();
    }

    // Copia o link direto (sem abrir o modal), igual ao comportamento antigo.
    // Só permite se o usuário logado possui um PIN cadastrado.
    function copyExternalLink(btn) {
        const msg = document.getElementById('share-external-msg');
        if (btn.getAttribute('data-has-pin') !== '1') {
            msg.textContent = 'Você não possui um PIN cadastrado';
            msg.className = 'small text-danger';
            return;
        }
        const link = btn.getAttribute('data-link') || '';
        const done = () => {
            msg.textContent = 'Link copiado!';
            msg.className = 'small text-success';
            setTimeout(() => { msg.textContent = ''; }, 3000);
        };
        const fallback = () => {
            const ta = document.createElement('textarea');
            ta.value = link; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.focus(); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {
                msg.textContent = 'Não foi possível copiar. Link: ' + link;
                msg.className = 'small text-muted';
            }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(done).catch(fallback);
        } else {
            fallback();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const clientSelect = document.getElementById('invite-client');
        const phoneInput = document.getElementById('invite-phone');
        const nameInput = document.getElementById('invite-name');
        const includePinInput = document.getElementById('invite-include-pin');
        const sendBtn = document.getElementById('invite-send');
        const feedback = document.getElementById('invite-feedback');
        if (!sendBtn) return; // modal só existe para quem pode compartilhar

        const setFeedback = (text, cls) => {
            feedback.textContent = text;
            feedback.className = 'small mb-2 ' + cls;
        };

        // Formata o número no padrão brasileiro conforme o usuário digita:
        // 10 dígitos -> (XX) XXXX-XXXX (fixo)
        // 11 dígitos -> (XX) XXXXX-XXXX (celular)
        // O envio continua usando só os dígitos (o backend limpa a máscara).
        const formatPhoneBR = (value) => {
            const d = (value || '').replace(/\D/g, '').slice(0, 11);
            if (d.length === 0) return '';
            if (d.length <= 2) return '(' + d;
            if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
            if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
        };

        // Aplica a máscara enquanto digita.
        phoneInput.addEventListener('input', () => {
            phoneInput.value = formatPhoneBR(phoneInput.value);
        });

        // Ao escolher um cliente cadastrado: puxa o telefone dele. Se não tiver
        // número cadastrado, avisa e deixa o campo livre para digitar.
        if (clientSelect) {
            clientSelect.addEventListener('change', () => {
                const opt = clientSelect.options[clientSelect.selectedIndex];
                if (!clientSelect.value) {
                    setFeedback('', '');
                    return;
                }
                const phone = (opt.getAttribute('data-phone') || '').replace(/\D/g, '');
                const name = opt.getAttribute('data-name') || '';
                nameInput.value = name;
                if (phone) {
                    phoneInput.value = formatPhoneBR(phone);
                    setFeedback('WhatsApp do cliente preenchido.', 'text-success');
                } else {
                    phoneInput.value = '';
                    setFeedback('⚠️ Este cliente não possui WhatsApp cadastrado. Digite o número manualmente.', 'text-warning');
                }
            });
        }

        sendBtn.addEventListener('click', async () => {
            const phone = phoneInput.value.replace(/\D/g, '');
            // Número nacional (sem DDI 55): celular tem 11 dígitos (DDD + 9 + 8).
            // O erro clássico é digitar sem o 9º dígito (10 dígitos), e aí o
            // WhatsApp não encontra o número. Orientamos o usuário de forma clara.
            const national = phone.startsWith('55') ? phone.slice(2) : phone;
            if (national.length === 10) {
                setFeedback('Parece que falta um dígito. Celular tem 11 números: DDD + 9 + número. Ex.: (17) 99970-3514.', 'text-danger');
                return;
            }
            if (national.length < 10 || national.length > 11) {
                setFeedback('Informe um WhatsApp válido com DDD. Ex.: (17) 99970-3514.', 'text-danger');
                return;
            }
            sendBtn.disabled = true;
            const original = sendBtn.innerHTML;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
            setFeedback('', '');
            try {
                const resp = await fetch(shareExternalInviteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        phone: phone,
                        name: nameInput.value.trim(),
                        user_id: clientSelect ? (clientSelect.value || '') : '',
                        include_pin: includePinInput ? includePinInput.checked : false
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    setFeedback('✓ ' + (data.message || 'Convite enviado!'), 'text-success');
                    phoneInput.value = ''; nameInput.value = '';
                } else {
                    setFeedback(data.error || 'Não foi possível enviar.', 'text-danger');
                }
            } catch (e) {
                setFeedback('Erro de conexão ao enviar.', 'text-danger');
            }
            sendBtn.disabled = false;
            sendBtn.innerHTML = original;
        });
    });
    </script>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Gravação de áudio -->
            <div class="mb-4 p-3 border rounded-3 bg-light">
                <h6 class="mb-2" style="font-size:0.9rem"><i class="bi bi-mic"></i> Gravação por Voz</h6>
                <p class="text-muted small mb-3">Clique no microfone, descreva sua demanda e o sistema transcreverá automaticamente.</p>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button type="button" id="btn-record" class="btn btn-lg btn-outline-danger rounded-circle flex-shrink-0" style="width:56px;height:56px">
                        <i class="bi bi-mic-fill fs-5"></i>
                    </button>
                    <div>
                        <span id="record-status" class="text-muted small">Clique para gravar</span>
                        <div id="record-timer" class="fw-bold" style="display:none">00:00</div>
                    </div>
                    <div id="record-loading" style="display:none" class="ms-auto">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="text-muted ms-1 small">Processando...</span>
                    </div>
                </div>
            </div>

            <form action="<?= baseUrl('tickets/store') ?>" method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <?php if (($user['role'] ?? '') === 'super_admin' && !empty($clients)): ?>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Empresa *</label>
                        <select id="company-select" class="form-select" required>
                            <option value="">Selecione a empresa</option>
                            <?php foreach (($companies ?? []) as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= escape($company['name']) ?></option>
                            <?php endforeach; ?>
                            <?php
                            // Verifica se há clientes sem empresa vinculada
                            $hasNoCompanyClients = false;
                            foreach ($clients as $c) { if (empty($c['company_id'])) { $hasNoCompanyClients = true; break; } }
                            ?>
                            <?php if ($hasNoCompanyClients): ?>
                            <option value="0">Sem empresa</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Primeiro selecione a empresa</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Cliente (solicitante) *</label>
                        <select name="client_id" id="client-select" class="form-select" required disabled>
                            <option value="">Selecione a empresa primeiro</option>
                        </select>
                        <small class="text-muted">Usuários cadastrados na empresa</small>
                    </div>
                    <script>
                        // Clientes agrupados por empresa (Empresa > Usuários)
                        window.clientsByCompany = <?= json_encode(array_map(function ($c) {
                            return [
                                'id' => (int)$c['id'],
                                'name' => $c['name'],
                                'email' => $c['email'],
                                'company_id' => $c['company_id'] ? (int)$c['company_id'] : 0,
                            ];
                        }, $clients)) ?>;
                    </script>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Atendentes (comunicação)</label>
                        <?php if (!empty($attendants)): ?>
                        <div class="border rounded-3 p-2" style="max-height:180px;overflow-y:auto">
                            <?php foreach (($attendants ?? []) as $att): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="attendant_ids[]" value="<?= $att['id'] ?>" id="att-<?= $att['id'] ?>">
                                <label class="form-check-label" for="att-<?= $att['id'] ?>">
                                    <?= escape($att['name']) ?> — <?= roleLabel($att['role']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-muted small mb-0">Nenhum atendente disponível.</p>
                        <?php endif; ?>
                        <small class="text-muted">Selecione um ou mais atendentes. O primeiro marcado será o principal.</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Responsável Técnico</label>
                        <select name="technical_responsible_id" class="form-select">
                            <option value="">Não atribuir agora</option>
                            <?php foreach (($technicalGrouped ?? []) as $roleKey => $usersInRole): ?>
                            <optgroup label="<?= roleLabel($roleKey) ?>">
                                <?php foreach ($usersInRole as $tu): ?>
                                <option value="<?= $tu['id'] ?>"><?= escape($tu['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hierarquia: Papel &gt; usuários daquele papel</small>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label fw-medium">Título *</label>
                        <input type="text" name="title" id="field-title" class="form-control" placeholder="Resumo da sua demanda" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Categoria</label>
                        <select name="category" id="field-category" class="form-select">
                            <option value="">Selecione</option>
                            <option value="design">Design</option>
                            <option value="desenvolvimento">Desenvolvimento</option>
                            <option value="marketing">Marketing</option>
                            <option value="suporte">Suporte</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium">Prioridade</label>
                        <select name="priority" id="field-priority" class="form-select">
                            <option value="low">Baixa</option>
                            <option value="medium" selected>Média</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Descrição *</label>
                        <textarea name="description" id="field-description" class="form-control" rows="5" placeholder="Descreva detalhadamente sua demanda..." required></textarea>
                    </div>
                    <input type="hidden" name="transcription" id="field-transcription" value="">
                    <div class="col-12">
                        <label class="form-label fw-medium">Anexos</label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                        <small class="text-muted">Máx. 10MB/arquivo (50MB para vídeos). JPG, PNG, GIF, PDF, DOC, MP4, WebM</small>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send"></i> Enviar Demanda
                        </button>
                        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Seleção hierárquica Empresa > Usuários
(function() {
    const companySelect = document.getElementById('company-select');
    const clientSelect = document.getElementById('client-select');
    if (!companySelect || !clientSelect || !window.clientsByCompany) return;

    companySelect.addEventListener('change', function() {
        const companyId = parseInt(this.value, 10);
        clientSelect.innerHTML = '';

        if (isNaN(companyId) || this.value === '') {
            clientSelect.innerHTML = '<option value="">Selecione a empresa primeiro</option>';
            clientSelect.disabled = true;
            return;
        }

        const users = window.clientsByCompany.filter(c => c.company_id === companyId);
        if (users.length === 0) {
            clientSelect.innerHTML = '<option value="">Nenhum usuário nesta empresa</option>';
            clientSelect.disabled = true;
            return;
        }

        clientSelect.disabled = false;
        clientSelect.innerHTML = '<option value="">Selecione o usuário</option>';
        users.forEach(function(u) {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = u.name + ' (' + u.email + ')';
            clientSelect.appendChild(opt);
        });
    });
})();

let mediaRecorder;
let audioChunks = [];
let recordingTimer;
let seconds = 0;

const btnRecord = document.getElementById('btn-record');
const recordStatus = document.getElementById('record-status');
const recordTimer = document.getElementById('record-timer');
const recordLoading = document.getElementById('record-loading');

btnRecord.addEventListener('click', async () => {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopRecording();
    } else {
        startRecording();
    }
});

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
        audioChunks = [];

        mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
        mediaRecorder.onstop = processAudio;

        mediaRecorder.start();
        btnRecord.classList.remove('btn-outline-danger');
        btnRecord.classList.add('btn-danger');
        btnRecord.innerHTML = '<i class="bi bi-stop-fill fs-5"></i>';
        recordStatus.textContent = 'Gravando...';
        recordStatus.className = 'text-danger small fw-medium';
        recordTimer.style.display = 'block';
        seconds = 0;
        recordingTimer = setInterval(() => {
            seconds++;
            const min = String(Math.floor(seconds / 60)).padStart(2, '0');
            const sec = String(seconds % 60).padStart(2, '0');
            recordTimer.textContent = `${min}:${sec}`;
        }, 1000);
    } catch (err) {
        alert('Não foi possível acessar o microfone.');
    }
}

function stopRecording() {
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach(track => track.stop());
    clearInterval(recordingTimer);
    btnRecord.classList.remove('btn-danger');
    btnRecord.classList.add('btn-outline-danger');
    btnRecord.innerHTML = '<i class="bi bi-mic-fill fs-5"></i>';
    recordStatus.textContent = 'Processando...';
    recordStatus.className = 'text-muted small';
    recordTimer.style.display = 'none';
    recordLoading.style.display = 'flex';
}

async function processAudio() {
    const blob = new Blob(audioChunks, { type: 'audio/webm' });
    const reader = new FileReader();
    reader.onload = async function() {
        const base64 = reader.result.split(',')[1];
        try {
            const response = await fetch('<?= baseUrl("api/transcribe") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ audio: base64 })
            });
            const data = await response.json();
            if (data.success && data.organized) {
                document.getElementById('field-title').value = data.organized.title || '';
                document.getElementById('field-description').value = data.organized.description || data.transcription;
                document.getElementById('field-transcription').value = data.transcription || '';
                if (data.organized.category) {
                    document.getElementById('field-category').value = data.organized.category;
                }
                if (data.organized.priority) {
                    document.getElementById('field-priority').value = data.organized.priority;
                }
                recordStatus.textContent = '✓ Campos preenchidos!';
                recordStatus.className = 'text-success small fw-medium';
            } else {
                recordStatus.textContent = data.error || 'Erro na transcrição';
                recordStatus.className = 'text-danger small';
            }
        } catch (err) {
            recordStatus.textContent = 'Erro ao processar áudio.';
            recordStatus.className = 'text-danger small';
        }
        recordLoading.style.display = 'none';
    };
    reader.readAsDataURL(blob);
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
