<?php $pageTitle = 'Admin Dashboard - ON Solutions Helpdesk'; $currentPage = 'dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Painel Administrativo</h5>
            <small class="text-muted">Visão geral do sistema</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card p-3">
                <div class="text-muted small">Demandas Abertas</div>
                <div class="fs-3 fw-bold text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#ff9800">
                <div class="text-muted small">Em Andamento</div>
                <div class="fs-3 fw-bold" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#2196f3">
                <div class="text-muted small">Total Clientes</div>
                <div class="fs-3 fw-bold" style="color:#2196f3"><?= $totalClients ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#9c27b0">
                <div class="text-muted small">Total Atendentes</div>
                <div class="fs-3 fw-bold" style="color:#9c27b0"><?= $totalAttendants ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Atalhos</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= baseUrl('users') ?>" class="btn btn-outline-primary"><i class="bi bi-people"></i> Gerenciar Usuários</a>
                        <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary"><i class="bi bi-kanban"></i> Kanban</a>
                        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-primary"><i class="bi bi-list-task"></i> Todas Demandas</a>
                        <a href="<?= baseUrl('settings') ?>" class="btn btn-outline-primary"><i class="bi bi-gear"></i> Configurações</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Últimas Demandas</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($tickets, 0, 8) as $t): ?>
                        <a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span class="fw-medium">#<?= $t['id'] ?> <?= escape($t['title']) ?></span>
                                <span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                            </div>
                            <small class="text-muted"><?= escape($t['client_name']) ?> - <?= timeAgo($t['created_at']) ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
