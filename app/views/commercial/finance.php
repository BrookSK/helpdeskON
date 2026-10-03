<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = ['open'=>'Aberto','entry_paid'=>'Entrada paga','in_progress'=>'Em andamento','done'=>'Concluído','cancelled'=>'Cancelado'];
$badge = ['open'=>'secondary','entry_paid'=>'info','in_progress'=>'primary','done'=>'success','cancelled'=>'dark'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Financeiro</h5>
            <small class="text-muted">Projetos, parcelas e recorrência (Asaas)</small>
        </div>
        <a href="<?= baseUrl('finance/accounts') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bank"></i> Contas Asaas</a>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Projeto</th><th>Valor</th><th>Status</th><th>Criado</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum projeto financeiro. Gere a partir de um contrato assinado.</td></tr>
                        <?php else: foreach ($projects as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('finance/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['title']) ?></td>
                            <td>R$ <?= number_format((float)$p['total_value'],2,',','.') ?></td>
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
