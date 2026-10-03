<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['planning'=>'Planejamento','in_progress'=>'Em andamento','delivered'=>'Entregue','warranty'=>'Em garantia','closed'=>'Encerrado','cancelled'=>'Cancelado'];
$badge  = ['planning'=>'secondary','in_progress'=>'primary','delivered'=>'info','warranty'=>'success','closed'=>'dark','cancelled'=>'dark'];
$ctype  = ['zero'=>'Projeto fechado (garantia 90d)','manutencao'=>'Manutenção','suporte'=>'Suporte','outro'=>'Outro'];
$st = $project['status'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><?= escape($project['name']) ?></h5>
            <small class="text-muted"><?= escape($project['company_name'] ?? '—') ?> · <?= $ctype[$project['contract_type']] ?? $project['contract_type'] ?></small>
        </div>
        <div>
            <span class="badge bg-<?= $badge[$st] ?? 'secondary' ?> me-2"><?= $labels[$st] ?? $st ?></span>
            <a href="<?= baseUrl('project') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
    </div>

    <?php if ($warnWarranty): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle"></i> Garantia termina em <strong><?= (int)$warrantyDaysLeft ?> dia(s)</strong> (<?= date('d/m/Y', strtotime($project['warranty_ends_at'])) ?>). Avise o cliente sobre contrato de suporte.</div>
    <?php endif; ?>

    <?php if (!$canOpenTicket && $blockReason): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-lock"></i> Chamados bloqueados: <?= escape($blockReason) ?></div>
    <?php elseif ($canOpenTicket): ?>
    <div class="alert alert-success py-2"><i class="bi bi-unlock"></i> Chamados liberados para este projeto.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2"><strong>Garantia & Suporte</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Tipo de contrato</dt><dd class="col-7"><?= $ctype[$project['contract_type']] ?? $project['contract_type'] ?></dd>
                        <dt class="col-5">Entregue em</dt><dd class="col-7"><?= $project['delivered_at'] ? date('d/m/Y', strtotime($project['delivered_at'])) : '—' ?></dd>
                        <dt class="col-5">Garantia (dias)</dt><dd class="col-7"><?= (int)$project['warranty_days'] ?></dd>
                        <dt class="col-5">Garantia até</dt><dd class="col-7"><?= $project['warranty_ends_at'] ? date('d/m/Y', strtotime($project['warranty_ends_at'])) : '—' ?></dd>
                        <dt class="col-5">Contrato de suporte</dt><dd class="col-7"><?= (int)$project['support_contract'] === 1 ? 'Ativo' : 'Não' ?></dd>
                    </dl>
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (!in_array($st, ['delivered','warranty','closed','cancelled'], true)): ?>
                        <button class="btn btn-sm btn-primary" onclick="deliver()"><i class="bi bi-box-seam"></i> Marcar entregue</button>
                        <?php endif; ?>
                        <?php if ((int)$project['support_contract'] === 1): ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleSupport(0)">Desativar suporte</button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-success" onclick="toggleSupport(1)">Ativar contrato de suporte</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:320px;overflow:auto;">
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
const PID = <?= (int)$project['id'] ?>;
async function post(url, data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const k in (data||{})) fd.append(k, data[k]);
    return fetch(`${BASE}${url}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
}
async function deliver() {
    if (!confirm('Marcar este projeto como entregue? A garantia será calculada a partir de hoje.')) return;
    const r = await post(`project/deliver/${PID}`, {});
    if (r.error) { alert(r.error); return; } location.reload();
}
async function toggleSupport(active) {
    const r = await post(`project/toggleSupport/${PID}`, { active });
    if (r.error) { alert(r.error); return; } location.reload();
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
