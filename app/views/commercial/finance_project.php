<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$kindLabel = ['entry'=>'Entrada','installment'=>'Parcela','recurring'=>'Recorrente'];
$stLabel = ['pending'=>'Pendente','paid'=>'Paga','overdue'=>'Vencida','cancelled'=>'Cancelada'];
$stBadge = ['pending'=>'secondary','paid'=>'success','overdue'=>'danger','cancelled'=>'dark'];
$accById = [];
foreach ($accounts as $a) $accById[(int)$a['id']] = $a;
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
// Sem cobranças ainda, o formulário já vem com a sugestão da proposta aceita.
$sg = $suggestion;
$prefill = empty($charges) && $sg;
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Financeiro — Projeto #<?= (int)$project['id'] ?></h5>
            <small class="text-muted"><?= escape($project['title']) ?> · Total R$ <?= number_format((float)$project['total_value'],2,',','.') ?></small>
        </div>
        <a href="<?= baseUrl('finance') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if ($canOnboard && $onboardingId): ?>
    <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-unlock"></i> Entrada paga — onboarding #<?= (int)$onboardingId ?> criado.</span>
        <a class="btn btn-sm btn-success" href="<?= baseUrl('onboarding/edit/' . (int)$onboardingId) ?>"><i class="bi bi-rocket-takeoff"></i> Abrir onboarding</a>
    </div>
    <?php elseif ($canOnboard): ?>
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
                    <?php if ($sg): ?>
                    <div class="alert alert-info py-2 small mb-2" id="plan-suggestion">
                        <strong>Sugestão da proposta aceita:</strong> pontual <?= $brl($sg['once_total']) ?>,
                        entrada de <?= rtrim(rtrim(number_format($sg['entry_percent'], 2, ',', ''), '0'), ',') ?>% (<?= $brl($sg['entry']) ?>),
                        entrega <?= $brl($sg['delivery']) ?><?= $sg['delivery_due'] ? ' em ' . date('d/m/Y', strtotime($sg['delivery_due'])) : '' ?><?php if ($sg['monthly'] > 0): ?>,
                        mensalidade <?= $brl($sg['monthly']) ?><?= $sg['monthly_months'] ? ' (plano ' . (int)$sg['monthly_months'] . ' meses)' : '' ?><?php endif; ?>.
                        <?php if (empty($sg['entry_percent_configured'])): ?>
                        <br><span class="text-muted">Percentual de entrada padrão (50%). A condição de pagamento padrão ainda não foi definida em Configurações.</span>
                        <?php endif; ?>
                        <?php if (!$prefill): ?><br><button type="button" class="btn btn-link btn-sm p-0" onclick="applySuggestion()">Preencher o formulário com a sugestão</button><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Valor pontual</label><input type="number" step="0.01" id="f-total" class="form-control form-control-sm" value="<?= $prefill ? $sg['once_total'] : (float)$project['total_value'] ?>" oninput="syncEntry('pct')"></div>
                        <div class="col-3"><label class="form-label small">Entrada %</label><input type="number" step="0.01" min="0" max="100" id="f-entry-pct" class="form-control form-control-sm" value="<?= $prefill ? $sg['entry_percent'] : '' ?>" oninput="syncEntry('pct')"></div>
                        <div class="col-3"><label class="form-label small">Entrada (R$)</label><input type="number" step="0.01" id="f-entry" class="form-control form-control-sm" value="<?= $prefill ? $sg['entry'] : '' ?>" oninput="syncEntry('value')"></div>
                        <div class="col-6"><label class="form-label small">Vencimento da entrada</label><input type="date" id="f-entry-due" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">Método entrada</label>
                            <select id="f-entry-method" class="form-select form-select-sm"><option value="pix">Pix</option><option value="boleto">Boleto</option><option value="cartao">Cartão</option></select>
                        </div>
                        <div class="col-6"><label class="form-label small">Nº de parcelas (restante)</label><input type="number" min="0" id="f-inst" class="form-control form-control-sm" value="<?= $prefill && $sg['delivery'] > 0 ? 1 : 0 ?>"></div>
                        <div class="col-6"><label class="form-label small">1ª parcela (entrega)</label><input type="date" id="f-inst-due" class="form-control form-control-sm" value="<?= $prefill ? escape($sg['delivery_due'] ?? '') : '' ?>"></div>
                        <div class="col-6"><label class="form-label small">Intervalo (dias)</label><input type="number" min="0" id="f-inst-interval" class="form-control form-control-sm" value="30"></div>
                        <div class="col-6"><label class="form-label small">Método parcelas</label>
                            <select id="f-inst-method" class="form-select form-select-sm"><option value="boleto">Boleto</option><option value="pix">Pix</option><option value="cartao">Cartão</option></select>
                        </div>
                        <div class="col-4"><label class="form-label small">Mensalidade (R$)</label><input type="number" step="0.01" min="0" id="f-monthly" class="form-control form-control-sm" value="<?= $prefill ? $sg['monthly'] : '' ?>"></div>
                        <div class="col-4"><label class="form-label small">1ª mensalidade</label><input type="date" id="f-monthly-start" class="form-control form-control-sm"></div>
                        <div class="col-4"><label class="form-label small">Método</label>
                            <select id="f-monthly-method" class="form-select form-select-sm"><option value="boleto">Boleto</option><option value="pix">Pix</option><option value="cartao">Cartão</option></select>
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Salvar de novo troca só as cobranças pendentes que ainda não foram ao Asaas. Pagas e emitidas ficam.</p>
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
                        <thead class="table-light"><tr><th>Tipo</th><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Método</th><th>Conta Asaas</th><th>Status</th><th></th></tr></thead>
                        <tbody id="charges-body">
                            <?php foreach ($charges as $c): ?>
                            <tr>
                                <td><?= $kindLabel[$c['kind']] ?? $c['kind'] ?></td>
                                <td><?= escape($c['description'] ?? '') ?></td>
                                <td>R$ <?= number_format((float)$c['amount'],2,',','.') ?></td>
                                <td><?= $c['due_date'] ? date('d/m/Y', strtotime($c['due_date'])) : '—' ?></td>
                                <td><?= escape(strtoupper($c['method'] ?? '—')) ?></td>
                                <?php $acc = $accById[(int)($c['account_id'] ?? 0)] ?? null; ?>
                                <td class="small"><?php if ($acc): ?><?= escape($acc['name']) ?>
                                    <span class="badge <?= (int)$acc['sandbox'] === 1 ? 'bg-warning text-dark' : 'bg-danger' ?> asaas-env"><?= (int)$acc['sandbox'] === 1 ? 'SANDBOX' : 'PRODUÇÃO' ?></span>
                                    <?php else: ?><span class="text-muted">sem conta</span><?php endif; ?></td>
                                <td><span class="badge bg-<?= $stBadge[$c['status']] ?? 'secondary' ?>"><?= $stLabel[$c['status']] ?? $c['status'] ?></span>
                                    <?php if (!empty($c['asaas_subscription_id']) && $c['kind'] === 'recurring' && empty($c['asaas_charge_id'])): ?><br><small class="text-success">Assinatura ativa</small><?php endif; ?>
                                    <?php if (!empty($c['issue_error'])): ?><br><small class="text-danger issue-error"><?= escape($c['issue_error']) ?></small><?php endif; ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if (!empty($c['invoice_url'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= escape($c['invoice_url']) ?>" target="_blank" rel="noopener">Link de pagamento</a>
                                    <?php endif; ?>
                                    <?php if ($c['status'] === 'pending' && empty($c['asaas_charge_id']) && empty($c['asaas_subscription_id']) && $acc): ?>
                                    <button class="btn btn-sm btn-dark btn-issue" data-charge="<?= (int)$c['id'] ?>" onclick="issueCharge(this, <?= (int)$c['id'] ?>, <?= (int)$acc['sandbox'] === 1 ? 'true' : 'false' ?>)">Emitir no Asaas</button>
                                    <?php endif; ?>
                                    <?php if ($c['status'] === 'pending' && !($c['kind'] === 'recurring' && !empty($c['asaas_subscription_id']))): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="markPaid(<?= (int)$c['id'] ?>)">Marcar paga</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($charges)): ?><tr><td colspan="8" class="text-center text-muted py-3">Nenhuma cobrança. Gere o plano ao lado.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($events)): ?>
            <div class="card mt-3">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body small" style="max-height:40vh;overflow:auto;" id="finance-events">
                    <?php foreach ($events as $e): ?>
                    <div class="mb-1 pb-1 border-bottom"><?= escape($e['description'] ?? $e['event_type']) ?>
                        <span class="text-muted">· <?= escape($e['user_name'] ?? 'Sistema') ?> · <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
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
        recurring_amount: document.getElementById('f-monthly').value,
        recurring_start: document.getElementById('f-monthly-start').value,
        recurring_method: document.getElementById('f-monthly-method').value,
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
const SUGGESTION = <?= json_encode($sg) ?>;
function syncEntry(from) {
    const total = parseFloat(document.getElementById('f-total').value) || 0;
    const pct = document.getElementById('f-entry-pct'), val = document.getElementById('f-entry');
    if (from === 'pct' && pct.value !== '') val.value = (Math.round(total * parseFloat(pct.value)) / 100).toFixed(2);
    if (from === 'value' && total > 0 && val.value !== '') pct.value = (parseFloat(val.value) / total * 100).toFixed(2);
}
function applySuggestion() {
    if (!SUGGESTION) return;
    document.getElementById('f-total').value = SUGGESTION.once_total;
    document.getElementById('f-entry-pct').value = SUGGESTION.entry_percent;
    document.getElementById('f-entry').value = SUGGESTION.entry;
    document.getElementById('f-inst').value = SUGGESTION.delivery > 0 ? 1 : 0;
    document.getElementById('f-inst-due').value = SUGGESTION.delivery_due || '';
    document.getElementById('f-monthly').value = SUGGESTION.monthly || '';
}
async function issueCharge(btn, chargeId, sandbox) {
    const env = sandbox ? 'SANDBOX (teste)' : 'PRODUÇÃO (cobrança real)';
    if (!confirm('Emitir esta cobrança no Asaas, conta em ' + env + '?')) return;
    btn.disabled = true;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}finance/issue/${chargeId}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); location.reload(); return; }
    location.reload();
}
async function markPaid(chargeId) {
    if (!confirm('Marcar esta cobrança como paga?')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${BASE}finance/markPaid/${chargeId}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    if (r.onboarding_unlocked) alert('Entrada paga! Onboarding' + (r.onboarding_id ? ' #' + r.onboarding_id + ' criado.' : ' liberado.'));
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
