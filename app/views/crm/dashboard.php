<?php $pageTitle = 'Dashboard CRM'; $currentPage = 'crm_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Dashboard CRM</h5>
            <small class="text-muted">Visão geral dos leads</small>
        </div>
        <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Boards</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#2196f3">
                <div class="stat-label">Leads no CRM</div>
                <div class="stat-value" style="color:#2196f3"><?= $stats['total'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#9c27b0">
                <div class="stat-label">Com Etiqueta</div>
                <div class="stat-value" style="color:#9c27b0"><?= $stats['with_label'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#ff9800">
                <div class="stat-label">Em Aberto</div>
                <div class="stat-value" style="color:#ff9800"><?= $stats['open'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#2e7d32">
                <div class="stat-label">Convertidos</div>
                <div class="stat-value" style="color:#2e7d32"><?= $stats['converted'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#c62828">
                <div class="stat-label">Perdidos</div>
                <div class="stat-value" style="color:#c62828"><?= $stats['lost'] ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#455a64">
                <div class="stat-label">Valor Cotado (tudo)</div>
                <div class="stat-value" style="color:#455a64;font-size:1.15rem;">R$ <?= number_format($stats['quoted_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#2e7d32">
                <div class="stat-label">Valor Convertido</div>
                <div class="stat-value" style="color:#2e7d32;font-size:1.15rem;">R$ <?= number_format($stats['converted_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#c62828">
                <div class="stat-label">Valor Perdido</div>
                <div class="stat-value" style="color:#c62828;font-size:1.15rem;">R$ <?= number_format($stats['lost_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card" style="border-left-color:#7e57c2">
                <div class="stat-label">Valor em Recuperação/Agendado</div>
                <div class="stat-value" style="color:#7e57c2;font-size:1.15rem;">R$ <?= number_format($stats['recovery_value'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
