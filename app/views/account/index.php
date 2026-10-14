<?php $pageTitle = 'Minha Conta - ON Solutions Helpdesk'; $currentPage = 'account'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minha Conta</h5>
            <small class="text-muted">Gerencie seus dados pessoais</small>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Dados pessoais -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-person"></i> Dados Pessoais</h6>
                </div>
                <div class="card-body">
                    <form action="<?= baseUrl('account/update') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nome</label>
                            <input type="text" name="name" class="form-control" value="<?= escape($userData['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= escape($userData['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Telefone</label>
                            <input type="text" name="phone" class="form-control" value="<?= escape($userData['phone'] ?? '') ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Papel</label>
                            <input type="text" class="form-control" value="<?= roleLabel($userData['role']) ?>" disabled>
                        </div>
                        <?php
                        $accountCompany = null;
                        if (!empty($userData['company_id'])) {
                            $accountCompany = (new Company())->findById($userData['company_id']);
                        }
                        if ($accountCompany): ?>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Empresa</label>
                            <input type="text" class="form-control" value="<?= escape($accountCompany['name']) ?>" disabled>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Membro desde</label>
                            <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($userData['created_at'])) ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Salvar Alterações
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-lock"></i> Alterar Senha</h6>
                </div>
                <div class="card-body">
                    <form action="<?= baseUrl('account/changePassword') ?>" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Senha Atual</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nova Senha</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Confirmar Nova Senha</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-lock"></i> Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php // ===== PIN de acesso — para qualquer usuário ===== ?>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-key"></i> PIN de Acesso</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Use este PIN de 4 dígitos para entrar rapidamente pela opção
                        <strong>Entrar com PIN</strong> na tela de login, sem digitar email e senha.
                    </p>
                    <?php $accHasPin = !empty($userData['client_pin']); ?>
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <span class="text-muted small">PIN atual:</span>
                        <?php if ($accHasPin): ?>
                        <?php // Começa oculto; o olhinho revela/oculta o próprio PIN do usuário. ?>
                        <span id="account-pin-value"
                              class="badge bg-light text-dark border"
                              data-pin="<?= escape($userData['client_pin']) ?>"
                              style="font-size:1rem;letter-spacing:3px;padding:6px 12px;">
                            ••••
                        </span>
                        <button type="button" id="account-pin-toggle"
                                class="btn btn-sm btn-outline-secondary"
                                onclick="toggleAccountPin()"
                                aria-label="Mostrar ou ocultar PIN" aria-pressed="false" title="Mostrar PIN">
                            <i class="bi bi-eye-slash" id="account-pin-icon"></i>
                        </button>
                        <?php else: ?>
                        <span class="badge bg-secondary">Nenhum PIN definido</span>
                        <?php endif; ?>
                    </div>
                    <?php // Com PIN já definido, o formulário fica recolhido atrás de um
                          // botão "Alterar PIN" — a barra de escrita só aparece ao clicar,
                          // deixando a aba mais limpa. Sem PIN, o campo já vem aberto para
                          // facilitar a definição inicial. ?>
                    <?php if ($accHasPin): ?>
                    <button type="button" id="account-pin-change-btn"
                            class="btn btn-outline-primary w-100"
                            onclick="showAccountPinForm()">
                        <i class="bi bi-key"></i> Alterar PIN
                    </button>
                    <?php endif; ?>
                    <form action="<?= baseUrl('account/updateClientPin') ?>" method="POST"
                          id="account-pin-form" <?= $accHasPin ? 'style="display:none"' : '' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Novo PIN (4 dígitos)</label>
                            <input type="text" name="client_pin" id="account-pin-input" class="form-control" maxlength="4" inputmode="numeric"
                                   autocomplete="off" placeholder="Ex: 1234" required
                                   oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                            <small class="text-muted">Somente números.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary flex-grow-1">
                                <i class="bi bi-key"></i> <?= $accHasPin ? 'Salvar novo PIN' : 'Definir PIN' ?>
                            </button>
                            <?php if ($accHasPin): ?>
                            <button type="button" id="account-pin-cancel-btn"
                                    class="btn btn-outline-secondary"
                                    onclick="hideAccountPinForm()">
                                Cancelar
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Olhinho do PIN em "Minha Conta": alterna entre PIN oculto (••••) e visível.
// O valor real fica em data-pin e só é escrito na tela ao revelar.
function toggleAccountPin() {
    var span = document.getElementById('account-pin-value');
    var icon = document.getElementById('account-pin-icon');
    var btn = document.getElementById('account-pin-toggle');
    if (!span || !icon || !btn) return;
    var revealed = btn.getAttribute('aria-pressed') === 'true';
    if (revealed) {
        span.textContent = '••••';
        icon.className = 'bi bi-eye-slash';
        btn.setAttribute('aria-pressed', 'false');
        btn.title = 'Mostrar PIN';
    } else {
        span.textContent = span.getAttribute('data-pin') || '••••';
        icon.className = 'bi bi-eye';
        btn.setAttribute('aria-pressed', 'true');
        btn.title = 'Ocultar PIN';
    }
}

// Alterar PIN (quando já existe um): o campo começa recolhido e só aparece
// ao clicar em "Alterar PIN". "Cancelar" recolhe de novo e limpa o que foi digitado.
function showAccountPinForm() {
    var btn = document.getElementById('account-pin-change-btn');
    var form = document.getElementById('account-pin-form');
    var input = document.getElementById('account-pin-input');
    if (!form) return;
    if (btn) btn.style.display = 'none';
    form.style.display = '';
    if (input) input.focus();
}

function hideAccountPinForm() {
    var btn = document.getElementById('account-pin-change-btn');
    var form = document.getElementById('account-pin-form');
    var input = document.getElementById('account-pin-input');
    if (!form) return;
    form.style.display = 'none';
    if (input) input.value = '';
    if (btn) btn.style.display = '';
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
