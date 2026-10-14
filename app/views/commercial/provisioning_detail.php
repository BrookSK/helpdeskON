<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$statusLabels = ['pending'=>'Pendente','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','cancelled'=>'Cancelado'];
$statusBadge  = ['pending'=>'secondary','in_progress'=>'primary','blocked'=>'warning','done'=>'success','cancelled'=>'dark'];
$stepLabels = ['pending'=>'Pendente','in_progress'=>'Em andamento','done'=>'Concluída','blocked'=>'Bloqueada','skipped'=>'Ignorada'];
$stepBadge  = ['pending'=>'secondary','in_progress'=>'info','done'=>'success','blocked'=>'warning','skipped'=>'dark'];
$st = $provisioning['status'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Provisionamento #<?= (int)$provisioning['id'] ?></h5>
            <small class="text-muted"><?= escape($provisioning['company_name'] ?? ('Empresa #' . (int)($provisioning['company_id'] ?? 0))) ?></small>
        </div>
        <div>
            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?> me-2"><?= $statusLabels[$st] ?? $st ?></span>
            <a href="<?= baseUrl('provisioning') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if (!$lrvConfigured): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> LRV Cloud sem API key configurada (Settings <code>lrv_cloud_api_key</code>). Etapas automáticas não vão executar.</div>
    <?php endif; ?>

    <?php if (!empty($manualSteps)): ?>
    <div class="alert alert-info py-2">
        <i class="bi bi-hand-index"></i> Etapas que hoje dependem de <strong>ação manual</strong> (a API do LRV Cloud ainda não expõe o endpoint):
        <strong><?= escape(implode(', ', $manualSteps)) ?></strong>.
    </div>
    <?php endif; ?>

    <?php if ($st === 'pending'): ?>
    <div class="alert alert-secondary py-2 d-flex justify-content-between align-items-center">
        <span>Provisionamento ainda não iniciado.</span>
        <button class="btn btn-sm btn-primary" onclick="startProv()"><i class="bi bi-play-fill"></i> Iniciar</button>
    </div>
    <?php elseif ($st === 'in_progress' && empty($pendingRequired)): ?>
    <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-all"></i> Todas as etapas obrigatórias concluídas.</span>
        <button class="btn btn-sm btn-success" onclick="finishProv()"><i class="bi bi-flag-fill"></i> Concluir</button>
    </div>
    <?php elseif ($st === 'in_progress'): ?>
    <div class="alert alert-light border py-2">Faltam etapas obrigatórias: <strong><?= escape(implode(', ', $pendingRequired)) ?></strong></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-2"><strong>Etapas</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Etapa</th><th>Modo</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($steps as $s): ?>
                            <tr>
                                <td>
                                    <?= escape($s['title']) ?>
                                    <?php if ((int)$s['required'] === 1): ?><span class="badge bg-light text-danger border ms-1">obrigatória</span><?php endif; ?>
                                    <?php if (!empty($s['external_ref'])): ?><br><small class="text-muted">ref: <?= escape($s['external_ref']) ?></small><?php endif; ?>
                                    <?php if ($s['status'] === 'blocked' && !empty($s['blocked_reason'])): ?><br><small class="text-warning"><?= escape($s['blocked_reason']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($s['mode'] ?? 'manual') === 'auto'): ?>
                                    <span class="badge bg-primary-subtle text-primary border">auto (API)</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">manual</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $stepBadge[$s['status']] ?? 'secondary' ?>"><?= $stepLabels[$s['status']] ?? $s['status'] ?></span></td>
                                <td class="text-end">
                                    <?php if (!in_array($s['status'], ['done','skipped'], true) && $st === 'in_progress'): ?>
                                        <?php if (($s['mode'] ?? 'manual') === 'auto'): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="runStep(<?= (int)$s['id'] ?>)">Executar</button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="runStep(<?= (int)$s['id'] ?>)">Marcar feita</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:360px;overflow:auto;">
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
const PID = <?= (int)$provisioning['id'] ?>;
async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
async function startProv() {
    const r = await post(`provisioning/start/${PID}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function runStep(stepId) {
    const r = await post(`provisioning/runStep/${stepId}`, {});
    if (r.manual_pending) { alert('Pendência manual: ' + (r.reason || 'endpoint indisponível na API.')); location.reload(); return; }
    if (r.error) { alert(r.error); return; }
    location.reload();
}
async function finishProv() {
    const r = await post(`provisioning/finish/${PID}`, {});
    if (r.error) { alert(r.error + (r.pending ? '\n' + r.pending.join(', ') : '')); return; } location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
