<?php $pageTitle = 'Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Todas as Demandas</h5>
            <small class="text-muted">Gerencie as demandas dos clientes</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('tickets/kanban') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> Kanban</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
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
                <div class="col-auto">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">Todas Prioridades</option>
                        <option value="low" <?= ($_GET['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Baixa</option>
                        <option value="medium" <?= ($_GET['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Média</option>
                        <option value="high" <?= ($_GET['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgent" <?= ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('tickets') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
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
                            <td><?= escape($t['title']) ?></td>
                            <td><?= escape($t['client_name']) ?></td>
                            <td><?= escape($t['attendant_name'] ?? 'Não atribuído') ?></td>
                            <td><span class="badge-status badge-<?= $t['status'] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                            <td><span class="priority-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><?= timeAgo($t['updated_at']) ?></td>
                            <td><a href="<?= baseUrl('tickets/view/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
