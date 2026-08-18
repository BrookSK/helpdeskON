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
                        <input type="text" name="phone" class="form-control" value="<?= escape($editUser['phone'] ?? '') ?>" placeholder="(00) 00000-0000" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')" onpaste="setTimeout(()=>{this.value=this.value.replace(/\D/g,'')},0)">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Papel *</label>
                        <select name="role" id="role-select" class="form-select" required onchange="toggleCompanyFields()">
                            <option value="client" <?= ($editUser['role'] ?? '') === 'client' ? 'selected' : '' ?>>Cliente</option>
                            <option value="attendant" <?= ($editUser['role'] ?? '') === 'attendant' ? 'selected' : '' ?>>Atendente</option>
                            <option value="developer" <?= ($editUser['role'] ?? '') === 'developer' ? 'selected' : '' ?>>Desenvolvedor</option>
                            <option value="analyst" <?= ($editUser['role'] ?? '') === 'analyst' ? 'selected' : '' ?>>Analista</option>
                            <option value="comercial" <?= ($editUser['role'] ?? '') === 'comercial' ? 'selected' : '' ?>>Comercial</option>
                            <option value="marketing" <?= ($editUser['role'] ?? '') === 'marketing' ? 'selected' : '' ?>>Marketing</option>
                            <option value="whatsapp_agent" <?= ($editUser['role'] ?? '') === 'whatsapp_agent' ? 'selected' : '' ?>>Agente WhatsApp</option>
                            <option value="super_admin" <?= ($editUser['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>

                    <!-- % de comissão (só para papel Comercial) -->
                    <div id="commission-field" class="col-sm-6" style="<?= ($editUser['role'] ?? '') === 'comercial' ? '' : 'display:none' ?>">
                        <label class="form-label fw-medium small">% de Comissão</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" min="0" max="100" name="commission_percent" class="form-control" value="<?= escape($editUser['commission_percent'] ?? '0') ?>" placeholder="Ex: 10">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Percentual sobre o valor dos leads convertidos por este usuário.</small>
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
                    <div id="access-fields" class="col-12" style="<?= in_array($editUser['role'] ?? '', ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']) ? '' : 'display:none' ?>">
                        <hr class="my-2">
                        <h6 class="fw-medium mb-3" style="font-size:0.88rem"><i class="bi bi-shield-lock"></i> Acesso a Empresas</h6>
                        <p class="small text-muted mb-2">Selecione quais empresas este usuário pode visualizar nos módulos de Planejamento, Demandas e CRM. Se nenhuma for selecionada, ele só verá cards sem empresa.</p>

                        <div class="form-check mb-3 p-2 rounded d-flex align-items-center gap-2" style="background:#e0f7f4;margin-left:0;padding-left:0.75rem !important;">
                            <input class="form-check-input mt-0 ms-0" type="checkbox" name="see_all_companies" value="1" id="seeAllCompanies" <?= !empty($editUser['see_all_companies']) ? 'checked' : '' ?> onchange="toggleSeeAll()" style="float:none;">
                            <label class="form-check-label small fw-medium mb-0" for="seeAllCompanies">
                                Sempre ver todas as empresas (inclusive as futuras)
                            </label>
                        </div>

                        <div class="row g-2" id="company-access-list">
                            <?php
                            $allCompaniesAccess = (new Company())->getAll();
                            $userAccessIds = $editUser ? PlanningCard::getUserCompanyAccessIds($editUser['id']) : [];
                            foreach ($allCompaniesAccess as $c): ?>
                            <div class="col-sm-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input company-access-check" type="checkbox" name="company_access[]" value="<?= $c['id'] ?>" id="access_<?= $c['id'] ?>" <?= in_array($c['id'], $userAccessIds) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="access_<?= $c['id'] ?>"><?= escape($c['name']) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Ramal SIP (Nvoip) — para webphone -->
                    <div id="sip-fields" class="col-12" style="<?= in_array($editUser['role'] ?? '', ['super_admin', 'comercial']) ? '' : 'display:none' ?>">
                        <hr class="my-2">
                        <h6 class="fw-medium mb-2" style="font-size:0.88rem"><i class="bi bi-telephone"></i> Ramal SIP (Nvoip)</h6>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle"></i> Cada operador deve ter um <strong>ramal SIP único</strong> no Nvoip. Dois usuários com o mesmo ramal causam conflito de registro (a ligação não completa).
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Ramal SIP (Nvoip)</label>
                                <input type="text" name="nvoip_sip_user" class="form-control form-control-sm" value="<?= escape($editUser['nvoip_sip_user'] ?? '') ?>" placeholder="ex.: 148379002">
                                <div class="form-text small">Ramal próprio do usuário para o webphone.<br>Deixe vazio para usar o ramal global.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Senha SIP (Nvoip)</label>
                                <input type="password" name="nvoip_sip_password" class="form-control form-control-sm" value="" placeholder="<?= !empty($editUser['nvoip_sip_password']) ? '•••••••• (salvo — deixe em branco para manter)' : 'senha SIP do ramal' ?>" autocomplete="new-password">
                            </div>
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
    const teamRoles = ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial'];
    accessFields.style.display = teamRoles.includes(role) ? '' : 'none';

    const commissionField = document.getElementById('commission-field');
    if (commissionField) commissionField.style.display = role === 'comercial' ? '' : 'none';

    const sipFields = document.getElementById('sip-fields');
    if (sipFields) sipFields.style.display = ['super_admin', 'comercial'].includes(role) ? '' : 'none';
}

function toggleNewCompany() {
    const select = document.getElementById('company-select');
    const field = document.getElementById('new-company-field');
    if (field) {
        field.style.display = select.value ? 'none' : '';
    }
}

function toggleSeeAll() {
    const seeAll = document.getElementById('seeAllCompanies');
    const list = document.getElementById('company-access-list');
    if (!seeAll || !list) return;
    const checks = list.querySelectorAll('.company-access-check');
    checks.forEach(function(chk) { chk.disabled = seeAll.checked; });
    list.style.opacity = seeAll.checked ? '0.5' : '1';
}

// Estado inicial
document.addEventListener('DOMContentLoaded', toggleSeeAll);
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
