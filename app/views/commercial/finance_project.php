<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$kindLabel = ['entry'=>'Entrada','installment'=>'Parcela','recurring'=>'Recorrente'];
$stLabel = ['pending'=>'Pendente','paid'=>'Paga','overdue'=>'Vencida','cancelled'=>'Cancelada'];
$stBadge = ['pending'=>'secondary','paid'=>'success','overdue'=>'danger','cancelled'=>'dark'];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Financeiro — Projeto #<?= (int)$project['id'] ?></h5>
            <small class="text-muted"><?= escape($project['title']) ?> · Total R$ <?= number_format((float)$project['total_value'],2,',','.') ?></small>
        </div>
        <a href="<?= baseUrl('finance') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($canOnboard): ?>
    <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-unlock"></i> Entrada paga — onboarding liberado.</span>
        <button class="btn btn-sm btn-success" onclick="startOnboarding()"><i class="bi bi-rocket-takeoff"></i> Iniciar onboarding</button>
    </div>
    <?php else: ?>
    <div class="alert alert-warning py-2"><i class="bi bi-lock"></i> Onboarding bloqueado até a entrada ser paga.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2"><strong>Definir plano de pagamento</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Valor total</label><input type="number" step="0.01" id="f-total" class="form-control form-control-sm" value="<?= (float)$project['total_value'] ?>"></div>
                        <div class="col-6"><label class="form-label small">Entrada (R$)</label><input type="number" step="0.01" id="f-entry" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Vencimento da entrada</label><input type="date" id="f-entry-due" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Método entrada</label>
                            <select id="f-entry-method" class="form-select form-select-sm"><option value="pix">Pix</option><option value="boleto">Boleto</option><option value="cartao">Cartão</option></select>
                        </div>
                        <div class="col-6"><label class="form-label small">Nº de parcelas (restante)</label><input type="number" min="0" id="f-inst" class="form-control form-control-sm" value="0"></div>
                        <div class="col-6"><label class="form-label small">1ª parcela</label><input type="date" id="f-inst-due" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Intervalo (dias)</label><input type="number" min="0" id="f-inst-interval" class="form-control form-control-sm" value="30"></div>
                        <div class="col-6"><label class="form-label small">Método parcelas</label>
                            <select id="f-inst-method" class="form-select form-select-sm"><option value="boleto">Boleto</option><option value="pix">Pix</option><option value="cartao">Cartão</option></select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="f-notify" checked>
                        <label class="form-check-label small" for="f-notify">Avisar o cliente (WhatsApp/e-mail) com o resumo do plano</label>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2" onclick="savePlan()"><i class="bi bi-calculator"></i> Gerar plano</button>
                    <?php if (empty($accounts)): ?>
                    <p class="text-muted small mt-2 mb-0">Nenhuma conta Asaas cadastrada ainda. <a href="<?= baseUrl('finance/accounts') ?>">Cadastrar</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header py-2"><strong>Cobranças</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Tipo</th><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Método</th><th>Status</th><th></th></tr></thead>
                        <tbody id="charges-body">
                            <?php foreach ($charges as $c): ?>
                            <tr>
                                <td><?= $kindLabel[$c['kind']] ?? $c['kind'] ?></td>
                                <td><?= escape($c['description'] ?? '') ?></td>
                                <td>R$ <?= number_format((float)$c['amount'],2,',','.') ?></td>
                                <td><?= $c['due_date'] ? date('d/m/Y', strtotime($c['due_date'])) : '—' ?></td>
                                <td><?= escape(strtoupper($c['method'] ?? '—')) ?></td>
                                <td><span class="badge bg-<?= $stBadge[$c['status']] ?? 'secondary' ?>"><?= $stLabel[$c['status']] ?? $c['status'] ?></span></td>
                                <td class="text-end">
                                    <?php if ($c['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="markPaid(<?= (int)$c['id'] ?>)">Marcar paga</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($charges)): ?><tr><td colspan="7" class="text-center text-muted py-3">Nenhuma cobrança. Gere o plano ao lado.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= baseUrl("") ?>';
const CSRF = '<?= csrf_token() ?>';
const PID = <?= (int)$project['id'] ?>;
async function savePlan() {
    const body = {
        total: document.getElementById('f-total').value,
        entry: document.getElementById('f-entry').value,
        entry_due: document.getElementById('f-entry-due').value,
        entry_method: document.getElementById('f-entry-method').value,
        installments: document.getElementById('f-inst').value,
        first_installment_due: document.getElementById('f-inst-due').value,
        installment_interval_days: document.getElementById('f-inst-interval').value,
        installment_method: document.getElementById('f-inst-method').value,
        notify_client: document.getElementById('f-notify').checked ? 1 : 0,
    };
    const r = await fetch(`${BASE}finance/savePlan/${PID}`, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF}, body: JSON.stringify(body) })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    const d = r.delivery || {};
    if (body.notify_client) {
        const canais = [];
        if (d.sent_whats) canais.push('WhatsApp');
        if (d.sent_email) canais.push('e-mail');
        if (canais.length) alert('Plano gerado. Cliente avisado por: ' + canais.join(' e ') + '.');
        else if (d.no_contact) alert('Plano gerado. O cliente não tem telefone/e-mail no contrato — avise manualmente.');
        else alert('Plano gerado, mas não foi possível avisar automaticamente (verifique WhatsApp/SMTP).');
    }
    location.reload();
}
async function markPaid(chargeId) {
    if (!confirm('Marcar esta cobrança como paga?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}finance/markPaid/${chargeId}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    if (r.onboarding_unlocked) alert('Entrada paga! Onboarding liberado.');
    location.reload();
}
async function startOnboarding() {
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('project_id', PID);
    const r = await fetch(`${BASE}onboarding/fromProject`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${BASE}onboarding/edit/${r.id}`;
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>
