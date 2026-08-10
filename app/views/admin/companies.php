<?php $pageTitle = 'Empresas - ON Solutions Helpdesk'; $currentPage = 'companies'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Empresas</h5>
            <small class="text-muted">Gerencie as empresas clientes</small>
        </div>
        <a href="<?= baseUrl('companies/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-building-add"></i> Nova Empresa
        </a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CNPJ/CPF</th>
                            <th>Email</th>
                            <th>Usuários</th>
                            <th>Demandas</th>
                            <th>Documentos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): ?>
                        <tr style="cursor:pointer" onclick="window.location='<?= baseUrl('companies/details/' . $c['id']) ?>'">
                            <td><?= $c['id'] ?></td>
                            <td class="fw-medium">
                                <i class="bi bi-building text-primary me-1"></i>
                                <?= escape($c['name']) ?>
                            </td>
                            <td style="font-size:0.85rem"><?= escape($c['document'] ?? '-') ?></td>
                            <td style="font-size:0.85rem"><?= escape($c['email'] ?? '-') ?></td>
                            <td><span class="badge bg-primary"><?= $c['users_count'] ?></span></td>
                            <td><span class="badge bg-secondary"><?= $c['tickets_count'] ?? 0 ?></span></td>
                            <td><span class="badge bg-info text-dark"><?= $c['documents_count'] ?? 0 ?></span></td>
                            <td onclick="event.stopPropagation()">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= baseUrl('companies/details/' . $c['id']) ?>" class="btn btn-outline-secondary" title="Abrir"><i class="bi bi-box-arrow-in-right"></i></a>
                                    <a href="<?= baseUrl('companies/edit/' . $c['id']) ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="<?= baseUrl('companies/delete/' . $c['id']) ?>" class="btn btn-outline-danger" title="Remover" onclick="return confirm('Remover esta empresa?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($companies)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma empresa cadastrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
