<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Cofre de credenciais</h5>
            <small class="text-muted">Acesso restrito. Segredos criptografados; cada revelação é auditada.</small>
        </div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#credForm"><i class="bi bi-plus"></i> Nova credencial</button>
    </div>

    <div class="collapse mb-3" id="credForm">
        <div class="card"><div class="card-body">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Empresa</label>
                    <select id="n-company" class="form-select form-select-sm">
                        <option value="">— Sem empresa —</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"><?= escape($co['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label small">Serviço/Rótulo</label><input id="n-label" class="form-control form-control-sm" placeholder="Servidor / Hospedagem / API..."></div>
                <div class="col-md-4"><label class="form-label small">Usuário</label><input id="n-user" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Senha/Token</label><input id="n-secret" type="password" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">URL</label><input id="n-url" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Notas</label><input id="n-notes" class="form-control form-control-sm"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" onclick="saveCred()">Salvar</button></div>
            </div>
        </div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Empresa</th><th>Serviço</th><th>Usuário</th><th>Senha/Token</th><th>URL</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($credentials)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma credencial cadastrada.</td></tr>
                        <?php else: foreach ($credentials as $c): ?>
                        <tr>
                            <td><?= escape($c['company_name'] ?? '—') ?></td>
                            <td class="fw-medium"><?= escape($c['service_label']) ?></td>
                            <td><?= escape($c['username'] ?? '—') ?></td>
                            <td>
                                <code id="sec-<?= (int)$c['id'] ?>"><?= escape($c['secret_mask']) ?></code>
                                <?php if ($c['secret_mask'] !== ''): ?>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="reveal(<?= (int)$c['id'] ?>)" title="Revelar"><i class="bi bi-eye"></i></button>
                                <?php endif; ?>
                            </td>
                            <td><?php if (!empty($c['url'])): ?><a href="<?= escape($c['url']) ?>" target="_blank" rel="noopener">abrir</a><?php else: ?>—<?php endif; ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="delCred(<?= (int)$c['id'] ?>)"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
async function saveCred() {
    const label = document.getElementById('n-label').value.trim();
    if (!label) { alert('Informe o rótulo do serviço.'); return; }
    const r = await post('credential/store', {
        company_id: document.getElementById('n-company').value,
        service_label: label,
        username: document.getElementById('n-user').value,
        secret: document.getElementById('n-secret').value,
        url: document.getElementById('n-url').value,
        notes: document.getElementById('n-notes').value,
    });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function reveal(id) {
    const r = await post(`credential/reveal/${id}`, {});
    if (r.error) { alert(r.error); return; }
    const el = document.getElementById('sec-' + id);
    el.textContent = r.secret;
    setTimeout(() => { el.textContent = '••••••••'; }, 15000); // reesconde após 15s
}
async function delCred(id) {
    if (!confirm('Excluir esta credencial?')) return;
    const r = await post(`credential/delete/${id}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
