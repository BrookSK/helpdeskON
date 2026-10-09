<?php $pageTitle = 'Nova Demanda - ON Solutions Helpdesk'; $currentPage = 'create'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<style>
/* ===== Padrão de campo do formulário de Nova Demanda =====
   Cada campo é um bloco vertical consistente: rótulo no topo, controle,
   e o texto de ajuda sempre no rodapé — alinhado à esquerda do controle.
   Colunas da mesma linha esticam para a mesma altura, então os campos
   ficam alinhados mesmo quando um controle (ex.: lista de atendentes) é
   mais alto que o vizinho. */
.form-field {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.form-field > .form-label {
    margin-bottom: 6px;
}
/* O texto de ajuda ocupa a base do campo, mantendo espaçamento uniforme. */
.form-field > .field-help {
    display: block;
    margin-top: 6px;
    color: #6c757d;
    font-size: 0.8rem;
    line-height: 1.3;
}
/* Empurra a ajuda para a base quando a coluna estica além do controle. */
.form-field > .field-help.field-help-bottom {
    margin-top: auto;
    padding-top: 6px;
}

/* ===== Título de seção (opção A: rótulo sutil + divisória) =====
   Agrupa os campos relacionados sob um cabeçalho leve, mantendo os tons
   claros da tela. */
.form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--primary-dark);
}
.form-section-title i {
    font-size: 0.85rem;
    color: var(--primary);
}
/* A primeira seção não precisa de respiro extra no topo; as demais ganham
   um espaço maior para separar visualmente os grupos. */
.form-section-title.mt-group {
    margin-top: 10px;
}

/* ===== Seleção de atendentes (dropdown + chips) ===== */
#att-dropdown-menu .att-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 0.875rem;
    white-space: normal;
}
#att-dropdown-menu .att-option:hover {
    background: var(--primary-50);
}
#att-dropdown-menu .att-option.selected {
    color: var(--primary-dark);
    font-weight: 500;
}
#att-dropdown-menu .att-option.selected .att-check {
    visibility: visible;
}
#att-dropdown-menu .att-option .att-check {
    visibility: hidden;
    color: var(--primary);
}
#att-dropdown-menu .att-empty {
    padding: 10px 12px;
    color: #6c757d;
    font-size: 0.8rem;
}
/* Chip de atendente selecionado */
.att-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px 4px 10px;
    border: 1px solid var(--primary-light);
    background: var(--primary-50);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--primary-dark);
    line-height: 1.2;
}
.att-chip .att-chip-main {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: var(--primary);
    color: #fff;
    border-radius: 10px;
    padding: 1px 6px;
}
.att-chip .att-chip-remove {
    border: none;
    background: transparent;
    color: var(--primary-dark);
    cursor: pointer;
    padding: 0;
    line-height: 1;
    display: inline-flex;
}
.att-chip .att-chip-remove:hover {
    color: #c62828;
}
</style>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Nova Demanda</h5>
            <small class="text-muted">Descreva por texto ou áudio</small>
        </div>
    </div>

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
                <div class="row g-4">
                    <?php if (in_array(($user['role'] ?? ''), ['super_admin', 'developer'], true) && !empty($clients)): ?>
                    <div class="col-12">
                        <div class="form-section-title"><i class="bi bi-people"></i> Destinatário e atribuição</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-field">
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
                            <small class="field-help field-help-bottom">Primeiro selecione a empresa</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-field">
                            <label class="form-label fw-medium">Cliente (solicitante) *</label>
                            <select name="client_id" id="client-select" class="form-select" required disabled>
                                <option value="">Selecione a empresa primeiro</option>
                            </select>
                            <small class="field-help field-help-bottom">Usuários cadastrados na empresa</small>
                        </div>
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
                        <div class="form-field">
                            <label class="form-label fw-medium">Atendentes (comunicação)</label>
                            <?php if (!empty($attendants)): ?>
                            <!-- Lista de atendentes disponíveis para o JS (nome + papel). -->
                            <script>
                                window.availableAttendants = <?= json_encode(array_map(function ($att) {
                                    return [
                                        'id'    => (int)$att['id'],
                                        'name'  => $att['name'],
                                        'role'  => roleLabel($att['role']),
                                    ];
                                }, $attendants)) ?>;
                            </script>
                            <!-- Botão compacto que abre o dropdown de seleção múltipla. -->
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center" type="button" id="att-dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span><i class="bi bi-person-plus"></i> Adicionar atendentes</span>
                                    <i class="bi bi-chevron-down small"></i>
                                </button>
                                <div class="dropdown-menu w-100 p-0" style="max-height:220px;overflow-y:auto" id="att-dropdown-menu" aria-labelledby="att-dropdown-toggle"></div>
                            </div>
                            <!-- Chips dos atendentes selecionados (ordem = ordem de seleção). -->
                            <div id="att-chips" class="d-flex flex-wrap gap-2 mt-2"></div>
                            <!-- Inputs attendant_ids[] são injetados aqui pelo JS, na ordem das chips. -->
                            <div id="att-hidden-inputs"></div>
                            <?php else: ?>
                            <p class="text-muted small mb-0">Nenhum atendente disponível.</p>
                            <?php endif; ?>
                            <small class="field-help field-help-bottom">Clique em "Adicionar atendentes" e escolha um ou mais. O primeiro selecionado será o principal.</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-field">
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
                            <small class="field-help field-help-bottom">Hierarquia: Papel &gt; usuários daquele papel</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php // Mostra respiro extra acima do título só quando houve a seção
                          // anterior (grupo de atribuição, visível apenas para admin). ?>
                    <?php $hasAssignmentSection = in_array(($user['role'] ?? ''), ['super_admin', 'developer'], true) && !empty($clients); ?>
                    <div class="col-12">
                        <div class="form-section-title <?= $hasAssignmentSection ? 'mt-group' : '' ?>"><i class="bi bi-card-text"></i> Dados da demanda</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <label class="form-label fw-medium">Título *</label>
                            <input type="text" name="title" id="field-title" class="form-control" placeholder="Resumo da sua demanda" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-field">
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
                    </div>
                    <div class="col-sm-6">
                        <div class="form-field">
                            <label class="form-label fw-medium">Prioridade</label>
                            <select name="priority" id="field-priority" class="form-select">
                                <option value="low">Baixa</option>
                                <option value="medium" selected>Média</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <label class="form-label fw-medium">Descrição *</label>
                            <textarea name="description" id="field-description" class="form-control" rows="5" placeholder="Descreva detalhadamente sua demanda..." required></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="transcription" id="field-transcription" value="">
                    <div class="col-12">
                        <div class="form-section-title mt-group"><i class="bi bi-paperclip"></i> Anexos</div>
                    </div>
                    <div class="col-12">
                        <div class="form-field">
                            <label class="form-label fw-medium">Arquivos</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                            <small class="field-help">Máx. 10MB/arquivo (50MB para vídeos). JPG, PNG, GIF, PDF, DOC, MP4, WebM</small>
                        </div>
                    </div>
                </div>
                <!-- Rodapé de ações: alinhado à esquerda, separado dos campos. -->
                <div class="d-flex justify-content-start gap-2 flex-wrap mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send"></i> Enviar Demanda
                    </button>
                    <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
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

