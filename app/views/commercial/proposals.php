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
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newProposalModal"><i class="bi bi-plus-lg"></i> Nova proposta</button>
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

<!-- Modal: Nova proposta -->
<div class="modal fade" id="newProposalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-file-earmark-text"></i> Nova proposta</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-medium">Título da proposta *</label>
          <input type="text" id="np-title" class="form-control form-control-sm" placeholder="Ex.: Desenvolvimento do site institucional" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-medium">Lead / contato (CRM)</label>
          <select id="np-lead" class="form-select form-select-sm">
            <option value="">— Sem vínculo —</option>
            <?php foreach (($leads ?? []) as $l): ?>
            <option value="<?= (int)$l['id'] ?>"
                    data-name="<?= escape($l['contact_name'] ?? '') ?>"
                    data-phone="<?= escape($l['phone'] ?? '') ?>">
              <?= escape($l['contact_name'] ?: ('Contato #' . (int)$l['id'])) ?><?= !empty($l['phone']) ? ' · ' . escape($l['phone']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Vincula a proposta ao lead do CRM (rastreabilidade da esteira).</small>
        </div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label small">Nome do cliente</label>
            <input type="text" id="np-client-name" class="form-control form-control-sm" placeholder="Preenche ao escolher o lead">
          </div>
          <div class="col-7">
            <label class="form-label small">E-mail</label>
            <input type="email" id="np-client-email" class="form-control form-control-sm">
          </div>
          <div class="col-5">
            <label class="form-label small">Telefone</label>
            <input type="text" id="np-client-phone" class="form-control form-control-sm">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" id="np-save"><i class="bi bi-check-lg"></i> Criar proposta</button>
      </div>
    </div>
  </div>
</div>

<script>
const PROP_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';

// Ao escolher um lead, preenche nome/telefone do cliente (snapshot).
document.getElementById('np-lead').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const name = opt.getAttribute('data-name') || '';
    const phone = opt.getAttribute('data-phone') || '';
    if (this.value) {
        if (!document.getElementById('np-client-name').value) document.getElementById('np-client-name').value = name;
        if (!document.getElementById('np-client-phone').value) document.getElementById('np-client-phone').value = phone;
    }
});

document.getElementById('np-save').addEventListener('click', async function () {
    const title = document.getElementById('np-title').value.trim();
    if (!title) { alert('Informe o título da proposta.'); return; }
    this.disabled = true;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('title', title);
    fd.append('contact_id', document.getElementById('np-lead').value);
    fd.append('client_name', document.getElementById('np-client-name').value.trim());
    fd.append('client_email', document.getElementById('np-client-email').value.trim());
    fd.append('client_phone', document.getElementById('np-client-phone').value.trim());
    try {
        const d = await fetch(`${PROP_BASE}proposal/store`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} }).then(r => r.json());
        if (d.error) { alert(d.error); this.disabled = false; return; }
        location.href = `${PROP_BASE}proposal/edit/${d.id}`;
    } catch (e) {
        alert('Erro ao criar a proposta.');
        this.disabled = false;
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
