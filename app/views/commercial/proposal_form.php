<?php $pageTitle = 'Proposta - ON Solutions Helpdesk'; $currentPage = 'proposals'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$ctLabels = [
    'dev_zero' => 'Desenvolvimento do zero', 'dev_manutencao' => 'Desenvolvimento/manutenção',
    'suporte' => 'Suporte', 'dev_suporte' => 'Desenvolvimento + suporte', 'outro' => 'Outro',
];
$isTerminal = in_array($proposal['status'], ['accepted','rejected','cancelled'], true);
$statusLabels = [
    'draft' => 'Em elaboração', 'ready' => 'Montada', 'sent' => 'Enviada',
    'awaiting' => 'Aguardando', 'accepted' => 'Aceita', 'rejected' => 'Recusada', 'cancelled' => 'Cancelada',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Proposta #<?= (int)$proposal['id'] ?></h5>
            <small class="text-muted"><?= escape($proposal['title']) ?> · <span id="p-status-label"><?= escape($statusLabels[$proposal['status']] ?? $proposal['status']) ?></span></small>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?= baseUrl('proposal') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPdf()"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</button>
            <?php if (!$isTerminal): ?>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="saveProposal()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-send" class="btn btn-sm btn-primary" onclick="sendProposal()"><i class="bi bi-send"></i> Enviar ao cliente</button>
            <?php endif; ?>
            <?php if ($proposal['status'] === 'accepted'): ?>
            <button class="btn btn-sm btn-dark" onclick="genContract()"><i class="bi bi-file-earmark-check"></i> Gerar contrato</button>
            <?php endif; ?>
            <?php if ($proposal['status'] === 'rejected'): ?>
            <button class="btn btn-sm btn-warning" onclick="reopenProposal()"><i class="bi bi-arrow-counterclockwise"></i> Reabrir para editar</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($proposal['status'] === 'accepted'): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Proposta aceita pelo cliente. Gere o contrato para seguir a esteira.</div>
    <?php endif; ?>

    <?php if ($proposal['status'] === 'rejected'): ?>
    <div class="alert alert-danger py-2">
        <i class="bi bi-x-circle"></i> <strong>Proposta recusada pelo cliente.</strong>
        <?php if (!empty($proposal['reject_reason'])): ?><br><span class="small">Motivo: <?= escape($proposal['reject_reason']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Dados da proposta</strong>
                    <a href="<?= baseUrl('proposal/blocks') ?>" class="small">Editar conteúdo fixo</a>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label small">Título</label><input id="p-title" class="form-control form-control-sm" value="<?= escape($proposal['title']) ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Tipo de contratação</label>
                            <select id="p-contract" class="form-select form-select-sm">
                                <option value="">—</option>
                                <?php foreach ($contractTypes as $ct): ?>
                                <option value="<?= $ct ?>" <?= ($proposal['contract_type'] === $ct) ? 'selected' : '' ?>><?= $ctLabels[$ct] ?? $ct ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Lead</label>
                            <select id="p-lead" class="form-select form-select-sm">
                                <option value="">— Sem vínculo —</option>
                                <?php foreach (($leads ?? []) as $l): ?>
                                <option value="<?= (int)$l['id'] ?>"
                                    data-name="<?= escape($l['contact_name'] ?? '') ?>"
                                    data-email="<?= escape($l['lead_email'] ?? '') ?>"
                                    data-phone="<?= escape($l['phone'] ?? '') ?>"
                                    <?= ((int)($proposal['contact_id'] ?? 0) === (int)$l['id']) ? 'selected' : '' ?>>
                                    <?= escape($l['contact_name'] ?: ('Contato #' . (int)$l['id'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Cliente relacionado</label>
                            <select id="p-company" class="form-select form-select-sm">
                                <option value="">— Sem cliente —</option>
                                <?php foreach (($companies ?? []) as $co): ?>
                                <option value="<?= (int)$co['id'] ?>"
                                    data-name="<?= escape($co['name'] ?? '') ?>"
                                    data-email="<?= escape($co['email'] ?? '') ?>"
                                    data-phone="<?= escape($co['phone'] ?? '') ?>"
                                    <?= ((int)($proposal['company_id'] ?? 0) === (int)$co['id']) ? 'selected' : '' ?>>
                                    <?= escape($co['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5"><label class="form-label small">Nome</label><input id="p-client-name" class="form-control form-control-sm" value="<?= escape($proposal['client_name'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">E-mail</label><input id="p-client-email" class="form-control form-control-sm" value="<?= escape($proposal['client_email'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label small">Telefone</label><input id="p-client-phone" class="form-control form-control-sm" value="<?= escape($proposal['client_phone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Validade</label><input type="date" id="p-validity" class="form-control form-control-sm" value="<?= escape($proposal['validity_date'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Início do desenvolvimento</label><input type="date" id="p-dev-start" class="form-control form-control-sm" value="<?= escape($proposal['dev_start'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Fim do desenvolvimento</label><input type="date" id="p-dev-end" class="form-control form-control-sm" value="<?= escape($proposal['dev_end'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label small">Observações do investimento <span class="text-muted">(aparece abaixo da tabela de valores)</span></label><textarea id="p-obs" class="form-control form-control-sm" rows="2"><?= escape($proposal['observations'] ?? '') ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header py-2"><strong>Entendimento do negócio</strong> <small class="text-muted">· muda por cliente. Campos vazios não aparecem no PDF.</small></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">Perfil da empresa</label><textarea class="form-control form-control-sm p-content" data-key="business_profile" rows="4"><?= escape($content['business_profile'] ?? '') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label small">Presença dos canais atuais</label><textarea class="form-control form-control-sm p-content" data-key="business_channels" rows="4"><?= escape($content['business_channels'] ?? '') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label small">Oportunidade</label><textarea class="form-control form-control-sm p-content" data-key="business_opportunity" rows="4"><?= escape($content['business_opportunity'] ?? '') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label small">Validar diagnóstico</label><textarea class="form-control form-control-sm p-content" data-key="business_diagnosis" rows="4"><?= escape($content['business_diagnosis'] ?? '') ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Serviços da contratação</strong>
                    <div class="d-flex gap-2">
                        <select id="svc-pick" class="form-select form-select-sm" style="width:auto;max-width:260px;">
                            <option value="">+ Do catálogo…</option>
                            <?php foreach ($services as $s):
                                $forecast = ProposalRules::forecast($s['est_hours'] ?? null, $s['hourly_rate'] ?? null);
                            ?>
                            <option value="<?= (int)$s['id'] ?>"><?= escape($s['name']) ?><?= $forecast !== null ? ' · R$ ' . number_format($forecast, 2, ',', '.') : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="applyPeriod()">Aplicar período</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Serviço, descrição e cronograma</th>
                                <th style="width:80px;">Qtd</th>
                                <th style="width:80px;">Horas</th>
                                <th style="width:110px;">Valor</th>
                                <th style="width:120px;">Total</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body"></tbody>
                        <tfoot><tr class="table-light"><td colspan="4" class="text-end fw-bold">Total</td><td class="fw-bold" id="grand-total">R$ 0,00</td><td></td></tr></tfoot>
                    </table>
                    </div>
                </div>
                <div class="card-footer small text-muted">O valor vem do catálogo como previsão e pode ser alterado nesta proposta. Com horas, o valor é por hora. Sem horas, o valor é o do item. Total = quantidade × horas × valor, ou quantidade × valor. Item mensal vira a mensalidade cheia (plano 3 meses); os descontos de 6 e 12 meses ficam no bloco abaixo. Trocar Pontual/Mensal não muda descrição nem valor, só a cobrança.</div>
            </div>

            <div class="card mt-3">
                <div class="card-header py-2"><strong>Projeção, retorno e fechamento</strong> <small class="text-muted">· muda por cliente</small></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label small">Preço do produto (R$)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm p-content" data-key="product_price" value="<?= escape($content['product_price'] ?? '') ?>"><small class="text-muted">Premissa da previsibilidade: quantos produtos a mais por mês cobrem o investimento, em margens de 30%, 50% e 70%.</small></div>
                        <div class="col-md-4"><label class="form-label small">Desconto plano 6 meses (%)</label><input type="number" step="0.1" min="0" max="90" class="form-control form-control-sm p-content" data-key="discount_6" value="<?= escape($content['discount_6'] ?? '20') ?>"><small class="text-muted">Sobre a mensalidade cheia (itens mensais). Padrão do modelo: 20%.</small></div>
                        <div class="col-md-4"><label class="form-label small">Desconto plano 12 meses (%)</label><input type="number" step="0.1" min="0" max="90" class="form-control form-control-sm p-content" data-key="discount_12" value="<?= escape($content['discount_12'] ?? '30') ?>"><small class="text-muted">Sobre a mensalidade cheia. Padrão do modelo: 30%.</small></div>
                        <div class="col-md-4"><label class="form-label small">Retorno mensal estimado (R$)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm p-content" data-key="expected_return" value="<?= escape($content['expected_return'] ?? '') ?>"><small class="text-muted">Só entra quando a proposta não tem mensalidade (projeto pontual).</small></div>
                        <div class="col-md-8"><label class="form-label small">Condição de pagamento</label><input class="form-control form-control-sm p-content" data-key="payment_method" value="<?= escape($content['payment_method'] ?? '') ?>" placeholder="Ex.: 50% na assinatura e 50% na entrega"><small class="text-muted">Entra no texto fixo de condições de pagamento.</small></div>
                        <div class="col-12"><label class="form-label small">Previsibilidade do retorno</label><textarea class="form-control form-control-sm p-content" data-key="return_text" rows="3"><?= escape($content['return_text'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label small">Renovação de serviços pontuais</label><textarea class="form-control form-control-sm p-content" data-key="renewal_text" rows="3"><?= escape($content['renewal_text'] ?? '') ?></textarea></div>
                        <div class="col-12"><label class="form-label small">Resumo final</label><textarea class="form-control form-control-sm p-content" data-key="summary_text" rows="3"><?= escape($content['summary_text'] ?? '') ?></textarea></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>PDF do cliente</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-2">O cliente recebe este link do PDF. A última página tem o link para aceitar ou recusar.</p>
                    <div class="input-group input-group-sm">
                        <input class="form-control" id="p-link" readonly value="<?= escape($publicLink) ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">Copiar</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2"><strong>Histórico</strong></div>
                <div class="card-body" style="max-height:50vh;overflow:auto;">
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
const CAN_EDIT = <?= $isTerminal ? 'false' : 'true' ?>;
const EXISTING_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
const SERVICES = <?= json_encode($services, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;

function money(v) { return 'R$ ' + (Number(v)||0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function addRow(data) {
    data = data || {};
    const tb = document.getElementById('items-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input class="form-control form-control-sm it-desc" placeholder="Nome do serviço">
            <textarea class="form-control form-control-sm mt-1 it-scope" rows="2" placeholder="Descrição"></textarea>
            <div class="d-flex gap-2 mt-1">
                <div style="flex:1"><label class="form-label small mb-0 text-muted">Cobrança</label><select class="form-select form-select-sm it-billing"><option value="once">Pontual</option><option value="monthly">Mensal</option></select></div>
                <div style="flex:1"><label class="form-label small mb-0 text-muted">Início</label><input type="date" class="form-control form-control-sm it-start"></div>
                <div style="flex:1"><label class="form-label small mb-0 text-muted">Fim</label><input type="date" class="form-control form-control-sm it-end"></div>
            </div>
            <div class="it-hint small text-muted mt-1"></div>
            <input type="hidden" class="it-service">
            <input type="hidden" class="it-hosting">
        </td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm it-qty"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm it-hours"></td>
        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm it-rate" title="Com horas, valor por hora. Sem horas, valor do item."></td>
        <td class="it-total text-nowrap"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger it-remove"><i class="bi bi-x"></i></button></td>`;
    const hours = (data.hours === null || data.hours === undefined) ? '' : data.hours;
    const rate = (data.hourly_rate === null || data.hourly_rate === undefined) ? '' : data.hourly_rate;
    const qty = (data.quantity === null || data.quantity === undefined || data.quantity === '') ? 1 : data.quantity;
    const hNum = parseFloat(hours) || 0;
    const rNum = parseFloat(rate) || 0;
    let unit = rate;
    if (!(hNum > 0 && rNum > 0) && data.amount) {
        const qn = parseFloat(qty) || 1;
        unit = (parseFloat(data.amount) / qn);
        if (!isFinite(unit)) unit = '';
    }
    tr.querySelector('.it-desc').value = data.description || '';
    tr.querySelector('.it-scope').value = data.scope || '';
    tr.querySelector('.it-qty').value = qty;
    tr.querySelector('.it-hours').value = hours === null ? '' : hours;
    tr.querySelector('.it-rate').value = unit === null ? '' : unit;
    tr.querySelector('.it-billing').value = data.billing === 'monthly' ? 'monthly' : 'once';
    tr.querySelector('.it-start').value = data.schedule_start || '';
    tr.querySelector('.it-end').value = data.schedule_end || '';
    tr.querySelector('.it-service').value = data.service_id || '';
    tr.querySelector('.it-hosting').value = data.is_hosting ? '1' : '0';
    if (data.hint) tr.querySelector('.it-hint').textContent = data.hint;
    tr.querySelector('.it-remove').addEventListener('click', () => { tr.remove(); recalc(); });
    tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));
    tb.appendChild(tr);
    recalc();
    return tr;
}
function lineAmount(tr) {
    const qRaw = parseFloat(tr.querySelector('.it-qty').value);
    const q = isNaN(qRaw) ? 1 : qRaw;
    const h = parseFloat(tr.querySelector('.it-hours').value) || 0;
    const r = parseFloat(tr.querySelector('.it-rate').value) || 0;
    if (h > 0 && r > 0) return q * h * r;
    if (r > 0) return q * r;
    return 0;
}
function recalc() {
    let total = 0;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const amt = lineAmount(tr);
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
        const h = tr.querySelector('.it-hours').value;
        const r = tr.querySelector('.it-rate').value;
        const hNum = parseFloat(h) || 0;
        const rNum = parseFloat(r) || 0;
        items.push({
            description: desc,
            scope: tr.querySelector('.it-scope').value.trim(),
            service_id: tr.querySelector('.it-service').value,
            quantity: tr.querySelector('.it-qty').value,
            hours: h,
            hourly_rate: hNum > 0 ? r : '',
            amount: hNum > 0 ? 0 : rNum,
            is_hosting: tr.querySelector('.it-hosting').value === '1' ? 1 : 0,
            billing: tr.querySelector('.it-billing').value,
            schedule_start: tr.querySelector('.it-start').value,
            schedule_end: tr.querySelector('.it-end').value,
        });
    });
    return items;
}
function collectContent() {
    const out = {};
    document.querySelectorAll('.p-content').forEach(el => { out[el.getAttribute('data-key')] = el.value; });
    return out;
}
function datesOk() {
    const a = document.getElementById('p-dev-start').value;
    const b = document.getElementById('p-dev-end').value;
    if (a && b && b < a) { alert('O fim do desenvolvimento não pode ser anterior ao início.'); return false; }
    let ok = true;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const s = tr.querySelector('.it-start').value;
        const e = tr.querySelector('.it-end').value;
        if (s && e && e < s) {
            const name = tr.querySelector('.it-desc').value.trim() || 'serviço';
            alert('No cronograma de "' + name + '", a data final não pode ser anterior à inicial.');
            ok = false;
        }
    });
    return ok;
}
function body() {
    return {
        title: document.getElementById('p-title').value.trim(),
        contract_type: document.getElementById('p-contract').value,
        contact_id: document.getElementById('p-lead').value,
        company_id: document.getElementById('p-company').value,
        client_name: document.getElementById('p-client-name').value.trim(),
        client_email: document.getElementById('p-client-email').value.trim(),
        client_phone: document.getElementById('p-client-phone').value.trim(),
        validity_date: document.getElementById('p-validity').value,
        dev_start: document.getElementById('p-dev-start').value,
        dev_end: document.getElementById('p-dev-end').value,
        observations: document.getElementById('p-obs').value.trim(),
        content: collectContent(),
        items: collectItems(),
    };
}
function applyPeriod() {
    const a = document.getElementById('p-dev-start').value;
    const b = document.getElementById('p-dev-end').value;
    document.querySelectorAll('#items-body tr').forEach(tr => {
        const s = tr.querySelector('.it-start');
        const e = tr.querySelector('.it-end');
        if (!s.value && a) s.value = a;
        if (!e.value && b) e.value = b;
    });
}
function fillIfEmpty(id, value) {
    const el = document.getElementById(id);
    if (value && !el.value) el.value = value;
}
document.getElementById('p-lead').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!this.value) return;
    fillIfEmpty('p-client-name', opt.getAttribute('data-name') || '');
    fillIfEmpty('p-client-email', opt.getAttribute('data-email') || '');
    fillIfEmpty('p-client-phone', opt.getAttribute('data-phone') || '');
});
document.getElementById('p-company').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!this.value) return;
    document.getElementById('p-client-name').value = opt.getAttribute('data-name') || '';
    document.getElementById('p-client-email').value = opt.getAttribute('data-email') || '';
    document.getElementById('p-client-phone').value = opt.getAttribute('data-phone') || '';
});
document.getElementById('svc-pick').addEventListener('change', function () {
    if (!this.value) return;
    const s = SERVICES.find(x => String(x.id) === String(this.value));
    this.value = '';
    if (!s) return;
    const hours = (s.est_hours === null || s.est_hours === undefined) ? '' : s.est_hours;
    const rate = (s.hourly_rate === null || s.hourly_rate === undefined) ? '' : s.hourly_rate;
    const h = parseFloat(hours) || 0;
    const r = parseFloat(rate) || 0;
    const forecast = (h > 0 && r > 0) ? (h * r) : (r > 0 ? r : 0);
    const tr = addRow({
        service_id: s.id,
        description: s.name || '',
        scope: s.description || '',
        hours: hours,
        hourly_rate: rate,
        quantity: 1,
        is_hosting: parseInt(s.is_hosting, 10) === 1,
        billing: s.billing === 'monthly' ? 'monthly' : 'once',
        hint: forecast ? ('Previsão do catálogo: ' + money(forecast) + '. Altere o valor se esta proposta for diferente.') : ''
    });
    const a = document.getElementById('p-dev-start').value;
    const b = document.getElementById('p-dev-end').value;
    if (a) tr.querySelector('.it-start').value = a;
    if (b) tr.querySelector('.it-end').value = b;
});
async function openPdf() {
    const win = window.open('', '_blank');
    if (CAN_EDIT && !await saveProposal()) { if (win) win.close(); return; }
    const url = `${PROP_BASE}proposal/pdf/${PROPOSAL_ID}?t=${Date.now()}`;
    if (win) win.location = url; else location.href = url;
}
function copyLink() {
    const input = document.getElementById('p-link');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => alert('Link copiado.')).catch(() => alert(input.value));
}

let SAVING = false;
async function saveProposal() {
    if (SAVING) return false;
    if (!datesOk()) return false;
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
    let msg = 'Proposta marcada como enviada.\n';
    const canais = [];
    if (r.sent_whats) canais.push('WhatsApp');
    if (r.sent_email) canais.push('e-mail');
    if (canais.length) msg += 'Enviada ao cliente por: ' + canais.join(' e ') + '.';
    else if (r.no_contact) msg += 'O cliente não tem telefone nem e-mail. Copie o link e envie manualmente:\n' + r.link;
    else msg += 'Não foi possível enviar automaticamente. Envie o link manualmente:\n' + r.link;
    alert(msg);
    location.reload();
}
if (EXISTING_ITEMS.length) EXISTING_ITEMS.forEach(it => addRow(it));

async function reopenProposal() {
    if (!confirm('Reabrir esta proposta recusada para editar? Ela volta para "em elaboração".')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('status', 'draft');
    const r = await fetch(`${PROP_BASE}proposal/status/${PROPOSAL_ID}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.reload();
}
function genContract() {
    const modal = new bootstrap.Modal(document.getElementById('genContractModal'));
    modal.show();
}
async function confirmGenContract() {
    const templateId = document.getElementById('gc-template').value;
    const btn = document.getElementById('gc-confirm');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando...';
    const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('proposal_id', PROPOSAL_ID);
    if (templateId) fd.append('template_id', templateId);
    const r = await fetch(`${PROP_BASE}contract/fromProposal`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    btn.disabled = false; btn.innerHTML = orig;
    if (r.error) { alert(r.error); return; }
    location.href = `${PROP_BASE}contract/edit/${r.id}`;
}
</script>

<div class="modal fade" id="genContractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-file-earmark-check"></i> Gerar contrato</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-medium">Modelo de contrato</label>
        <select id="gc-template" class="form-select form-select-sm">
          <option value="">— Em branco (sem modelo) —</option>
          <?php foreach (($contractTemplates ?? []) as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= escape($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted d-block mt-2">
          O corpo do modelo é copiado para o contrato e pode ser editado depois.
          <a href="<?= baseUrl('contract/templates') ?>" target="_blank">Gerenciar modelos</a>.
        </small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="gc-confirm" class="btn btn-sm btn-primary" onclick="confirmGenContract()"><i class="bi bi-check-lg"></i> Gerar contrato</button>
      </div>
    </div>
  </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
