<?php $pageTitle = ($editUser ? 'Editar' : 'Novo') . ' Usuário - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= $editUser ? 'Editar Usuário' : 'Novo Usuário' ?></h5>
            <small class="text-muted"><?= $editUser ? 'Editar dados de ' . escape($editUser['name']) : 'Cadastrar novo usuário no sistema' ?></small>
        </div>
        <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?= baseUrl($editUser ? 'users/update/' . $editUser['id'] : 'users/store') ?>" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Nome *</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($editUser['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= escape($editUser['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Senha <?= $editUser ? '(deixe vazio para manter)' : '*' ?></label>
                        <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Telefone</label>
                        <input type="text" name="phone" class="form-control" value="<?= escape($editUser['phone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Papel no Sistema *</label>
                        <select name="role" class="form-select" required>
                            <option value="client" <?= ($editUser['role'] ?? '') === 'client' ? 'selected' : '' ?>>Cliente</option>
                            <option value="attendant" <?= ($editUser['role'] ?? '') === 'attendant' ? 'selected' : '' ?>>Atendente</option>
                            <option value="super_admin" <?= ($editUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg"></i> <?= $editUser ? 'Atualizar' : 'Cadastrar' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
