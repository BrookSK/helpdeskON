<?php $pageTitle = 'Caixa de Entrada - Prospecção'; $currentPage = 'prospection_inbox'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-inbox"></i> Caixa de Entrada</h5>
            <small class="text-muted">E-mails recebidos nas suas contas de prospecção</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('prospection') ?>" class="btn btn-sm btn-primary"><i class="bi bi-send"></i> Novo E-mail</a>
            <a href="<?= baseUrl('prospection/history') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history"></i> Enviados</a>
        </div>
    </div>

    <?php if (empty($accounts)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> Nenhuma conta com IMAP configurado vinculada ao seu perfil.
        <?php if ($user['role'] === 'super_admin'): ?>
        <a href="<?= baseUrl('settings/emailAccounts') ?>">Configurar contas</a>
        <?php else: ?>
        Peça ao administrador para configurar o IMAP nas contas de e-mail.
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small mb-0">Conta</label>
                    <select name="account_id" class="form-select form-select-sm" style="min-width:200px;">
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= $selectedAccountId == $acc['id'] ? 'selected' : '' ?>><?= escape($acc['display_name'] ?: $acc['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-0">Buscar</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="<?= escape($search ?? '') ?>" placeholder="Assunto ou remetente...">
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Buscar</button>
                    <?php if ($search): ?>
                    <a href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId) ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                    <?php endif; ?>
                </div>
                <div class="ms-auto">
                    <small class="text-muted"><?= $total ?> e-mail<?= $total !== 1 ? 's' : '' ?></small>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= escape($error) ?></div>
    <?php endif; ?>

    <!-- Lista de e-mails -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($messages) && !$error): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2 mb-0">Nenhum e-mail encontrado.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush" id="email-list">
                <?php foreach ($messages as $msg): ?>
                <a href="#" class="list-group-item list-group-item-action email-row <?= $msg['seen'] ? '' : 'fw-bold' ?>" data-uid="<?= $msg['uid'] ?>" onclick="openEmail(<?= $msg['uid'] ?>); return false;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!$msg['seen']): ?>
                                <span class="badge bg-primary rounded-circle" style="width:8px;height:8px;padding:0;"></span>
                                <?php endif; ?>
                                <span class="text-truncate" style="max-width:200px;font-size:0.82rem;"><?= escape($msg['from']) ?></span>
                            </div>
                            <div class="text-truncate" style="font-size:0.82rem;"><?= escape($msg['subject']) ?></div>
                        </div>
                        <div class="text-end text-nowrap ms-3" style="font-size:0.72rem;">
                            <span class="text-muted"><?= $msg['date'] ? date('d/m H:i', strtotime($msg['date'])) : '' ?></span>
                            <?php if ($msg['has_attachments']): ?>
                            <br><i class="bi bi-paperclip text-muted"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-center">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : '')) ?>">&laquo;</a></li>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . $p . ($search ? '&search=' . urlencode($search) : '')) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="<?= baseUrl('prospection/inbox?account_id=' . $selectedAccountId . '&page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : '')) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Modal de leitura -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-truncate" id="email-modal-subject" style="max-width:60%;"><i class="bi bi-envelope-open"></i> E-mail</h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-reply" onclick="toggleReply()"><i class="bi bi-reply"></i> Responder</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-archive" onclick="archiveCurrent()"><i class="bi bi-archive"></i> Arquivar</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete" onclick="deleteCurrent()"><i class="bi bi-trash"></i> Excluir</button>
                    <button type="button" class="btn-close ms-1" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" id="email-modal-body">
                <div class="text-center py-5"><span class="spinner-border"></span></div>
            </div>
            <!-- Área de resposta (oculta por padrão) -->
            <div class="modal-footer flex-column align-items-stretch" id="reply-area" style="display:none;">
                <div class="w-100">
                    <label class="form-label small fw-medium mb-1">Responder para <span id="reply-to-label" class="text-muted"></span></label>
                    <textarea id="reply-body" class="form-control form-control-sm mb-2" rows="5" placeholder="Escreva sua resposta..."></textarea>
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleReply()">Cancelar</button>
                        <button class="btn btn-sm btn-primary" id="reply-send-btn" onclick="sendReply()"><i class="bi bi-send"></i> Enviar resposta</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const ACCOUNT_ID = <?= (int)$selectedAccountId ?>;
let emailModal = null;
let currentEmail = null; // { uid, from_email, from, subject }

function getEmailModal() {
    if (!emailModal) emailModal = new bootstrap.Modal(document.getElementById('emailModal'));
    return emailModal;
}

