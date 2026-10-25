<?php $pageTitle = 'Proposta - ON Solutions Helpdesk'; $currentPage = 'proposals'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
$ctLabels = [
    'dev_zero' => 'Desenvolvimento do zero', 'dev_manutencao' => 'Desenvolvimento/manutenção',
    'suporte' => 'Suporte', 'dev_suporte' => 'Desenvolvimento + suporte', 'outro' => 'Outro',
];
$isTerminal = in_array($proposal['status'], ['accepted','rejected','cancelled'], true);
$isSuperseded = !empty($proposal['superseded_by']);
// Enviada, aguardando ou recusada: editar cria nova versão (R02...).
$needsRevision = !$isSuperseded && ProposalRules::requiresNewRevision($proposal['status']);
$canEdit = !$isSuperseded && in_array($proposal['status'], ['draft','ready'], true);
$revLabel = ProposalRules::revisionLabel($proposal['revision_no'] ?? 1);
$statusLabels = [
    'draft' => 'Em elaboração', 'ready' => 'Montada', 'sent' => 'Enviada',
    'awaiting' => 'Aguardando', 'accepted' => 'Aceita', 'rejected' => 'Recusada', 'cancelled' => 'Cancelada',
];
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0">Proposta #<?= (int)$proposal['id'] ?> <span class="badge bg-secondary" id="p-revision"><?= escape($revLabel) ?></span></h5>
            <small class="text-muted"><?= escape($proposal['title']) ?> · <span id="p-status-label"><?= escape($statusLabels[$proposal['status']] ?? $proposal['status']) ?></span></small>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?= baseUrl('proposal') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openPdf()"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</button>
            <button type="button" class="btn btn-sm btn-outline-info" onclick="openOnline()"><i class="bi bi-globe"></i> Ver proposta online</button>
            <?php if ($canEdit): ?>
            <button id="btn-save" class="btn btn-sm btn-success" onclick="saveProposal()"><i class="bi bi-check-lg"></i> Salvar</button>
            <button id="btn-send" class="btn btn-sm btn-primary" onclick="sendProposal()"><i class="bi bi-send"></i> Enviar ao cliente</button>
            <?php endif; ?>
            <?php if ($proposal['status'] === 'accepted'): ?>
            <button class="btn btn-sm btn-dark" onclick="genContract()"><i class="bi bi-file-earmark-check"></i> Gerar contrato</button>
            <?php endif; ?>
            <?php if ($needsRevision): ?>
            <button id="btn-reopen" class="btn btn-sm btn-warning" onclick="reopenProposal()"><i class="bi bi-arrow-counterclockwise"></i> Reabrir para editar (nova versão)</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isSuperseded): ?>
    <div class="alert alert-secondary py-2" id="superseded-alert"><i class="bi bi-lock"></i> Versão <?= escape($revLabel) ?> substituída, somente leitura. <a href="<?= baseUrl('proposal/edit/' . (int)$proposal['superseded_by']) ?>">Abrir a versão seguinte</a>.</div>
    <?php elseif ($needsRevision && $proposal['status'] !== 'rejected'): ?>
    <div class="alert alert-info py-2"><i class="bi bi-info-circle"></i> O cliente já recebeu esta versão. Para mudar, use "Reabrir para editar": o sistema cria a próxima versão e guarda esta.</div>
    <?php endif; ?>

    <?php if (count($revisions ?? []) > 1): ?>
    <div class="card mb-3" id="revisions-card">
        <div class="card-header py-2"><strong>Versões</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Versão</th><th>Data</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($revisions as $rv): ?>
                <tr class="<?= (int)$rv['id'] === (int)$proposal['id'] ? 'table-active' : '' ?>">
                    <td><?= escape(ProposalRules::revisionLabel($rv['revision_no'])) ?></td>
                    <td><?= $rv['created_at'] ? date('d/m/Y H:i', strtotime($rv['created_at'])) : '' ?></td>
                    <td><?= escape($statusLabels[$rv['status']] ?? $rv['status']) ?><?= !empty($rv['superseded_by']) ? ' <span class="text-muted small">(substituída)</span>' : '' ?></td>
                    <td class="text-end">R$ <?= number_format((float)$rv['total'], 2, ',', '.') ?></td>
                    <td class="text-end"><?php if ((int)$rv['id'] !== (int)$proposal['id']): ?><a href="<?= baseUrl('proposal/edit/' . (int)$rv['id']) ?>">Abrir</a><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

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

            <div class="card mt-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Condições comerciais — descontos</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addDiscount()"><i class="bi bi-plus-lg"></i> Adicionar desconto</button>
                </div>
                <div class="card-body">
                    <div id="discounts-body" class="d-flex flex-column gap-2"></div>
                    <small class="text-muted d-block mt-2">O desconto pode incidir no valor pontual, na mensalidade ou no total. "Por período (mensal)" permite desconto só em alguns meses (ex.: 1º mês 20%, 2º mês 10%, demais cheios). Os valores são recalculados pelo backend.</small>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header py-2"><strong>Resumo comercial</strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0" id="summary-table">
                        <tbody>
                            <tr><td>Pontual (cheio)</td><td class="text-end" id="sum-once-gross">—</td></tr>
                            <tr><td>Mensalidade (cheia)</td><td class="text-end" id="sum-monthly-gross">—</td></tr>
                            <tr class="text-success"><td>Desconto total</td><td class="text-end" id="sum-discount">—</td></tr>
                            <tr class="fw-bold"><td>Pontual (líquido)</td><td class="text-end" id="sum-once-net">—</td></tr>
                            <tr class="fw-bold"><td>Mensalidade (líquida)</td><td class="text-end" id="sum-monthly-net">—</td></tr>
                            <tr class="fw-bold"><td>Total do 1º ano (líquido)</td><td class="text-end" id="sum-year-net">—</td></tr>
                        </tbody>
                    </table>
                    <div id="sum-months" class="small text-muted mt-2"></div>
                    <small class="text-muted d-block mt-1">Prévia; o cálculo oficial é feito ao salvar e aparece no PDF e na proposta online.</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Proposta online</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-1">Link da proposta online. É para onde o botão dentro do PDF leva o cliente para revisar e responder.</p>
                    <div class="input-group input-group-sm">
                        <input class="form-control" id="p-online-link" readonly value="<?= escape($onlineLink ?? '') ?>">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyOnline()">Copiar</button>
                        <a class="btn btn-outline-primary" href="<?= escape($onlineLink ?? '#') ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                    <p class="small text-muted mb-0 mt-2">Ao clicar em <strong>Enviar ao cliente</strong>, o PDF é enviado automaticamente (WhatsApp e e-mail). Use <strong>Ver PDF</strong> para conferir antes.</p>
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
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const EXISTING_ITEMS = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
const EXISTING_DISCOUNTS = <?= json_encode($discounts ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
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
            <div class="d-flex gap-2 mt-1 it-recurrence-wrap" style="display:none !important;">
                <div style="flex:1"><label class="form-label small mb-0 text-muted">Renovação</label><select class="form-select form-select-sm it-renewal"><option value="none">Sem renovação</option><option value="auto">Automática</option><option value="manual">Manual</option></select></div>
                <div style="flex:1"><label class="form-label small mb-0 text-muted">Periodicidade</label><select class="form-select form-select-sm it-recurrence"><option value="monthly">Mensal</option><option value="yearly">Anual</option></select></div>
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
    tr.querySelector('.it-renewal').value = ['none','auto','manual'].includes(data.renewal) ? data.renewal : 'none';
    tr.querySelector('.it-recurrence').value = data.recurrence_period === 'yearly' ? 'yearly' : 'monthly';
    tr.querySelector('.it-service').value = data.service_id || '';
    tr.querySelector('.it-hosting').value = data.is_hosting ? '1' : '0';
    if (data.hint) tr.querySelector('.it-hint').textContent = data.hint;
    tr.querySelector('.it-remove').addEventListener('click', () => { tr.remove(); recalc(); });
    tr.querySelectorAll('input,select').forEach(inp => inp.addEventListener('input', recalc));
    const billingSel = tr.querySelector('.it-billing');
    const toggleRecurrence = () => {
        tr.querySelector('.it-recurrence-wrap').style.display = billingSel.value === 'monthly' ? 'flex' : 'none';
    };
    billingSel.addEventListener('change', toggleRecurrence);
    toggleRecurrence();
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
    refreshSummary();
}

