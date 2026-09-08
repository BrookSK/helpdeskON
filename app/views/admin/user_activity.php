<?php $pageTitle = 'Auditoria de ' . escape($targetUser['name']) . ' - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Auditoria do Usuário</h5>
            <small class="text-muted"><?= escape($targetUser['name']) ?> — <?= escape($targetUser['email']) ?> · <?= escape(roleLabel($targetUser['role'])) ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('users/edit/' . $targetUser['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body py-3">
                <div class="text-muted small">Total de logins</div>
                <div class="h4 mb-0"><?= number_format($totalLogins, 0, ',', '.') ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card"><div class="card-body py-3">
                <div class="text-muted small">Total de ações</div>
                <div class="h4 mb-0"><?= number_format($totalActions, 0, ',', '.') ?></div>
            </div></div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-logins" type="button" role="tab">
                <i class="bi bi-box-arrow-in-right"></i> Logins (<?= count($logins) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions" type="button" role="tab">
                <i class="bi bi-activity"></i> Ações
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- LOGINS -->
        <div class="tab-pane fade show active" id="tab-logins" role="tabpanel">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Data / Hora</th>
                                    <th>Tipo</th>
                                    <th>IP</th>
                                    <th>Navegador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logins)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Nenhum login registrado ainda.</td></tr>
                                <?php else: foreach ($logins as $l): ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                                    <td>
                                        <?php if ($l['login_type'] === 'impersonation'): ?>
                                            <span class="badge bg-warning text-dark">Impersonação</span>
                                            <?php if (!empty($l['impersonator_name'])): ?>
                                                <small class="text-muted">por <?= escape($l['impersonator_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-success">Senha</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?= escape($l['ip_address'] ?? '—') ?></td>
                                    <td><small class="text-muted"><?= escape($l['user_agent'] ?? '—') ?></small></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- AÇÕES -->
        <div class="tab-pane fade" id="tab-actions" role="tabpanel">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Data / Hora</th>
                                    <th>Módulo</th>
                                    <th>Ação</th>
                                    <th>Ref.</th>
                                    <th>Método</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($actions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma ação registrada ainda.</td></tr>
                                <?php else: foreach ($actions as $a): ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('d/m/Y H:i:s', strtotime($a['created_at'])) ?></td>
                                    <td><?= escape(activityModuleLabel($a['controller'])) ?></td>
                                    <td><?= escape(activityActionLabel($a['action'])) ?></td>
                                    <td class="text-muted small"><?= escape($a['params'] ?? '') ?: '—' ?></td>
                                    <td>
                                        <span class="badge <?= $a['http_method'] === 'POST' ? 'bg-primary' : 'bg-secondary' ?>"><?= escape($a['http_method']) ?></span>
                                    </td>
                                    <td class="text-nowrap"><?= escape($a['ip_address'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('users/activity/' . $targetUser['id'] . '?page=' . $p) ?>#tab-actions"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
