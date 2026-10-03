<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['prospect'=>'Prospecto','proposal'=>'Proposta','contract'=>'Contrato','active'=>'Ativo','terminated'=>'Encerrado','cancelled'=>'Cancelado'];
$badge  = ['prospect'=>'secondary','proposal'=>'info','contract'=>'primary','active'=>'success','terminated'=>'dark','cancelled'=>'dark'];
$engage = ['clt'=>'CLT','pj'=>'PJ','freelancer'=>'Freelancer','estagio'=>'Estágio','outro'=>'Outro'];
// Próximos status possíveis (fluxo feliz) para os botões.
$next = ['prospect'=>'proposal','proposal'=>'contract','contract'=>'active'];
$st = $provider['status'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= escape($provider['name']) ?></h5>
            <small class="text-muted"><?= escape($provider['role_title'] ?? '') ?> · <?= $engage[$provider['engagement_type']] ?? $provider['engagement_type'] ?></small>
        </div>
        <div>
            <span class="badge bg-<?= $badge[$st] ?? 'secondary' ?> me-2"><?= $labels[$st] ?? $st ?></span>
            <a href="<?= baseUrl('provider') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (isset($next[$st])): ?>
    <div class="alert alert-light border py-2 d-flex justify-content-between align-items-center">
        <span>Avançar para <strong><?= $labels[$next[$st]] ?></strong>?</span>
        <button class="btn btn-sm btn-primary" onclick="changeStatus('<?= $next[$st] ?>')"><i class="bi bi-arrow-right"></i> Avançar</button>
    </div>
    <?php elseif ($st === 'active'): ?>
    <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle"></i> Para encerrar, revogue <strong>todos os acessos</strong> primeiro.
        <?php if (!empty($pendingAccesses)): ?>Pendentes: <strong><?= escape(implode(', ', $pendingAccesses)) ?></strong><?php endif; ?>
    </div>
    <?php elseif ($st === 'terminated'): ?>
    <div class="alert alert-dark py-2">
        <i class="bi bi-slash-circle"></i> Encerrado em <?= $provider['terminated_at'] ? date('d/m/Y', strtotime($provider['terminated_at'])) : '—' ?>.
        <?php if (!empty($provider['termination_reason'])): ?><br><small><?= escape($provider['termination_reason']) ?></small><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Dados</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Nome</label><input id="f-name" class="form-control form-control-sm" value="<?= escape($provider['name']) ?>"></div>
                        <div class="col-6"><label class="form-label small">E-mail</label><input id="f-email" class="form-control form-control-sm" value="<?= escape($provider['email'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Função</label><input id="f-role" class="form-control form-control-sm" value="<?= escape($provider['role_title'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small">Jornada</label><input id="f-workload" class="form-control form-control-sm" value="<?= escape($provider['workload'] ?? '') ?>"></div>
                        <div class="col-4"><label class="form-label small">Contratação</label>
                            <select id="f-engage" class="form-select form-select-sm">
                                <?php foreach ($engage as $k=>$v): ?><option value="<?= $k ?>" <?= $provider['engagement_type']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4"><label class="form-label small">Modelo</label>
                            <select id="f-model" class="form-select form-select-sm">
                                <option value="">—</option>
                                <?php foreach (['remoto'=>'Remoto','hibrido'=>'Híbrido','presencial'=>'Presencial'] as $k=>$v): ?><option value="<?= $k ?>" <?= ($provider['work_model']??'')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4"><label class="form-label small">Valor (R$)</label><input id="f-pay" class="form-control form-control-sm" value="<?= $provider['pay_amount'] !== null ? number_format((float)$provider['pay_amount'],2,',','.') : '' ?>"></div>
                        <div class="col-12"><label class="form-label small">Escopo (o que faz parte)</label><textarea id="f-scope" class="form-control form-control-sm" rows="2"><?= escape($provider['scope'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label small">Fora do escopo (o que NÃO faz parte)</label><textarea id="f-out" class="form-control form-control-sm" rows="2"><?= escape($provider['out_of_scope'] ?? '') ?></textarea></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" onclick="saveProv()">Salvar dados</button></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Acessos</strong>
                    <?php if ($st === 'active' && !empty($pendingAccesses)): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="revokeAll()"><i class="bi bi-x-octagon"></i> Revogar todos</button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <input id="a-label" class="form-control form-control-sm" list="access-opts" placeholder="Acesso (GitHub, Servidor...)">
                            <datalist id="access-opts">
                                <?php foreach ($defaultAccessLabels as $l): ?><option value="<?= escape($l) ?>"></option><?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-4"><input id="a-details" class="form-control form-control-sm" placeholder="Detalhe (opcional)"></div>
                        <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100" onclick="addAccess()">Conceder</button></div>
                    </div>
                    <?php if (empty($accesses)): ?>
                    <p class="text-muted small mb-0">Nenhum acesso concedido.</p>
                    <?php else: foreach ($accesses as $a): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <div>
                            <strong><?= escape($a['access_label']) ?></strong>
                            <?php if (!empty($a['details'])): ?><small class="text-muted">· <?= escape($a['details']) ?></small><?php endif; ?>
                            <?php if (!empty($a['revoked_at'])): ?>
                            <span class="badge bg-dark ms-1">revogado <?= date('d/m', strtotime($a['revoked_at'])) ?></span>
                            <?php else: ?>
                            <span class="badge bg-success ms-1">ativo</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($a['revoked_at'])): ?>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="revokeAccess(<?= (int)$a['id'] ?>)">Revogar</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <?php if ($st === 'active'): ?>
            <div class="card mb-3 border-danger">
                <div class="card-header py-2 text-danger"><strong>Encerrar contrato</strong></div>
                <div class="card-body">
                    <textarea id="t-reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Motivo do encerramento (obrigatório)"></textarea>
                    <button class="btn btn-sm btn-danger" onclick="terminate()"><i class="bi bi-slash-circle"></i> Encerrar (revoga acesso ao sistema)</button>
                    <small class="d-block text-muted mt-1">Só é possível encerrar com todos os acessos revogados.</small>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:220px;overflow:auto;">
                    <?php if (empty($events)): ?>
                    <p class="text-muted small mb-0">Sem eventos.</p>
                    <?php else: foreach ($events as $e): ?>
                    <div class="small border-bottom py-1">
                        <span class="text-muted"><?= date('d/m H:i', strtotime($e['created_at'])) ?></span> ·
                        <?= escape($e['description'] ?? $e['event_type']) ?>
                        <?php if (!empty($e['user_name'])): ?><em class="text-muted">(<?= escape($e['user_name']) ?>)</em><?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const PRID = <?= (int)$provider['id'] ?>;
async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
async function saveProv() {
    const r = await post(`provider/save/${PRID}`, {
        name: document.getElementById('f-name').value,
        email: document.getElementById('f-email').value,
        role_title: document.getElementById('f-role').value,
        workload: document.getElementById('f-workload').value,
        engagement_type: document.getElementById('f-engage').value,
        work_model: document.getElementById('f-model').value,
        pay_amount: document.getElementById('f-pay').value,
        scope: document.getElementById('f-scope').value,
        out_of_scope: document.getElementById('f-out').value,
    });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function changeStatus(to) {
    const r = await post(`provider/status/${PRID}`, { status: to });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function addAccess() {
    const label = document.getElementById('a-label').value.trim();
    if (!label) { alert('Informe o acesso.'); return; }
    const r = await post(`provider/addAccess/${PRID}`, { access_label: label, details: document.getElementById('a-details').value });
    if (r.error) { alert(r.error); return; } location.reload();
}
async function revokeAccess(id) {
    if (!confirm('Revogar este acesso?')) return;
    const r = await post(`provider/revokeAccess/${id}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function revokeAll() {
    if (!confirm('Revogar TODOS os acessos ativos?')) return;
    const r = await post(`provider/revokeAll/${PRID}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function terminate() {
    const reason = document.getElementById('t-reason').value.trim();
    if (!reason) { alert('Informe o motivo.'); return; }
    const r = await post(`provider/terminate/${PRID}`, { reason });
    if (r.error) { alert(r.error + (r.pending ? '\nPendentes: ' + r.pending.join(', ') : '')); return; }
    location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