// ===== Descontos comerciais =====
function discountRowHtml() {
    return `
        <div class="d-flex gap-2 align-items-end flex-wrap disc-row border rounded p-2">
            <div><label class="form-label small mb-0 text-muted">Incide em</label>
                <select class="form-select form-select-sm d-scope">
                    <option value="total">Total</option>
                    <option value="once">Pontual</option>
                    <option value="monthly">Mensalidade</option>
                </select></div>
            <div><label class="form-label small mb-0 text-muted">Tipo</label>
                <select class="form-select form-select-sm d-type">
                    <option value="percent">Percentual (%)</option>
                    <option value="fixed">Valor fixo (R$)</option>
                    <option value="schedule">Por período (mensal)</option>
                </select></div>
            <div class="d-simple"><label class="form-label small mb-0 text-muted">Valor</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm d-value" style="width:120px;"></div>
            <div class="flex-grow-1"><label class="form-label small mb-0 text-muted">Rótulo (opcional)</label>
                <input class="form-control form-control-sm d-label" placeholder="Ex.: Desconto de lançamento"></div>
            <button type="button" class="btn btn-sm btn-outline-danger d-remove"><i class="bi bi-x"></i></button>
            <div class="d-months w-100 mt-1" style="display:none;">
                <div class="small text-muted mb-1">Desconto por mês (1 a 12). Deixe vazio o mês sem desconto.</div>
                <div class="d-flex flex-wrap gap-1 d-months-grid"></div>
            </div>
        </div>`;
}
function addDiscount(data) {
    data = data || {};
    const wrap = document.createElement('div');
    wrap.innerHTML = discountRowHtml();
    const row = wrap.firstElementChild;
    const scope = row.querySelector('.d-scope');
    const type = row.querySelector('.d-type');
    const value = row.querySelector('.d-value');
    const label = row.querySelector('.d-label');
    const monthsBox = row.querySelector('.d-months');
    const monthsGrid = row.querySelector('.d-months-grid');
    for (let m = 1; m <= 12; m++) {
        const cell = document.createElement('div');
        cell.innerHTML = `<label class="form-label small mb-0 text-muted">M${m}</label>
            <div class="input-group input-group-sm" style="width:92px;">
                <input type="number" step="0.01" min="0" class="form-control dm-value" data-month="${m}">
                <select class="form-select dm-type" data-month="${m}" style="max-width:42px;"><option value="percent">%</option><option value="fixed">R$</option></select>
            </div>`;
        monthsGrid.appendChild(cell);
    }
    const sync = () => {
        const isSchedule = type.value === 'schedule';
        if (isSchedule) scope.value = 'monthly';
        scope.disabled = isSchedule;
        row.querySelector('.d-simple').style.display = isSchedule ? 'none' : '';
        monthsBox.style.display = isSchedule ? 'block' : 'none';
        refreshSummary();
    };
    type.addEventListener('change', sync);
    [scope, value, label].forEach(el => el.addEventListener('input', refreshSummary));
    monthsGrid.querySelectorAll('input,select').forEach(el => el.addEventListener('input', refreshSummary));
    row.querySelector('.d-remove').addEventListener('click', () => { row.remove(); refreshSummary(); });

    // Preenche com dados existentes
    scope.value = ['total','once','monthly'].includes(data.scope) ? data.scope : 'total';
    type.value = ['percent','fixed','schedule'].includes(data.type) ? data.type : 'percent';
    if (data.type !== 'schedule') value.value = (data.value ?? '') === 0 ? '' : (data.value ?? '');
    label.value = data.label || '';
    if (data.type === 'schedule' && Array.isArray(data.months)) {
        data.months.forEach(m => {
            const vi = monthsGrid.querySelector(`.dm-value[data-month="${m.month}"]`);
            const ti = monthsGrid.querySelector(`.dm-type[data-month="${m.month}"]`);
            if (vi) vi.value = m.value;
            if (ti) ti.value = m.type === 'fixed' ? 'fixed' : 'percent';
        });
    }
    document.getElementById('discounts-body').appendChild(row);
    sync();
    return row;
}
function collectDiscounts() {
    const out = [];
    document.querySelectorAll('#discounts-body .disc-row').forEach(row => {
        const type = row.querySelector('.d-type').value;
        const scope = row.querySelector('.d-scope').value;
        const label = row.querySelector('.d-label').value.trim();
        if (type === 'schedule') {
            const months = [];
            row.querySelectorAll('.dm-value').forEach(vi => {
                const v = parseFloat(vi.value);
                if (!isNaN(v) && v > 0) {
                    const m = parseInt(vi.getAttribute('data-month'), 10);
                    const ti = row.querySelector(`.dm-type[data-month="${m}"]`);
                    months.push({ month: m, type: ti ? ti.value : 'percent', value: v });
                }
            });
            if (months.length) out.push({ scope: 'monthly', type: 'schedule', label, months });
        } else {
            const v = parseFloat(row.querySelector('.d-value').value);
            if (!isNaN(v) && v > 0) out.push({ scope, type, value: v, label });
        }
    });
    return out;
}

