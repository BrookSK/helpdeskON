<?php $pageTitle = 'Configurações - ON Solutions Helpdesk'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Configurações</h5>
            <small class="text-muted">Sistema, email, IA e webhooks</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form action="<?= baseUrl('settings/save') ?>" method="POST">
        <!-- Geral -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-gear"></i> Geral</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome do Sistema</label>
                        <input type="text" name="app_name" class="form-control form-control-sm" value="<?= escape($settings['app_name'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Email do Sistema</label>
                        <input type="email" name="app_email" class="form-control form-control-sm" value="<?= escape($settings['app_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-envelope"></i> Email (SMTP)</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="testSmtp()">
                    <i class="bi bi-send-check"></i> Testar
                </button>
            </div>
            <div class="card-body">
                <div id="smtp-test-result" class="mb-3" style="display:none"></div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Servidor SMTP</label>
                        <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= escape($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label fw-medium small">Porta</label>
                        <input type="text" name="smtp_port" class="form-control form-control-sm" value="<?= escape($settings['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label fw-medium small">Criptografia</label>
                        <select name="smtp_encryption" class="form-select form-select-sm">
                            <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= empty($settings['smtp_encryption'] ?? '') ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Usuário SMTP</label>
                        <input type="text" name="smtp_username" class="form-control form-control-sm" value="<?= escape($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Senha SMTP</label>
                        <input type="password" name="smtp_password" class="form-control form-control-sm" value="<?= escape($settings['smtp_password'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome do Remetente</label>
                        <input type="text" name="smtp_from_name" class="form-control form-control-sm" value="<?= escape($settings['smtp_from_name'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Email do Remetente</label>
                        <input type="email" name="smtp_from_email" class="form-control form-control-sm" value="<?= escape($settings['smtp_from_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- OpenAI -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-robot"></i> OpenAI</h6></div>
            <div class="card-body">
                <label class="form-label fw-medium small">API Key</label>
                <input type="password" name="openai_api_key" class="form-control form-control-sm" value="<?= escape($settings['openai_api_key'] ?? '') ?>" placeholder="sk-...">
                <small class="text-muted">Necessário para transcrição por voz.</small>
            </div>
        </div>

        <!-- Webhook -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-broadcast"></i> Webhook</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="webhook_enabled" value="1" id="webhookEnabled" <?= ($settings['webhook_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium small" for="webhookEnabled">Ativado</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">URL do Webhook</label>
                        <input type="url" name="webhook_url" class="form-control form-control-sm" value="<?= escape($settings['webhook_url'] ?? '') ?>" placeholder="https://...">
                        <small class="text-muted">POST com JSON: {message, phone, name}</small>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Telefone (padrão)</label>
                        <input type="text" name="webhook_phone" class="form-control form-control-sm" value="<?= escape($settings['webhook_phone'] ?? '') ?>" placeholder="5511999999999">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome (padrão)</label>
                        <input type="text" name="webhook_name" class="form-control form-control-sm" value="<?= escape($settings['webhook_name'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">Template da Mensagem</label>
                        <input type="text" name="webhook_message_template" class="form-control form-control-sm" value="<?= escape($settings['webhook_message_template'] ?? '') ?>">
                        <small class="text-muted">Variáveis: {ticket_id}, {message}, {client_name}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banco -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem"><i class="bi bi-database"></i> Banco de Dados</h6></div>
            <div class="card-body">
                <a href="<?= baseUrl('settings/database') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-wrench"></i> Configurar Banco
                </a>
            </div>
        </div>

        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg"></i> Salvar Configurações
        </button>
    </form>
</div>

<script>
function testSmtp() {
    const btn = event.target.closest('button');
    const result = document.getElementById('smtp-test-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    result.style.display = 'none';

    fetch('<?= baseUrl("settings/testEmail") ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            result.style.display = 'block';
            if (data.success) {
                result.className = 'mb-3 alert alert-success py-2';
                result.innerHTML = '<small><i class="bi bi-check-circle"></i> ' + data.message + '</small>';
            } else {
                result.className = 'mb-3 alert alert-danger py-2';
                result.innerHTML = '<small><i class="bi bi-x-circle"></i> ' + data.message + '</small>';
            }
        })
        .catch(() => {
            result.style.display = 'block';
            result.className = 'mb-3 alert alert-danger py-2';
            result.innerHTML = '<small>Erro na requisição.</small>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-check"></i> Testar';
        });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
