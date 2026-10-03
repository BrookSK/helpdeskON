<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$ctLabels = [
    'dev_zero' => 'Desenvolvimento do zero', 'dev_manutencao' => 'Desenvolvimento/manutenção',
    'suporte' => 'Suporte', 'dev_suporte' => 'Desenvolvimento + suporte', 'outro' => 'Outro',
];
$isTerminal = in_array($proposal['status'], ['accepted','rejected','cancelled'], true);
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Proposta #<?= (int)$proposal['id'] ?></h5>
            <small class="text-muted"><?= escape($proposal['title']) ?> · <span id="p-status-label"><?= escape($proposal['status']) ?></span></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= baseUrl('proposal') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <?php if (!$isTerminal): ?>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="saveProposal()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-send" class="btn btn-sm btn-primary" onclick="sendProposal()"><i class="bi bi-send"></i> Enviar ao cliente</button>
            <?php endif; ?>
            <?php if ($proposal['status'] === 'accepted'): ?>
            <button class="btn btn-sm btn-dark" onclick="genContract()"><i class="bi bi-file-earmark-check"></i> Gerar contrato</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($proposal['status'] === 'accepted'): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Proposta aceita pelo cliente. Gere o contrato para seguir a esteira.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Dados</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">Título</label><input id="p-title" class="form-control form-control-sm" value="<?= escape($proposal['title']) ?>"></div>
                        <div class="col-md-6"><label class="form-label small">Tipo de contratação</label>
                            <select id="p-contract" class="form-select form-select-sm">
                                <option value="">—</option>
                                <?php foreach ($contractTypes as $ct): ?>
                                <option value="<?= $ct ?>" <?= ($proposal['contract_type'] === $ct) ? 'selected' : '' ?>><?= $ctLabels[$ct] ?? $ct ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5"><label class="form-label small">Cliente</label><input id="p-client-name" class="form-control form-control-sm" value="<?= escape($proposal['client_name'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">E-mail</label><input id="p-client-email" class="form-control form-control-sm" value="<?= escape($proposal['client_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small">Telefone</label><input id="p-client-phone" class="form-control form-control-sm" value="<?= escape($proposal['client_phone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Validade</label><input type="date" id="p-validity" class="form-control form-control-sm" value="<?= escape($proposal['validity_date'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label small">Observações</label><textarea id="p-obs" class="form-control form-control-sm" rows="2"><?= escape($proposal['observations'] ?? '') ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Itens</strong>
                    <div class="d-flex gap-2">
                        <select id="svc-pick" class="form-select form-select-sm" style="width:auto;">
                            <option value="">+ Do catálogo…</option>
                            <?php foreach ($services as $s): ?>
                            <option value='<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><?= escape($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="bi bi-plus"></i> Item</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Descrição / escopo</th><th style="width:90px;">Horas</th><th style="width:120px;">R$/hora</th><th style="width:130px;">Total</th><th style="width:40px;"></th></tr>
                        </thead>
                        <tbody id="items-body"></tbody>
                        <tfoot><tr class="table-light"><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold" id="grand-total">R$ 0,00</td><td></td></tr></tfoot>
                    </table>
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
                        <small class="text-muted"><?= escape($e['user_name'] ?? 'Cliente') ?> · <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></small>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PROP_BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const PROPOSAL_ID = <?= (int)$proposal['id'] ?>;
const EXISTING_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

function money(v) { return 'R$ ' + (Number(v)||0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function addRow(data) {
    data = data || {};
    const tb = document.getElementById('items-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input class="form-control form-control-sm it-desc" placeholder="Descrição" value="${(data.description||'').replace(/"/g,'&quot;')}">
            <textarea class="form-control form-control-sm mt-1 it-scope" rows="1" placeholder="Escopo (opcional)">${data.scope||''}</textarea>
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm it-hours" value="${data.hours||''}" oninput="recalc()"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm it-rate" value="${data.hourly_rate||''}" oninput="recalc()"></td>
        <td class="it-total">${money(data.amount||0)}</td>
        <td><button class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc();"><i class="bi bi-x"></i></button></td>`;
    tb.appendChild(tr);
    recalc();
}
function recalc() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const h = parseFloat(tr.querySelector('.it-hours').value) || 0;
        const r = parseFloat(tr.querySelector('.it-rate').value) || 0;
        const amt = (h > 0 && r > 0) ? (h * r) : 0;
        tr.querySelector('.it-total').textContent = money(amt);
        tr.dataset.amount = amt;
        total += amt;
    });
    document.getElementById('grand-total').textContent = money(total);
}
function collectItems() {
    const items = [];
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const desc = tr.querySelector('.it-desc').value.trim();
        if (!desc) return;
        items.push({
            description: desc,
            scope: tr.querySelector('.it-scope').value.trim(),
            hours: tr.querySelector('.it-hours').value,
            hourly_rate: tr.querySelector('.it-rate').value,
            amount: tr.dataset.amount || 0,
        });
    });
    return items;
}
function body() {
    return {
        title: document.getElementById('p-title').value.trim(),
        contract_type: document.getElementById('p-contract').value,
        client_name: document.getElementById('p-client-name').value.trim(),
        client_email: document.getElementById('p-client-email').value.trim(),
        client_phone: document.getElementById('p-client-phone').value.trim(),
        validity_date: document.getElementById('p-validity').value,
        observations: document.getElementById('p-obs').value.trim(),
        items: collectItems(),
    };
}
// Trava para evitar salvamentos duplicados (clique repetido enquanto salva).
let SAVING = false;
async function saveProposal() {
    if (SAVING) return false;            // já há um save em andamento
    SAVING = true;
    const btn = document.getElementById('btn-save');
    const original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...'; }
    try {
        const r = await fetch(`${PROP_BASE}proposal/save/${PROPOSAL_ID}`, {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify(body())
        }).then(x => x.json());
        if (r.error) { alert(r.error); return false; }
        document.getElementById('grand-total').textContent = money(r.total);
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
async function sendProposal() {
    if (!await saveProposal()) return;
    if (!confirm('Enviar a proposta ao cliente? Ela ficará disponível pelo link público.')) return;
    const btn = document.getElementById('btn-send');
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...'; }
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${PROP_BASE}proposal/send/${PROPOSAL_ID}`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(x => x.json()).catch(() => ({error:'Falha de rede'}));
    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    if (r.error) { alert(r.error); return; }

    // Feedback do que foi enviado ao cliente.
    let msg = 'Proposta marcada como enviada.\n';
    const canais = [];
    if (r.sent_whats) canais.push('WhatsApp');
    if (r.sent_email) canais.push('e-mail');
    if (canais.length) {
        msg += 'Enviada ao cliente por: ' + canais.join(' e ') + '.';
    } else if (r.no_contact) {
        msg += 'Atenção: o cliente não tem telefone/e-mail cadastrado — nada foi enviado automaticamente. Copie o link e envie manualmente:\n' + r.link;
    } else {
        msg += 'Não foi possível enviar automaticamente (verifique WhatsApp/SMTP). Envie o link manualmente:\n' + r.link;
    }
    alert(msg);
    location.reload();
}
// Adicionar item a partir do catálogo.
document.getElementById('svc-pick').addEventListener('change', function() {
    if (!this.value) return;
    try { const s = JSON.parse(this.value); addRow({ description: s.name, scope: s.description, hours: s.est_hours, hourly_rate: s.hourly_rate }); } catch(e){}
    this.value = '';
});
// Carrega os itens existentes.
if (EXISTING_ITEMS.length) EXISTING_ITEMS.forEach(it => addRow(it)); else addRow();

const CONTRACT_TEMPLATES = <?= json_encode($contractTemplates ?? [], JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
async function genContract() {
    let templateId = '';
    if (CONTRACT_TEMPLATES.length) {
        const opts = CONTRACT_TEMPLATES.map(t => `${t.id}: ${t.name}`).join('\n');
        const pick = prompt('Informe o ID do modelo de contrato (ou deixe vazio para em branco):\n' + opts);
        if (pick === null) return;
        templateId = pick.trim();
    }
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('proposal_id', PROPOSAL_ID);
    if (templateId) fd.append('template_id', templateId);
    const r = await fetch(`${PROP_BASE}contract/fromProposal`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${PROP_BASE}contract/edit/${r.id}`;
}
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
