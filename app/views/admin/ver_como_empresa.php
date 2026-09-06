<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Ver como <?= escape($targetUser['name']) ?></h5>
            <small class="text-muted">Selecione a empresa que deseja visualizar</small>
        </div>
        <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="bi bi-info-circle"></i>
                Este usuário está vinculado a <strong><?= count($companies) ?></strong> empresas.
                Escolha em qual contexto deseja acessar o Helpdesk. Você continuará como o mesmo usuário
                (<?= escape($targetUser['email']) ?>).
            </p>

            <div class="row g-3">
                <?php foreach ($companies as $c): ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <a href="<?= baseUrl('login/verComoEmpresa/' . $targetUser['id'] . '/' . $c['id']) ?>"
                       class="text-decoration-none">
                        <div class="border rounded-3 p-3 h-100 d-flex align-items-center gap-3 company-choice">
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:46px;height:46px;">
                                <i class="bi bi-building" style="font-size:1.2rem;color:#00BFA6;"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-medium text-dark text-truncate"><?= escape($c['name']) ?></div>
                                <?php if (!empty($c['is_primary'])): ?>
                                    <span class="badge bg-success" style="font-size:0.65rem">Empresa principal</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark" style="font-size:0.65rem">Vínculo adicional</span>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.company-choice { transition: all .15s ease; cursor: pointer; }
.company-choice:hover { border-color: #00BFA6 !important; box-shadow: 0 2px 10px rgba(0,191,166,0.15); }
</style>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