function openEmail(uid) {
    const body = document.getElementById('email-modal-body');
    const title = document.getElementById('email-modal-subject');
    body.innerHTML = '<div class="text-center py-5"><span class="spinner-border"></span></div>';
    title.innerHTML = '<i class="bi bi-envelope-open"></i> Carregando...';
    // Reseta a área de resposta
    document.getElementById('reply-area').style.display = 'none';
    document.getElementById('reply-body').value = '';
    getEmailModal().show();

    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', uid);

    fetch(`${BASE}prospection/readEmail`, {
        method: 'POST',
        body: fd,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            body.innerHTML = `<div class="alert alert-danger">${d.error}</div>`;
            title.innerHTML = '<i class="bi bi-envelope-open"></i> Erro';
            return;
        }

        const msg = d.message;
        currentEmail = { uid: uid, from_email: msg.from_email, from: msg.from, subject: msg.subject };
        title.innerHTML = '<i class="bi bi-envelope-open"></i> ' + escapeHtml(msg.subject);

        let html = `
            <div class="mb-3" style="font-size:0.82rem;">
                <div class="row g-1">
                    <div class="col-12"><strong>De:</strong> ${escapeHtml(msg.from)}</div>
                    <div class="col-12"><strong>Para:</strong> ${escapeHtml(msg.to)}</div>
                    ${msg.cc ? `<div class="col-12"><strong>CC:</strong> ${escapeHtml(msg.cc)}</div>` : ''}
                    <div class="col-12"><strong>Data:</strong> ${msg.date ? new Date(msg.date).toLocaleString('pt-BR') : '—'}</div>
                </div>
            </div>
            <hr>
        `;

        // Corpo do e-mail
        if (msg.body_html) {
            html += `<div class="email-body-frame border rounded p-3 bg-white" style="max-height:500px;overflow-y:auto;">${msg.body_html}</div>`;
        } else if (msg.body_text) {
            html += `<pre class="border rounded p-3 bg-light" style="max-height:500px;overflow-y:auto;white-space:pre-wrap;font-size:0.82rem;">${escapeHtml(msg.body_text)}</pre>`;
        } else {
            html += '<p class="text-muted">Sem conteúdo.</p>';
        }

        // Anexos
        if (msg.attachments && msg.attachments.length) {
            html += '<hr><h6 class="small fw-semibold"><i class="bi bi-paperclip"></i> Anexos (' + msg.attachments.length + ')</h6><ul class="small">';
            msg.attachments.forEach(a => {
                const size = a.size > 1024 ? (a.size / 1024).toFixed(1) + ' KB' : a.size + ' B';
                html += `<li>${escapeHtml(a.filename)} <span class="text-muted">(${size})</span></li>`;
            });
            html += '</ul>';
        }

        body.innerHTML = html;

        // Marca como lido visualmente na lista
        const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
        if (row) row.classList.remove('fw-bold');
    })
    .catch(() => {
        body.innerHTML = '<div class="alert alert-danger">Erro ao carregar o e-mail.</div>';
    });
}

// Remove a linha da lista e fecha o modal após excluir/arquivar
function removeRowAndClose(uid) {
    const row = document.querySelector(`.email-row[data-uid="${uid}"]`);
    if (row) row.remove();
    getEmailModal().hide();
}

function deleteCurrent() {
    if (!currentEmail) return;
    if (!confirm('Excluir este e-mail? Ele será movido para a lixeira da conta.')) return;
    const btn = document.getElementById('btn-delete');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', currentEmail.uid);
    fetch(`${BASE}prospection/deleteEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.error) { alert(d.error); return; }
            removeRowAndClose(currentEmail.uid);
        })
        .catch(() => { btn.disabled = false; alert('Erro ao excluir o e-mail.'); });
}

function archiveCurrent() {
    if (!currentEmail) return;
    const btn = document.getElementById('btn-archive');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('uid', currentEmail.uid);
    fetch(`${BASE}prospection/archiveEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            if (d.error) { alert(d.error); return; }
            removeRowAndClose(currentEmail.uid);
        })
        .catch(() => { btn.disabled = false; alert('Erro ao arquivar o e-mail.'); });
}

function toggleReply() {
    const area = document.getElementById('reply-area');
    const showing = area.style.display !== 'none';
    if (showing) { area.style.display = 'none'; return; }
    if (!currentEmail) return;
    document.getElementById('reply-to-label').textContent = currentEmail.from_email || currentEmail.from;
    area.style.display = '';
    document.getElementById('reply-body').focus();
}

function sendReply() {
    if (!currentEmail) return;
    const bodyText = document.getElementById('reply-body').value.trim();
    if (!bodyText) { alert('Escreva a resposta.'); return; }

    const subject = /^re:/i.test(currentEmail.subject) ? currentEmail.subject : ('Re: ' + currentEmail.subject);
    // Converte quebras de linha em <br> para o corpo HTML
    const htmlBody = escapeHtml(bodyText).replace(/\n/g, '<br>');

    const btn = document.getElementById('reply-send-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    const fd = new FormData();
    fd.append('account_id', ACCOUNT_ID);
    fd.append('to', currentEmail.from_email);
    fd.append('subject', subject);
    fd.append('body', htmlBody);
    fetch(`${BASE}prospection/replyEmail`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Enviar resposta';
            if (d.error) { alert(d.error); return; }
            alert(d.message || 'Resposta enviada!');
            document.getElementById('reply-area').style.display = 'none';
            document.getElementById('reply-body').value = '';
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Enviar resposta'; alert('Erro ao enviar a resposta.'); });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
.email-row { border-left: 3px solid transparent; transition: border-color .15s; }
.email-row:hover { border-left-color: var(--bs-primary); background: #f8f9fa; }
.email-row.fw-bold { border-left-color: #1976d2; background: #eef5ff; }
.email-body-frame img { max-width: 100%; height: auto; }
</style>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
