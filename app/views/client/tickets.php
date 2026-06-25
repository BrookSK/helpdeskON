<?php $pageTitle = 'Minhas Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minhas Demandas</h5>
            <small class="text-muted">Acompanhe suas solicitações</small>
        </div>
        <a href="<?= baseUrl('tickets/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova Demanda
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atendente</th>
                            <th>Criado em</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td><?= escape($t['title']) ?></td>
                            <td><?= escape($t['category'] ?? '-') ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><?= escape($t['attendant_name'] ?? 'Aguardando') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td><a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
