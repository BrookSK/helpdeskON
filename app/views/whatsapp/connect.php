<?php $pageTitle = 'WhatsApp - Conexão'; $currentPage = 'whatsapp'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-whatsapp text-success"></i> WhatsApp — Conexão</h5>
            <small class="text-muted">Gerencie suas instâncias e conexões</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('whatsapp/chat') ?>" class="btn btn-sm btn-primary"><i class="bi bi-chat-dots"></i> Ir para Chat</a>
            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newInstanceModal">
                <i class="bi bi-plus-lg"></i> Nova Instância
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Instâncias existentes -->
    <div class="row g-3">
        <?php if (empty($instances)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-whatsapp text-success" style="font-size:3rem;"></i>
                    <h6 class="mt-3">Nenhuma instância configurada</h6>
                    <p class="text-muted">Crie uma nova instância para conectar seu WhatsApp.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($instances as $inst): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card instance-card" data-id="<?= $inst['id'] ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h6 class="mb-1"><?= escape($inst['display_name'] ?: $inst['instance_name']) ?></h6>
                            <small class="text-muted"><?= escape($inst['instance_name']) ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($inst['is_default']): ?>
                            <span class="badge bg-primary" style="font-size:0.65rem;">Padrão</span>
                            <?php endif; ?>
                            <span class="badge connection-badge status-<?= $inst['connection_status'] ?>">
                                <?= $inst['connection_status'] === 'open' ? 'Conectado' : ($inst['connection_status'] === 'connecting' ? 'Conectando...' : 'Desconectado') ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($inst['owner_phone']): ?>
                    <p class="small text-muted mb-2"><i class="bi bi-phone"></i> <?= escape($inst['owner_phone']) ?></p>
                    <?php endif; ?>

                    <!-- QR Code area -->
                    <div class="qr-area text-center my-3" id="qr-area-<?= $inst['id'] ?>" style="display:none;">
                        <div class="qr-loading"><div class="spinner-border spinner-border-sm text-primary"></div> Gerando QR Code...</div>
                        <div class="qr-image"></div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($inst['connection_status'] !== 'open'): ?>
                        <button class="btn btn-sm btn-success" onclick="connectInstance(<?= $inst['id'] ?>)">
                            <i class="bi bi-qr-code"></i> Conectar
                        </button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-danger" onclick="disconnectInstance(<?= $inst['id'] ?>)">
                            <i class="bi bi-x-circle"></i> Desconectar
                        </button>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-secondary" onclick="checkStatus(<?= $inst['id'] ?>)">
                            <i class="bi bi-arrow-repeat"></i> Status
                        </button>

                        <?php if (!$inst['is_default'] && ($user['role'] ?? '') === 'super_admin'): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="setDefault(<?= $inst['id'] ?>)">
                            <i class="bi bi-star"></i> Padrão
                        </button>
                        <?php endif; ?>

                        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteInstance(<?= $inst['id'] ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Nova Instância -->
<div class="modal fade" id="newInstanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Nova Instância WhatsApp</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome da Instância *</label>
                    <input type="text" id="new-instance-name" class="form-control form-control-sm" placeholder="ex: minha-empresa">
                    <small class="text-muted">Sem espaços ou caracteres especiais</small>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome de Exibição</label>
                    <input type="text" id="new-display-name" class="form-control form-control-sm" placeholder="ex: WhatsApp Empresa">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">URL da Evolution API *</label>
                    <input type="url" id="new-api-url" class="form-control form-control-sm" placeholder="https://api.seuservidor.com">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">API Key *</label>
                    <input type="text" id="new-api-key" class="form-control form-control-sm" placeholder="Sua chave de API">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="createInstance()">Criar Instância</button>
            </div>
        </div>
    </div>
</div>

<style>
.connection-badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 12px; }
.status-open { background: #e8f5e9; color: #2e7d32; }
.status-connected { background: #e8f5e9; color: #2e7d32; }
.status-connecting { background: #fff3e0; color: #e65100; }
.status-close { background: #fbe9e7; color: #d84315; }
.instance-card { transition: box-shadow 0.2s; }
.instance-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.qr-area img { max-width: 250px; border-radius: 8px; border: 2px solid #e0e0e0; }
</style>

<script>
const BASE = '<?= baseUrl("") ?>';

function connectInstance(id) {
    const qrArea = document.getElementById('qr-area-' + id);
    qrArea.style.display = 'block';
    qrArea.querySelector('.qr-loading').style.display = 'block';
    qrArea.querySelector('.qr-image').innerHTML = '';

    fetch(BASE + 'whatsapp/connect/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        qrArea.querySelector('.qr-loading').style.display = 'none';
        if (data.base64) {
            qrArea.querySelector('.qr-image').innerHTML = '<img src="' + data.base64 + '" alt="QR Code"><br><small class="text-muted mt-2 d-block">Escaneie com seu WhatsApp</small>';
        } else if (data.code) {
            qrArea.querySelector('.qr-image').innerHTML = '<img src="data:image/png;base64,' + data.code + '" alt="QR Code"><br><small class="text-muted mt-2 d-block">Escaneie com seu WhatsApp</small>';
        } else if (data.pairingCode) {
            qrArea.querySelector('.qr-image').innerHTML = '<div class="alert alert-info small">Código de pareamento: <strong>' + data.pairingCode + '</strong></div>';
        } else {
            qrArea.querySelector('.qr-image').innerHTML = '<div class="alert alert-warning small">QR Code não disponível. Verifique o status.</div>';
        }
        // Polling status
        startStatusPolling(id);
    })
    .catch(() => {
        qrArea.querySelector('.qr-loading').style.display = 'none';
        qrArea.querySelector('.qr-image').innerHTML = '<div class="alert alert-danger small">Erro ao conectar.</div>';
    });
}

let statusIntervals = {};
function startStatusPolling(id) {
    if (statusIntervals[id]) clearInterval(statusIntervals[id]);
    statusIntervals[id] = setInterval(() => {
        fetch(BASE + 'whatsapp/status/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.state === 'open' || data.state === 'connected') {
                clearInterval(statusIntervals[id]);
                location.reload();
            }
        });
    }, 5000);
}

function checkStatus(id) {
    fetch(BASE + 'whatsapp/status/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        alert('Status: ' + (data.state || 'Desconhecido'));
        location.reload();
    });
}

function disconnectInstance(id) {
    if (!confirm('Deseja desconectar esta instância?')) return;
    fetch(BASE + 'whatsapp/disconnect/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

function setDefault(id) {
    fetch(BASE + 'whatsapp/setDefault/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

function deleteInstance(id) {
    if (!confirm('ATENÇÃO: Isso apagará todas as mensagens e contatos desta instância. Continuar?')) return;
    const fd = new FormData();
    fetch(BASE + 'whatsapp/deleteInstance/' + id, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(() => location.reload());
}

function createInstance() {
    const fd = new FormData();
    fd.append('instance_name', document.getElementById('new-instance-name').value);
    fd.append('display_name', document.getElementById('new-display-name').value);
    fd.append('api_url', document.getElementById('new-api-url').value);
    fd.append('api_key', document.getElementById('new-api-key').value);

    fetch(BASE + 'whatsapp/createInstance', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao criar instância');
        }
    })
    .catch(() => alert('Erro de conexão'));
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
