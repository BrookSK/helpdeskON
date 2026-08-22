<?php $pageTitle = 'Histórico de Prospecção'; $currentPage = 'prospection'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-clock-history"></i> Histórico de Prospecção</h5>
            <small class="text-muted">E-mails enviados pela equipe comercial</small>
        </div>
        <a href="<?= baseUrl('prospection') ?>" class="btn btn-sm btn-primary"><i class="bi bi-envelope-paper"></i> Novo E-mail</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                <?php if ($isAdmin): ?>
                <div>
                    <label class="form-label small mb-0">Usuário</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($comerciais as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['user_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label small mb-0">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="sent" <?= ($_GET['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Enviado</option>
                        <option value="failed" <?= ($_GET['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Falhou</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-0">De</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= escape($_GET['start_date'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label small mb-0">Até</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= escape($_GET['end_date'] ?? '') ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="<?= baseUrl('prospection/history') ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de resultados -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:0.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <?php if ($isAdmin): ?><th>Remetente</th><?php endif; ?>
                            <th>Conta</th>
                            <th>Destinatário</th>
                            <th>Assunto</th>
                            <th>Lead</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prospections)): ?>
                        <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="text-center text-muted py-4">Nenhum e-mail encontrado.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($prospections as $p): ?>
                        <tr>
                            <td class="text-nowrap"><?= $p['sent_at'] ? date('d/m/Y H:i', strtotime($p['sent_at'])) : date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                            <?php if ($isAdmin): ?><td><?= escape($p['user_name'] ?? '—') ?></td><?php endif; ?>
                            <td><small class="text-muted"><?= escape($p['account_email'] ?? '—') ?></small></td>
                            <td>
                                <span><?= escape($p['recipient_email']) ?></span>
                                <?php if ($p['recipient_name']): ?>
                                <br><small class="text-muted"><?= escape($p['recipient_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= escape($p['subject']) ?></td>
                            <td>
                                <?php if ($p['lead_name']): ?>
                                <span class="badge bg-light text-dark border" style="font-size:0.68rem;"><?= escape($p['lead_name']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['status'] === 'sent'): ?>
                                <span class="badge bg-success">Enviado</span>
                                <?php elseif ($p['status'] === 'failed'): ?>
                                <span class="badge bg-danger" title="<?= escape($p['error_message'] ?? '') ?>">Falhou</span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?= escape($p['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewDetail(<?= $p['id'] ?>)" title="Ver detalhes"><i class="bi bi-eye"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de detalhes -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-envelope-open"></i> Detalhes do E-mail</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detail-body">
                <div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
let detailModal = null;

function viewDetail(id) {
    if (!detailModal) detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    const body = document.getElementById('detail-body');
    body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></div>';
    detailModal.show();

    fetch(`${BASE}prospection/view_detail/${id}`, { headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            if (d.error) { body.innerHTML = `<div class="alert alert-danger">${d.error}</div>`; return; }
            const p = d.prospection;
            let html = `
                <div class="row g-2 mb-3" style="font-size:0.82rem;">
                    <div class="col-md-6"><strong>De:</strong> ${p.account_email || '—'} ${p.account_name ? '(' + p.account_name + ')' : ''}</div>
                    <div class="col-md-6"><strong>Enviado por:</strong> ${p.user_name || '—'}</div>
                    <div class="col-md-6"><strong>Para:</strong> ${p.recipient_email} ${p.recipient_name ? '(' + p.recipient_name + ')' : ''}</div>
                    <div class="col-md-6"><strong>Data:</strong> ${p.sent_at ? new Date(p.sent_at).toLocaleString('pt-BR') : '—'}</div>
                    ${p.cc ? `<div class="col-md-6"><strong>CC:</strong> ${p.cc}</div>` : ''}
                    ${p.bcc ? `<div class="col-md-6"><strong>CCO:</strong> ${p.bcc}</div>` : ''}
                    ${p.lead_name ? `<div class="col-12"><strong>Lead vinculado:</strong> ${p.lead_name} ${p.lead_phone ? '— ' + p.lead_phone : ''}</div>` : ''}
                    <div class="col-12"><strong>Status:</strong> <span class="badge ${p.status === 'sent' ? 'bg-success' : 'bg-danger'}">${p.status === 'sent' ? 'Enviado' : 'Falhou'}</span>
                    ${p.error_message ? ' <small class="text-danger">' + p.error_message + '</small>' : ''}</div>
                </div>
                <hr>
                <h6 class="small fw-semibold">Assunto: ${p.subject}</h6>
                <div class="border rounded p-3 bg-light" style="max-height:400px;overflow-y:auto;">${p.body}</div>
            `;

            if (p.attachments_json) {
                try {
                    const atts = JSON.parse(p.attachments_json);
                    if (atts.length) {
                        html += '<hr><h6 class="small fw-semibold"><i class="bi bi-paperclip"></i> Anexos</h6><ul class="small">';
                        atts.forEach(a => { html += `<li><a href="${BASE}${a.path}" target="_blank">${a.name}</a></li>`; });
                        html += '</ul>';
                    }
                } catch(e) {}
            }

            body.innerHTML = html;
        })
        .catch(() => { body.innerHTML = '<div class="alert alert-danger">Erro ao carregar.</div>'; });
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
