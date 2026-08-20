<?php $pageTitle = 'Planejamento - ON Solutions Helpdesk'; $currentPage = 'demands'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusLabels = [
    'open' => ['Aberto', 'primary'],
    'in_progress' => ['Em andamento', 'warning'],
    'em_revisao_interna' => ['Em Revisão Interna', 'info'],
    'waiting_client' => ['Aguardando', 'secondary'],
    'em_homologacao' => ['Em Homologação', 'info'],
    'aprovado_producao' => ['Aprov. Produção', 'success'],
    'completed' => ['Concluído', 'success'],
    'denied' => ['Negado', 'danger'],
];
$priorityLabels = [
    'low' => ['Baixa', 'success'],
    'medium' => ['Média', 'warning'],
    'high' => ['Alta', 'orange'],
    'urgent' => ['Urgente', 'danger'],
];
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Planejamento</h5>
            <small class="text-muted">Acompanhe o andamento das atividades da sua empresa</small>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="filtered" value="1">
                <div class="col-6 col-md-auto">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos Status</option>
                        <?php foreach ($statusLabels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $info[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Todas Prioridades</option>
                        <?php foreach ($priorityLabels as $key => $info): ?>
                        <option value="<?= $key ?>" <?= ($filters['priority'] ?? '') === $key ? 'selected' : '' ?>><?= $info[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex gap-3">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="hide_completed" value="1" id="hideCompleted" <?= !empty($filters['hide_completed']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hideCompleted">Ocultar concluídos</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="checkbox" name="hide_archived" value="1" id="hideArchived" <?= !empty($hideArchived) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hideArchived">Ocultar arquivados</label>
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('planning/clientDemands') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista -->
    <div class="card">
        <div class="card-body p-0">
            <!-- Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards as $card): ?>
                        <tr>
                            <td class="text-muted"><?= $card['id'] ?></td>
                            <td><?= escape($card['title']) ?></td>
                            <td><?= escape($card['company_name'] ?? '-') ?></td>
                            <td>
                                <?php $st = $statusLabels[$card['status']] ?? [$card['status'], 'secondary']; ?>
                                <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                            </td>
                            <td>
                                <?php $pr = $priorityLabels[$card['priority']] ?? [$card['priority'], 'secondary']; ?>
                                <span class="badge bg-<?= $pr[1] ?>"><?= $pr[0] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cards)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma atividade encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($cards as $card): ?>
                <div class="d-block mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:65%">#<?= $card['id'] ?> <?= escape($card['title']) ?></span>
                        <?php $st = $statusLabels[$card['status']] ?? [$card['status'], 'secondary']; ?>
                        <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <span class="text-muted"><i class="bi bi-building"></i> <?= escape($card['company_name'] ?? '-') ?></span>
                        <?php $pr = $priorityLabels[$card['priority']] ?? [$card['priority'], 'secondary']; ?>
                        <span class="badge bg-<?= $pr[1] ?>"><?= $pr[0] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cards)): ?>
                <p class="text-center text-muted py-4">Nenhuma atividade encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
