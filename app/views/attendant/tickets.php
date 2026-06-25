<?php $pageTitle = 'Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Todas as Demandas</h5>
            <small class="text-muted">Gerencie as demandas dos clientes</small>
        </div>
        <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> Kanban</a>
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
                        <option value="waiting_client" <?= ($_GET['status'] ?? '') === 'waiting_client' ? 'selected' : '' ?>>Aguardando</option>
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
                <div class="col-12 col-md-auto">
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
                            <th>Título</th>
                            <th>Cliente</th>
                            <th>Atendente</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atualizado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td class="text-truncate" style="max-width:180px"><?= escape($t['title']) ?></td>
                            <td><?= escape($t['client_name']) ?></td>
                            <td><?= escape($t['attendant_name'] ?? 'Não atribuído') ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= priorityLabel($t['priority']) ?></span></td>
                            <td><?= timeAgo($t['updated_at']) ?></td>
                            <td><a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($tickets as $t): ?>
                <a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="d-block text-decoration-none mb-2 p-3 border rounded-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:70%">#<?= $t['id'] ?> <?= escape($t['title']) ?></span>
                        <span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <span class="text-muted"><i class="bi bi-person"></i> <?= escape($t['client_name']) ?></span>
                        <span class="priority-<?= $t['priority'] ?>"><?= priorityLabel($t['priority']) ?></span>
                        <span class="text-muted"><?= timeAgo($t['updated_at']) ?></span>
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
