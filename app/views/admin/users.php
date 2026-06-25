<?php $pageTitle = 'Usuários - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Gerenciar Usuários</h5>
            <small class="text-muted">Clientes e atendentes</small>
        </div>
        <a href="<?= baseUrl('users/create') ?>" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Novo Usuário
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Papel</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= escape($u['name']) ?></td>
                            <td><?= escape($u['email']) ?></td>
                            <td><?= escape($u['phone'] ?? '-') ?></td>
                            <td>
                                <?php
                                $roleLabels = ['super_admin' => 'Admin', 'attendant' => 'Atendente', 'client' => 'Cliente'];
                                echo $roleLabels[$u['role']] ?? $u['role'];
                                ?>
                            </td>
                            <td>
                                <?= $u['is_active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= baseUrl('users/edit/' . $u['id']) ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= baseUrl('users/toggleStatus/' . $u['id']) ?>" class="btn btn-outline-warning" title="<?= $u['is_active'] ? 'Desativar' : 'Ativar' ?>">
                                        <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
