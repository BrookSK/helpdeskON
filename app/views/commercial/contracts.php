<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = [
    'draft' => 'Em elaboração', 'client_review' => 'Em aprovação do cliente', 'approved' => 'Aprovado',
    'awaiting_signature' => 'Aguardando assinatura', 'signed' => 'Assinado',
    'client_rejected' => 'Ajuste solicitado', 'cancelled' => 'Cancelado',
];
$badge = [
    'draft' => 'secondary', 'client_review' => 'info', 'approved' => 'primary',
    'awaiting_signature' => 'warning', 'signed' => 'success', 'client_rejected' => 'danger', 'cancelled' => 'dark',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Contratos</h5>
            <small class="text-muted">Da elaboração à assinatura (ClickSign)</small>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex gap-2 align-items-center">
            <label class="small text-muted mb-0">Status:</label>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='<?= baseUrl('contracts') ?>'+(this.value?('?status='+this.value):'')">
                <option value="">Todos</option>
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= (($_GET['status'] ?? '') === $st) ? 'selected' : '' ?>><?= $labels[$st] ?? $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Título</th><th>Cliente</th><th>Status</th><th>Criado</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($contracts)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Nenhum contrato. Gere a partir de uma proposta aceita.</td></tr>
                        <?php else: foreach ($contracts as $c): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('contracts/edit/' . (int)$c['id']) ?>'">
                            <td><?= (int)$c['id'] ?></td>
                            <td class="fw-medium"><?= escape($c['title']) ?></td>
                            <td><?= escape($c['client_name'] ?: '—') ?></td>
                            <td><span class="badge bg-<?= $badge[$c['status']] ?? 'secondary' ?>"><?= $labels[$c['status']] ?? $c['status'] ?></span></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></small></td>
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
