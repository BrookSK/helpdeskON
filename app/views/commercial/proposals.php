<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$statusLabels = [
    'draft' => 'Em elaboração', 'ready' => 'Montada', 'sent' => 'Enviada',
    'awaiting' => 'Aguardando', 'accepted' => 'Aceita', 'rejected' => 'Recusada',
    'cancelled' => 'Cancelada',
];
$statusBadge = [
    'draft' => 'secondary', 'ready' => 'info', 'sent' => 'primary',
    'awaiting' => 'warning', 'accepted' => 'success', 'rejected' => 'danger',
    'cancelled' => 'dark',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Propostas / Orçamentos</h5>
            <small class="text-muted">Processo comercial — da elaboração ao aceite</small>
        </div>
        <button class="btn btn-primary btn-sm" onclick="newProposal()"><i class="bi bi-plus-lg"></i> Nova proposta</button>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex gap-2 align-items-center">
            <label class="small text-muted mb-0">Status:</label>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='<?= baseUrl('proposal') ?>'+(this.value?('?status='+this.value):'')">
                <option value="">Todos</option>
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= (($_GET['status'] ?? '') === $st) ? 'selected' : '' ?>><?= $statusLabels[$st] ?? $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>Título</th><th>Cliente</th><th>Status</th><th>Total</th><th>Criada</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proposals)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma proposta.</td></tr>
                        <?php else: foreach ($proposals as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('proposal/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['title']) ?></td>
                            <td><?= escape($p['client_name'] ?: ($p['crm_contact_name'] ?? '—')) ?></td>
                            <td><span class="badge bg-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= $statusLabels[$p['status']] ?? $p['status'] ?></span></td>
                            <td>R$ <?= number_format((float)$p['total'], 2, ',', '.') ?></td>
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

<script>
const PROP_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
function newProposal() {
    const title = prompt('Título da proposta:');
    if (!title) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('title', title);
    fetch(`${PROP_BASE}proposal/store`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => { if (d.error) { alert(d.error); return; } location.href = `${PROP_BASE}proposal/edit/${d.id}`; })
        .catch(() => alert('Erro ao criar a proposta.'));
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