// ===== Resumo comercial (prévia; backend é a autoridade) =====
function refreshSummary() {
    const items = collectItems();
    let onceGross = 0, monthlyGross = 0;
    items.forEach(it => {
        const amt = parseFloat(it.amount) || (parseFloat(it.hours) > 0 ? (parseFloat(it.quantity||1) * parseFloat(it.hours) * parseFloat(it.hourly_rate||0)) : 0);
        // amount já vem de collectItems (0 quando por hora). Recalcula igual ao backend:
        const q = parseFloat(it.quantity) || 1, h = parseFloat(it.hours) || 0, r = parseFloat(it.hourly_rate) || parseFloat(it.amount) || 0;
        const val = (h > 0 && r > 0) ? q*h*r : (r > 0 ? q*r : 0);
        if (it.billing === 'monthly') monthlyGross += val; else onceGross += val;
    });
    const discounts = collectDiscounts();
    const prev = computeCommercial(onceGross, monthlyGross, discounts);
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = money(v); };
    set('sum-once-gross', onceGross);
    set('sum-monthly-gross', monthlyGross);
    set('sum-discount', prev.totalDiscount);
    set('sum-once-net', prev.onceNet);
    set('sum-monthly-net', prev.monthlyNet);
    set('sum-year-net', prev.yearNet);
    const monthsEl = document.getElementById('sum-months');
    if (prev.hasSchedule) {
        monthsEl.textContent = 'Mensalidade por mês: ' + prev.monthlyByMonth.map((v,i)=>'M'+(i+1)+' '+money(v)).join(' · ');
    } else { monthsEl.textContent = ''; }
}
// Réplica fiel de ProposalRules (só para prévia visual).
function computeCommercial(onceGross, monthlyGross, discounts) {
    const totalBase = onceGross + monthlyGross;
    const scopeDiscount = (scope, base) => {
        let disc = 0;
        discounts.forEach(d => {
            if (d.type === 'schedule') return;
            if (d.scope === scope) disc += (d.type === 'percent' ? base * Math.min(100,d.value)/100 : Math.min(base, d.value));
            else if (d.scope === 'total' && totalBase > 0) {
                const whole = (d.type === 'percent' ? totalBase * Math.min(100,d.value)/100 : Math.min(totalBase, d.value));
                disc += whole * (base / totalBase);
            }
        });
        return Math.min(base, disc);
    };
    const onceDisc = scopeDiscount('once', onceGross);
    const onceNet = onceGross - onceDisc;
    // mensalidade: flat + schedule
    const flatMonthlyDisc = scopeDiscount('monthly', monthlyGross);
    const flatMonthlyNet = monthlyGross - flatMonthlyDisc;
    const byMonth = Array(12).fill(flatMonthlyNet);
    let hasSchedule = false;
    discounts.forEach(d => {
        if (d.type !== 'schedule') return;
        hasSchedule = true;
        (d.months||[]).forEach(m => {
            const idx = m.month - 1;
            if (idx < 0 || idx > 11) return;
            byMonth[idx] = m.type === 'percent' ? flatMonthlyNet * (1 - Math.min(100,m.value)/100) : Math.max(0, flatMonthlyNet - m.value);
        });
    });
    const monthlyYearNet = byMonth.reduce((a,b)=>a+b, 0);
    const yearNet = onceNet + monthlyYearNet;
    const yearGross = onceGross + monthlyGross * 12;
    return {
        onceNet, monthlyNet: byMonth[11], monthlyByMonth: byMonth, hasSchedule,
        totalDiscount: yearGross - yearNet, yearNet
    };
}

