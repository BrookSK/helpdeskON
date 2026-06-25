<?php $pageTitle = 'Kanban - ON Solutions Helpdesk'; $currentPage = 'kanban'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Kanban</h5>
            <small class="text-muted">Arraste os cards para alterar status</small>
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

    <div class="kanban-scroll" style="overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:10px;">
        <div class="d-flex gap-3" style="min-width:max-content;">
            <?php foreach ($statusLabels as $status => $info): ?>
            <div style="width:250px;flex-shrink:0;">
                <div class="kanban-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold" style="color:<?= $info[1] ?>;font-size:0.85rem;">
                            <?= $info[0] ?>
                        </h6>
                        <span class="badge rounded-pill" style="background:<?= $info[1] ?>;color:#fff;font-size:0.7rem"><?= count($grouped[$status] ?? []) ?></span>
                    </div>
                    <div class="kanban-list" data-status="<?= $status ?>" style="min-height:60px;">
                        <?php foreach (($grouped[$status] ?? []) as $ticket): ?>
                        <div class="kanban-card" data-id="<?= $ticket['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="text-muted" style="font-size:0.72rem">#<?= $ticket['id'] ?></span>
                                <span class="priority-<?= $ticket['priority'] ?>" style="font-size:0.72rem"><?= priorityLabel($ticket['priority']) ?></span>
                            </div>
                            <a href="<?= baseUrl('tickets/show/' . $ticket['id']) ?>" class="text-dark text-decoration-none fw-medium" style="font-size:0.82rem">
                                <?= escape($ticket['title']) ?>
                            </a>
                            <div class="text-muted mt-2 d-flex justify-content-between" style="font-size:0.72rem">
                                <span><i class="bi bi-person"></i> <?= escape($ticket['client_name']) ?></span>
                                <span><?= timeAgo($ticket['updated_at']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.querySelectorAll('.kanban-list').forEach(list => {
    new Sortable(list, {
        group: 'kanban',
        animation: 200,
        ghostClass: 'kanban-ghost',
        dragClass: 'kanban-drag',
        handle: '.kanban-card',
        onEnd: function(evt) {
            const cardEl = evt.item;
            const ticketId = cardEl.dataset.id;
            const newStatus = evt.to.dataset.status;

            // Atualizar no servidor
            const formData = new FormData();
            formData.append('status', newStatus);

            fetch('<?= baseUrl("tickets/updateStatus/") ?>' + ticketId, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => {
                if (r.redirected || r.ok) {
                    // Atualizar contadores
                    updateColumnCounts();
                }
            })
            .catch(() => {
                // Reverter se falhar
                evt.from.appendChild(cardEl);
                updateColumnCounts();
            });
        }
    });
});

function updateColumnCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const list = col.querySelector('.kanban-list');
        const badge = col.querySelector('.badge');
        if (list && badge) {
            badge.textContent = list.querySelectorAll('.kanban-card').length;
        }
    });
}
</script>

<style>
.kanban-ghost {
    opacity: 0.4;
    background: var(--primary-50, #E0F7F4) !important;
    border: 2px dashed var(--primary, #00BFA6) !important;
}
.kanban-drag {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    transform: rotate(2deg);
}
.kanban-card {
    cursor: grab;
}
.kanban-card:active {
    cursor: grabbing;
}
</style>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
