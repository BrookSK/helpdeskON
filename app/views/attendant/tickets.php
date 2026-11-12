<?php $pageTitle = 'Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Helpers da tag/selo de categoria (mesmas cores das demais telas).
if (!function_exists('categoryBadgeClass')) {
    function categoryBadgeClass(?string $category): string
    {
        switch (strtolower(trim((string)$category))) {
            case 'suporte':         return 'bg-danger';
            case 'desenvolvimento': return 'bg-primary';
            case 'design':          return 'text-white';
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
        $c = strtolower(trim((string)$category));
        $map = ['suporte' => 'Suporte', 'desenvolvimento' => 'Desenvolvimento', 'design' => 'Design', 'marketing' => 'Marketing', 'outro' => 'Outro'];
        return $map[$c] ?? ucfirst($c);
    }
}
// --- Tag informativa "Recusado": aparece quando o cliente recusou o escopo
// (scope_rejected_reason preenchido). NÃO altera fluxo nem status real; é só um
// selo para identificar na lista quais demandas tiveram o escopo recusado.
// Some sozinha quando o escopo é reaprovado (o fluxo zera o motivo). ---
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

<style>
    .dm-title-cell { min-width: 240px; max-width: 360px; }
    .dm-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-weight: 600; color: #1f2937; text-decoration: none; line-height: 1.25; }
    .dm-title:hover { color: #0d9488; text-decoration: underline; }
    .dm-company { font-size: 0.75rem; color: #6b7280; margin-top: 2px; }
    .dm-sub { font-size: 0.72rem; }
    .dm-unassigned { display: inline-block; font-size: 0.75rem; font-weight: 600; color: #b45309; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 999px; padding: 2px 8px; white-space: nowrap; }
    .dm-row-unassigned { box-shadow: inset 3px 0 0 #f59e0b; }
    .dm-prev { font-size: 0.82rem; font-weight: 600; white-space: nowrap; }
    .dm-prev-late { color: #dc2626; }
    .dm-prev-soon { color: #d97706; }
    .dm-prev-ok { color: #047857; }
    .dm-prev-done { color: #6b7280; }
    .table td { vertical-align: middle; }
</style>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Todas as Demandas</h5>
            <small class="text-muted">Gerencie as demandas dos clientes</small>
        </div>
        <a href="<?= baseUrl('planning') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> Kanban</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-6 col-md-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos Status</option>
                        <option value="open" <?= ($_GET['status'] ?? '') === 'open' ? 'selected' : '' ?>>Aberto</option>
                        <option value="in_progress" <?= ($_GET['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>Em andamento</option>
                        <option value="em_revisao_interna" <?= ($_GET['status'] ?? '') === 'em_revisao_interna' ? 'selected' : '' ?>>Em Revisão Interna</option>
                        <option value="waiting_client" <?= ($_GET['status'] ?? '') === 'waiting_client' ? 'selected' : '' ?>>Aguardando</option>
                        <option value="em_homologacao" <?= ($_GET['status'] ?? '') === 'em_homologacao' ? 'selected' : '' ?>>Em Homologação</option>
                        <option value="aprovado_producao" <?= ($_GET['status'] ?? '') === 'aprovado_producao' ? 'selected' : '' ?>>Aprov. Produção</option>
                        <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Concluído</option>
                        <option value="denied" <?= ($_GET['status'] ?? '') === 'denied' ? 'selected' : '' ?>>Negado</option>
                        <option value="archived" <?= ($_GET['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Todas Prioridades</option>
                        <option value="low" <?= ($_GET['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Baixa</option>
                        <option value="medium" <?= ($_GET['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Média</option>
                        <option value="high" <?= ($_GET['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgent" <?= ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <select name="company" class="form-select form-select-sm">
                        <option value="">Todas Empresas</option>
                        <?php foreach ($companies ?? [] as $company): ?>
                        <option value="<?= $company['id'] ?>" <?= ($_GET['company'] ?? '') == $company['id'] ? 'selected' : '' ?>><?= escape($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($isAdmin)): ?>
                <div class="col-6 col-md-auto">
                    <select name="attendant" class="form-select form-select-sm">
                        <option value="">Todos Atendentes</option>
                        <?php
                        $selectedAttendant = isset($_GET['attendant']) ? $_GET['attendant'] : $user['id'];
                        ?>
                        <?php foreach ($attendants ?? [] as $att): ?>
                        <option value="<?= $att['id'] ?>" <?= $selectedAttendant == $att['id'] ? 'selected' : '' ?>><?= escape($att['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-6 col-md-auto">
                    <div class="input-group input-group-sm" title="Previsão de término (a partir de)">
                        <span class="input-group-text">Prev. de</span>
                        <input type="date" name="prev_from" class="form-control form-control-sm" value="<?= escape($_GET['prev_from'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-auto">
                    <div class="input-group input-group-sm" title="Previsão de término (até)">
                        <span class="input-group-text">até</span>
                        <input type="date" name="prev_until" class="form-control form-control-sm" value="<?= escape($_GET['prev_until'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-6 col-md-auto">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="hide_completed" value="1" id="hideCompleted" <?= !empty($_GET['hide_completed']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hideCompleted">Ocultar concluídas</label>
                    </div>
                    <?php $hideArchivedChecked = isset($_GET['filtered']) ? !empty($_GET['hide_archived']) : true; ?>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="hide_archived" value="1" id="hideArchived" <?= $hideArchivedChecked ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hideArchived">Ocultar arquivados</label>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <input type="hidden" name="filtered" value="1">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('tickets') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Demanda</th>
                            <th>Categoria</th>
                            <th>Aberto por</th>
                            <th>Responsável</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Previsão de término</th>
                            <th>Atualizado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards ?? [] as $c): ?>
                        <?php
                        $noAssignee = empty($c['assigned_to']) && empty($c['assigned_name']);
                        $isClosed = in_array($c['status'] ?? '', ['completed', 'archived', 'denied'], true);
                        $prevTs = !empty($c['data_prevista_entrega']) ? strtotime($c['data_prevista_entrega']) : null;
                        $prevClass = 'dm-prev-none';
                        $prevHint = '';
                        if ($prevTs && !$isClosed) {
                            $daysLeft = (int)floor(($prevTs - strtotime(date('Y-m-d'))) / 86400);
                            if ($daysLeft < 0) { $prevClass = 'dm-prev-late'; $prevHint = 'Atrasada há ' . abs($daysLeft) . 'd'; }
                            elseif ($daysLeft === 0) { $prevClass = 'dm-prev-soon'; $prevHint = 'Vence hoje'; }
                            elseif ($daysLeft <= 3) { $prevClass = 'dm-prev-soon'; $prevHint = 'Em ' . $daysLeft . 'd'; }
                            else { $prevClass = 'dm-prev-ok'; $prevHint = 'Em ' . $daysLeft . 'd'; }
                        } elseif ($prevTs) {
                            $prevClass = 'dm-prev-done';
                        }
                        ?>
                        <tr class="<?= $noAssignee && !$isClosed ? 'dm-row-unassigned' : '' ?>">
                            <td class="text-muted small">#<?= $c['id'] ?></td>
                            <td class="dm-title-cell">
                                <a href="<?= baseUrl('tickets/showCard/' . $c['id']) ?>" class="dm-title" title="<?= escape($c['title']) ?>"><?= escape($c['title']) ?></a>
                                <div class="dm-company"><i class="bi bi-building"></i> <?= escape($c['company_name'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php if (!empty($c['category'])): ?>
                                <span class="badge <?= categoryBadgeClass($c['category']) ?>" style="font-size:0.72rem;<?= categoryBadgeStyle($c['category']) ?>"><?= escape(categoryLabel($c['category'])) ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-medium"><?= escape($c['created_by_name'] ?? '-') ?></div>
                                <div class="text-muted dm-sub"><?= !empty($c['created_at']) ? date('d/m/Y H:i', strtotime($c['created_at'])) : '-' ?></div>
                            </td>
                            <td>
                                <?php if ($noAssignee): ?>
                                <span class="dm-unassigned"><i class="bi bi-person-exclamation"></i> Sem responsável</span>
                                <?php else: ?>
                                <span class="small fw-medium"><i class="bi bi-person-check text-muted"></i> <?= escape($c['assigned_name'] ?? '-') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-status badge-<?= $c['status'] ?>"><?= statusLabel($c['status']) ?></span><?= scopeRejectedBadge($c['scope_rejected_reason'] ?? null) ?></td>
                            <td><span class="priority-<?= $c['priority'] ?>"><?= priorityLabel($c['priority']) ?></span></td>
                            <td>
                                <?php if ($prevTs): ?>
                                <div class="dm-prev <?= $prevClass ?>"><i class="bi bi-calendar-check"></i> <?= date('d/m/Y', $prevTs) ?></div>
                                <?php if ($prevHint): ?><div class="dm-sub <?= $prevClass ?>"><?= $prevHint ?></div><?php endif; ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= !empty($c['updated_at']) ? timeAgo($c['updated_at']) : (!empty($c['created_at']) ? timeAgo($c['created_at']) : '-') ?></td>
                            <td class="text-nowrap">
                                <a href="<?= baseUrl('tickets/showCard/' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                                <form action="<?= baseUrl('planning/delete/' . $c['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('Excluir PERMANENTEMENTE o card #<?= $c['id'] ?>? Esta ação não pode ser desfeita.');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash3-fill"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cards)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($cards ?? [] as $c): ?>
                <a href="<?= baseUrl('tickets/showCard/' . $c['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:70%">#<?= $c['id'] ?> <?= escape($c['title']) ?></span>
                        <span class="text-end"><span class="badge-status badge-<?= $c['status'] ?>"><?= statusLabel($c['status']) ?></span><?= scopeRejectedBadge($c['scope_rejected_reason'] ?? null) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <?php if (!empty($c['category'])): ?>
                        <span class="badge <?= categoryBadgeClass($c['category']) ?>" style="font-size:0.65rem;<?= categoryBadgeStyle($c['category']) ?>"><?= escape(categoryLabel($c['category'])) ?></span>
                        <?php endif; ?>
                        <span class="text-muted"><i class="bi bi-building"></i> <?= escape($c['company_name'] ?? '-') ?></span>
                        <?php if (empty($c['assigned_to']) && empty($c['assigned_name'])): ?>
                        <span class="dm-unassigned"><i class="bi bi-person-exclamation"></i> Sem responsável</span>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-person-check"></i> <?= escape($c['assigned_name'] ?? '-') ?></span>
                        <?php endif; ?>
                        <span class="priority-<?= $c['priority'] ?>"><?= priorityLabel($c['priority']) ?></span>
                        <?php if (!empty($c['data_prevista_entrega'])): ?>
                        <span class="text-muted"><i class="bi bi-calendar-check"></i> Prev.: <?= date('d/m/Y', strtotime($c['data_prevista_entrega'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:0.72rem">
                        Aberto por <?= escape($c['created_by_name'] ?? '-') ?><?= !empty($c['created_at']) ? ' em ' . date('d/m/Y H:i', strtotime($c['created_at'])) : '' ?>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($cards)): ?>
                <p class="text-center text-muted py-4">Nenhuma demanda encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
