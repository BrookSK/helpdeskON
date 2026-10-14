<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['planning'=>'Planejamento','in_progress'=>'Em andamento','delivered'=>'Entregue','warranty'=>'Em garantia','closed'=>'Encerrado','cancelled'=>'Cancelado'];
$badge  = ['planning'=>'secondary','in_progress'=>'primary','delivered'=>'info','warranty'=>'success','closed'=>'dark','cancelled'=>'dark'];
$ctype  = ['zero'=>'Projeto fechado','manutencao'=>'Manutenção','suporte'=>'Suporte','outro'=>'Outro'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Projetos</h5>
            <small class="text-muted">Entrega, garantia e suporte</small>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Projeto</th><th>Empresa</th><th>Tipo</th><th>Status</th><th>Garantia</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum projeto cadastrado.</td></tr>
                        <?php else: foreach ($items as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('project/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['name']) ?></td>
                            <td><small class="text-muted"><?= escape($p['company_name'] ?? '—') ?></small></td>
                            <td><small><?= $ctype[$p['contract_type']] ?? $p['contract_type'] ?></small></td>
                            <td><span class="badge bg-<?= $badge[$p['status']] ?? 'secondary' ?>"><?= $labels[$p['status']] ?? $p['status'] ?></span></td>
                            <td>
                                <?php if (!empty($p['warranty_ends_at'])): ?>
                                    <?php if ($p['warranty_days_left'] !== null && $p['warranty_days_left'] >= 0): ?>
                                        <small class="<?= $p['warn_warranty'] ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                            <?= (int)$p['warranty_days_left'] ?> dia(s)
                                            <?php if ($p['warn_warranty']): ?><i class="bi bi-exclamation-triangle"></i><?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">vencida</small>
                                    <?php endif; ?>
                                <?php else: ?><small class="text-muted">—</small><?php endif; ?>
                            </td>
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
