<?php $pageTitle = 'Minha Equipe - ON Solutions Helpdesk'; $currentPage = 'subusers'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minha Equipe</h5>
            <small class="text-muted">Gerencie os usuários da sua empresa</small>
        </div>
        <a href="<?= baseUrl('subusers/create') ?>" class="btn btn-primary btn-sm">
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
            <?php if (!empty($subusers)): ?>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Criado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subusers as $su): ?>
                        <tr>
                            <td><?= escape($su['name']) ?></td>
                            <td style="font-size:0.85rem"><?= escape($su['email']) ?></td>
                            <td style="font-size:0.85rem"><?= escape($su['phone'] ?? '-') ?></td>
                            <td>
                                <?= $su['is_active']
                                    ? '<span class="badge bg-success" style="font-size:0.7rem">Ativo</span>'
                                    : '<span class="badge bg-secondary" style="font-size:0.7rem">Inativo</span>' ?>
                            </td>
                            <td style="font-size:0.85rem"><?= date('d/m/Y', strtotime($su['created_at'])) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= baseUrl('subusers/edit/' . $su['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?= baseUrl('subusers/toggleStatus/' . $su['id']) ?>" class="btn btn-outline-warning">
                                        <i class="bi bi-<?= $su['is_active'] ? 'pause' : 'play' ?>-fill"></i>
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
                <?php foreach ($subusers as $su): ?>
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-medium"><?= escape($su['name']) ?></div>
                            <div class="text-muted small"><?= escape($su['email']) ?></div>
                        </div>
                        <?= $su['is_active']
                            ? '<span class="badge bg-success" style="font-size:0.68rem">Ativo</span>'
                            : '<span class="badge bg-secondary" style="font-size:0.68rem">Inativo</span>' ?>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <a href="<?= baseUrl('subusers/edit/' . $su['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                        <a href="<?= baseUrl('subusers/toggleStatus/' . $su['id']) ?>" class="btn btn-sm btn-outline-warning">
                            <?= $su['is_active'] ? 'Desativar' : 'Ativar' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people fs-2"></i>
                <p class="mt-2 mb-0">Nenhum usuário na sua equipe ainda</p>
                <a href="<?= baseUrl('subusers/create') ?>" class="btn btn-primary btn-sm mt-3">Criar primeiro usuário</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
