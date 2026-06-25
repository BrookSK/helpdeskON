<?php $pageTitle = 'Dashboard - ON Solutions Helpdesk'; $currentPage = 'dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Olá, <?= escape($user['name']) ?>!</h5>
            <small class="text-muted">Bem-vindo ao seu painel de demandas</small>
        </div>
        <a href="<?= baseUrl('tickets/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nova Demanda
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stat-card p-3">
                <div class="text-muted small">Abertas</div>
                <div class="fs-3 fw-bold text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#ff9800">
                <div class="text-muted small">Em andamento</div>
                <div class="fs-3 fw-bold" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#4caf50">
                <div class="text-muted small">Concluídas</div>
                <div class="fs-3 fw-bold" style="color:#4caf50"><?= $counts['completed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card p-3" style="border-left-color:#9c27b0">
                <div class="text-muted small">Total</div>
                <div class="fs-3 fw-bold" style="color:#9c27b0"><?= array_sum($counts) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Últimas Demandas</h6>
            <a href="<?= baseUrl('tickets') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($tickets, 0, 10) as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td><?= escape($t['title']) ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td><a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
