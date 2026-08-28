<?php $pageTitle = 'WhatsApp - Conexão'; $currentPage = 'whatsapp'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$defaultApiUrl = $defaultInstance['api_url'] ?? '';
$defaultApiKey = $defaultInstance['api_key'] ?? '';
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-whatsapp text-success"></i> WhatsApp — Conexão</h5>
            <small class="text-muted">Gerencie suas instâncias e conexões</small>
        </div>
        <div class="d-flex gap-2">
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
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1"><?= escape($inst['display_name'] ?: $inst['instance_name']) ?></h6>
                            <small class="text-muted"><?= escape($inst['instance_name']) ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <?php if ($inst['is_default']): ?>
                            <span class="badge bg-primary" style="font-size:0.6rem;">Padrão</span>
                            <?php endif; ?>
                            <span class="badge connection-badge status-<?= $inst['connection_status'] ?>">
                                <?= $inst['connection_status'] === 'open' ? 'Conectado' : ($inst['connection_status'] === 'connecting' ? 'Conectando...' : 'Desconectado') ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($inst['owner_phone']): ?>
                    <p class="small text-muted mb-1"><i class="bi bi-phone"></i> <?= escape($inst['owner_phone']) ?></p>
                    <?php endif; ?>

                    <?php if ($inst['linked_user_name']): ?>
                    <p class="small mb-2"><i class="bi bi-person-badge"></i> <span class="text-primary fw-medium"><?= escape($inst['linked_user_name']) ?></span></p>
                    <?php else: ?>
                    <p class="small text-muted mb-2"><i class="bi bi-person-badge"></i> Sem usuário vinculado</p>
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

                        <button class="btn btn-sm btn-outline-secondary" onclick="restartInstance(<?= $inst['id'] ?>, this)" title="Renovar conexão (reiniciar socket)">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>

                        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= $inst['id'] ?>, '<?= escape($inst['display_name']) ?>', '<?= escape($inst['api_url']) ?>', '<?= escape($inst['api_key']) ?>', '<?= $inst['user_id'] ?? '' ?>')">
                            <i class="bi bi-pencil"></i> Editar
                        </button>

                        <?php if (!$inst['is_default']): ?>
                        <button class="btn btn-sm btn-outline-warning" onclick="setDefault(<?= $inst['id'] ?>)" title="Definir como padrão">
                            <i class="bi bi-star"></i>
                        </button>
                        <?php endif; ?>

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
                <hr>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="use-default-credentials" checked onchange="toggleDefaultCredentials()">
                    <label class="form-check-label small" for="use-default-credentials">
                        Usar URL e API Key da instância padrão
                    </label>
                </div>
                <div id="custom-credentials" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">URL da Evolution API *</label>
                        <input type="url" id="new-api-url" class="form-control form-control-sm" placeholder="https://api.seuservidor.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">API Key *</label>
                        <input type="text" id="new-api-key" class="form-control form-control-sm" placeholder="Sua chave de API">
                    </div>
                </div>
                <div id="default-credentials-info" class="alert alert-light small py-2">
                    <i class="bi bi-info-circle"></i> Será usada a mesma URL e chave da instância padrão.
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Vincular ao Usuário (opcional)</label>
                    <select id="new-user-id" class="form-select form-select-sm">
                        <option value="">Nenhum (disponível para todos)</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?> (<?= roleLabel($m['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Se vinculada, apenas este usuário verá os contatos desta instância.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="createInstance()">Criar Instância</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Instância -->
<div class="modal fade" id="editInstanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Editar Instância</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-instance-id">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Nome de Exibição</label>
                    <input type="text" id="edit-display-name" class="form-control form-control-sm">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">URL da Evolution API</label>
                    <input type="url" id="edit-api-url" class="form-control form-control-sm">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium">API Key</label>
                    <input type="text" id="edit-api-key" class="form-control form-control-sm">
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Vincular ao Usuário</label>
                    <select id="edit-user-id" class="form-select form-select-sm">
                        <option value="">Nenhum (disponível para todos)</option>
                        <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?> (<?= roleLabel($m['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Se vinculada, apenas este usuário verá os contatos desta instância no chat.</small>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Confirmações de mensagem</label>
                    <button type="button" class="btn btn-sm btn-outline-success w-100" onclick="fixDeliveryEvents(this)">
                        <i class="bi bi-check2-all"></i> Ativar confirmação de entrega/leitura
                    </button>
                    <small class="text-muted d-block mt-1">Habilita os checks de entregue/lida (registra o evento de status no webhook desta instância).</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveInstance()">Salvar</button>
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
const DEFAULT_API_URL = '<?= escape($defaultApiUrl) ?>';
const DEFAULT_API_KEY = '<?= escape($defaultApiKey) ?>';

// =========================================
// Toggle credenciais padrão no modal de criar
// =========================================
function toggleDefaultCredentials() {
    const checked = document.getElementById('use-default-credentials').checked;
    document.getElementById('custom-credentials').style.display = checked ? 'none' : 'block';
    document.getElementById('default-credentials-info').style.display = checked ? 'block' : 'none';
}

// =========================================
// Criar instância
// =========================================
function createInstance() {
    const useDefault = document.getElementById('use-default-credentials').checked;
    const apiUrl = useDefault ? DEFAULT_API_URL : document.getElementById('new-api-url').value;
    const apiKey = useDefault ? DEFAULT_API_KEY : document.getElementById('new-api-key').value;
    const instanceName = document.getElementById('new-instance-name').value.trim();

    if (!instanceName) { alert('Nome da instância é obrigatório'); return; }
    if (!apiUrl || !apiKey) { alert('URL e API Key são obrigatórios. Configure uma instância padrão primeiro.'); return; }

    const fd = new FormData();
    fd.append('instance_name', instanceName);
    fd.append('display_name', document.getElementById('new-display-name').value);
    fd.append('api_url', apiUrl);
    fd.append('api_key', apiKey);

    const userId = document.getElementById('new-user-id').value;
    if (userId) fd.append('user_id', userId);

    fetch(BASE + 'whatsapp/createInstance', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Se tiver user_id, vincular
            if (userId && data.instance_id) {
                const fd2 = new FormData();
                fd2.append('user_id', userId);
                fetch(BASE + 'whatsapp/updateInstance/' + data.instance_id, { method: 'POST', body: fd2, headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(() => location.reload());
            } else {
                location.reload();
            }
        } else {
            alert(data.error || 'Erro ao criar instância');
        }
    })
    .catch(() => alert('Erro de conexão'));
}

// =========================================
// Editar instância
// =========================================
function openEditModal(id, displayName, apiUrl, apiKey, userId) {
    document.getElementById('edit-instance-id').value = id;
    document.getElementById('edit-display-name').value = displayName || '';
    document.getElementById('edit-api-url').value = apiUrl || '';
    document.getElementById('edit-api-key').value = apiKey || '';
    document.getElementById('edit-user-id').value = userId || '';
    new bootstrap.Modal(document.getElementById('editInstanceModal')).show();
}

function saveInstance() {
    const id = document.getElementById('edit-instance-id').value;
    const fd = new FormData();
    fd.append('display_name', document.getElementById('edit-display-name').value);
    fd.append('api_url', document.getElementById('edit-api-url').value);
    fd.append('api_key', document.getElementById('edit-api-key').value);
    fd.append('user_id', document.getElementById('edit-user-id').value);

    fetch(BASE + 'whatsapp/updateInstance/' + id, { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao salvar');
        }
    });
}

// =========================================
// Conexão / Status / Padrão / Deletar
// =========================================
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
            qrArea.querySelector('.qr-image').innerHTML = '<img src="' + data.base64 + '"><br><small class="text-muted mt-2 d-block">Escaneie com seu WhatsApp</small>';
        } else if (data.code) {
            qrArea.querySelector('.qr-image').innerHTML = '<img src="data:image/png;base64,' + data.code + '"><br><small class="text-muted mt-2 d-block">Escaneie com seu WhatsApp</small>';
        } else if (data.pairingCode) {
            qrArea.querySelector('.qr-image').innerHTML = '<div class="alert alert-info small">Código de pareamento: <strong>' + data.pairingCode + '</strong></div>';
        } else {
            qrArea.querySelector('.qr-image').innerHTML = '<div class="alert alert-warning small">QR Code não disponível. Verifique o status.</div>';
        }
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

// Apenas consulta o status (sem reiniciar)
function checkStatus(id) {
    fetch(BASE + 'whatsapp/status/' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        alert('Status: ' + (data.state || 'Desconhecido'));
        location.reload();
    });
}

// Botão de refresh (setas): reinicia a instância para renovar o socket travado
// do Baileys. Resolve o caso "Conectado" no painel mas com "Connection Closed"
// no envio, sem precisar ler o QR Code de novo.
function restartInstance(id, btn) {
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    fetch(BASE + 'whatsapp/restart/' + id, { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.connected) {
            alert('Conexão renovada com sucesso. A instância está pronta para enviar.');
        } else if (data.success) {
            alert('Instância reiniciada. Estado atual: ' + (data.state || 'connecting') + '.\nAguarde alguns segundos e verifique novamente. Se não conectar, use "Conectar" para ler o QR Code.');
        } else {
            alert('Não foi possível reiniciar a instância. Tente "Desconectar" e "Conectar" novamente.');
        }
        location.reload();
    })
    .catch(() => {
        alert('Erro ao reiniciar a instância.');
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
    });
}

function fixDeliveryEvents(btn) {
    const instanceId = document.getElementById('edit-instance-id').value;
    const url = instanceId
        ? BASE + 'whatsapp/registerWebhookEvents/' + instanceId
        : BASE + 'whatsapp/registerWebhookEvents';
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ativando...'; }
    fetch(url, { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Confirmações de entrega/leitura ativadas. As próximas mensagens mostrarão os dois checks.');
        } else {
            alert('Não foi possível ativar as confirmações. Verifique a conexão da instância.');
        }
    })
    .catch(() => alert('Erro ao ativar confirmações.'))
    .finally(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = original || '<i class="bi bi-check2-all"></i> Ativar confirmação de entrega/leitura'; }
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
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
