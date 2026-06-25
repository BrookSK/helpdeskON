<?php $pageTitle = 'Minhas Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minhas Demandas</h5>
            <small class="text-muted">Acompanhe suas solicitações</small>
        </div>
        <a href="<?= baseUrl('tickets/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nova Demanda
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atendente</th>
                            <th>Criado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td class="text-truncate" style="max-width:200px"><?= escape($t['title']) ?></td>
                            <td><?= escape($t['category'] ?? '-') ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><?= escape($t['attendant_name'] ?? 'Aguardando') ?></td>
                            <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td><a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($tickets as $t): ?>
                <a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:65%">#<?= $t['id'] ?> <?= escape($t['title']) ?></span>
                        <span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
                        <span class="text-muted"><?= escape($t['attendant_name'] ?? 'Aguardando') ?></span>
                        <span class="text-muted"><?= timeAgo($t['created_at']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                <p class="text-center text-muted py-4">Nenhuma demanda encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
