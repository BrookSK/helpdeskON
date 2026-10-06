<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$labels = [
    'draft' => 'Em elaboração', 'client_review' => 'Em aprovação do cliente', 'approved' => 'Aprovado',
    'awaiting_signature' => 'Aguardando assinatura', 'signed' => 'Assinado',
    'client_rejected' => 'Ajuste solicitado', 'cancelled' => 'Cancelado',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Contrato #<?= (int)$contract['id'] ?></h5>
            <small class="text-muted"><?= escape($contract['title']) ?> · <?= $labels[$contract['status']] ?? $contract['status'] ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('contract') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <?php if ($canEdit): ?>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="saveContract()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-review" class="btn btn-sm btn-primary" onclick="sendReview()"><i class="bi bi-send"></i> Enviar p/ aprovação</button>
            <?php endif; ?>
            <?php if ($canSign): ?>
            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#signModal"><i class="bi bi-pen"></i> Enviar p/ assinatura (ClickSign)</button>
            <?php endif; ?>
            <?php if ($contract['status'] === 'signed' && Permissions::canAccess($user['role'] ?? null, 'finance')): ?>
            <button class="btn btn-sm btn-success" onclick="startFinance()"><i class="bi bi-cash-coin"></i> Iniciar financeiro</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($contract['status'] === 'signed'): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Contrato assinado. Siga para o financeiro.</div>
    <?php endif; ?>

    <?php if ($contract['status'] === 'client_rejected' && !empty($contract['reject_reason'])): ?>
    <div class="alert alert-warning py-2"><strong>Ajuste solicitado pelo cliente:</strong> <?= escape($contract['reject_reason']) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-2"><strong>Contrato</strong></div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5"><label class="form-label small">Título</label><input id="c-title" class="form-control form-control-sm" value="<?= escape($contract['title']) ?>" <?= $canEdit?'':'disabled' ?>></div>
                        <div class="col-md-4"><label class="form-label small">E-mail do cliente</label><input id="c-email" class="form-control form-control-sm" value="<?= escape($contract['client_email'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>></div>
                        <div class="col-md-3"><label class="form-label small">Telefone</label><input id="c-phone" class="form-control form-control-sm" value="<?= escape($contract['client_phone'] ?? '') ?>" <?= $canEdit?'':'disabled' ?>></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="tab-edit-btn" class="btn btn-outline-secondary active" onclick="showBodyTab('edit')"><i class="bi bi-code-slash"></i> Editar HTML</button>
                            <button type="button" id="tab-prev-btn" class="btn btn-outline-secondary" onclick="showBodyTab('prev')"><i class="bi bi-eye"></i> Pré-visualizar</button>
                        </div>
                        <?php if ($canEdit && !empty($templates)): ?>
                        <div class="d-flex align-items-center gap-1">
                            <select id="c-template" class="form-select form-select-sm" style="width:auto;font-size:0.8rem;">
                                <option value="">Carregar de um modelo…</option>
                                <?php foreach ($templates as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= escape($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="loadTemplate()">Carregar</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <textarea id="c-body" class="form-control" style="min-height:52vh;font-family:ui-monospace,Consolas,monospace;font-size:.85rem;" <?= $canEdit?'':'disabled' ?>><?= escape($contract['body'] ?? '') ?></textarea>
                    <div id="c-preview" class="border rounded p-4 bg-white" style="min-height:52vh;display:none;overflow:auto;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:60vh;overflow:auto;">
                    <?php if (empty($events)): ?><p class="text-muted small mb-0">Sem eventos.</p>
                    <?php else: foreach ($events as $e): ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <div class="small"><?= escape($e['description'] ?? $e['event_type']) ?></div>
                        <small class="text-muted"><?= escape($e['user_name'] ?? 'Cliente/Sistema') ?> · <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></small>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const CID = <?= (int)$contract['id'] ?>;

// Alterna entre editar o HTML e ver a pré-visualização formatada.
function showBodyTab(which) {
    const ta = document.getElementById('c-body');
    const prev = document.getElementById('c-preview');
    const bEdit = document.getElementById('tab-edit-btn');
    const bPrev = document.getElementById('tab-prev-btn');
    if (which === 'prev') {
        prev.innerHTML = ta.value || '<p class="text-muted">Sem conteúdo.</p>';
        prev.style.display = 'block';
        ta.style.display = 'none';
        bPrev.classList.add('active'); bEdit.classList.remove('active');
    } else {
        prev.style.display = 'none';
        ta.style.display = 'block';
        bEdit.classList.add('active'); bPrev.classList.remove('active');
    }
}
// Carrega o corpo de um modelo JÁ com as variáveis preenchidas pelos dados do
// contrato (o backend faz a substituição dos {{...}}).
async function loadTemplate() {
    const sel = document.getElementById('c-template');
    if (!sel || !sel.value) { alert('Escolha um modelo.'); return; }
    const ta = document.getElementById('c-body');
    if (ta.value.trim() && !confirm('Isto substitui o conteúdo atual do contrato pelo modelo (com as variáveis preenchidas). Continuar?')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('contract_id', CID);
    fd.append('template_id', sel.value);
    const r = await fetch(`${BASE}contract/renderTemplate`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    ta.value = r.body || '';
}

function cbody() {
    return { title: document.getElementById('c-title').value.trim(), body: document.getElementById('c-body').value,
        client_email: document.getElementById('c-email').value.trim(), client_phone: document.getElementById('c-phone').value.trim() };
}
let SAVING = false;
async function saveContract() {
    if (SAVING) return false;
    SAVING = true;
    const btn = document.getElementById('btn-save');
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...'; }
    try {
        const r = await fetch(`${BASE}contract/save/${CID}`, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF}, body: JSON.stringify(cbody()) })
            .then(x=>x.json());
        if (r.error) { alert(r.error); return false; }
        if (btn) {
            btn.classList.remove('btn-success'); btn.classList.add('btn-outline-success');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Salvo!';
            setTimeout(() => { btn.classList.add('btn-success'); btn.classList.remove('btn-outline-success'); btn.innerHTML = original; }, 1500);
        }
        return true;
    } catch (e) {
        alert('Falha de rede ao salvar.');
        if (btn) btn.innerHTML = original;
        return false;
    } finally {
        SAVING = false;
        if (btn) btn.disabled = false;
    }
}
async function sendReview() {
    if (!await saveContract()) return;
    if (!confirm('Enviar o contrato ao cliente para aprovação?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}contract/sendForReview/${CID}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }

    let msg = 'Contrato enviado para aprovação.\n';
    const canais = [];
    if (r.sent_whats) canais.push('WhatsApp');
    if (r.sent_email) canais.push('e-mail');
    if (canais.length) {
        msg += 'Enviado ao cliente por: ' + canais.join(' e ') + '.';
    } else if (r.no_contact) {
        msg += 'Atenção: o cliente não tem telefone/e-mail cadastrado. Copie o link e envie manualmente:\n' + r.link;
    } else {
        msg += 'Não foi possível enviar automaticamente (verifique WhatsApp/SMTP). Envie o link manualmente:\n' + r.link;
    }
    alert(msg); location.reload();
}
async function sendSignature() {
    const btn = document.getElementById('sign-confirm');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    // Signatários da empresa marcados (o cliente sempre assina, no backend).
    document.querySelectorAll('.company-signer:checked').forEach(cb => fd.append('company_signers[]', cb.value));
    const r = await fetch(`${BASE}contract/sendForSignature/${CID}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    btn.disabled = false; btn.innerHTML = orig;
    if (r.error) { alert(r.error); return; }
    let msg = 'Contrato enviado para assinatura (' + (r.signers || 1) + ' signatário(s), ' + (r.notified || 0) + ' notificado(s) por e-mail).';
    if (r.sent_whats) msg += '\nLink de assinatura também enviado ao cliente por WhatsApp.';
    if (r.sign_url) msg += '\nLink de assinatura do cliente:\n' + r.sign_url;
    if (r.fails && r.fails.length) msg += '\n\nFalhas: ' + r.fails.join('; ');
    alert(msg); location.reload();
}
async function startFinance() {
    if (!confirm('Criar o projeto financeiro a partir deste contrato assinado?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('contract_id', CID);
    const r = await fetch(`${BASE}finance/fromContract`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${BASE}finance/edit/${r.id}`;
}
</script>

<!-- Modal: enviar p/ assinatura (escolher signatários) -->
<div class="modal fade" id="signModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-pen"></i> Enviar para assinatura</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-2"><strong>Cliente</strong> (assina sempre):<br>
           <span class="text-muted"><?= escape($contract['client_name'] ?? 'Cliente') ?> · <?= escape($contract['client_email'] ?? 'sem e-mail') ?></span></p>
        <?php if (empty($contract['client_email'])): ?>
        <div class="alert alert-warning py-2 small">O cliente não tem e-mail. Informe o e-mail do cliente e salve antes de enviar.</div>
        <?php endif; ?>
        <hr class="my-2">
        <label class="form-label small fw-medium mb-1">Signatários da empresa (opcional)</label>
        <?php if (empty($companySigners)): ?>
        <p class="small text-muted mb-0">Nenhum signatário da empresa cadastrado.
           <a href="<?= baseUrl('contract/signers') ?>" target="_blank">Cadastrar</a>.</p>
        <?php else: foreach ($companySigners as $cs): ?>
        <div class="form-check">
            <input class="form-check-input company-signer" type="checkbox" value="<?= (int)$cs['id'] ?>" id="cs-<?= (int)$cs['id'] ?>" <?= ((int)$cs['is_default'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="cs-<?= (int)$cs['id'] ?>">
                <?= escape($cs['name']) ?> <span class="text-muted">· <?= escape($cs['email']) ?><?= !empty($cs['role_label']) ? ' · ' . escape($cs['role_label']) : '' ?></span>
            </label>
        </div>
        <?php endforeach; endif; ?>
        <small class="text-muted d-block mt-2">Cada signatário recebe o e-mail da ClickSign para assinar. O link do cliente também é enviado por WhatsApp (se tiver telefone).</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="sign-confirm" class="btn btn-sm btn-dark" onclick="sendSignature()"><i class="bi bi-pen"></i> Enviar p/ assinatura</button>
      </div>
    </div>
  </div>
</div>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
