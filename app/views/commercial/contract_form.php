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
            <button class="btn btn-sm btn-success" onclick="saveContract()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button class="btn btn-sm btn-primary" onclick="sendReview()"><i class="bi bi-send"></i> Enviar p/ aprovação</button>
            <?php endif; ?>
            <?php if ($canSign): ?>
            <button class="btn btn-sm btn-dark" onclick="sendSignature()"><i class="bi bi-pen"></i> Enviar p/ assinatura (ClickSign)</button>
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
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small mb-0">Corpo do contrato (HTML)</label>
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
async function saveContract() {
    const r = await fetch(`${BASE}contract/save/${CID}`, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF}, body: JSON.stringify(cbody()) })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return false; } return true;
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
    if (!confirm('Enviar o contrato para assinatura na ClickSign?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}contract/sendForSignature/${CID}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    alert('Contrato enviado para assinatura. Acompanhe o status aqui — será atualizado quando o cliente assinar.'); location.reload();
}
async function startFinance() {
    if (!confirm('Criar o projeto financeiro a partir deste contrato assinado?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('contract_id', CID);
    const r = await fetch(`${BASE}finance/fromContract`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${BASE}finance/edit/${r.id}`;
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
