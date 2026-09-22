<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Demanda - Solicitação Externa</title>
    <?php
    $faviconUrl = Config::get('app_favicon');
    if ($faviconUrl): ?>
    <link rel="icon" href="<?= baseUrl($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00BFA6; --primary-dark: #00997D; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; min-height: 100vh; padding: 24px 16px; }
        .ext-wrap { max-width: 720px; margin: 0 auto; }
        .ext-topbar {
            background: #fff; border-radius: 14px; padding: 16px 20px; margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
        }
        .logo-text { color: var(--primary); font-weight: 700; font-size: 1.4rem; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e8e8e8; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(0,191,166,0.15); }
        .btn-ext { background: var(--primary); border-color: var(--primary); border-radius: 10px; font-weight: 600; }
        .btn-ext:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    </style>
</head>
<body>
    <div class="ext-wrap">
        <div class="ext-topbar">
            <div class="d-flex align-items-center gap-2">
                <?php $logoUrl = Config::get('app_logo'); ?>
                <?php if ($logoUrl): ?>
                <img src="<?= baseUrl($logoUrl) ?>" alt="Logo" style="max-height:34px;">
                <?php else: ?>
                <span class="logo-text">ON</span>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold" style="font-size:0.95rem;">Nova demanda</div>
                    <div class="text-muted" style="font-size:0.78rem;">Atendente: <?= escape($owner['name']) ?></div>
                </div>
            </div>
            <a href="<?= baseUrl('solicitacaoexterna/logout') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>

        <?php if ($error = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" style="font-size:0.88rem" role="alert">
                <?= escape($error) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Gravação de áudio com transcrição automática (mesma da Nova Demanda interna) -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2" style="font-size:0.9rem"><i class="bi bi-mic"></i> Gravação por voz</h6>
                <p class="text-muted small mb-3">Clique no microfone, descreva sua demanda e o sistema transcreverá e preencherá os campos automaticamente.</p>
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
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= baseUrl('solicitacaoexterna/store') ?>" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">Seu nome *</label>
                            <input type="text" name="requester_name" class="form-control" placeholder="Como podemos te identificar?" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">Empresa vinculada</label>
                            <select name="requester_company" id="field-company" class="form-select">
                                <option value="">Selecione</option>
                                <?php foreach (($companies ?? []) as $company): ?>
                                <option value="<?= escape($company['name']) ?>"><?= escape($company['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">Outra (digitar)</option>
                            </select>
                            <input type="text" name="requester_company_other" id="field-company-other"
                                   class="form-control mt-2" placeholder="Nome da sua empresa" style="display:none;">
                            <small class="text-muted">Opcional. Ajuda a identificar de qual empresa é a solicitação.</small>
                        </div>
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
                            <textarea name="description" id="field-description" class="form-control" rows="6" placeholder="Descreva detalhadamente sua demanda..." required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Anexos</label>
                            <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,video/*,.pdf,.doc,.docx">
                            <small class="text-muted">JPG, PNG, GIF, PDF, DOC, MP4, WebM.</small>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-ext btn-primary text-white px-4">
                                <i class="bi bi-send"></i> Enviar demanda
                            </button>
                            <a href="<?= baseUrl('solicitacaoexterna/logout') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-3">
            <small class="text-muted" style="font-size:0.75rem">&copy; <?= date('Y') ?> ON Solutions.</small>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Campo "Empresa vinculada": mostra o input de texto livre só quando o
    // usuário escolhe "Outra (digitar)" no select.
    (function () {
        const companySelect = document.getElementById('field-company');
        const companyOther = document.getElementById('field-company-other');
        if (companySelect && companyOther) {
            const toggleOther = () => {
                const isOther = companySelect.value === '__other__';
                companyOther.style.display = isOther ? 'block' : 'none';
                if (!isOther) companyOther.value = '';
            };
            companySelect.addEventListener('change', toggleOther);
            toggleOther();
        }
    })();

    // Gravação por voz + transcrição (endpoint externo protegido por PIN).
    let mediaRecorder, audioChunks = [], recordingTimer, seconds = 0;
    const btnRecord = document.getElementById('btn-record');
    const recordStatus = document.getElementById('record-status');
    const recordTimer = document.getElementById('record-timer');
    const recordLoading = document.getElementById('record-loading');

    btnRecord.addEventListener('click', async () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') { stopRecording(); }
        else { startRecording(); }
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
                const response = await fetch('<?= baseUrl("solicitacaoexterna/transcribe") ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ audio: base64 })
                });
                const data = await response.json();
                if (data.success && data.organized) {
                    document.getElementById('field-title').value = data.organized.title || '';
                    document.getElementById('field-description').value = data.organized.description || data.transcription;
                    if (data.organized.category) document.getElementById('field-category').value = data.organized.category;
                    if (data.organized.priority) document.getElementById('field-priority').value = data.organized.priority;
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
</body>
</html>
