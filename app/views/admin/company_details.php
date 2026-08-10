<?php $pageTitle = escape($company['name']) . ' - Empresas'; $currentPage = 'companies'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <!-- Breadcrumb / hierarquia -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0" style="font-size:0.85rem">
            <li class="breadcrumb-item"><a href="<?= baseUrl('companies') ?>" class="text-decoration-none">Empresas</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= escape($company['name']) ?></li>
        </ol>
    </nav>

    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-building text-primary me-1"></i> <?= escape($company['name']) ?></h5>
            <small class="text-muted">Dados da empresa e recursos vinculados</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('companies/edit/' . $company['id']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="<?= baseUrl('companies') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Dados da empresa -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">CNPJ / CPF</div>
                    <div class="fw-medium"><?= escape($company['document'] ?? '-') ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Email</div>
                    <div class="fw-medium"><?= escape($company['email'] ?? '-') ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Telefone</div>
                    <div class="fw-medium"><?= escape($company['phone'] ?? '-') ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small">Cadastrada em</div>
                    <div class="fw-medium"><?= !empty($company['created_at']) ? date('d/m/Y', strtotime($company['created_at'])) : '-' ?></div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="text-muted small"><i class="bi bi-whatsapp text-success"></i> Grupo de WhatsApp</div>
                    <div class="fw-medium"><?= !empty($company['whatsapp_group_jid']) ? 'Vinculado' : '<span class="text-muted">Não vinculado</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Abas -->
    <ul class="nav nav-tabs mb-3" id="companyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-users-btn" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab">
                <i class="bi bi-people"></i> Usuários <span class="badge bg-primary ms-1"><?= count($companyUsers) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-tickets-btn" data-bs-toggle="tab" data-bs-target="#tab-tickets" type="button" role="tab">
                <i class="bi bi-ticket-detailed"></i> Demandas <span class="badge bg-secondary ms-1"><?= count($tickets) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-docs-btn" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
                <i class="bi bi-folder"></i> Documentos <span class="badge bg-info text-dark ms-1"><?= count($documents) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Usuários -->
        <div class="tab-pane fade show active" id="tab-users" role="tabpanel">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-medium">Usuários da empresa</span>
                    <a href="<?= baseUrl('users/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Novo Usuário</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Papel</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companyUsers as $u): ?>
                                <tr>
                                    <td><?= $u['id'] ?></td>
                                    <td class="fw-medium">
                                        <?= escape($u['name']) ?>
                                        <?php if (!empty($u['is_company_owner'])): ?>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size:0.62rem"><i class="bi bi-star-fill"></i> Responsável</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.85rem"><?= escape($u['email']) ?></td>
                                    <td style="font-size:0.85rem"><?= escape($u['phone'] ?? '-') ?></td>
                                    <td><?= roleLabel($u['role']) ?></td>
                                    <td>
                                        <?= $u['is_active']
                                            ? '<span class="badge bg-success" style="font-size:0.7rem">Ativo</span>'
                                            : '<span class="badge bg-secondary" style="font-size:0.7rem">Inativo</span>' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($u['is_active']): ?>
                                            <a href="<?= baseUrl('login/loginAs/' . $u['id']) ?>" class="btn btn-outline-success" title="Login como usuário" onclick="return confirm('Entrar no sistema como <?= escape($u['name']) ?>?')">
                                                <i class="bi bi-box-arrow-in-right"></i> Login
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?= baseUrl('users/edit/' . $u['id']) ?>" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <a href="<?= baseUrl('users/toggleStatus/' . $u['id']) ?>" class="btn btn-outline-warning" title="<?= $u['is_active'] ? 'Desativar' : 'Ativar' ?>"><i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-fill"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($companyUsers)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum usuário vinculado a esta empresa.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demandas -->
        <div class="tab-pane fade" id="tab-tickets" role="tabpanel">
            <div class="card">
                <div class="card-header bg-white fw-medium">Demandas da empresa</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Título</th>
                                    <th>Solicitante</th>
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
                                    <td class="text-truncate fw-medium" style="max-width:220px"><?= escape($t['title']) ?></td>
                                    <td style="font-size:0.85rem"><?= escape($t['client_name'] ?? '-') ?></td>
                                    <td style="font-size:0.85rem"><?= escape($t['attendant_name'] ?? 'Não atribuído') ?></td>
                                    <td><span class="badge-status badge-<?= $t['status'] ?>"><?= statusLabel($t['status']) ?></span></td>
                                    <td><?= priorityLabel($t['priority']) ?></td>
                                    <td style="font-size:0.82rem"><?= timeAgo($t['updated_at']) ?></td>
                                    <td><a href="<?= baseUrl('tickets/show/' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tickets)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma demanda para esta empresa.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentos -->
        <div class="tab-pane fade" id="tab-docs" role="tabpanel">
            <div class="card">
                <div class="card-header bg-white fw-medium">Documentos da empresa</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th>Enviado por</th>
                                    <th>Tamanho</th>
                                    <th>Data</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $d): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium"><i class="bi bi-file-earmark me-1"></i> <?= escape($d['title']) ?></div>
                                        <div class="text-muted small"><?= escape($d['file_name']) ?></div>
                                    </td>
                                    <td style="font-size:0.85rem"><?= escape($d['uploaded_by'] ?? '-') ?></td>
                                    <td style="font-size:0.85rem"><?= isset($d['file_size']) ? round($d['file_size'] / 1024) . ' KB' : '-' ?></td>
                                    <td style="font-size:0.85rem"><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                                    <td><a href="<?= baseUrl($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($documents)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Nenhum documento para esta empresa.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
