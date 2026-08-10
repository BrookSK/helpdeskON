<?php $pageTitle = 'Notificações - ON Solutions Helpdesk'; $currentPage = 'notifications'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Notificações</h5>
            <small class="text-muted">Atualizações das demandas</small>
        </div>
        <button onclick="markAllRead()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check-all"></i> <span class="d-none d-sm-inline">Marcar todas como lidas</span><span class="d-sm-none">Ler todas</span>
        </button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="list-group list-group-flush" id="notif-list">
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item px-3 py-3 <?= $n['is_read'] ? '' : 'bg-light' ?>" id="notif-<?= $n['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1 overflow-hidden">
                            <h6 class="mb-1 <?= $n['is_read'] ? 'fw-normal' : 'fw-bold' ?>" style="font-size:0.88rem"><?= escape($n['title']) ?></h6>
                            <p class="mb-1 text-muted" style="font-size:0.8rem"><?= escape($n['message']) ?></p>
                            <small class="text-muted" style="font-size:0.72rem"><?= timeAgo($n['created_at']) ?></small>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0 notif-actions">
                            <?php if ($n['ticket_id']): ?>
                            <a href="<?= baseUrl('tickets/show/' . $n['ticket_id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                            <?php endif; ?>
                            <?php if (!$n['is_read']): ?>
                            <button data-mark-btn onclick="markRead(<?= $n['id'] ?>, this)" class="btn btn-sm btn-outline-secondary" title="Marcar como lida">
                                <i class="bi bi-check"></i>
                            </button>
                            <?php else: ?>
                            <span class="notif-read-badge text-success align-self-center" style="font-size:0.75rem"><i class="bi bi-check-all"></i> Lida</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($notifications)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-bell-slash fs-2"></i>
                    <p class="mt-2 mb-0">Nenhuma notificação</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toast de confirmação -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080;">
    <div id="notifToast" class="toast align-items-center text-white border-0" style="background:#00997D;" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle-fill me-1"></i> <span id="notifToastMsg">Notificação marcada como lida</span></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
function showNotifToast(msg) {
    const msgEl = document.getElementById('notifToastMsg');
    if (msgEl && msg) msgEl.textContent = msg;
    const toastEl = document.getElementById('notifToast');
    if (toastEl && window.bootstrap) {
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2000 }).show();
    }
}

// Marca visualmente um item como lido (sem recarregar)
function markItemReadUI(el) {
    if (!el) return;
    el.classList.remove('bg-light');
    const title = el.querySelector('h6');
    if (title) { title.classList.remove('fw-bold'); title.classList.add('fw-normal'); }
    const btn = el.querySelector('button[data-mark-btn]');
    if (btn) btn.remove();
    // Marca de "lida" visível
    if (!el.querySelector('.notif-read-badge')) {
        const actions = el.querySelector('.notif-actions');
        if (actions) {
            const badge = document.createElement('span');
            badge.className = 'notif-read-badge text-success align-self-center';
            badge.style.fontSize = '0.75rem';
            badge.innerHTML = '<i class="bi bi-check-all"></i> Lida';
            actions.appendChild(badge);
        }
    }
}

function markRead(id, btn) {
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    fetch('<?= baseUrl("notifications/markRead/") ?>' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                markItemReadUI(document.getElementById('notif-' + id));
                showNotifToast('Notificação marcada como lida');
                updateSidebarBadge();
            } else if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check"></i>';
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check"></i>'; }
        });
}

function markAllRead() {
    fetch('<?= baseUrl("notifications/markAllRead") ?>')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('#notif-list .list-group-item').forEach(el => markItemReadUI(el));
                showNotifToast('Todas as notificações foram marcadas como lidas');
                updateSidebarBadge();
            }
        });
}

// Atualiza o contador do sidebar (se o polling existir)
function updateSidebarBadge() {
    const badge = document.querySelector('.notification-count-sidebar');
    if (badge) { badge.style.display = 'none'; badge.textContent = ''; }
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
