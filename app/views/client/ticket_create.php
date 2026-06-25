<?php $pageTitle = 'Nova Demanda - ON Solutions Helpdesk'; $currentPage = 'create'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Nova Demanda</h5>
            <small class="text-muted">Descreva sua necessidade por texto ou áudio</small>
        </div>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Gravação de áudio -->
            <div class="mb-4 p-3 border rounded-3 bg-light">
                <h6><i class="bi bi-mic"></i> Gravação por Voz (opcional)</h6>
                <p class="text-muted small mb-3">Clique no microfone, descreva sua demanda e o sistema transcreverá e organizará automaticamente.</p>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" id="btn-record" class="btn btn-lg btn-outline-danger rounded-circle" style="width:60px;height:60px">
                        <i class="bi bi-mic-fill fs-4"></i>
                    </button>
                    <div>
                        <span id="record-status" class="text-muted">Clique para gravar</span>
                        <div id="record-timer" class="fw-bold" style="display:none">00:00</div>
                    </div>
                    <div id="record-loading" style="display:none" class="ms-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="text-muted ms-2">Processando com IA...</span>
                    </div>
                </div>
            </div>

            <form action="<?= baseUrl('tickets/store') ?>" method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">Título da Demanda *</label>
                        <input type="text" name="title" id="field-title" class="form-control" placeholder="Resumo da sua demanda" required>
                    </div>
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Prioridade</label>
                        <select name="priority" id="field-priority" class="form-select">
                            <option value="low">Baixa</option>
                            <option value="medium" selected>Média</option>
                            <option value="high">Alta</option>
                            <option value="urgent">Urgente</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Descrição Detalhada *</label>
                        <textarea name="description" id="field-description" class="form-control" rows="6" placeholder="Descreva detalhadamente sua demanda..." required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Anexos (imagens, documentos)</label>
                        <input type="file" name="attachments[]" class="form-control" multiple accept="image/*,.pdf,.doc,.docx">
                        <small class="text-muted">Máx. 10MB por arquivo. Formatos: JPG, PNG, GIF, PDF, DOC</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            <i class="bi bi-send"></i> Enviar Demanda
                        </button>
                        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-secondary px-4 py-2 ms-2">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
        btnRecord.innerHTML = '<i class="bi bi-stop-fill fs-4"></i>';
        recordStatus.textContent = 'Gravando...';
        recordTimer.style.display = 'block';
        seconds = 0;
        recordingTimer = setInterval(() => {
            seconds++;
            const min = String(Math.floor(seconds / 60)).padStart(2, '0');
            const sec = String(seconds % 60).padStart(2, '0');
            recordTimer.textContent = `${min}:${sec}`;
        }, 1000);
    } catch (err) {
        alert('Não foi possível acessar o microfone. Verifique as permissões.');
    }
}

function stopRecording() {
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach(track => track.stop());
    clearInterval(recordingTimer);
    btnRecord.classList.remove('btn-danger');
    btnRecord.classList.add('btn-outline-danger');
    btnRecord.innerHTML = '<i class="bi bi-mic-fill fs-4"></i>';
    recordStatus.textContent = 'Processando...';
    recordTimer.style.display = 'none';
    recordLoading.style.display = 'block';
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
                if (data.organized.category) {
                    document.getElementById('field-category').value = data.organized.category;
                }
                if (data.organized.priority) {
                    document.getElementById('field-priority').value = data.organized.priority;
                }
                recordStatus.textContent = 'Transcrição aplicada com sucesso!';
                recordStatus.classList.add('text-success');
            } else {
                recordStatus.textContent = data.error || 'Erro na transcrição';
                recordStatus.classList.add('text-danger');
            }
        } catch (err) {
            recordStatus.textContent = 'Erro ao processar áudio.';
            recordStatus.classList.add('text-danger');
        }
        recordLoading.style.display = 'none';
    };
    reader.readAsDataURL(blob);
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
