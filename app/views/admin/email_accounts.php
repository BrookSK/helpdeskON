<?php $pageTitle = 'Contas de E-mail - Prospecção'; $currentPage = 'settings'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-envelope-at"></i> Contas de E-mail</h5>
            <small class="text-muted">Gerencie as contas SMTP para prospecção por e-mail</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('settings') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Configurações</a>
            <button class="btn btn-sm btn-primary" onclick="openAccountModal()"><i class="bi bi-plus-lg"></i> Nova Conta</button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= escape($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Servidor Padrão -->
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0" style="font-size:0.85rem;"><i class="bi bi-server"></i> Servidor Padrão</h6>
            <small class="text-muted">Preenche automaticamente ao criar novas contas</small>
        </div>
        <div class="card-body py-2">
            <form method="POST" action="<?= baseUrl('settings/saveEmailDefaults') ?>" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Servidor SMTP</label>
                    <input type="text" name="prospection_smtp_host" class="form-control form-control-sm" value="<?= escape($defaults['prospection_smtp_host'] ?? '') ?>" placeholder="smtp.seudominio.com">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Porta</label>
                    <input type="number" name="prospection_smtp_port" class="form-control form-control-sm" value="<?= escape($defaults['prospection_smtp_port'] ?? '587') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Criptografia</label>
                    <select name="prospection_smtp_encryption" class="form-select form-select-sm">
                        <option value="tls" <?= ($defaults['prospection_smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                        <option value="ssl" <?= ($defaults['prospection_smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                        <option value="none" <?= ($defaults['prospection_smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma (25)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg"></i> Salvar Padrão</button>
                </div>
                <div class="col-md-4 mt-2">
                    <label class="form-label small mb-0">Servidor IMAP</label>
                    <input type="text" name="prospection_imap_host" class="form-control form-control-sm" value="<?= escape($defaults['prospection_imap_host'] ?? '') ?>" placeholder="imap.seudominio.com">
                </div>
                <div class="col-md-2 mt-2">
                    <label class="form-label small mb-0">Porta IMAP</label>
                    <input type="number" name="prospection_imap_port" class="form-control form-control-sm" value="<?= escape($defaults['prospection_imap_port'] ?? '993') ?>">
                </div>
                <div class="col-md-3 mt-2">
                    <label class="form-label small mb-0">Criptografia IMAP</label>
                    <select name="prospection_imap_encryption" class="form-select form-select-sm">
                        <option value="ssl" <?= ($defaults['prospection_imap_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL (993)</option>
                        <option value="tls" <?= ($defaults['prospection_imap_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (143)</option>
                        <option value="none" <?= ($defaults['prospection_imap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma (143)</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>E-mail</th>
                            <th>Nome de Exibição</th>
                            <th>Servidor SMTP</th>
                            <th>Usuários Vinculados</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accounts)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma conta cadastrada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($accounts as $acc): ?>
                        <?php $linkedUsers = (new EmailAccount())->getLinkedUsers($acc['id']); ?>
                        <tr>
                            <td class="fw-medium"><?= escape($acc['email']) ?></td>
                            <td><?= escape($acc['display_name'] ?: '—') ?></td>
                            <td><small class="text-muted"><?= escape($acc['smtp_host']) ?>:<?= $acc['smtp_port'] ?> (<?= strtoupper($acc['smtp_encryption']) ?>)</small></td>
                            <td>
                                <?php if (empty($linkedUsers)): ?>
                                <span class="text-muted small">Nenhum</span>
                                <?php else: ?>
                                <?php foreach ($linkedUsers as $lu): ?>
                                <span class="badge bg-light text-dark border" style="font-size:0.7rem;"><?= escape($lu['name']) ?></span>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($acc['is_active']): ?>
                                <span class="badge bg-success">Ativa</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="editAccount(<?= $acc['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="<?= baseUrl('settings/deleteEmailAccount/' . $acc['id']) ?>" class="d-inline" onsubmit="return confirm('Excluir esta conta?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cadastro/Edição -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= baseUrl('settings/saveEmailAccount') ?>" enctype="multipart/form-data">
                <input type="hidden" name="id" id="acc-id">
                <div class="modal-header">
                    <h6 class="modal-title" id="acc-modal-title"><i class="bi bi-envelope-at"></i> Nova Conta de E-mail</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">E-mail *</label>
                            <input type="email" name="email" id="acc-email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Nome de Exibição</label>
                            <input type="text" name="display_name" id="acc-display-name" class="form-control form-control-sm" placeholder="Ex: Comercial ON Solutions">
                        </div>

                        <div class="col-12"><hr class="my-1"><small class="text-muted fw-medium">Configuração SMTP</small></div>

                        <div class="col-md-5">
                            <label class="form-label small fw-medium">Servidor SMTP *</label>
                            <input type="text" name="smtp_host" id="acc-smtp-host" class="form-control form-control-sm" required placeholder="smtp.seudominio.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Porta</label>
                            <input type="number" name="smtp_port" id="acc-smtp-port" class="form-control form-control-sm" value="587">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Criptografia</label>
                            <select name="smtp_encryption" id="acc-smtp-encryption" class="form-select form-select-sm">
                                <option value="tls">TLS (587)</option>
                                <option value="ssl">SSL (465)</option>
                                <option value="none">Nenhuma (25)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Usuário SMTP *</label>
                            <input type="text" name="smtp_username" id="acc-smtp-username" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Senha SMTP *</label>
                            <input type="password" name="smtp_password" id="acc-smtp-password" class="form-control form-control-sm" placeholder="Deixe vazio para manter a atual (edição)">
                        </div>

                        <div class="col-12"><hr class="my-1"><small class="text-muted fw-medium">Configuração IMAP (leitura de e-mails)</small></div>

                        <div class="col-md-5">
                            <label class="form-label small fw-medium">Servidor IMAP</label>
                            <input type="text" name="imap_host" id="acc-imap-host" class="form-control form-control-sm" placeholder="imap.seudominio.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Porta</label>
                            <input type="number" name="imap_port" id="acc-imap-port" class="form-control form-control-sm" value="993">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Criptografia</label>
                            <select name="imap_encryption" id="acc-imap-encryption" class="form-select form-select-sm">
                                <option value="ssl">SSL (993)</option>
                                <option value="tls">TLS (143)</option>
                                <option value="none">Nenhuma (143)</option>
                            </select>
                        </div>

                        <div class="col-12"><hr class="my-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted fw-medium"><i class="bi bi-pen"></i> Assinatura de e-mail (deste domínio)</small>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="signature_enabled" id="acc-sig-enabled" value="1" checked>
                                    <label class="form-check-label small" for="acc-sig-enabled">Usar assinatura</label>
                                </div>
                            </div>
                            <small class="text-muted">Se ficar vazio, usa a assinatura padrão do sistema. Preencha para o e-mail deste domínio sair com a assinatura própria (ex.: LRV Web).</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Nome na assinatura</label>
                            <input type="text" name="signature_name" id="acc-sig-name" class="form-control form-control-sm" placeholder="Ex: Lucas Vacari">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Cargo</label>
                            <input type="text" name="signature_role" id="acc-sig-role" class="form-control form-control-sm" placeholder="Ex: Consultor comercial">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Empresa</label>
                            <input type="text" name="signature_company" id="acc-sig-company" class="form-control form-control-sm" placeholder="Ex: LRV Web">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Site</label>
                            <input type="text" name="signature_site" id="acc-sig-site" class="form-control form-control-sm" placeholder="www.lrvweb.com.br">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">E-mail de contato</label>
                            <input type="text" name="signature_email" id="acc-sig-email" class="form-control form-control-sm" placeholder="contato@lrvweb.com.br">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Telefone</label>
                            <input type="text" name="signature_phone" id="acc-sig-phone" class="form-control form-control-sm" placeholder="(11) 90000-0000">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Linha final (tagline)</label>
                            <input type="text" name="signature_tagline" id="acc-sig-tagline" class="form-control form-control-sm" placeholder="Ex: Sites, sistemas e soluções digitais.">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Cor dos links</label>
                            <input type="color" name="signature_color" id="acc-sig-color" class="form-control form-control-sm form-control-color" value="#00997D" style="height:31px;">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Logo da assinatura</label>
                            <input type="file" name="signature_logo" id="acc-sig-logo" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted">PNG/JPG/SVG até 2MB. Deixe vazio para manter a atual.</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div id="acc-sig-logo-preview" class="small text-muted"></div>
                        </div>

                        <div class="col-12"><hr class="my-1"><small class="text-muted fw-medium">Usuários que podem usar esta conta</small></div>

                        <div class="col-12">
                            <select name="users[]" id="acc-users" class="form-select form-select-sm" multiple size="5" style="min-height:120px;">
                                <?php foreach ($allUsers as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?> (<?= roleLabel($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Segure Ctrl para selecionar vários</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
let accountModal = null;

function getAccountModal() {
    if (!accountModal) accountModal = new bootstrap.Modal(document.getElementById('accountModal'));
    return accountModal;
}

function openAccountModal() {
    document.getElementById('acc-id').value = '';
    document.getElementById('acc-email').value = '';
    document.getElementById('acc-display-name').value = '';
    document.getElementById('acc-smtp-host').value = '<?= escape($defaults['prospection_smtp_host'] ?? '') ?>';
    document.getElementById('acc-smtp-port').value = '<?= escape($defaults['prospection_smtp_port'] ?? '587') ?>';
    document.getElementById('acc-smtp-encryption').value = '<?= escape($defaults['prospection_smtp_encryption'] ?? 'tls') ?>';
    document.getElementById('acc-smtp-username').value = '';
    document.getElementById('acc-smtp-password').value = '';
    document.getElementById('acc-smtp-password').required = true;
    document.getElementById('acc-imap-host').value = '<?= escape($defaults['prospection_imap_host'] ?? '') ?>';
    document.getElementById('acc-imap-port').value = '<?= escape($defaults['prospection_imap_port'] ?? '993') ?>';
    document.getElementById('acc-imap-encryption').value = '<?= escape($defaults['prospection_imap_encryption'] ?? 'ssl') ?>';
    Array.from(document.getElementById('acc-users').options).forEach(o => o.selected = false);
    // Assinatura (novo: limpa)
    document.getElementById('acc-sig-enabled').checked = true;
    ['name','role','company','site','email','phone','tagline'].forEach(k => { const el = document.getElementById('acc-sig-'+k); if (el) el.value = ''; });
    document.getElementById('acc-sig-color').value = '#00997D';
    document.getElementById('acc-sig-logo').value = '';
    document.getElementById('acc-sig-logo-preview').innerHTML = '';
    document.getElementById('acc-modal-title').textContent = 'Nova Conta de E-mail';
    getAccountModal().show();
}

function editAccount(id) {
    fetch(`${BASE}settings/getEmailAccount/${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.error) { alert(d.error); return; }
            const a = d.account;
            document.getElementById('acc-id').value = a.id;
            document.getElementById('acc-email').value = a.email || '';
            document.getElementById('acc-display-name').value = a.display_name || '';
            document.getElementById('acc-smtp-host').value = a.smtp_host || '';
            document.getElementById('acc-smtp-port').value = a.smtp_port || '587';
            document.getElementById('acc-smtp-encryption').value = a.smtp_encryption || 'tls';
            document.getElementById('acc-smtp-username').value = a.smtp_username || '';
            document.getElementById('acc-smtp-password').value = '';
            document.getElementById('acc-smtp-password').required = false;
            // IMAP
            document.getElementById('acc-imap-host').value = a.imap_host || '';
            document.getElementById('acc-imap-port').value = a.imap_port || '993';
            document.getElementById('acc-imap-encryption').value = a.imap_encryption || 'ssl';
            // Assinatura da conta
            document.getElementById('acc-sig-enabled').checked = Number(a.signature_enabled) !== 0;
            document.getElementById('acc-sig-name').value = a.signature_name || '';
            document.getElementById('acc-sig-role').value = a.signature_role || '';
            document.getElementById('acc-sig-company').value = a.signature_company || '';
            document.getElementById('acc-sig-site').value = a.signature_site || '';
            document.getElementById('acc-sig-email').value = a.signature_email || '';
            document.getElementById('acc-sig-phone').value = a.signature_phone || '';
            document.getElementById('acc-sig-tagline').value = a.signature_tagline || '';
            document.getElementById('acc-sig-color').value = a.signature_color || '#00997D';
            document.getElementById('acc-sig-logo').value = '';
            document.getElementById('acc-sig-logo-preview').innerHTML = a.signature_logo
                ? ('<img src="' + BASE + a.signature_logo + '" style="max-height:40px;border:1px solid #eee;border-radius:6px;padding:2px;"> <span class="text-success">logo atual</span>')
                : '<span class="text-muted">sem logo</span>';
            // Marcar usuários vinculados
            const ids = (a.linked_users || []).map(String);
            Array.from(document.getElementById('acc-users').options).forEach(o => o.selected = ids.includes(o.value));
            document.getElementById('acc-modal-title').textContent = 'Editar Conta de E-mail';
            getAccountModal().show();
        });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
