<?php $pageTitle = 'Kanban - ON Solutions Helpdesk'; $currentPage = 'kanban'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Kanban</h5>
            <small class="text-muted">Demandas por status</small>
        </div>
        <a href="<?= baseUrl('tickets') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-list"></i> Lista</a>
    </div>

    <?php
    $statusLabels = [
        'open' => ['Aberto', '#1565c0'],
        'in_progress' => ['Em andamento', '#e65100'],
        'waiting_client' => ['Aguardando', '#c62828'],
        'completed' => ['Concluído', '#2e7d32'],
        'denied' => ['Negado', '#d84315'],
        'archived' => ['Arquivado', '#546e7a'],
    ];
    ?>

    <div class="kanban-scroll">
        <div class="row g-3 flex-nowrap flex-lg-wrap">
            <?php foreach ($statusLabels as $status => $info): ?>
            <div class="col" style="min-width:260px; max-width:320px;">
                <div class="kanban-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold" style="color:<?= $info[1] ?>;font-size:0.85rem;">
                            <?= $info[0] ?>
                        </h6>
                        <span class="badge bg-secondary rounded-pill" style="font-size:0.7rem"><?= count($grouped[$status] ?? []) ?></span>
                    </div>
                    <?php foreach (($grouped[$status] ?? []) as $ticket): ?>
                    <a href="<?= baseUrl('tickets/view/' . $ticket['id']) ?>" class="text-decoration-none">
                        <div class="kanban-card">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="text-muted" style="font-size:0.72rem">#<?= $ticket['id'] ?></span>
                                <span class="priority-<?= $ticket['priority'] ?>" style="font-size:0.72rem"><?= ucfirst($ticket['priority']) ?></span>
                            </div>
                            <div class="text-dark fw-medium" style="font-size:0.82rem"><?= escape($ticket['title']) ?></div>
                            <div class="text-muted mt-2 d-flex justify-content-between" style="font-size:0.72rem">
                                <span><i class="bi bi-person"></i> <?= escape($ticket['client_name']) ?></span>
                                <span><?= timeAgo($ticket['updated_at']) ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($grouped[$status])): ?>
                    <p class="text-center text-muted small py-3 mb-0">Nenhuma demanda</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
