<?php $pageTitle = 'Minhas Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// --- Tag de categoria: cor do badge por categoria (suporte=vermelho, dev=azul, design=roxo, marketing=laranja, outro=secondary) ---
if (!function_exists('categoryBadgeClass')) {
    function categoryBadgeClass(?string $category): string
    {
        switch (strtolower(trim((string)$category))) {
            case 'suporte':         return 'bg-danger';
            case 'desenvolvimento': return 'bg-primary';
            case 'design':          return 'text-white'; // roxo via style inline
            case 'marketing':       return 'bg-warning text-dark';
            default:                return 'bg-secondary';
        }
    }
}
if (!function_exists('categoryBadgeStyle')) {
    function categoryBadgeStyle(?string $category): string
    {
        return strtolower(trim((string)$category)) === 'design' ? 'background-color:#6f42c1;' : '';
    }
}
if (!function_exists('categoryLabel')) {
    function categoryLabel(?string $category): string
    {
        $map = ['suporte' => 'Suporte', 'desenvolvimento' => 'Desenvolvimento', 'design' => 'Design', 'marketing' => 'Marketing', 'outro' => 'Outro'];
        $c = strtolower(trim((string)$category));
        return $map[$c] ?? ucfirst($c);
    }
}
// --- Tag informativa "Recusado": aparece quando o cliente recusou o escopo
// (scope_rejected_reason preenchido). NÃO altera o fluxo nem o status real da
// demanda; é só um selo visual para identificar na lista quais tiveram o escopo
// recusado. Some sozinha quando o escopo é reaprovado (o fluxo zera o motivo). ---
if (!function_exists('scopeRejectedBadge')) {
    function scopeRejectedBadge(?string $reason): string
    {
        if (trim((string)$reason) === '') {
            return '';
        }
        return '<span class="badge bg-danger ms-1" style="font-size:0.65rem;" title="Escopo recusado pelo cliente">Recusado</span>';
    }
}
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= !empty($isOwner) ? 'Demandas da Empresa' : 'Minhas Demandas' ?></h5>
            <small class="text-muted"><?= !empty($isOwner) ? 'Todas as demandas da sua empresa' : 'Acompanhe suas solicitações' ?></small>
        </div>
        <a href="<?= baseUrl('tickets/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Nova Demanda
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Título</th>
                            <?php if (!empty($isOwner)): ?><th>Solicitante</th><?php endif; ?>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atendente</th>
                            <th>Criado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= $t['client_ticket_number'] ?? $t['id'] ?></td>
                            <td class="text-truncate" style="max-width:200px"><?= escape($t['title']) ?></td>
                            <?php if (!empty($isOwner)): ?><td style="font-size:0.85rem"><?= escape($t['client_name'] ?? '-') ?></td><?php endif; ?>
                            <td><?php if (!empty($t['category'])): ?><span class="badge <?= categoryBadgeClass($t['category']) ?>" style="<?= categoryBadgeStyle($t['category']) ?>"><?= escape(categoryLabel($t['category'])) ?></span><?php else: ?>-<?php endif; ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span><?= scopeRejectedBadge($t['scope_rejected_reason'] ?? null) ?></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= priorityLabel($t['priority']) ?></span></td>
                            <td><?= escape($t['attendant_name'] ?? 'Aguardando') ?></td>
                            <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                            <td><a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="<?= !empty($isOwner) ? 9 : 8 ?>" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($tickets as $t): ?>
                <a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:65%">#<?= $t['client_ticket_number'] ?? $t['id'] ?> <?= escape($t['title']) ?></span>
                        <span class="text-end"><span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span><?= scopeRejectedBadge($t['scope_rejected_reason'] ?? null) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <?php if (!empty($t['category'])): ?>
                        <span class="badge <?= categoryBadgeClass($t['category']) ?>" style="<?= categoryBadgeStyle($t['category']) ?>"><?= escape(categoryLabel($t['category'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($isOwner) && !empty($t['client_name'])): ?>
                        <span class="text-dark fw-medium"><i class="bi bi-person"></i> <?= escape($t['client_name']) ?></span>
                        <?php endif; ?>
                        <span class="priority-<?= $t['priority'] ?>"><?= priorityLabel($t['priority']) ?></span>
                        <span class="text-muted"><?= escape($t['attendant_name'] ?? 'Aguardando') ?></span>
                        <span class="text-muted"><?= timeAgo($t['created_at']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                <p class="text-center text-muted py-4">Nenhuma demanda encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
