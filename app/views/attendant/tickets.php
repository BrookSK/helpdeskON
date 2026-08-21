<?php $pageTitle = 'Demandas - ON Solutions Helpdesk'; $currentPage = 'tickets'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

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
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Atendente</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Atualizado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards ?? [] as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td class="text-truncate" style="max-width:220px"><?= escape($c['title']) ?></td>
                            <td><?= escape($c['company_name'] ?? '-') ?></td>
                            <td><?= escape($c['assigned_name'] ?? 'Não atribuído') ?></td>
                            <td><span class="badge-status badge-<?= $c['status'] ?>"><?= statusLabel($c['status']) ?></span></td>
                            <td><span class="priority-<?= $c['priority'] ?>"><?= priorityLabel($c['priority']) ?></span></td>
                            <td><?= !empty($c['updated_at']) ? timeAgo($c['updated_at']) : (!empty($c['created_at']) ? timeAgo($c['created_at']) : '-') ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="openCardModal(<?= $c['id'] ?>)">Ver</button>
                                <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                                <form action="<?= baseUrl('planning/delete/' . $c['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('Excluir PERMANENTEMENTE o card #<?= $c['id'] ?>? Esta ação não pode ser desfeita.');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash3-fill"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cards)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile -->
            <div class="d-md-none p-3">
                <?php foreach ($cards ?? [] as $c): ?>
                <div class="mb-2 p-3 border rounded-3" onclick="openCardModal(<?= $c['id'] ?>)" style="cursor:pointer;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-medium text-dark text-truncate" style="max-width:70%">#<?= $c['id'] ?> <?= escape($c['title']) ?></span>
                        <span class="badge-status badge-<?= $c['status'] ?>"><?= statusLabel($c['status']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:0.75rem">
                        <span class="text-muted"><i class="bi bi-building"></i> <?= escape($c['company_name'] ?? '-') ?></span>
                        <span class="text-muted"><i class="bi bi-person"></i> <?= escape($c['assigned_name'] ?? 'Não atribuído') ?></span>
                        <span class="priority-<?= $c['priority'] ?>"><?= priorityLabel($c['priority']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cards)): ?>
                <p class="text-center text-muted py-4">Nenhuma demanda encontrada.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para visualizar card (reusa a mesma lógica do planejamento) -->
<div class="modal fade" id="cardDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="cardDetailTitle">Detalhes do Card</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cardDetailBody">
                <div class="text-center py-4"><span class="spinner-border"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
function openCardModal(cardId) {
    const modal = new bootstrap.Modal(document.getElementById('cardDetailModal'));
    const body = document.getElementById('cardDetailBody');
    body.innerHTML = '<div class="text-center py-4"><span class="spinner-border"></span></div>';
    modal.show();

    fetch('<?= baseUrl("planning/get/") ?>' + cardId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            body.innerHTML = '<p class="text-danger">' + data.error + '</p>';
            return;
        }
        const card = data.card;
        let html = '<div class="mb-3">';
        html += '<h6 class="fw-bold">#' + card.id + ' - ' + escapeHtml(card.title) + '</h6>';
        if (card.description) html += '<div class="mt-2 small">' + card.description + '</div>';
        html += '</div>';
        html += '<div class="row g-2 mb-3 small">';
        html += '<div class="col-sm-6"><strong>Empresa:</strong> ' + escapeHtml(card.company_name || '-') + '</div>';
        html += '<div class="col-sm-6"><strong>Responsável:</strong> ' + escapeHtml(card.assigned_name || 'Não atribuído') + '</div>';
        html += '<div class="col-sm-6"><strong>Status:</strong> ' + escapeHtml(card.status) + '</div>';
        html += '<div class="col-sm-6"><strong>Prioridade:</strong> ' + escapeHtml(card.priority) + '</div>';
        if (card.due_date) html += '<div class="col-sm-6"><strong>Prazo:</strong> ' + card.due_date + '</div>';
        html += '</div>';

        // Comentários
        if (data.comments && data.comments.length) {
            html += '<h6 class="mt-3 small fw-bold">Comentários</h6>';
            data.comments.forEach(c => {
                html += '<div class="border-start border-3 ps-2 mb-2 small"><strong>' + escapeHtml(c.user_name || '') + ':</strong> ' + escapeHtml(c.message) + '</div>';
            });
        }

        body.innerHTML = html;
        document.getElementById('cardDetailTitle').textContent = 'Card #' + card.id;
    })
    .catch(() => {
        body.innerHTML = '<p class="text-danger">Erro ao carregar detalhes.</p>';
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
