<?php $pageTitle = 'Configurações - ON Solutions Helpdesk'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Configurações</h5>
            <small class="text-muted">Configurar sistema, email, IA e webhooks</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <form action="<?= baseUrl('settings/save') ?>" method="POST">
        <!-- Geral -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-gear"></i> Geral</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Nome do Sistema</label>
                        <input type="text" name="app_name" class="form-control" value="<?= escape($settings['app_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Email do Sistema</label>
                        <input type="email" name="app_email" class="form-control" value="<?= escape($settings['app_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-envelope"></i> Configuração de Email (SMTP)</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Servidor SMTP</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= escape($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Porta</label>
                        <input type="text" name="smtp_port" class="form-control" value="<?= escape($settings['smtp_port'] ?? '587') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Criptografia</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= empty($settings['smtp_encryption'] ?? '') ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Usuário SMTP</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= escape($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Senha SMTP</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?= escape($settings['smtp_password'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Nome do Remetente</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= escape($settings['smtp_from_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Email do Remetente</label>
                        <input type="email" name="smtp_from_email" class="form-control" value="<?= escape($settings['smtp_from_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- OpenAI -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-robot"></i> OpenAI (Transcrição de Áudio)</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">API Key da OpenAI</label>
                        <input type="password" name="openai_api_key" class="form-control" value="<?= escape($settings['openai_api_key'] ?? '') ?>" placeholder="sk-...">
                        <small class="text-muted">Necessário para transcrição por voz e organização automática de demandas.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-broadcast"></i> Webhook (Notificações Externas)</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="webhook_enabled" value="1" id="webhookEnabled" <?= ($settings['webhook_enabled'] ?? '') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium" for="webhookEnabled">Webhook Ativado</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">URL do Webhook</label>
                        <input type="url" name="webhook_url" class="form-control" value="<?= escape($settings['webhook_url'] ?? '') ?>" placeholder="https://api.exemplo.com/webhook">
                        <small class="text-muted">Receberá um POST com JSON: {message, phone, name}</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Número de Telefone (padrão)</label>
                        <input type="text" name="webhook_phone" class="form-control" value="<?= escape($settings['webhook_phone'] ?? '') ?>" placeholder="5511999999999">
                        <small class="text-muted">Número para envio via webhook.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Nome (padrão)</label>
                        <input type="text" name="webhook_name" class="form-control" value="<?= escape($settings['webhook_name'] ?? '') ?>" placeholder="ON Solutions Helpdesk">
                        <small class="text-muted">Nome que será enviado no payload.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Template da Mensagem</label>
                        <input type="text" name="webhook_message_template" class="form-control" value="<?= escape($settings['webhook_message_template'] ?? '') ?>">
                        <small class="text-muted">Variáveis: {ticket_id}, {message}, {client_name}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuração do Banco -->
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-database"></i> Banco de Dados</h6></div>
            <div class="card-body">
                <a href="<?= baseUrl('settings/database') ?>" class="btn btn-outline-primary">
                    <i class="bi bi-wrench"></i> Configurar Banco de Dados
                </a>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-check-lg"></i> Salvar Configurações
        </button>
    </form>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
