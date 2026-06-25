<div class="sidebar" id="sidebar">
    <div class="p-3 text-center border-bottom border-secondary">
        <span class="logo-text">ON</span>
        <span class="text-white fw-light"> Solutions</span>
        <div class="text-muted small mt-1">Helpdesk</div>
    </div>
    <nav class="mt-3">
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
                <small class="text-muted px-3 text-uppercase" style="font-size:0.7rem">Administração</small>
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
                    <span class="notification-count-sidebar badge bg-danger ms-2" style="display:none"></span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="position-absolute bottom-0 w-100 p-3 border-top border-secondary">
        <div class="d-flex align-items-center text-white">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:35px;height:35px">
                <i class="bi bi-person"></i>
            </div>
            <div class="ms-2 flex-grow-1">
                <div class="small fw-medium"><?= escape($user['name'] ?? '') ?></div>
                <div class="text-muted" style="font-size:0.7rem"><?= escape($user['role'] ?? '') ?></div>
            </div>
            <a href="<?= baseUrl('login/logout') ?>" class="text-muted" title="Sair">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
