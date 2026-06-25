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
            <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> Kanban</a>
            <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-list"></i> Lista</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-2">
            <div class="card stat-card p-3">
                <div class="text-muted small">Abertas</div>
                <div class="fs-4 fw-bold text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card p-3" style="border-left-color:#ff9800">
                <div class="text-muted small">Em andamento</div>
                <div class="fs-4 fw-bold" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card p-3" style="border-left-color:#e91e63">
                <div class="text-muted small">Aguardando</div>
                <div class="fs-4 fw-bold" style="color:#e91e63"><?= $counts['waiting_client'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card p-3" style="border-left-color:#4caf50">
                <div class="text-muted small">Concluídas</div>
                <div class="fs-4 fw-bold" style="color:#4caf50"><?= $counts['completed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card p-3" style="border-left-color:#f44336">
                <div class="text-muted small">Negadas</div>
                <div class="fs-4 fw-bold" style="color:#f44336"><?= $counts['denied'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card stat-card p-3" style="border-left-color:#607d8b">
                <div class="text-muted small">Msgs não lidas</div>
                <div class="fs-4 fw-bold" style="color:#607d8b"><?= $unreadMessages ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Últimas Demandas</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
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
                            <td><?= escape($t['title']) ?></td>
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
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
