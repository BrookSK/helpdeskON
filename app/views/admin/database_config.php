<?php $pageTitle = 'Configurar Banco - ON Solutions Helpdesk'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php if ($user): ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php endif; ?>

<div class="<?= $user ? 'main-content' : 'container py-5' ?>">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Configurar Banco de Dados</h5>
            <small class="text-muted">Defina os dados de conexão com o MySQL</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card" style="max-width:600px">
        <div class="card-body">
            <form action="<?= baseUrl('settings/saveDatabase') ?>" method="POST">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-medium">Host</label>
                        <input type="text" name="host" class="form-control" value="<?= escape($config['host'] ?? 'localhost') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Porta</label>
                        <input type="text" name="port" class="form-control" value="<?= escape($config['port'] ?? '3306') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium">Nome do Banco</label>
                        <input type="text" name="database" class="form-control" value="<?= escape($config['database'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Usuário</label>
                        <input type="text" name="username" class="form-control" value="<?= escape($config['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Senha</label>
                        <input type="password" name="password" class="form-control" value="<?= escape($config['password'] ?? '') ?>">
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Salvar</button>
                        <?php if ($user): ?>
                        <a href="<?= baseUrl('settings') ?>" class="btn btn-outline-secondary ms-2">Voltar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
