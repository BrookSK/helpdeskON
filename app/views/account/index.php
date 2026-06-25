<?php $pageTitle = 'Minha Conta - ON Solutions Helpdesk'; $currentPage = 'account'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minha Conta</h5>
            <small class="text-muted">Gerencie seus dados pessoais</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Dados pessoais -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-person"></i> Dados Pessoais</h6>
                </div>
                <div class="card-body">
                    <form action="<?= baseUrl('account/update') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nome</label>
                            <input type="text" name="name" class="form-control" value="<?= escape($userData['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= escape($userData['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Telefone</label>
                            <input type="text" name="phone" class="form-control" value="<?= escape($userData['phone'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Papel</label>
                            <input type="text" class="form-control" value="<?= ucfirst(str_replace('_', ' ', $userData['role'])) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Membro desde</label>
                            <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($userData['created_at'])) ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Salvar Alterações
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-lock"></i> Alterar Senha</h6>
                </div>
                <div class="card-body">
                    <form action="<?= baseUrl('account/changePassword') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Senha Atual</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nova Senha</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Confirmar Nova Senha</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-lock"></i> Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
