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
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card stat-card" style="border-left-color:#00BFA6">
                <div class="stat-label">Valor Convertido</div>
                <div class="stat-value" style="color:#00997D;font-size:1.05rem;">R$ <?= number_format($stats['total_converted_value'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <?php
    $totalDecided = $stats['converted'] + $stats['lost'];
    $conversionRate = $totalDecided > 0 ? round(($stats['converted'] / $totalDecided) * 100) : 0;
    ?>
    <div class="card">
        <div class="card-header bg-white"><h6 class="mb-0">Taxa de Conversão</h6></div>
        <div class="card-body">
            <div class="d-flex justify-content-between mb-1" style="font-size:0.82rem">
                <span class="text-success"><?= $stats['converted'] ?> convertidos</span>
                <span class="text-danger"><?= $stats['lost'] ?> perdidos</span>
            </div>
            <div class="progress" style="height:22px;">
                <div class="progress-bar bg-success" role="progressbar" style="width:<?= $conversionRate ?>%"><?= $conversionRate ?>%</div>
                <div class="progress-bar bg-danger" role="progressbar" style="width:<?= 100 - $conversionRate ?>%"></div>
            </div>
            <small class="text-muted">Com base em leads já decididos (convertidos + perdidos).</small>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
