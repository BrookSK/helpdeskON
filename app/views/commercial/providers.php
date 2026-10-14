<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['prospect'=>'Prospecto','proposal'=>'Proposta','contract'=>'Contrato','active'=>'Ativo','terminated'=>'Encerrado','cancelled'=>'Cancelado'];
$badge  = ['prospect'=>'secondary','proposal'=>'info','contract'=>'primary','active'=>'success','terminated'=>'dark','cancelled'=>'dark'];
$engage = ['clt'=>'CLT','pj'=>'PJ','freelancer'=>'Freelancer','estagio'=>'Estágio','outro'=>'Outro'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Prestadores</h5>
            <small class="text-muted">Contratação, acessos e encerramento</small>
        </div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#provForm"><i class="bi bi-plus"></i> Novo prestador</button>
    </div>

    <div class="collapse mb-3" id="provForm">
        <div class="card"><div class="card-body">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small">Nome</label><input id="n-name" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">E-mail</label><input id="n-email" class="form-control form-control-sm"></div>
                <div class="col-md-4"><label class="form-label small">Função</label><input id="n-role" class="form-control form-control-sm" placeholder="Dev PHP pleno"></div>
                <div class="col-md-3"><label class="form-label small">Contratação</label>
                    <select id="n-engage" class="form-select form-select-sm">
                        <option value="pj">PJ</option><option value="clt">CLT</option>
                        <option value="freelancer">Freelancer</option><option value="estagio">Estágio</option><option value="outro">Outro</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label small">Modelo</label>
                    <select id="n-model" class="form-select form-select-sm">
                        <option value="">—</option><option value="remoto">Remoto</option><option value="hibrido">Híbrido</option><option value="presencial">Presencial</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label small">Pagamento</label>
                    <select id="n-paytype" class="form-select form-select-sm">
                        <option value="">—</option><option value="mensal">Mensal</option><option value="hora">Por hora</option><option value="projeto">Por projeto</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label small">Valor (R$)</label><input id="n-pay" class="form-control form-control-sm"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" onclick="saveProv()">Salvar</button></div>
            </div>
        </div></div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Nome</th><th>Função</th><th>Contratação</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum prestador cadastrado.</td></tr>
                        <?php else: foreach ($items as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('provider/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['name']) ?></td>
                            <td><small class="text-muted"><?= escape($p['role_title'] ?? '—') ?></small></td>
                            <td><?= $engage[$p['engagement_type']] ?? $p['engagement_type'] ?></td>
                            <td><span class="badge bg-<?= $badge[$p['status']] ?? 'secondary' ?>"><?= $labels[$p['status']] ?? $p['status'] ?></span></td>
                            <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
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
async function saveProv() {
    const name = document.getElementById('n-name').value.trim();
    if (!name) { alert('Informe o nome.'); return; }
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('name', name);
    fd.append('email', document.getElementById('n-email').value);
    fd.append('role_title', document.getElementById('n-role').value);
    fd.append('engagement_type', document.getElementById('n-engage').value);
    fd.append('work_model', document.getElementById('n-model').value);
    fd.append('pay_type', document.getElementById('n-paytype').value);
    fd.append('pay_amount', document.getElementById('n-pay').value);
    const r = await fetch(`${BASE}provider/store`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${BASE}provider/edit/${r.id}`;
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
