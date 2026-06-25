<!-- Mobile Top Bar -->
<div class="mobile-topbar">
    <button class="btn btn-sm btn-outline-secondary" id="btn-toggle-sidebar" aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
    </button>
    <span class="logo-text">ON</span><span class="fw-light"> Solutions</span>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <span class="logo-text">ON</span>
        <span class="text-white fw-light"> Solutions</span>
        <div class="text-muted small mt-1">Helpdesk</div>
    </div>
    <nav class="mt-2 flex-grow-1" style="overflow-y:auto;">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= baseUrl('dashboard') ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>

            <?php if (($user['role'] ?? '') === 'client'): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>" href="<?= baseUrl('tickets') ?>">
                    <i class="bi bi-ticket-detailed"></i> Minhas Demandas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'create' ? 'active' : '' ?>" href="<?= baseUrl('tickets/create') ?>">
                    <i class="bi bi-plus-circle"></i> Nova Demanda
                </a>
            </li>
            <?php endif; ?>

            <?php if (in_array($user['role'] ?? '', ['super_admin', 'attendant'])): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>" href="<?= baseUrl('tickets') ?>">
                    <i class="bi bi-list-task"></i> Demandas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'kanban' ? 'active' : '' ?>" href="<?= baseUrl('tickets/kanban') ?>">
                    <i class="bi bi-kanban"></i> Kanban
                </a>
            </li>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <li class="nav-item mt-3">
                <small class="text-uppercase px-3" style="font-size:0.65rem;color:rgba(255,255,255,0.35);letter-spacing:0.5px;">Administração</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>" href="<?= baseUrl('users') ?>">
                    <i class="bi bi-people"></i> Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>" href="<?= baseUrl('settings') ?>">
                    <i class="bi bi-gear"></i> Configurações
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'notifications' ? 'active' : '' ?>" href="<?= baseUrl('notifications') ?>">
                    <i class="bi bi-bell"></i> Notificações
                    <span class="notification-count-sidebar badge bg-danger ms-1" style="display:none;font-size:0.65rem;"></span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'account' ? 'active' : '' ?>" href="<?= baseUrl('account') ?>">
                    <i class="bi bi-person-circle"></i> Minha Conta
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="d-flex align-items-center text-white">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:34px;height:34px;">
                <i class="bi bi-person" style="font-size:0.9rem;"></i>
            </div>
            <div class="ms-2 flex-grow-1 overflow-hidden">
                <div class="small fw-medium text-truncate"><?= escape($user['name'] ?? '') ?></div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);"><?= ucfirst(str_replace('_', ' ', $user['role'] ?? '')) ?></div>
            </div>
            <a href="<?= baseUrl('login/logout') ?>" class="btn btn-sm btn-outline-danger ms-2 flex-shrink-0" title="Sair" style="padding:5px 10px;border-radius:8px;">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const btn = document.getElementById('btn-toggle-sidebar');

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (btn) btn.addEventListener('click', openSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Fechar ao clicar num link (mobile)
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) closeSidebar();
        });
    });
})();
</script>
