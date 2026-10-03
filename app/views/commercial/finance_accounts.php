<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php $purposeLabel = ['parcela'=>'Parcelas','recorrente'=>'Recorrente','outra'=>'Outra']; ?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Contas Asaas</h5>
            <small class="text-muted">As contas usadas nas cobranças (selecionáveis por tipo)</small>
        </div>
        <button class="btn btn-sm btn-primary" onclick="document.getElementById('acc-form').scrollIntoView()"><i class="bi bi-plus-lg"></i> Nova conta</button>
    </div>
    <div class="card mb-3">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>#</th><th>Nome</th><th>Finalidade</th><th>Ambiente</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($accounts)): ?><tr><td colspan="5" class="text-center text-muted py-3">Nenhuma conta.</td></tr>
                    <?php else: foreach ($accounts as $a): ?>
                    <tr>
                        <td><?= (int)$a['id'] ?></td>
                        <td class="fw-medium"><?= escape($a['name']) ?></td>
                        <td><?= $purposeLabel[$a['purpose']] ?? $a['purpose'] ?></td>
                        <td><?= ((int)$a['sandbox']===1)?'<span class="badge bg-warning">Sandbox</span>':'<span class="badge bg-success">Produção</span>' ?></td>
                        <td><?= ((int)$a['active']===1)?'Ativa':'Inativa' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card" id="acc-form">
        <div class="card-header py-2"><strong>Nova conta Asaas</strong></div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">Nome *</label><input id="a-name" class="form-control form-control-sm" placeholder="Ex.: Conta Parcelas"></div>
                <div class="col-md-3"><label class="form-label small">Finalidade</label>
                    <select id="a-purpose" class="form-select form-select-sm"><option value="parcela">Parcelas</option><option value="recorrente">Recorrente</option><option value="outra">Outra</option></select>
                </div>
                <div class="col-md-5"><label class="form-label small">Token Asaas</label><input id="a-token" class="form-control form-control-sm" placeholder="access_token da conta"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="a-sandbox" checked><label class="form-check-label small" for="a-sandbox">Ambiente sandbox (teste)</label></div></div>
            </div>
            <button class="btn btn-sm btn-primary mt-2" onclick="saveAccount()">Salvar conta</button>
        </div>
    </div>
</div>
<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
async function saveAccount() {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('name', document.getElementById('a-name').value.trim());
    fd.append('purpose', document.getElementById('a-purpose').value);
    fd.append('asaas_token', document.getElementById('a-token').value.trim());
    fd.append('sandbox', document.getElementById('a-sandbox').checked ? '1' : '0');
    const r = await fetch(`${BASE}finance/storeAccount`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
