<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['blocked'=>'Bloqueado','in_progress'=>'Em andamento','done'=>'Concluído','cancelled'=>'Cancelado'];
$badge  = ['blocked'=>'warning','in_progress'=>'primary','done'=>'success','cancelled'=>'dark'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Onboarding</h5>
            <small class="text-muted">Etapas de implantação após a entrada paga</small>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Projeto</th><th>Responsável técnico</th><th>Status</th><th>Criado</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum onboarding. Gere a partir de um projeto financeiro com entrada paga.</td></tr>
                        <?php else: foreach ($items as $o): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('onboarding/edit/' . (int)$o['id']) ?>'">
                            <td><?= (int)$o['id'] ?></td>
                            <td class="fw-medium"><?= escape($o['title']) ?></td>
                            <td><?= escape($o['tech_responsible_name'] ?? '—') ?></td>
                            <td><span class="badge bg-<?= $badge[$o['status']] ?? 'secondary' ?>"><?= $labels[$o['status']] ?? $o['status'] ?></span></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($o['created_at'])) ?></small></td>
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
