<?php $pageTitle = 'Apresentações - ON Solutions Helpdesk'; $currentPage = 'presentations'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$statusLabels = ['draft' => 'Em edição', 'published' => 'Link ativo'];
$statusBadge = ['draft' => 'secondary', 'published' => 'success'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Apresentações</h5>
            <small class="text-muted">Esteira comercial — apresente a ON Solutions ao cliente antes da proposta</small>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPresentationModal"><i class="bi bi-plus-lg"></i> Nova apresentação</button>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 d-flex gap-2 align-items-center">
            <label class="small text-muted mb-0">Status:</label>
            <select class="form-select form-select-sm" style="width:auto;" onchange="location.href='<?= baseUrl('presentation') ?>'+(this.value?('?status='+this.value):'')">
                <option value="">Todos</option>
                <?php foreach ($statusLabels as $st => $label): ?>
                <option value="<?= $st ?>" <?= (($_GET['status'] ?? '') === $st) ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>Título</th><th>Cliente</th><th>Status</th><th>Visualizações</th><th>Criada</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($presentations)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma apresentação.</td></tr>
                        <?php else: foreach ($presentations as $p): ?>
                        <tr style="cursor:pointer;" onclick="location.href='<?= baseUrl('presentation/edit/' . (int)$p['id']) ?>'">
                            <td><?= (int)$p['id'] ?></td>
                            <td class="fw-medium"><?= escape($p['title']) ?></td>
                            <td><?= escape($p['client_name'] ?: ($p['crm_contact_name'] ?? '—')) ?></td>
                            <td><span class="badge bg-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= $statusLabels[$p['status']] ?? $p['status'] ?></span></td>
                            <td>
                                <?= (int)$p['view_count'] ?>
                                <?php if (!empty($p['last_viewed_at'])): ?><small class="text-muted d-block">última: <?= date('d/m/Y H:i', strtotime($p['last_viewed_at'])) ?></small><?php endif; ?>
                            </td>
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

<!-- Modal: Nova apresentação -->
<div class="modal fade" id="newPresentationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-easel"></i> Nova apresentação</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-medium">Título *</label>
          <input type="text" id="na-title" class="form-control form-control-sm" placeholder="Ex.: Apresentação ON Solutions — Empresa X" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Lead / contato (CRM)</label>
          <select id="na-lead" class="form-select form-select-sm">
            <option value="">— Sem vínculo —</option>
            <?php foreach (($leads ?? []) as $l): ?>
            <option value="<?= (int)$l['id'] ?>"
                    data-name="<?= escape($l['contact_name'] ?? '') ?>"
                    data-phone="<?= escape($l['phone'] ?? '') ?>">
              <?= escape($l['contact_name'] ?: ('Contato #' . (int)$l['id'])) ?><?= !empty($l['phone']) ? ' · ' . escape($l['phone']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label small">Nome do cliente / empresa</label>
            <input type="text" id="na-client-name" class="form-control form-control-sm" placeholder="Usado no texto como {{cliente}}">
          </div>
          <div class="col-7">
            <label class="form-label small">E-mail</label>
            <input type="email" id="na-client-email" class="form-control form-control-sm">
          </div>
          <div class="col-5">
            <label class="form-label small">Telefone</label>
            <input type="text" id="na-client-phone" class="form-control form-control-sm">
          </div>
        </div>
        <small class="text-muted d-block mt-2">A apresentação nasce a partir do modelo padrão e pode ser editada em seguida.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" id="na-save"><i class="bi bi-check-lg"></i> Criar apresentação</button>
      </div>
    </div>
  </div>
</div>

<script>
const PRES_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';

document.getElementById('na-lead').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!this.value) return;
    if (!document.getElementById('na-client-name').value) document.getElementById('na-client-name').value = opt.getAttribute('data-name') || '';
    if (!document.getElementById('na-client-phone').value) document.getElementById('na-client-phone').value = opt.getAttribute('data-phone') || '';
});

document.getElementById('na-save').addEventListener('click', async function () {
    const title = document.getElementById('na-title').value.trim();
    if (!title) { alert('Informe o título da apresentação.'); return; }
    this.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('title', title);
    fd.append('contact_id', document.getElementById('na-lead').value);
    fd.append('client_name', document.getElementById('na-client-name').value.trim());
    fd.append('client_email', document.getElementById('na-client-email').value.trim());
    fd.append('client_phone', document.getElementById('na-client-phone').value.trim());
    try {
        const d = await fetch(`${PRES_BASE}presentation/store`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} }).then(r => r.json());
        if (d.error) { alert(d.error); this.disabled = false; return; }
        location.href = `${PRES_BASE}presentation/edit/${d.id}`;
    } catch (e) {
        alert('Erro ao criar a apresentação.');
        this.disabled = false;
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
