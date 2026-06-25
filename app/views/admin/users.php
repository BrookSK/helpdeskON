<?php $pageTitle = 'Usuários - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Gerenciar Usuários</h5>
            <small class="text-muted">Clientes e atendentes</small>
        </div>
        <a href="<?= baseUrl('users/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus"></i> Novo
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
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Papel</th>
                            <th>Status</th>
                            <th>Criado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $roleLabels = ['super_admin' => 'Admin', 'attendant' => 'Atendente', 'client' => 'Cliente'];
                        ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= escape($u['name']) ?></td>
                            <td class="text-truncate" style="max-width:160px"><?= escape($u['email']) ?></td>
                            <td><?= escape($u['phone'] ?? '-') ?></td>
                            <td><?= $roleLabels[$u['role']] ?? $u['role'] ?></td>
                            <td>
                                <?= $u['is_active']
                                    ? '<span class="badge bg-success" style="font-size:0.7rem">Ativo</span>'
                                    : '<span class="badge bg-secondary" style="font-size:0.7rem">Inativo</span>' ?>
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
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($users as $u): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="fw-medium"><?= escape($u['name']) ?></div>
                            <div class="text-muted small"><?= escape($u['email']) ?></div>
                        </div>
                        <?= $u['is_active']
                            ? '<span class="badge bg-success" style="font-size:0.68rem">Ativo</span>'
                            : '<span class="badge bg-secondary" style="font-size:0.68rem">Inativo</span>' ?>
                    </div>
                    <div class="d-flex gap-2 align-items-center mt-2 flex-wrap" style="font-size:0.78rem">
                        <span class="badge bg-light text-dark"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span>
                        <?php if ($u['phone']): ?><span class="text-muted"><?= escape($u['phone']) ?></span><?php endif; ?>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <a href="<?= baseUrl('users/edit/' . $u['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                        <a href="<?= baseUrl('users/toggleStatus/' . $u['id']) ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-fill"></i> <?= $u['is_active'] ? 'Desativar' : 'Ativar' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
