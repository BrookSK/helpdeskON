<?php $pageTitle = 'Demanda #' . $ticket['id'] . ' - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Demanda #<?= $ticket['id'] ?></h5>
            <small class="text-muted text-truncate d-block" style="max-width:250px"><?= escape($ticket['title']) ?></small>
        </div>
        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Detalhes + Chat -->
        <div class="col-lg-8">
            <!-- Detalhes -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0">Detalhes</h6>
                    <span class="badge-status badge-<?= $ticket['status'] ?>"><?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-2" style="font-size:0.88rem">
                        <div class="col-sm-6">
                            <strong>Cliente:</strong> <?= escape($ticket['client_name']) ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Email:</strong> <?= escape($ticket['client_email']) ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Categoria:</strong> <?= escape($ticket['category'] ?? 'Não definida') ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Prioridade:</strong> <span class="priority-<?= $ticket['priority'] ?>"><?= ucfirst($ticket['priority']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <strong>Atendente:</strong> <?= escape($ticket['attendant_name'] ?? 'Não atribuído') ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Criado:</strong> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                        </div>
                    </div>
                    <hr>
                    <h6 class="fw-bold" style="font-size:0.88rem">Descrição</h6>
                    <div class="p-3 bg-light rounded" style="font-size:0.85rem;line-height:1.6"><?= nl2br(escape($ticket['description'])) ?></div>

                    <?php if (!empty($ticket['transcription'])): ?>
                    <div class="mt-3">
                        <h6 class="fw-bold" style="font-size:0.88rem"><i class="bi bi-mic"></i> Transcrição Original</h6>
                        <div class="p-3 rounded border" style="font-size:0.83rem;line-height:1.6;background:#f0faf8;border-color:#b2f2e8 !important;">
                            <?= nl2br(escape($ticket['transcription'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Anexos -->
            <?php if (!empty($attachments)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-paperclip"></i> Anexos (<?= count($attachments) ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($attachments as $att): ?>
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-2 text-center">
                                <?php if (strpos($att['file_type'], 'image') !== false): ?>
                                    <a href="<?= baseUrl($att['file_path']) ?>" target="_blank">
                                        <img src="<?= baseUrl($att['file_path']) ?>" class="img-fluid rounded" style="max-height:100px;object-fit:cover" alt="Anexo">
                                    </a>
                                <?php else: ?>
                                    <a href="<?= baseUrl($att['file_path']) ?>" target="_blank" class="text-decoration-none">
                                        <i class="bi bi-file-earmark fs-2 text-muted"></i>
                                    </a>
                                <?php endif; ?>
                                <div class="small text-muted mt-1 text-truncate"><?= escape($att['file_name']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Chat -->
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-chat-dots"></i> Chat</h6>
                </div>
                <div class="card-body">
                    <div class="chat-container" id="chat-container">
                        <?php foreach ($messages as $msg): ?>
                        <div class="chat-message <?= $msg['user_id'] == $user['id'] ? 'mine' : 'other' ?>">
                            <div class="chat-bubble">
                                <div class="chat-sender"><?= escape($msg['user_name']) ?></div>
                                <?= nl2br(escape($msg['message'])) ?>
                                <div class="chat-time"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                        <p class="text-center text-muted mb-0" id="no-messages">Nenhuma mensagem. Inicie a conversa!</p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <input type="text" id="chat-input" class="form-control form-control-sm" placeholder="Digite sua mensagem..." onkeypress="if(event.key==='Enter')sendMessage()">
                        <button type="button" onclick="sendMessage()" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar de ações -->
        <div class="col-lg-4">
            <?php if (in_array($user['role'], ['super_admin', 'attendant'])): ?>
            <!-- Alterar Status -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Alterar Status</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/updateStatus/' . $ticket['id']) ?>" method="POST">
                        <select name="status" class="form-select form-select-sm mb-2">
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Aberto</option>
                            <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="waiting_client" <?= $ticket['status'] === 'waiting_client' ? 'selected' : '' ?>>Aguardando cliente</option>
                            <option value="completed" <?= $ticket['status'] === 'completed' ? 'selected' : '' ?>>Concluído</option>
                            <option value="denied" <?= $ticket['status'] === 'denied' ? 'selected' : '' ?>>Negado</option>
                            <option value="archived" <?= $ticket['status'] === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Atualizar</button>
                    </form>
                </div>
            </div>

            <!-- Atribuir Atendente -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Atribuir Atendente</h6></div>
                <div class="card-body">
                    <form action="<?= baseUrl('tickets/assign/' . $ticket['id']) ?>" method="POST">
                        <select name="attendant_id" class="form-select form-select-sm mb-2">
                            <option value="">Selecione</option>
                            <?php foreach ($attendants as $att): ?>
                            <option value="<?= $att['id'] ?>" <?= $ticket['attendant_id'] == $att['id'] ? 'selected' : '' ?>>
                                <?= escape($att['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Atribuir</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upload de anexo -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.88rem">Enviar Anexo</h6></div>
                <div class="card-body">
                    <input type="file" id="upload-file" class="form-control form-control-sm mb-2" accept="image/*,.pdf,.doc,.docx">
                    <button type="button" onclick="uploadFile()" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-upload"></i> Enviar
                    </button>
                    <div id="upload-result" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ticketId = <?= $ticket['id'] ?>;
const userId = <?= $user['id'] ?>;
let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;

function sendMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;

    const formData = new FormData();
    formData.append('message', message);

    fetch('<?= baseUrl("tickets/sendMessage/") ?>' + ticketId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            appendMessage(data.message, true);
            input.value = '';
            lastMessageId = data.message.id;
            const noMsg = document.getElementById('no-messages');
            if (noMsg) noMsg.remove();
        }
    });
}

function appendMessage(msg, isMine) {
    const container = document.getElementById('chat-container');
    const div = document.createElement('div');
    div.className = 'chat-message ' + (isMine ? 'mine' : 'other');
    div.innerHTML = `<div class="chat-bubble">
        <div class="chat-sender">${msg.user_name}</div>
        ${msg.message}
        <div class="chat-time">${msg.created_at}</div>
    </div>`;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Polling
setInterval(() => {
    fetch(`<?= baseUrl("tickets/getMessages/") ?>${ticketId}?last_id=${lastMessageId}`)
        .then(r => r.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (msg.user_id != userId) {
                        appendMessage({
                            user_name: msg.user_name,
                            message: msg.message,
                            created_at: new Date(msg.created_at).toLocaleString('pt-BR', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})
                        }, false);
                    }
                    lastMessageId = msg.id;
                });
                const noMsg = document.getElementById('no-messages');
                if (noMsg) noMsg.remove();
            }
        });
}, 5000);

function uploadFile() {
    const fileInput = document.getElementById('upload-file');
    if (!fileInput.files[0]) return;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    fetch('<?= baseUrl("tickets/uploadAttachment/") ?>' + ticketId, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const result = document.getElementById('upload-result');
        if (data.success) {
            result.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle"></i> Enviado!</div>';
            fileInput.value = '';
            setTimeout(() => location.reload(), 1200);
        } else {
            result.innerHTML = '<div class="text-danger small">' + (data.error || 'Erro') + '</div>';
        }
    });
}

// Scroll chat
document.getElementById('chat-container').scrollTop = document.getElementById('chat-container').scrollHeight;
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
