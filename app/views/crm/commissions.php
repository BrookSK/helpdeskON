<?php $pageTitle = 'Comissões - CRM'; $currentPage = 'crm'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-cash-stack"></i> <?= !empty($isComercial) ? 'Minhas Comissões' : 'Comissões' ?></h5>
            <small class="text-muted"><?= !empty($isComercial) ? 'Suas comissões a receber por leads convertidos' : 'Comissões dos usuários comerciais por leads convertidos' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if (empty($isComercial)): ?>
            <a href="<?= baseUrl('crm/dashboard') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i> Dashboard CRM</a>
            <?php endif; ?>
            <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> CRM</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="<?= baseUrl('crm/commissions') ?>" class="row g-2 align-items-end">
                <div class="col-6 col-md-auto">
                    <label class="form-label small fw-medium mb-1">Mês</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="<?= escape($month) ?>">
                </div>
                <?php if (empty($isComercial)): ?>
                <div class="col-6 col-md-auto">
                    <label class="form-label small fw-medium mb-1">Usuário</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($comerciais as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterUserId == $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('crm/commissions') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <?php
    $totalCommission = 0;
    foreach ($commissions as $c) { $totalCommission += (float)$c['commission_value']; }
    ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card stat-card" style="border-left-color:#00BFA6">
                <div class="stat-label"><?= !empty($isComercial) ? 'Total a receber (mês)' : 'Total a pagar (mês)' ?></div>
                <div class="stat-value" style="color:#00997D;font-size:1.2rem;">R$ <?= number_format($totalCommission, 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Comercial</th>
                            <th>% Comissão</th>
                            <th>Leads Convertidos</th>
                            <th>Valor Total</th>
                            <th>Comissão a Pagar</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commissions as $c): ?>
                        <tr class="commission-row" style="cursor:pointer" onclick="toggleLeads(<?= $c['user_id'] ?>)">
                            <td class="fw-medium"><i class="bi bi-person-badge"></i> <?= escape($c['user_name']) ?></td>
                            <td><?= number_format((float)$c['commission_percent'], 2, ',', '.') ?>%</td>
                            <td><span class="badge bg-success"><?= $c['converted_count'] ?></span></td>
                            <td>R$ <?= number_format((float)$c['total_value'], 2, ',', '.') ?></td>
                            <td class="fw-medium text-success">R$ <?= number_format((float)$c['commission_value'], 2, ',', '.') ?></td>
                            <td><i class="bi bi-chevron-down" id="chevron-<?= $c['user_id'] ?>"></i></td>
                        </tr>
                        <tr id="leads-row-<?= $c['user_id'] ?>" style="display:none;">
                            <td colspan="6" class="p-0">
                                <div id="leads-content-<?= $c['user_id'] ?>" class="p-3 bg-light">
                                    <div class="text-muted small">Carregando...</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($commissions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuário comercial encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const FILTER_MONTH = '<?= escape($month) ?>';
const loadedLeads = {};

function toggleLeads(userId) {
    const row = document.getElementById('leads-row-' + userId);
    const chevron = document.getElementById('chevron-' + userId);
    if (row.style.display === 'none') {
        row.style.display = '';
        if (chevron) chevron.className = 'bi bi-chevron-up';
        if (!loadedLeads[userId]) {
            fetch(BASE + 'crm/commissionLeads/' + userId + '?month=' + FILTER_MONTH, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('leads-content-' + userId);
                if (data.leads && data.leads.length) {
                    let html = '<div class="fw-medium small mb-2">Leads convertidos no período</div><div class="list-group">';
                    data.leads.forEach(l => {
                        const val = l.value ? 'R$ ' + parseFloat(l.value).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '—';
                        const name = l.contact_name || l.title || 'Lead';
                        const date = l.outcome_at ? new Date(l.outcome_at.replace(' ','T')).toLocaleDateString('pt-BR') : '';
                        html += `<div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span><i class="bi bi-check-circle text-success"></i> ${escapeHtml(name)} <small class="text-muted">${l.contact_phone ? '· ' + escapeHtml(l.contact_phone) : ''}</small></span>
                            <span class="text-success fw-medium">${val} <small class="text-muted ms-2">${date}</small></span>
                        </div>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-muted small">Nenhum lead convertido no período.</div>';
                }
                loadedLeads[userId] = true;
            });
        }
    } else {
        row.style.display = 'none';
        if (chevron) chevron.className = 'bi bi-chevron-down';
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
