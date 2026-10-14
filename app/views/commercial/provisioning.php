<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['pending'=>'Pendente','in_progress'=>'Em andamento','blocked'=>'Bloqueado','done'=>'Concluído','cancelled'=>'Cancelado'];
$badge  = ['pending'=>'secondary','in_progress'=>'primary','blocked'=>'warning','done'=>'success','cancelled'=>'dark'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Provisionamento</h5>
            <small class="text-muted">Infra no LRV Cloud (conta, VPS, banco, app, homologação)</small>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Empresa</th><th>Modo</th><th>Status</th><th>Criado</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum provisionamento. Gere a partir de um onboarding.</td></tr>
                        <?php else: foreach ($items as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('provisioning/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['company_name'] ?? ('Empresa #' . (int)($p['company_id'] ?? 0))) ?></td>
                            <td><small class="text-muted"><?= $p['pipeline_mode'] === 'esteira_cx' ? 'Esteira (CX)' : 'Fora da esteira' ?></small></td>
                            <td><span class="badge bg-<?= $badge[$p['status']] ?? 'secondary' ?>"><?= $labels[$p['status']] ?? $p['status'] ?></span></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['created_at'])) ?></small></td>
                            <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