// Seleção múltipla de atendentes (dropdown + chips).
// Mantém o mesmo contrato do backend: envia attendant_ids[] na ordem de
// seleção, sendo o primeiro o atendente principal.
(function() {
    const menu = document.getElementById('att-dropdown-menu');
    const chips = document.getElementById('att-chips');
    const hidden = document.getElementById('att-hidden-inputs');
    if (!menu || !chips || !hidden || !window.availableAttendants) return;

    // Ordem de seleção = ordem no array (primeiro = principal).
    const selected = [];

    function render() {
        // Opções do dropdown (marca as já selecionadas).
        menu.innerHTML = '';
        if (!window.availableAttendants.length) {
            const empty = document.createElement('div');
            empty.className = 'att-empty';
            empty.textContent = 'Nenhum atendente disponível.';
            menu.appendChild(empty);
        }
        window.availableAttendants.forEach(function(att) {
            const isSel = selected.indexOf(att.id) !== -1;
            const opt = document.createElement('div');
            opt.className = 'att-option' + (isSel ? ' selected' : '');
            opt.dataset.id = att.id;
            opt.innerHTML =
                '<i class="bi bi-check-lg att-check"></i>' +
                '<span>' + escapeHtml(att.name) + ' — ' + escapeHtml(att.role) + '</span>';
            opt.addEventListener('click', function(e) {
                e.stopPropagation();
                toggle(att.id);
            });
            menu.appendChild(opt);
        });

        // Chips na ordem de seleção.
        chips.innerHTML = '';
        hidden.innerHTML = '';
        selected.forEach(function(id, index) {
            const att = window.availableAttendants.find(a => a.id === id);
            if (!att) return;

            const chip = document.createElement('span');
            chip.className = 'att-chip';
            chip.innerHTML =
                (index === 0 ? '<span class="att-chip-main">Principal</span>' : '') +
                '<span>' + escapeHtml(att.name) + '</span>';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'att-chip-remove';
            btn.setAttribute('aria-label', 'Remover ' + att.name);
            btn.innerHTML = '<i class="bi bi-x-lg"></i>';
            btn.addEventListener('click', function() { toggle(id); });
            chip.appendChild(btn);
            chips.appendChild(chip);

            // Input que o backend lê (attendant_ids[]), na ordem das chips.
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'attendant_ids[]';
            input.value = id;
            hidden.appendChild(input);
        });
    }

    function toggle(id) {
        const i = selected.indexOf(id);
        if (i === -1) {
            selected.push(id);
        } else {
            selected.splice(i, 1);
        }
        render();
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    render();
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
