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

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="stat-label">Demandas Abertas</div>
                <div class="stat-value text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#ff9800">
                <div class="stat-label">Em Andamento</div>
                <div class="stat-value" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#2196f3">
                <div class="stat-label">Total Clientes</div>
                <div class="stat-value" style="color:#2196f3"><?= $totalClients ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#9c27b0">
                <div class="stat-label">Total Atendentes</div>
                <div class="stat-value" style="color:#9c27b0"><?= $totalAttendants ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Atalhos Rápidos</h6></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= baseUrl('users') ?>" class="btn btn-outline-primary text-start">
                            <i class="bi bi-people me-2"></i> Gerenciar Usuários
                        </a>
                        <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary text-start">
                            <i class="bi bi-kanban me-2"></i> Kanban
                        </a>
                        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-primary text-start">
                            <i class="bi bi-list-task me-2"></i> Todas Demandas
                        </a>
                        <a href="<?= baseUrl('settings') ?>" class="btn btn-outline-primary text-start">
                            <i class="bi bi-gear me-2"></i> Configurações
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Últimas Demandas</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($tickets, 0, 8) as $t): ?>
                        <a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="list-group-item list-group-item-action px-3 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-2">
                                    <span class="fw-medium">#<?= $t['id'] ?></span>
                                    <span class="text-dark"><?= escape($t['title']) ?></span>
                                </div>
                                <span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                            </div>
                            <small class="text-muted"><?= escape($t['client_name']) ?> · <?= timeAgo($t['created_at']) ?></small>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <div class="text-center text-muted py-4">Nenhuma demanda ainda</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
