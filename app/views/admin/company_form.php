<?php $pageTitle = ($editCompany ? 'Editar' : 'Nova') . ' Empresa - ON Solutions Helpdesk'; $currentPage = 'companies'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= $editCompany ? 'Editar Empresa' : 'Nova Empresa' ?></h5>
            <small class="text-muted"><?= $editCompany ? escape($editCompany['name']) : 'Cadastrar nova empresa cliente' ?></small>
        </div>
        <a href="<?= baseUrl('companies') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card" style="max-width:600px">
        <div class="card-body">
            <form action="<?= baseUrl($editCompany ? 'companies/update/' . $editCompany['id'] : 'companies/store') ?>" method="POST">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium small">Nome da Empresa *</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($editCompany['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">CNPJ / CPF</label>
                        <input type="text" name="document" class="form-control" value="<?= escape($editCompany['document'] ?? '') ?>" placeholder="00.000.000/0001-00">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium small">Telefone</label>
                        <input type="text" name="phone" class="form-control" value="<?= escape($editCompany['phone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium small">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= escape($editCompany['email'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-medium small"><i class="bi bi-whatsapp text-success"></i> Grupo de WhatsApp da empresa</label>
                        <select name="whatsapp_group_jid" class="form-select">
                            <option value="">Nenhum grupo vinculado</option>
                            <?php foreach (($whatsappGroups ?? []) as $g): ?>
                            <option value="<?= escape($g['remote_jid']) ?>" <?= ($editCompany['whatsapp_group_jid'] ?? '') === $g['remote_jid'] ? 'selected' : '' ?>>
                                <?= escape($g['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Grupos conectados à plataforma. As notificações de homologação e atualizações da empresa serão enviadas neste grupo.</small>
                    </div>

                    <?php if (!$editCompany): ?>
                    <div class="col-12">
                        <hr class="my-2">
                        <h6 class="fw-medium mb-1" style="font-size:0.88rem"><i class="bi bi-person-badge"></i> Responsável (acesso ao painel)</h6>
                        <p class="small text-muted mb-3">Será criado um usuário responsável com acesso ao painel do cliente. Ele receberá um email para definir a senha e poderá criar usuários, abrir demandas etc.</p>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Nome do responsável</label>
                                <input type="text" name="owner_name" class="form-control form-control-sm" placeholder="Nome do responsável">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium small">Email do responsável</label>
                                <input type="email" name="owner_email" class="form-control form-control-sm" placeholder="Se vazio, usa o email da empresa">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg"></i> <?= $editCompany ? 'Atualizar' : 'Cadastrar' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
