<?php $pageTitle = 'Dashboard - ON Solutions Helpdesk'; $currentPage = 'dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Olá, <?= escape($user['name']) ?>!</h5>
            <small class="text-muted">Painel do Atendente</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> <span class="d-none d-sm-inline">Kanban</span></a>
            <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-list"></i> <span class="d-none d-sm-inline">Lista</span></a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-4 col-md-2">
            <div class="card stat-card">
                <div class="stat-label">Abertas</div>
                <div class="stat-value text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="card stat-card" style="border-left-color:#ff9800">
                <div class="stat-label">Andamento</div>
                <div class="stat-value" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="card stat-card" style="border-left-color:#e91e63">
                <div class="stat-label">Aguardando</div>
                <div class="stat-value" style="color:#e91e63"><?= $counts['waiting_client'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="card stat-card" style="border-left-color:#4caf50">
                <div class="stat-label">Concluídas</div>
                <div class="stat-value" style="color:#4caf50"><?= $counts['completed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="card stat-card" style="border-left-color:#f44336">
                <div class="stat-label">Negadas</div>
                <div class="stat-value" style="color:#f44336"><?= $counts['denied'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-4 col-md-2">
            <div class="card stat-card" style="border-left-color:#607d8b">
                <div class="stat-label">Não lidas</div>
                <div class="stat-value" style="color:#607d8b"><?= $unreadMessages ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <h6 class="mb-0">Últimas Demandas</h6>
        </div>
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atualizado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($tickets, 0, 15) as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td class="text-truncate" style="max-width:180px"><?= escape($t['title']) ?></td>
                            <td><?= escape($t['client_name']) ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><?= timeAgo($t['updated_at']) ?></td>
                            <td><a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach (array_slice($tickets, 0, 15) as $t): ?>
                <a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate">#<?= $t['id'] ?> <?= escape($t['title']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                        <span class="priority-<?= $t['priority'] ?>" style="font-size:0.75rem"><?= ucfirst($t['priority']) ?></span>
                        <span class="text-muted" style="font-size:0.72rem"><?= escape($t['client_name']) ?></span>
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
