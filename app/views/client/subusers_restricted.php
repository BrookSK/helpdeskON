<?php $pageTitle = 'Minha Equipe - ON Solutions Helpdesk'; $currentPage = 'subusers'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Minha Equipe</h5>
            <small class="text-muted">Gerenciamento de usuários da empresa</small>
        </div>
    </div>

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-lock fs-1 text-muted"></i>
            <h6 class="mt-3">Acesso restrito</h6>
            <p class="text-muted" style="font-size:0.88rem">
                Esta funcionalidade está disponível apenas para o responsável da empresa.<br>
                Fale com o administrador do sistema para ativar este recurso na sua conta.
            </p>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
