<?php $pageTitle = ($editUser ? 'Editar' : 'Novo') . ' Usuário - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= $editUser ? 'Editar Usuário' : 'Novo Usuário' ?></h5>
            <small class="text-muted"><?= $editUser ? escape($editUser['name']) : 'Cadastrar novo usuário' ?></small>
        </div>
        <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card" style="max-width:700px">
        <div class="card-body">
            <form action="<?= baseUrl($editUser ? 'users/update/' . $editUser['id'] : 'users/store') ?>" method="POST">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Nome *</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($editUser['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= escape($editUser['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Senha <?= $editUser ? '(vazio = manter)' : '(vazio = enviar convite)' ?></label>
                        <input type="password" name="password" class="form-control">
                        <?php if (!$editUser): ?>
                        <small class="text-muted">Deixe em branco para enviar um email de definição de senha (primeiro acesso).</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Telefone</label>
                        <input type="text" name="phone" class="form-control" value="<?= escape($editUser['phone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Papel *</label>
                        <select name="role" id="role-select" class="form-select" required onchange="toggleCompanyFields()">
                            <option value="client" <?= ($editUser['role'] ?? '') === 'client' ? 'selected' : '' ?>>Cliente</option>
                            <option value="attendant" <?= ($editUser['role'] ?? '') === 'attendant' ? 'selected' : '' ?>>Atendente</option>
                            <option value="developer" <?= ($editUser['role'] ?? '') === 'developer' ? 'selected' : '' ?>>Desenvolvedor</option>
                            <option value="analyst" <?= ($editUser['role'] ?? '') === 'analyst' ? 'selected' : '' ?>>Analista</option>
                            <option value="whatsapp_agent" <?= ($editUser['role'] ?? '') === 'whatsapp_agent' ? 'selected' : '' ?>>Agente WhatsApp</option>
                            <option value="super_admin" <?= ($editUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>

                    <!-- Campos de empresa (só para clientes) -->
                    <div id="company-fields" class="col-12" style="<?= ($editUser['role'] ?? 'client') !== 'client' ? 'display:none' : '' ?>">
                        <hr class="my-2">
                        <h6 class="fw-medium mb-3" style="font-size:0.88rem"><i class="bi bi-building"></i> Empresa</h6>
                        <div class="row g-3">
                            <?php if (!$editUser): ?>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Empresa existente</label>
                                <select name="company_id" class="form-select form-select-sm" id="company-select" onchange="toggleNewCompany()">
                                    <option value="">Nova empresa</option>
                                    <?php
                                    $companies = (new Company())->getAll();
                                    foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6" id="new-company-field">
                                <label class="form-label fw-medium small">Nome da nova empresa</label>
                                <input type="text" name="company_name" class="form-control form-control-sm" placeholder="Nome da empresa">
                            </div>
                            <?php else: ?>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Empresa existente</label>
                                <select name="company_id" class="form-select form-select-sm" id="company-select" onchange="toggleNewCompany()">
                                    <option value="">Nova empresa</option>
                                    <?php
                                    $companies = (new Company())->getAll();
                                    foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($editUser['company_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6" id="new-company-field" style="<?= !empty($editUser['company_id']) ? 'display:none' : '' ?>">
                                <label class="form-label fw-medium small">Nome da nova empresa</label>
                                <input type="text" name="company_name" class="form-control form-control-sm" placeholder="Nome da empresa">
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_company_owner" value="1" id="isOwner" <?= ($editUser['is_company_owner'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="isOwner">
                                        Responsável da empresa (pode criar sub-usuários)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!$editUser): ?>
                    <div class="col-12">
                        <div class="alert alert-info py-2" style="font-size:0.82rem">
                            <i class="bi bi-envelope"></i> Um email será enviado ao usuário com um link para definir a senha. Após defini-la, ele entra automaticamente no sistema.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Acesso a Empresas (para equipe interna) -->
                    <div id="access-fields" class="col-12" style="<?= in_array($editUser['role'] ?? '', ['attendant', 'whatsapp_agent', 'developer', 'analyst']) ? '' : 'display:none' ?>">
                        <hr class="my-2">
                        <h6 class="fw-medium mb-3" style="font-size:0.88rem"><i class="bi bi-shield-lock"></i> Acesso a Empresas</h6>
                        <p class="small text-muted mb-2">Selecione quais empresas este usuário pode visualizar nos módulos de Planejamento, Demandas e CRM. Se nenhuma for selecionada, ele só verá cards sem empresa.</p>
                        <div class="row g-2">
                            <?php
                            $allCompaniesAccess = (new Company())->getAll();
                            $userAccessIds = $editUser ? PlanningCard::getUserCompanyAccessIds($editUser['id']) : [];
                            foreach ($allCompaniesAccess as $c): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="company_access[]" value="<?= $c['id'] ?>" id="access_<?= $c['id'] ?>" <?= in_array($c['id'], $userAccessIds) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="access_<?= $c['id'] ?>"><?= escape($c['name']) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg"></i> <?= $editUser ? 'Atualizar' : 'Cadastrar' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCompanyFields() {
    const role = document.getElementById('role-select').value;
    const fields = document.getElementById('company-fields');
    const accessFields = document.getElementById('access-fields');
    fields.style.display = role === 'client' ? '' : 'none';
    const teamRoles = ['attendant', 'whatsapp_agent', 'developer', 'analyst'];
    accessFields.style.display = teamRoles.includes(role) ? '' : 'none';
}

function toggleNewCompany() {
    const select = document.getElementById('company-select');
    const field = document.getElementById('new-company-field');
    if (field) {
        field.style.display = select.value ? 'none' : '';
    }
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
