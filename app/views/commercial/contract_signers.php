<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Signatários da empresa</h5>
            <small class="text-muted">Representantes que podem assinar os contratos (lado contratada)</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('contract') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Contratos</a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#signerModal" onclick="resetSignerForm()"><i class="bi bi-plus-lg"></i> Novo signatário</button>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Cargo</th><th>Padrão</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <?php if (empty($signers)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum signatário cadastrado.</td></tr>
                        <?php else: foreach ($signers as $s): ?>
                        <tr>
                            <td class="fw-medium"><?= escape($s['name']) ?></td>
                            <td><?= escape($s['email']) ?></td>
                            <td><?= escape($s['phone'] ?? '—') ?></td>
                            <td><small class="text-muted"><?= escape($s['role_label'] ?? '—') ?></small></td>
                            <td><?= ((int)$s['is_default'] === 1) ? '<span class="badge bg-primary">Padrão</span>' : '' ?></td>
                            <td><?= ((int)$s['active'] === 1) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary py-0 px-2" onclick='editSigner(<?= json_encode($s, JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="toggleSigner(<?= (int)$s['id'] ?>)"><?= ((int)$s['active'] === 1) ? 'Desativar' : 'Ativar' ?></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="signerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Signatário da empresa</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="s-id">
        <div class="mb-2"><label class="form-label small">Nome *</label><input id="s-name" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small">E-mail *</label><input id="s-email" type="email" class="form-control form-control-sm"></div>
        <div class="row g-2">
          <div class="col-7"><label class="form-label small">Telefone (WhatsApp)</label><input id="s-phone" class="form-control form-control-sm"></div>
          <div class="col-5"><label class="form-label small">Cargo</label><input id="s-role" class="form-control form-control-sm" placeholder="Sócio-adm."></div>
        </div>
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="s-default"><label class="form-check-label small" for="s-default">Pré-selecionar ao enviar p/ assinatura</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" onclick="saveSigner()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
function resetSignerForm() {
    document.getElementById('s-id').value = '';
    ['s-name','s-email','s-phone','s-role'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('s-default').checked = false;
}
function editSigner(s) {
    document.getElementById('s-id').value = s.id;
    document.getElementById('s-name').value = s.name || '';
    document.getElementById('s-email').value = s.email || '';
    document.getElementById('s-phone').value = s.phone || '';
    document.getElementById('s-role').value = s.role_label || '';
    document.getElementById('s-default').checked = String(s.is_default) === '1';
    new bootstrap.Modal(document.getElementById('signerModal')).show();
}
async function saveSigner() {
    const id = document.getElementById('s-id').value;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('name', document.getElementById('s-name').value.trim());
    fd.append('email', document.getElementById('s-email').value.trim());
    fd.append('phone', document.getElementById('s-phone').value.trim());
    fd.append('role_label', document.getElementById('s-role').value.trim());
    if (document.getElementById('s-default').checked) fd.append('is_default', '1');
    const url = id ? `${BASE}contract/updateSigner/${id}` : `${BASE}contract/storeSigner`;
    const r = await fetch(url, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; } location.reload();
}
async function toggleSigner(id) {
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}contract/toggleSigner/${id}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; } location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
