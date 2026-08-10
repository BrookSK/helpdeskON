<?php $pageTitle = 'Dashboard - ON Solutions Helpdesk'; $currentPage = 'dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Olá, <?= escape($user['name']) ?>!</h5>
            <small class="text-muted">Bem-vindo ao seu painel</small>
        </div>
        <a href="<?= baseUrl('tickets/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nova Demanda
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <style>
        .stat-card.compact { padding: 0.6rem 0.75rem; }
        .stat-card.compact .stat-label { font-size: 0.68rem; margin-bottom: 2px; }
        .stat-card.compact .stat-value { font-size: 1.25rem; font-weight: 700; line-height: 1.1; }
    </style>
    <div class="row row-cols-2 row-cols-md-5 g-2 mb-4">
        <div class="col">
            <div class="card stat-card compact">
                <div class="stat-label">Abertas</div>
                <div class="stat-value text-primary"><?= $counts['open'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#ff9800">
                <div class="stat-label">Em andamento</div>
                <div class="stat-value" style="color:#ff9800"><?= $counts['in_progress'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#5c6bc0">
                <div class="stat-label">Em Revisão Interna</div>
                <div class="stat-value" style="color:#5c6bc0"><?= $counts['em_revisao_interna'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#0097a7">
                <div class="stat-label">Em Homologação</div>
                <div class="stat-value" style="color:#0097a7"><?= $counts['em_homologacao'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#8bc34a">
                <div class="stat-label">Aprov. Produção</div>
                <div class="stat-value" style="color:#8bc34a"><?= $counts['aprovado_producao'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#e91e63">
                <div class="stat-label">Aguardando</div>
                <div class="stat-value" style="color:#e91e63"><?= $counts['waiting_client'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#4caf50">
                <div class="stat-label">Concluídas</div>
                <div class="stat-value" style="color:#4caf50"><?= $counts['completed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#f44336">
                <div class="stat-label">Negadas</div>
                <div class="stat-value" style="color:#f44336"><?= $counts['denied'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#9e9e9e">
                <div class="stat-label">Arquivadas</div>
                <div class="stat-value" style="color:#9e9e9e"><?= $counts['archived'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card compact" style="border-left-color:#607d8b">
                <div class="stat-label">Total</div>
                <div class="stat-value" style="color:#607d8b"><?= array_sum($counts) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Últimas Demandas</h6>
            <a href="<?= baseUrl('tickets') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
        <div class="card-body p-0">
            <!-- Desktop table -->
            <div class="table-responsive d-none d-md-block">
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
                            <td><?= $t['client_ticket_number'] ?? $t['id'] ?></td>
                            <td class="text-truncate" style="max-width:200px"><?= escape($t['title']) ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= priorityLabel($t['priority']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td><a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile cards -->
            <div class="d-md-none p-3">
                <?php foreach (array_slice($tickets, 0, 10) as $t): ?>
                <a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-medium text-dark">#<?= $t['client_ticket_number'] ?? $t['id'] ?> <?= escape($t['title']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span>
                        <span class="priority-<?= $t['priority'] ?>" style="font-size:0.75rem"><?= priorityLabel($t['priority']) ?></span>
                        <span class="text-muted" style="font-size:0.72rem"><?= timeAgo($t['created_at']) ?></span>
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
