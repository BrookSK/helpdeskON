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
            <div class="card stat-card" style="border-left-color:#5c6bc0">
                <div class="stat-label">Em Revisão Interna</div>
                <div class="stat-value" style="color:#5c6bc0"><?= $counts['em_revisao_interna'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#0097a7">
                <div class="stat-label">Em Homologação</div>
                <div class="stat-value" style="color:#0097a7"><?= $counts['em_homologacao'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#8bc34a">
                <div class="stat-label">Aprov. Produção</div>
                <div class="stat-value" style="color:#8bc34a"><?= $counts['aprovado_producao'] ?? 0 ?></div>
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
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger me-1"></i> Demandas em Atraso</h6>
                    <?php if (!empty($overdueCards)): ?>
                    <span class="badge bg-danger"><?= count($overdueCards) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($overdueCards)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($overdueCards as $card): ?>
                        <?php $link = !empty($card['ticket_ref']) ? baseUrl('tickets/show/' . $card['ticket_ref']) : baseUrl('planning'); ?>
                        <a href="<?= $link ?>" class="list-group-item list-group-item-action px-3 py-2" style="border-left:3px solid #dc3545;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-2">
                                    <span class="fw-medium text-dark"><?= escape($card['title']) ?></span>
                                    <?php if (!empty($card['company_name'])): ?>
                                    <div class="text-muted" style="font-size:0.72rem"><i class="bi bi-building"></i> <?= escape($card['company_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-danger-subtle text-danger flex-shrink-0" style="font-size:0.7rem">
                                    <i class="bi bi-clock"></i> <?= date('d/m H:i', strtotime($card['due_date'])) ?>
                                </span>
                            </div>
                            <small class="text-muted"><i class="bi bi-person"></i> <?= escape($card['assigned_name'] ?? 'Não atribuído') ?></small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check2-circle fs-2 text-success"></i>
                        <p class="mt-2 mb-0">Nenhuma demanda em atraso</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Últimas Demandas</h6></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($tickets, 0, 8) as $t): ?>
                        <a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="list-group-item list-group-item-action px-3 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-2">
                                    <span class="fw-medium">#<?= $t['id'] ?></span>
                                    <span class="text-dark"><?= escape($t['title']) ?></span>
                                </div>
                                <span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span>
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
