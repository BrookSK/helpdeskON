<?php $pageTitle = 'Notificações - ON Solutions Helpdesk'; $currentPage = 'notifications'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Notificações</h5>
            <small class="text-muted">Acompanhe atualizações das demandas</small>
        </div>
        <button onclick="markAllRead()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check-all"></i> Marcar todas como lidas
        </button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item <?= $n['is_read'] ? '' : 'bg-light' ?>" id="notif-<?= $n['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1 <?= $n['is_read'] ? 'fw-normal' : 'fw-bold' ?>"><?= escape($n['title']) ?></h6>
                            <p class="mb-1 text-muted small"><?= escape($n['message']) ?></p>
                            <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <?php if ($n['ticket_id']): ?>
                            <a href="<?= baseUrl('tickets/view/' . $n['ticket_id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                            <button onclick="markRead(<?= $n['id'] ?>)" class="btn btn-sm btn-outline-secondary" title="Marcar como lida">
                                <i class="bi bi-check"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-bell-slash fs-1"></i>
                    <p class="mt-2">Nenhuma notificação</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function markRead(id) {
    fetch('<?= baseUrl("notifications/markRead/") ?>' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notif-' + id).classList.remove('bg-light');
            }
        });
}

function markAllRead() {
    fetch('<?= baseUrl("notifications/markAllRead") ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.list-group-item.bg-light').forEach(el => {
                    el.classList.remove('bg-light');
                });
            }
        });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
