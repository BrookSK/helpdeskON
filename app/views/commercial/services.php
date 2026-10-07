<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Catálogo de serviços</h5>
            <small class="text-muted">Serviços pré-cadastrados usados ao montar propostas</small>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openServiceModal()"><i class="bi bi-plus-lg"></i> Novo serviço</button>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Serviço</th><th>Horas</th><th>Valor/hora</th><th>Custo/hora</th>
                            <th>Hospedagem</th><th>Status</th><th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum serviço cadastrado.</td></tr>
                        <?php else: foreach ($services as $s): ?>
                        <tr>
                            <td><?= (int)$s['id'] ?></td>
                            <td>
                                <div class="fw-medium"><?= escape($s['name']) ?></div>
                                <?php if (!empty($s['description'])): ?><small class="text-muted"><?= escape(mb_substr($s['description'],0,80)) ?></small><?php endif; ?>
                            </td>
                            <td><?= $s['est_hours'] !== null ? escape($s['est_hours']) : '—' ?></td>
                            <td><?= $s['hourly_rate'] !== null ? ('R$ ' . number_format((float)$s['hourly_rate'],2,',','.')) : '—' ?></td>
                            <td><?= (isset($s['cost_price']) && $s['cost_price'] !== null) ? ('R$ ' . number_format((float)$s['cost_price'],2,',','.')) : '—' ?></td>
                            <td><?= ((int)$s['is_hosting'] === 1) ? '<span class="badge bg-info">Sim</span>' : 'Não' ?></td>
                            <td><?= ((int)$s['active'] === 1) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='editService(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-warning" onclick="toggleService(<?= (int)$s['id'] ?>)"><i class="bi bi-power"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="svc-modal-title">Novo serviço</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="svc-id">
                <div class="mb-2">
                    <label class="form-label small fw-medium">Nome *</label>
                    <input type="text" id="svc-name" class="form-control form-control-sm" maxlength="200">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Descrição / escopo padrão</label>
                    <textarea id="svc-description" class="form-control form-control-sm" rows="3"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="form-label small fw-medium">Horas estimadas</label>
                        <input type="number" step="0.01" min="0" id="svc-hours" class="form-control form-control-sm">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-medium">Valor/hora (R$)</label>
                        <input type="number" step="0.01" min="0" id="svc-rate" class="form-control form-control-sm">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-medium">Custo/hora (R$)</label>
                        <input type="number" step="0.01" min="0" id="svc-cost" class="form-control form-control-sm">
                        <small class="text-muted" style="font-size:0.68rem">Interno. Não aparece na proposta.</small>
                    </div>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="svc-hosting">
                    <label class="form-check-label small" for="svc-hosting">É serviço de hospedagem</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-sm btn-primary" id="svc-save-btn" onclick="saveService()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
const SVC_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
let svcModal = null;
function getSvcModal() { if (!svcModal) svcModal = new bootstrap.Modal(document.getElementById('serviceModal')); return svcModal; }
function openServiceModal() {
    document.getElementById('svc-modal-title').textContent = 'Novo serviço';
    document.getElementById('svc-id').value = '';
    document.getElementById('svc-name').value = '';
    document.getElementById('svc-description').value = '';
    document.getElementById('svc-hours').value = '';
    document.getElementById('svc-rate').value = '';
    document.getElementById('svc-cost').value = '';
    document.getElementById('svc-hosting').checked = false;
    getSvcModal().show();
}
function editService(s) {
    document.getElementById('svc-modal-title').textContent = 'Editar serviço';
    document.getElementById('svc-id').value = s.id;
    document.getElementById('svc-name').value = s.name || '';
    document.getElementById('svc-description').value = s.description || '';
    document.getElementById('svc-hours').value = s.est_hours || '';
    document.getElementById('svc-rate').value = s.hourly_rate || '';
    document.getElementById('svc-cost').value = s.cost_price || '';
    document.getElementById('svc-hosting').checked = (parseInt(s.is_hosting, 10) === 1);
    getSvcModal().show();
}
function saveService() {
    const id = document.getElementById('svc-id').value;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('name', document.getElementById('svc-name').value.trim());
    fd.append('description', document.getElementById('svc-description').value.trim());
    fd.append('est_hours', document.getElementById('svc-hours').value);
    fd.append('hourly_rate', document.getElementById('svc-rate').value);
    fd.append('cost_price', document.getElementById('svc-cost').value);
    fd.append('is_hosting', document.getElementById('svc-hosting').checked ? '1' : '0');
    const url = id ? `${SVC_BASE}servicecatalog/update/${id}` : `${SVC_BASE}servicecatalog/store`;
    fetch(url, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => { if (d.error) { alert(d.error); return; } location.reload(); })
        .catch(() => alert('Erro ao salvar o serviço.'));
}
function toggleService(id) {
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    fetch(`${SVC_BASE}servicecatalog/toggle/${id}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => { if (d.error) { alert(d.error); return; } location.reload(); })
        .catch(() => alert('Erro ao alterar o status.'));
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
