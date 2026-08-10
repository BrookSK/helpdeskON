<!-- Mobile Top Bar -->
<div class="mobile-topbar">
    <button class="btn btn-sm btn-outline-secondary" id="btn-toggle-sidebar" aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
    </button>
    <?php $logoUrlMobile = Config::get('app_logo'); ?>
    <?php if ($logoUrlMobile): ?>
    <img src="<?= baseUrl($logoUrlMobile) ?>" alt="Logo" style="max-height:28px;">
    <?php else: ?>
    <span class="logo-text">ON</span><span class="fw-light"> Solutions</span>
    <?php endif; ?>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header position-relative">
        <?php $logoUrl = Config::get('app_logo'); ?>
        <?php if ($logoUrl): ?>
        <img src="<?= baseUrl($logoUrl) ?>" alt="Logo" style="max-height:38px;max-width:180px;">
        <?php else: ?>
        <span class="logo-text">ON</span>
        <span class="text-white fw-light"> Solutions</span>
        <?php endif; ?>
        <div style="color:#00BFA6;font-size:0.78rem;font-weight:500;margin-top:3px;">Helpdesk</div>
        <button type="button" class="sidebar-collapse-btn position-absolute" id="btn-collapse-sidebar" title="Recolher menu" style="top:12px;right:10px;">
            <i class="bi bi-chevron-double-left" id="collapse-icon"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
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
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'documents' ? 'active' : '' ?>" href="<?= baseUrl('documents') ?>">
                    <i class="bi bi-folder"></i> Documentos
                </a>
            </li>
            <?php
            $sidebarFullUser = (new User())->findById($user['id'] ?? 0);
            if ($sidebarFullUser && $sidebarFullUser['is_company_owner']): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'subusers' ? 'active' : '' ?>" href="<?= baseUrl('subusers') ?>">
                    <i class="bi bi-people"></i> Minha Equipe
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (in_array($user['role'] ?? '', ['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial'])): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'tickets' ? 'active' : '' ?>" href="<?= baseUrl('tickets') ?>">
                    <i class="bi bi-list-task"></i> Demandas
                </a>
            </li>

            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'create' ? 'active' : '' ?>" href="<?= baseUrl('tickets/create') ?>">
                    <i class="bi bi-plus-circle"></i> Nova Demanda
                </a>
            </li>
            <?php endif; ?>
            <?php if (($user['role'] ?? '') !== 'comercial'): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'documents' ? 'active' : '' ?>" href="<?= baseUrl('documents') ?>">
                    <i class="bi bi-folder"></i> Documentos
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage ?? '') === 'planning' ? 'active' : '' ?>" href="<?= baseUrl('planning') ?>">
                    <i class="bi bi-calendar2-check"></i> Planejamento
                </a>
            </li>
            <?php if (in_array($user['role'] ?? '', ['super_admin', 'attendant', 'whatsapp_agent', 'comercial'])): ?>
            <li class="nav-item mt-3">
                <small class="text-uppercase px-3" style="font-size:0.65rem;color:rgba(255,255,255,0.35);letter-spacing:0.5px;">WhatsApp & CRM</small>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= in_array($currentPage ?? '', ['whatsapp', 'whatsapp_chat']) ? 'active' : '' ?>" href="<?= baseUrl('whatsapp/chat') ?>">
                    <i class="bi bi-whatsapp"></i> WhatsApp Chat
                </a>
            </li>
            <?php $crmSectionActive = in_array($currentPage ?? '', ['crm', 'crm_dashboard', 'crm_commissions']); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?= ($currentPage ?? '') === 'crm' ? 'active' : '' ?>" href="<?= baseUrl('crm') ?>">
                    <span class="nav-link-body"><i class="bi bi-kanban"></i> <span class="nav-text">CRM</span></span>
                    <i class="bi bi-chevron-down crm-caret <?= $crmSectionActive ? '' : 'collapsed-caret' ?>" onclick="event.preventDefault();event.stopPropagation();toggleCrmSub(this);" style="font-size:0.7rem;padding:4px;cursor:pointer;transition:transform 0.2s;"></i>
                </a>
            </li>
            <ul class="nav flex-column crm-subnav" id="crm-subnav" style="<?= $crmSectionActive ? '' : 'display:none;' ?>list-style:none;padding-left:0;">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'crm_dashboard' ? 'active' : '' ?>" href="<?= baseUrl('crm/dashboard') ?>" style="padding-left:2.6rem;font-size:0.85rem;">
                        <i class="bi bi-graph-up"></i> Dashboard CRM
                    </a>
                </li>
                <?php if (in_array($user['role'] ?? '', ['super_admin', 'comercial'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'crm_commissions' ? 'active' : '' ?>" href="<?= baseUrl('crm/commissions') ?>" style="padding-left:2.6rem;font-size:0.85rem;">
                        <i class="bi bi-cash-stack"></i> Comissões
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (($user['role'] ?? '') === 'super_admin'): ?>
            <li class="nav-item mt-3">
                <small class="text-uppercase px-3" style="font-size:0.65rem;color:rgba(255,255,255,0.35);letter-spacing:0.5px;">Administração</small>
            </li>
            <?php $companiesSectionActive = in_array($currentPage ?? '', ['companies', 'users']); ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center justify-content-between <?= ($currentPage ?? '') === 'companies' ? 'active' : '' ?>" href="<?= baseUrl('companies') ?>">
                    <span class="nav-link-body"><i class="bi bi-building"></i> <span class="nav-text">Empresas</span></span>
                    <i class="bi bi-chevron-down companies-caret <?= $companiesSectionActive ? '' : 'collapsed-caret' ?>" onclick="event.preventDefault();event.stopPropagation();toggleSubnav(this, 'companies-subnav');" style="font-size:0.7rem;padding:4px;cursor:pointer;transition:transform 0.2s;"></i>
                </a>
            </li>
            <ul class="nav flex-column" id="companies-subnav" style="<?= $companiesSectionActive ? '' : 'display:none;' ?>list-style:none;padding-left:0;">
                <li class="nav-item">
                    <a class="nav-link <?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>" href="<?= baseUrl('users') ?>" style="padding-left:2.6rem;font-size:0.85rem;">
                        <i class="bi bi-people"></i> Todos os Usuários
                    </a>
                </li>
            </ul>
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
        <?php if (!empty($_SESSION['impersonator'])): ?>
        <a href="<?= baseUrl('login/returnAdmin') ?>" class="btn btn-warning btn-sm w-100 mb-2 fw-medium" style="border-radius:8px;">
            <i class="bi bi-arrow-return-left"></i> Voltar para <?= escape($_SESSION['impersonator']['user_name']) ?>
        </a>
        <?php endif; ?>
        <div class="d-flex align-items-center text-white">
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:34px;height:34px;">
                <i class="bi bi-person" style="font-size:0.9rem;"></i>
            </div>
            <div class="ms-2 flex-grow-1 overflow-hidden">
                <div class="small fw-medium text-truncate"><?= escape($user['name'] ?? '') ?></div>
                <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);"><?= roleLabel($user['role'] ?? '') ?></div>
            </div>
            <a href="<?= baseUrl('login/logout') ?>" class="btn btn-sm btn-outline-danger ms-2 flex-shrink-0" title="Sair" style="padding:5px 10px;border-radius:8px;">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<style>
.collapsed-caret { transform: rotate(-90deg); }
</style>
<script>
// Recolher/expandir subabas genéricas do sidebar
function toggleSubnav(caret, subId) {
    const sub = document.getElementById(subId);
    if (!sub) return;
    const isHidden = sub.style.display === 'none';
    sub.style.display = isHidden ? '' : 'none';
    caret.classList.toggle('collapsed-caret', !isHidden);
}

// Compatibilidade: toggle do CRM
function toggleCrmSub(caret) {
    toggleSubnav(caret, 'crm-subnav');
}

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

    // Recolher sidebar (desktop) — só ícones
    const collapseBtn = document.getElementById('btn-collapse-sidebar');
    const collapseIcon = document.getElementById('collapse-icon');

    function applyCollapsedState(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (collapseIcon) {
            collapseIcon.className = collapsed ? 'bi bi-chevron-double-right' : 'bi bi-chevron-double-left';
        }
    }

    // Restaurar preferência salva
    try {
        if (localStorage.getItem('sidebarCollapsed') === '1') applyCollapsedState(true);
    } catch (e) {}

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            const collapsed = !document.body.classList.contains('sidebar-collapsed');
            applyCollapsedState(collapsed);
            try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
        });
    }

    // Fechar ao clicar num link (mobile)
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) closeSidebar();
        });
    });
})();
</script>
