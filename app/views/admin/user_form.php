<?php $pageTitle = ($editUser ? 'Editar' : 'Novo') . ' Usuário - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<style>
    /* Barra de ações fixa no rodapé da viewport: o botão Atualizar/Cadastrar
       fica sempre acessível, sem precisar rolar até o fim da página. */
    .user-form-actions {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background: #f8f9fa;
        padding: 12px 0;
        margin-top: 4px;
        border-top: 1px solid #e6e8ec;
    }
</style>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= $editUser ? 'Editar Usuário' : 'Novo Usuário' ?></h5>
            <small class="text-muted"><?= $editUser ? escape($editUser['name']) : 'Cadastrar novo usuário' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if ($editUser): ?>
            <a href="<?= baseUrl('users/activity/' . $editUser['id']) ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-clock-history"></i> Logins e Ações</a>
            <?php endif; ?>
            <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="alert alert-light border d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size:0.85rem">
        <i class="bi bi-info-circle text-primary"></i>
        <span>Os campos marcados com <span class="text-danger fw-bold">*</span> são obrigatórios. Escolha o <strong>Papel</strong> primeiro &mdash; os demais campos mudam conforme a função.</span>
    </div>

    <form action="<?= baseUrl($editUser ? 'users/update/' . $editUser['id'] : 'users/store') ?>" method="POST">
        <div class="row g-3">
            <!-- ===================== COLUNA ESQUERDA ===================== -->
            <div class="col-12 col-xl-6">
                <!-- Dados de acesso -->
                <div class="card mb-3">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-person-lines-fill"></i> Dados de acesso
                    </div>
                    <div class="card-body">
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
                            <div class="col-12">
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
                                <div id="role-help" class="alert alert-info d-flex align-items-start gap-2 py-2 px-3 mt-2 mb-0" style="font-size:0.82rem">
                                    <i class="bi bi-lightbulb mt-1"></i>
                                    <span id="role-help-text"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== Opções avançadas (recolhíveis) ===== -->
                <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-3 d-flex align-items-center justify-content-between" data-bs-toggle="collapse" data-bs-target="#advanced-options" aria-expanded="false" aria-controls="advanced-options">
                    <span><i class="bi bi-sliders"></i> Opções avançadas (telefonia e PIN)</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="advanced-options">
                <!-- Telefonia (Ramal SIP - Nvoip) -->
                <div class="card mb-3">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-telephone"></i> Telefonia (SIP - Nvoip)
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="bi bi-info-circle"></i> Cada operador deve ter um <strong>ramal SIP único</strong> na Nvoip.
                            Dois usuários com o mesmo ramal causam conflito de registro (a ligação não completa).
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Ramal SIP (Nvoip)</label>
                                <input type="text" name="sip_user" class="form-control" value="<?= escape($editUser['sip_user'] ?? '') ?>" placeholder="ex.: 148379001">
                                <small class="text-muted">Ramal próprio do usuário para o webphone. Deixe vazio para usar o ramal global.</small>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Senha SIP (Nvoip)</label>
                                <input type="password" name="sip_password" class="form-control" value="" placeholder="<?= !empty($editUser['sip_password']) ? '•••••••• (salva — deixe em branco para manter)' : 'senha SIP do ramal' ?>" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PIN de login (4 dígitos) — para qualquer usuário -->
                <?php $hasClientPin = !empty($editUser['client_pin']); ?>
                <div id="client-pin-field" class="card mb-3">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-key-fill"></i> PIN de acesso (login por PIN)
                    </div>
                    <div class="card-body">
                        <?php if ($editUser): ?>
                        <?php // EDIÇÃO: o admin não vê nem escolhe o PIN de outro usuário.
                              // Só pode disparar um link para o próprio usuário (re)definir
                              // o PIN, ou remover o PIN existente. ?>
                        <p class="small text-muted mb-2">
                            PIN de <strong>4 dígitos</strong> para o usuário entrar pelo botão
                            <em>Entrar com PIN</em>. Por segurança, o valor é visível apenas para o
                            próprio usuário. Você pode enviar um link para ele (re)definir o PIN.
                        </p>
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <span class="badge <?= $hasClientPin ? 'bg-success' : 'bg-secondary' ?>">
                                <i class="bi bi-<?= $hasClientPin ? 'check-circle' : 'dash-circle' ?>"></i>
                                <?= $hasClientPin ? 'PIN cadastrado' : 'Sem PIN' ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="<?= baseUrl('users/resendPinReset/' . (int)$editUser['id']) ?>"
                               class="btn btn-sm btn-outline-primary"
                               onclick="return confirm('Enviar ao usuário um link por email para redefinir o PIN de acesso?');">
                                <i class="bi bi-envelope"></i> Reenviar link de redefinição de PIN
                            </a>
                            <?php if ($hasClientPin): ?>
                            <div class="form-check ms-1">
                                <input class="form-check-input" type="checkbox" name="client_pin_remove" value="1" id="client-pin-remove">
                                <label class="form-check-label small" for="client-pin-remove">Remover o PIN (revoga o login por PIN)</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted d-block mt-2">O link expira em 24 horas e o valor do PIN nunca é exibido aqui.</small>
                        <?php else: ?>
                        <?php // CRIAÇÃO: opcionalmente define um PIN inicial. Se deixado em branco,
                              // o sistema gera um PIN automático que o usuário pode alterar depois. ?>
                        <p class="small text-muted mb-2">
                            PIN de <strong>4 dígitos</strong> para o usuário entrar pelo botão
                            <em>Entrar com PIN</em>. Opcional: deixe em branco para gerar um
                            automaticamente. O usuário poderá alterá-lo em "Minha Conta".
                        </p>
                        <label class="form-label fw-medium small">PIN inicial (4 dígitos)</label>
                        <input type="text" name="client_pin" class="form-control" maxlength="4"
                               inputmode="numeric" autocomplete="off" placeholder="Ex: 1234"
                               oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                        <small class="text-muted">Somente números. Em branco = PIN gerado automaticamente.</small>
                        <?php endif; ?>
                    </div>
                </div>
                </div><!-- /#advanced-options -->
            </div>

            <!-- ===================== COLUNA DIREITA ===================== -->
            <div class="col-12 col-xl-6">
                <!-- % de comissão (só para papel Comercial) -->
                <div id="commission-field" class="card mb-3" style="<?= ($editUser['role'] ?? '') === 'comercial' ? '' : 'display:none' ?>">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-cash-coin"></i> Comissões e créditos
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">% Comissão (Prospecção)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" max="100" name="commission_prospection_percent" class="form-control" value="<?= escape($editUser['commission_prospection_percent'] ?? '0') ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Trouxe o lead, mas outra pessoa fechou.</small>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">% Comissão (Fechamento)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" max="100" name="commission_closing_percent" class="form-control" value="<?= escape($editUser['commission_closing_percent'] ?? '0') ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Trouxe o lead E fechou ele mesmo.</small>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">% Comissão (legado)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" min="0" max="100" name="commission_percent" class="form-control" value="<?= escape($editUser['commission_percent'] ?? '0') ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Percentual geral (usado em cálculos anteriores).</small>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Créditos Apollo por dia</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="1" min="0" name="apollo_daily_credits" class="form-control" value="<?= escape($editUser['apollo_daily_credits'] ?? '0') ?>">
                                    <span class="input-group-text"><i class="bi bi-coin"></i></span>
                                </div>
                                <small class="text-muted">Máximo de liberações/enriquecimentos por dia. 0 = ilimitado. Reinicia no dia seguinte.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campos de empresa (só para clientes) -->
                <div id="company-fields" class="card mb-3" style="<?= ($editUser['role'] ?? 'client') !== 'client' ? 'display:none' : '' ?>">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-building"></i> Empresa
                    </div>
                    <div class="card-body">
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

                            <!-- Multi-Empresas: vínculos adicionais para o cliente -->
                            <?php
                            $allCompaniesLink = (new Company())->getAll();
                            $clientLinkedIds = $editUser ? PlanningCard::getUserCompanyAccessIds($editUser['id']) : [];
                            // Opções (excluindo a empresa principal, que já é selecionada acima)
                            $extraSelected = array_values(array_map('intval', $clientLinkedIds));
                            ?>
                            <div class="col-12">
                                <hr class="my-2">
                                <h6 class="fw-medium mb-1" style="font-size:0.86rem"><i class="bi bi-buildings"></i> Empresas adicionais (Multi-Empresas)</h6>
                                <p class="small text-muted mb-2">
                                    Opcional. Vincule este mesmo usuário a outras empresas. Ao usar <strong>Ver como</strong>,
                                    você poderá escolher qual empresa visualizar. A empresa selecionada acima é a principal.
                                </p>

                                <div id="extra-companies-list" class="d-flex flex-column gap-2">
                                    <?php if (!empty($extraSelected)): ?>
                                        <?php foreach ($extraSelected as $selId): ?>
                                        <div class="input-group input-group-sm extra-company-row">
                                            <select name="company_access[]" class="form-select form-select-sm extra-company-select">
                                                <option value="">Selecione uma empresa...</option>
                                                <?php foreach ($allCompaniesLink as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= $selId === (int)$c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="btn btn-outline-danger remove-extra-company" title="Remover"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <button type="button" id="add-extra-company" class="btn btn-outline-secondary btn-sm mt-2">
                                    <i class="bi bi-plus-lg"></i> Adicionar outra empresa
                                </button>

                                <!-- Template de linha (usado pelo JS) -->
                                <template id="extra-company-template">
                                    <div class="input-group input-group-sm extra-company-row">
                                        <select name="company_access[]" class="form-select form-select-sm extra-company-select">
                                            <option value="">Selecione uma empresa...</option>
                                            <?php foreach ($allCompaniesLink as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-danger remove-extra-company" title="Remover"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acesso a Empresas (para equipe interna) -->
                <div id="access-fields" class="card mb-3" style="<?= in_array($editUser['role'] ?? '', ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']) ? '' : 'display:none' ?>">
                    <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                        <i class="bi bi-shield-lock"></i> Acesso a Empresas
                    </div>
                    <div class="card-body">
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
                            <div class="col-sm-6">
                                <div class="form-check">
                                    <input class="form-check-input company-access-check" type="checkbox" name="company_access[]" value="<?= $c['id'] ?>" id="access_<?= $c['id'] ?>" <?= in_array($c['id'], $userAccessIds) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="access_<?= $c['id'] ?>"><?= escape($c['name']) ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== RODAPÉ / AÇÕES ===================== -->
            <div class="col-12">
                <?php if (!$editUser): ?>
                <div class="alert alert-info py-2" style="font-size:0.82rem">
                    <i class="bi bi-envelope"></i> Um email será enviado ao usuário com um link para definir a senha. Após defini-la, ele entra automaticamente no sistema.
                </div>
                <?php endif; ?>

                <div class="user-form-actions d-flex justify-content-end gap-2">
                    <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg"></i> <?= $editUser ? 'Atualizar' : 'Cadastrar' ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Explicação curta de cada papel (mostrada abaixo do seletor).
const ROLE_DESCRIPTIONS = {
    client: 'Cliente: pertence a uma empresa e abre demandas para ela. Vê apenas o que é da própria empresa.',
    attendant: 'Atendente: responde e resolve as demandas dos clientes. Você escolhe quais empresas ele enxerga.',
    developer: 'Desenvolvedor: equipe técnica interna. Acessa demandas das empresas que você liberar.',
    analyst: 'Analista: acompanha e analisa demandas. Acesso às empresas que você liberar.',
    comercial: 'Comercial: cuida de prospecção e vendas. Tem campos de comissão e créditos próprios.',
    marketing: 'Marketing: cuida de conteúdo e campanhas. Acesso interno, sem vínculo a uma empresa cliente.',
    whatsapp_agent: 'Agente WhatsApp: atende conversas pelo WhatsApp. Acesso às empresas que você liberar.',
    super_admin: 'Super Admin: acesso total ao sistema, incluindo o cadastro de usuários. Use com cuidado.'
};

function updateRoleHelp() {
    const sel = document.getElementById('role-select');
    const box = document.getElementById('role-help');
    const txt = document.getElementById('role-help-text');
    if (!sel || !box || !txt) return;
    const desc = ROLE_DESCRIPTIONS[sel.value];
    if (desc) {
        txt.textContent = desc;
        box.style.display = '';
    } else {
        box.style.display = 'none';
    }
}

function toggleCompanyFields() {
    updateRoleHelp();
    const role = document.getElementById('role-select').value;
    const fields = document.getElementById('company-fields');
    const accessFields = document.getElementById('access-fields');
    fields.style.display = role === 'client' ? '' : 'none';
    const teamRoles = ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial'];
    accessFields.style.display = teamRoles.includes(role) ? '' : 'none';

    const commissionField = document.getElementById('commission-field');
    if (commissionField) commissionField.style.display = role === 'comercial' ? '' : 'none';

    // PIN de login (por usuário): disponível para qualquer papel — sempre visível.
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

// ===== Empresas adicionais (Multi-Empresas) — selects dinâmicos =====
(function() {
    const list = document.getElementById('extra-companies-list');
    const addBtn = document.getElementById('add-extra-company');
    const template = document.getElementById('extra-company-template');
    if (!list || !addBtn || !template) return;

    const form = list.closest('form');
    const mainCompanySelect = document.getElementById('company-select');

    // Mensagem de aviso (criada sob demanda, logo após a lista)
    let warning = document.getElementById('extra-company-warning');
    function ensureWarning() {
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'extra-company-warning';
            warning.className = 'text-danger small mt-2';
            warning.style.display = 'none';
            warning.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Essa empresa já foi selecionada. Remova a duplicata para continuar.';
            list.insertAdjacentElement('afterend', warning);
        }
        return warning;
    }

    // Valida duplicatas entre os selects adicionais e contra a empresa principal.
    // Retorna true se estiver tudo certo (sem duplicatas).
    function validateDuplicates() {
        const selects = Array.from(list.querySelectorAll('.extra-company-select'));
        const seen = {};
        // Empresa principal (se houver) também não pode ser repetida nas adicionais.
        const mainVal = mainCompanySelect ? (mainCompanySelect.value || '') : '';

        let hasDuplicate = false;

        selects.forEach(function(sel) {
            sel.classList.remove('is-invalid');
            const v = sel.value || '';
            if (v === '') return; // linha vazia não conta como duplicata

            const isDupOfMain = (mainVal !== '' && v === mainVal);
            if (seen[v] || isDupOfMain) {
                hasDuplicate = true;
                sel.classList.add('is-invalid');
                // marca também a primeira ocorrência para deixar claro o par
                if (seen[v]) seen[v].classList.add('is-invalid');
            } else {
                seen[v] = sel;
            }
        });

        ensureWarning().style.display = hasDuplicate ? 'block' : 'none';
        return !hasDuplicate;
    }

    function addRow() {
        const clone = template.content.firstElementChild.cloneNode(true);
        list.appendChild(clone);
        validateDuplicates();
    }

    addBtn.addEventListener('click', addRow);

    // Revalida quando qualquer select adicional muda (delegação)
    list.addEventListener('change', function(e) {
        if (e.target.classList.contains('extra-company-select')) validateDuplicates();
    });

    // Se a empresa principal mudar, revalida (pode passar a colidir com uma adicional)
    if (mainCompanySelect) {
        mainCompanySelect.addEventListener('change', validateDuplicates);
    }

    // Remover linha (delegação de evento)
    list.addEventListener('click', function(e) {
        const btn = e.target.closest('.remove-extra-company');
        if (btn) {
            const row = btn.closest('.extra-company-row');
            if (row) row.remove();
            validateDuplicates();
        }
    });

    // Impede a atualização/cadastro enquanto houver empresa repetida
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateDuplicates()) {
                e.preventDefault();
                // Leva o aviso à vista e foca o primeiro select duplicado
                const firstInvalid = list.querySelector('.extra-company-select.is-invalid');
                if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    // Estado inicial (caso a página já venha com vínculos repetidos por algum motivo)
    validateDuplicates();
})();

// Estado inicial
document.addEventListener('DOMContentLoaded', function() {
    toggleSeeAll();
    updateRoleHelp();
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