function openOnline() {
    const win = window.open('', '_blank');
    (async () => {
        if (CAN_EDIT && !await saveProposal()) { if (win) win.close(); return; }
        const url = `${PROP_BASE}proposal/show/${'<?= escape($proposal['public_token']) ?>'}?t=${Date.now()}`;
        if (win) win.location = url; else location.href = url;
    })();
}
function copyOnline() {
    const input = document.getElementById('p-online-link');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => alert('Link copiado.')).catch(() => alert(input.value));
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
            renewal: tr.querySelector('.it-renewal').value,
            recurrence_period: tr.querySelector('.it-billing').value === 'monthly' ? tr.querySelector('.it-recurrence').value : '',
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
        discounts: collectDiscounts(),
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
        renewal: ['none','auto','manual'].includes(s.renewal) ? s.renewal : 'none',
        recurrence_period: s.recurrence_period === 'yearly' ? 'yearly' : 'monthly',
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
    if (canais.length) msg += 'PDF enviado ao cliente por: ' + canais.join(' e ') + '. Dentro do PDF há o botão para responder.';
    else if (r.no_contact) msg += 'O cliente não tem telefone nem e-mail cadastrados. Cadastre um contato para enviar o PDF.';
    else msg += 'Não foi possível enviar automaticamente. Verifique a conexão do WhatsApp e o e-mail do cliente.';
    alert(msg);
    location.reload();
}
if (EXISTING_ITEMS.length) EXISTING_ITEMS.forEach(it => addRow(it));
if (EXISTING_DISCOUNTS.length) EXISTING_DISCOUNTS.forEach(d => addDiscount(d));
refreshSummary();
// Versão enviada, substituída ou encerrada: tela só de leitura.
if (!CAN_EDIT) {
    document.querySelectorAll('.main-content input, .main-content textarea, .main-content select, .main-content .btn-outline-danger')
        .forEach(el => { if (!el.closest('.modal') && el.id !== 'p-online-link') el.disabled = true; });
}

async function reopenProposal() {
    if (!confirm('Criar uma nova versão desta proposta para editar? A versão atual fica guardada, somente leitura.')) return;
    const fd = new FormData(); fd.append('csrf_token', CSRF);
    const r = await fetch(`${PROP_BASE}proposal/reopen/${PROPOSAL_ID}`, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(x=>x.json()).catch(()=>({error:'Falha de rede'}));
    if (r.error) { alert(r.error); return; }
    location.href = `${PROP_BASE}proposal/edit/${r.id}`;
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
