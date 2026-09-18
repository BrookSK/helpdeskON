<?php $pageTitle = 'Analisar ' . escape($viewUser['name']) . ' - ON Solutions Helpdesk'; $currentPage = 'users'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Iniciais para o avatar
$initials = '';
$parts = preg_split('/\s+/', trim($viewUser['name'] ?? ''));
foreach (array_slice($parts, 0, 2) as $p) {
    if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
}
if ($initials === '') $initials = '?';

$isComercial = ($viewUser['role'] ?? '') === 'comercial';
$isClient    = ($viewUser['role'] ?? '') === 'client';

// Helper local para exibir um valor ou um traço quando vazio
$val = function ($v) {
    $v = trim((string)($v ?? ''));
    return $v === ''
        ? '<span class="text-muted">—</span>'
        : escape($v);
};
?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-file-earmark-person"></i> Ficha do Usuário</h5>
            <small class="text-muted">Consulta dos dados cadastrados e empresas vinculadas</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('users/activity/' . $viewUser['id']) ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-clock-history"></i> Logins e Ações</a>
            <a href="<?= baseUrl('users') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Cabeçalho da ficha: avatar, nome, papel e status (full-width) -->
    <div class="card mb-3">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-semibold"
                 style="width:56px;height:56px;background:#00BFA6;font-size:1.25rem;flex:0 0 auto;">
                <?= escape($initials) ?>
            </div>
            <div class="flex-grow-1">
                <div class="h5 mb-1"><?= escape($viewUser['name']) ?></div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="badge bg-light text-dark"><i class="bi bi-person-badge"></i> <?= escape(roleLabel($viewUser['role'])) ?></span>
                    <?= !empty($viewUser['is_active'])
                        ? '<span class="badge bg-success">Ativo</span>'
                        : '<span class="badge bg-secondary">Inativo</span>' ?>
                    <?php if ($isClient && !empty($viewUser['is_company_owner'])): ?>
                        <span class="badge bg-info text-dark"><i class="bi bi-key"></i> Responsável da empresa</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ms-auto">
                <a href="<?= baseUrl('users/edit/' . $viewUser['id']) ?>" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-pencil-square"></i> Editar dados do usuário
                </a>
            </div>
        </div>
    </div>

    <!-- Cards lado a lado -->
    <div class="row g-3">
        <!-- ===================== COLUNA ESQUERDA: Dados ===================== -->
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-medium" style="font-size:0.9rem">
                    <i class="bi bi-person-lines-fill"></i> Dados do usuário
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="text-muted small">Nome</div>
                            <div><?= $val($viewUser['name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted small">Email</div>
                            <div><?= $val($viewUser['email']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted small">Telefone</div>
                            <div><?= $val($viewUser['phone']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted small">Papel</div>
                            <div><?= escape(roleLabel($viewUser['role'])) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted small">Ramal SIP (Nvoip)</div>
                            <div><?= $val($viewUser['sip_user']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted small">Senha SIP (Nvoip)</div>
                            <div>
                                <?= !empty($viewUser['sip_password'])
                                    ? '<span class="text-muted"><i class="bi bi-lock-fill"></i> Configurada</span>'
                                    : '<span class="text-muted">—</span>' ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($isComercial): ?>
                    <hr class="my-3">
                    <h6 class="fw-medium mb-2" style="font-size:0.86rem"><i class="bi bi-cash-coin"></i> Comissões e créditos (Comercial)</h6>
                    <div class="row g-3">
                        <div class="col-6 col-sm-3">
                            <div class="text-muted small">% Prospecção</div>
                            <div><?= escape(number_format((float)($viewUser['commission_prospection_percent'] ?? 0), 2, ',', '.')) ?>%</div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="text-muted small">% Fechamento</div>
                            <div><?= escape(number_format((float)($viewUser['commission_closing_percent'] ?? 0), 2, ',', '.')) ?>%</div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="text-muted small">% Legado</div>
                            <div><?= escape(number_format((float)($viewUser['commission_percent'] ?? 0), 2, ',', '.')) ?>%</div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="text-muted small">Créditos Apollo/dia</div>
                            <div><?= (int)($viewUser['apollo_daily_credits'] ?? 0) === 0 ? 'Ilimitado' : (int)$viewUser['apollo_daily_credits'] ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===================== COLUNA DIREITA: Empresas ===================== -->
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-medium d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                    <span><i class="bi bi-buildings"></i> Empresas vinculadas</span>
                    <span class="badge bg-light text-dark"><?= count($linkedCompanies) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($linkedCompanies)): ?>
                        <div class="text-muted small py-2">Este usuário não está vinculado a nenhuma empresa.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($linkedCompanies as $c): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    <i class="bi bi-building text-muted"></i>
                                    <a href="<?= baseUrl('companies/details/' . $c['id']) ?>" class="text-decoration-none">
                                        <?= escape($c['name']) ?>
                                    </a>
                                </span>
                                <?php if (!empty($c['is_primary'])): ?>
                                    <span class="badge bg-primary"><i class="bi bi-star-fill"></i> Principal</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">Adicional</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
