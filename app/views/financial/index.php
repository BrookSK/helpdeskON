<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>
<?php
// ===== Labels/ Helpers locais =====
$kindLabel = [
    'ganho'         => ['label' => 'Ganho',           'icon' => 'bi-graph-up-arrow',    'cls' => 'success'],
    'gasto'         => ['label' => 'Gasto',            'icon' => 'bi-cart-dash',        'cls' => 'danger'],
    'salario'       => ['label' => 'Salário',          'icon' => 'bi-people',           'cls' => 'danger'],
    'emprestimo'    => ['label' => 'Empréstimo',       'icon' => 'bi-bank',             'cls' => 'warning'],
    'transferencia' => ['label' => 'Transferência',    'icon' => 'bi-arrow-left-right', 'cls' => 'info'],
];
$statusLabel = [
    'pending'  => ['Pendente', 'warning'],
    'paid'     => ['Pago',     'success'],
    'cancelled'=> ['Cancelado','secondary'],
];
function finMoney($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
$cardsById = [];
foreach ($cards as $c) $cardsById[(int)$c['id']] = $c;
?>
<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-bank2"></i> Controle Financeiro</h5>
            <small class="text-muted">Ganhos, gastos, salários, empréstimos, cartões e cobranças Asaas — exclusivo do super administrador</small>
        </div>
    </div>

    <?php // ===== Cards de resumo ===== ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-success bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-check-circle"></i> Recebido</div>
                    <div class="fw-bold text-success"><?= finMoney($summary['received']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-success-subtle bg-opacity-25">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-hourglass-split"></i> A Receber</div>
                    <div class="fw-bold text-success-emphasis"><?= finMoney($summary['to_receive']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-danger bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-check2"></i> Pago</div>
                    <div class="fw-bold text-danger"><?= finMoney($summary['paid']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-danger-subtle bg-opacity-25">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-hourglass-top"></i> A Pagar</div>
                    <div class="fw-bold text-danger-emphasis"><?= finMoney($summary['to_pay']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-secondary bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-wallet2"></i> Saldo (<?= escape($filters['month']) ?>)</div>
                    <div class="fw-bold <?= $summary['balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= finMoney($summary['balance']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-secondary bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-calendar-check"></i> Projetado</div>
                    <div class="fw-bold"><?= finMoney($summary['projected']) ?></div>
                    <div class="small text-muted" style="font-size:0.65rem;">
                        Empréstimos: tomado <?= finMoney($summary['loans_out']) ?> · a receber <?= finMoney($summary['loans_in']) ?><br>
                        Comissões: a pagar <?= finMoney($commissionsSummary['to_pay']) ?> · já pago <?= finMoney($commissionsSummary['paid']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php // ===== Abas ===== ?>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-lancamentos" type="button"><i class="bi bi-list-ul"></i> Lançamentos</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cartoes" type="button"><i class="bi bi-credit-card"></i> Cartões (<?= count($cards) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-comissoes" type="button"><i class="bi bi-hand-coins"></i> Comissões (<?= count($commissions) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-asaas" type="button"><i class="bi bi-cloud-arrow-down"></i> Asaas (<?= count($accounts) ?>)</button></li>
    </ul>

    <div class="tab-content">
        <?php // ================= LANÇAMENTOS ================= ?>
        <div class="tab-pane fade show active" id="tab-lancamentos">
            <div class="card mb-3">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <strong>Novo lançamento</strong>
                    <form class="d-flex gap-2 align-items-center" method="get" action="<?= baseUrl('financial') ?>">
                        <label class="small text-muted mb-0">Mês</label>
                        <input type="month" class="form-control form-control-sm" name="month" value="<?= escape($filters['month']) ?>" onchange="this.form.submit()">
                    </form>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <label class="form-label small">Tipo *</label>
                            <select id="f-kind" class="form-select form-select-sm">
                                <?php foreach ($kindLabel as $k => $meta): ?>
                                <option value="<?= $k ?>"><?= $meta['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" id="f-direction-wrap" style="display:none;">
                            <label class="form-label small">Direção</label>
                            <select id="f-direction" class="form-select form-select-sm">
                                <option value="out" id="f-dir-out-opt">Saída</option>
                                <option value="in" id="f-dir-in-opt">Entrada</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Categoria</label>
                            <input id="f-category" class="form-control form-control-sm" placeholder="Ex.: Infra, Ferramentas">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Descrição</label>
                            <input id="f-description" class="form-control form-control-sm" placeholder="Ex.: Servidor AWS">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Valor (R$) *</label>
                            <input id="f-amount" class="form-control form-control-sm" placeholder="0,00" inputmode="decimal">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">Parcelas</label>
                            <input id="f-installments" type="number" class="form-control form-control-sm" value="1" min="1" max="48">
                        </div>
                        <div class="col-md-2" id="f-card-wrap" style="display:none;">
                            <label class="form-label small">Cartão *</label>
                            <select id="f-card" class="form-select form-select-sm">
                                <option value="">Selecione...</option>
                                <?php foreach ($cards as $c): if ((int)$c['active'] !== 1) continue; ?>
                                <option value="<?= (int)$c['id'] ?>"><?= escape($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" id="f-loandate-wrap" style="display:none;">
                            <label class="form-label small">Data do empréstimo</label>
                            <input id="f-loandate" type="date" class="form-control form-control-sm" title="Quando pegou/concedeu o empréstimo">
                        </div>
                        <div class="col-md-3" id="f-recurring-wrap" style="display:none;">
                            <label class="form-label small mb-0">Recorrência</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="f-recurring-chk">
                                <label class="form-check-label small" for="f-recurring-chk">Repetir automaticamente</label>
                            </div>
                            <div class="d-flex gap-2 align-items-center" id="f-recurring-fields" style="display:none;">
                                <select id="f-cycle" class="form-select form-select-sm w-auto">
                                    <option value="monthly">Mensal</option>
                                    <option value="yearly">Anual</option>
                                </select>
                                <input id="f-recurring-until" type="month" class="form-control form-control-sm" title="Até quando (vazio = para sempre)">
                            </div>
                            <div class="small text-muted" style="font-size:0.65rem;">Ao pagar, a próxima ocorrência nasce automaticamente.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Data *</label>
                            <input id="f-due" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2 col-xl-1 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="f-paid">
                                <label class="form-check-label small" for="f-paid">Já pago</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary" onclick="saveTransaction()"><i class="bi bi-plus-lg"></i> Lançar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Vencimento</th><th>Tipo</th><th>Categoria</th><th>Descrição</th>
                                <th>Origem</th><th>Parcelas</th><th class="text-end">Valor</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nenhum lançamento neste filtro.</td></tr>
                            <?php else: foreach ($transactions as $t):
                                $meta = $kindLabel[$t['kind']] ?? $kindLabel['gasto'];
                                $st = $statusLabel[$t['status']] ?? $statusLabel['pending'];
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= escape($t['due_date'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $meta['cls'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?></span></td>
                                <td><?= escape($t['category'] ?? '—') ?></td>
                                <td><?= escape($t['description'] ?? '—') ?></td>
                                <td class="small text-muted">
                                    <?php if ($t['source'] === 'asaas'): ?>
                                        <i class="bi bi-cloud-arrow-down"></i> <?= escape($t['asaas_account_name'] ?? 'Asaas') ?>
                                    <?php elseif ($t['card_id'] && isset($cardsById[(int)$t['card_id']])): ?>
                                        <i class="bi bi-credit-card"></i> <?= escape($cardsById[(int)$t['card_id']]['name']) ?>
                                    <?php else: ?>
                                        <i class="bi bi-pencil"></i> Manual
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ((int)$t['installments'] > 1): ?><?= (int)$t['installments'] ?>x
                                    <?php elseif (!empty($t['recurring_cycle'])): ?><span class="badge bg-info" title="Recorrente (<?= $t['recurring_cycle'] === 'yearly' ? 'anual' : 'mensal' ?><?= $t['recurring_until'] ? ' até ' . escape($t['recurring_until']) : '' ?>)"><i class="bi bi-arrow-repeat"></i> rec.</span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-nowrap <?= $t['direction'] === 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?= $t['direction'] === 'in' ? '+' : '−' ?> <?= finMoney($t['amount']) ?>
                                </td>
                                <td><span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span></td>
                                <td class="text-nowrap">
                                    <?php if ($t['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar (parcelado: quita a próxima parcela)" onclick="payTx(<?= (int)$t['id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                    <?php elseif ($t['source'] !== 'asaas'): ?>
                                    <button class="btn btn-sm btn-outline-warning py-0 px-1" title="Estornar" onclick="unpayTx(<?= (int)$t['id'] ?>)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php endif; ?>
                                    <?php if ($t['source'] !== 'asaas'): ?>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir" onclick="removeTx(<?= (int)$t['id'] ?>)"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // ================= CARTÕES ================= ?>
        <div class="tab-pane fade" id="tab-cartoes">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Novo cartão de crédito</strong></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3"><label class="form-label small">Nome *</label><input id="c-name" class="form-control form-control-sm" placeholder="Ex.: Nubank PJ"></div>
                        <div class="col-md-2"><label class="form-label small">Limite (R$)</label><input id="c-limit" class="form-control form-control-sm" placeholder="0,00" inputmode="decimal"></div>
                        <div class="col-md-2"><label class="form-label small">Dia fechamento</label><input id="c-closing" type="number" min="1" max="31" class="form-control form-control-sm"></div>
                        <div class="col-md-2"><label class="form-label small">Dia vencimento</label><input id="c-due" type="number" min="1" max="31" class="form-control form-control-sm"></div>
                        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-primary" onclick="saveCard()"><i class="bi bi-plus-lg"></i> Salvar cartão</button></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Nome</th><th class="text-end">Limite</th><th class="text-end">Fatura prevista (parcelas pendentes)</th><th>Fechamento</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($cards)): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhum cartão.</td></tr>
                            <?php else: foreach ($cards as $c):
                                $pend = $cardPending[(int)$c['id']] ?? 0.0;
                                $usage = ((float)$c['limit'] > 0) ? round($pend / (float)$c['limit'] * 100, 1) : null;
                            ?>
                            <tr>
                                <td class="fw-medium"><i class="bi bi-credit-card"></i> <?= escape($c['name']) ?></td>
                                <td class="text-end"><?= finMoney($c['limit']) ?></td>
                                <td class="text-end fw-bold <?= $pend > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= finMoney($pend) ?><?= $usage !== null ? ' <span class="small text-muted">(' . $usage . '% do limite)</span>' : '' ?>
                                </td>
                                <td><?= $c['closing_day'] ? 'dia ' . (int)$c['closing_day'] : '—' ?></td>
                                <td><?= $c['due_day'] ? 'dia ' . (int)$c['due_day'] : '—' ?></td>
                                <td><?= ((int)$c['active'] === 1) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?></td>
                                <td><button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="toggleCard(<?= (int)$c['id'] ?>)"><i class="bi bi-power"></i></button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // ================= COMISSÕES ================= ?>
        <div class="tab-pane fade" id="tab-comissoes">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Nova comissão</strong> <span class="small text-muted">— vinculada a um ganho (projeto/venda): pra quem, quanto e em quantas vezes</span></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Ganho de origem *</label>
                            <select id="com-ganho" class="form-select form-select-sm">
                                <option value="">Selecione...</option>
                                <?php foreach ($gainOptions as $g): ?>
                                <option value="<?= (int)$g['id'] ?>" data-amount="<?= (float)$g['amount'] ?>">
                                    #<?= (int)$g['id'] ?> · <?= escape($g['description'] ?? $g['category'] ?? 'Ganho') ?> (<?= finMoney($g['amount']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Para quem *</label>
                            <select id="com-user" class="form-select form-select-sm">
                                <option value="">Outro (nome abaixo)</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= escape($u['name']) ?> (<?= escape($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Nome (se fora do sistema)</label>
                            <input id="com-recipient" class="form-control form-control-sm" placeholder="Ex.: Parceiro">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Valor</label>
                            <div class="d-flex gap-2">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-text p-0"><button class="btn btn-sm" type="button" id="com-mode-fixo" onclick="setComMode('fixo')">R$</button><button class="btn btn-sm" type="button" id="com-mode-pct" onclick="setComMode('pct')">%</button></div>
                                    <input id="com-amount" class="form-control" placeholder="0,00" inputmode="decimal">
                                </div>
                            </div>
                            <div class="small text-muted" style="font-size:0.65rem;" id="com-hint">Percentual é calculado sobre o valor do ganho selecionado.</div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">Parcelas</label>
                            <input id="com-installments" type="number" class="form-control form-control-sm" value="1" min="1" max="48">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">1ª data</label>
                            <input id="com-due" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary" onclick="saveCommission()"><i class="bi bi-plus-lg"></i> Lançar comissão</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Ganho de origem</th><th>Para quem</th><th>Nota</th><th class="text-end">Valor total</th><th>Parcelas</th><th>Próximo venc.</th><th class="text-end">Resta</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($commissions)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma comissão lançada.</td></tr>
                            <?php else: foreach ($commissions as $c):
                                $who = $c['user_name'] ?? ($c['recipient'] ?? '—');
                                $remaining = FinancialRules::commissionRemaining((float)$c['amount'], (int)$c['installments'], (int)$c['installments_paid']);
                                $done = ($c['status'] === 'paid');
                            ?>
                            <tr>
                                <td class="small">
                                    <?php if (!empty($c['ganho_desc'])): ?>#<?= (int)$c['transaction_id'] ?> · <?= escape($c['ganho_desc']) ?>
                                    <div class="text-muted" style="font-size:0.65rem;">(<?= finMoney($c['ganho_amount'] ?? 0) ?>)</div>
                                    <?php else: ?>#<?= (int)$c['transaction_id'] ?><?php endif; ?>
                                </td>
                                <td class="fw-medium"><i class="bi bi-person"></i> <?= escape($who) ?></td>
                                <td class="small text-muted"><?= escape($c['note'] ?? '—') ?></td>
                                <td class="text-end"><?= finMoney($c['amount']) ?></td>
                                <td class="text-nowrap"><?= (int)$c['installments_paid'] ?>/<?= (int)$c['installments'] ?><?= (int)$c['installments'] > 1 ? ' pago' : '' ?></td>
                                <td><?= $done ? '—' : escape($c['next_due_date'] ?? '—') ?></td>
                                <td class="text-end fw-bold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= finMoney($remaining) ?></td>
                                <td><span class="badge bg-<?= $done ? 'success' : 'warning' ?>"><?= $done ? 'Quitada' : 'Pendente' ?></span></td>
                                <td class="text-nowrap">
                                    <?php if (!$done): ?>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar próxima parcela" onclick="payCommission(<?= (int)$c['id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir" onclick="removeCommission(<?= (int)$c['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // ================= ASAAS ================= ?>
        <div class="tab-pane fade" id="tab-asaas">
            <div class="card mb-3">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>Importar cobranças do Asaas</strong>
                        <div class="small text-muted">Puxa as cobranças de TODAS as contas Asaas ativas com token (finance_accounts) e consolida aqui como ganhos. Registros já importados são ignorados — pode sincronizar quantas vezes quiser.</div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-sm btn-primary" id="btn-sync" onclick="syncAsaas()"><i class="bi bi-cloud-arrow-down"></i> Sincronizar agora</button>
                        <div id="sync-result" class="small mt-1"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header py-2"><strong>Contas Asaas configuradas</strong></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Nome</th><th>Finalidade</th><th>Ambiente</th><th>Token</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($accounts)): ?><tr><td colspan="5" class="text-center text-muted py-4">Nenhuma conta Asaas. Cadastre em Financeiro → Contas Asaas.</td></tr>
                            <?php else: foreach ($accounts as $a): ?>
                            <tr>
                                <td class="fw-medium"><?= escape($a['name']) ?></td>
                                <td><?= escape($a['purpose']) ?></td>
                                <td><?= ((int)$a['sandbox'] === 1) ? '<span class="badge bg-warning">Sandbox</span>' : '<span class="badge bg-success">Produção</span>' ?></td>
                                <td><?= !empty($a['asaas_token']) ? '<i class="bi bi-key-fill text-success"></i> configurado' : '<span class="text-danger">sem token</span>' ?></td>
                                <td><?= ((int)$a['active'] === 1) ? 'Ativa' : 'Inativa' ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
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

// Mostra/esconde campos conforme o tipo escolhido.
// canParcel: tipos que aceitam parcelamento; canRecur: aceitam recorrência.
const KIND_DIRECTIONS = { ganho: null, gasto: null, salario: null, emprestimo: 'both', transferencia: 'both' };
const KIND_PARCEL = { ganho: true, gasto: true, salario: false, emprestimo: true, transferencia: false };
function refreshKindFields() {
    const kind = document.getElementById('f-kind').value;
    const dirWrap = document.getElementById('f-direction-wrap');
    const dirOut = document.getElementById('f-dir-out-opt');
    const dirIn = document.getElementById('f-dir-in-opt');
    const canParcel = KIND_PARCEL[kind] === true;
    document.getElementById('f-card-wrap').style.display = kind === 'gasto' ? '' : 'none';
    document.getElementById('f-loandate-wrap').style.display = kind === 'emprestimo' ? '' : 'none';
    document.getElementById('f-recurring-wrap').style.display = (kind === 'ganho' || kind === 'gasto') ? '' : 'none';
    const d = KIND_DIRECTIONS[kind];
    dirWrap.style.display = d === 'both' ? '' : 'none';
    if (d === 'both') {
        dirOut.textContent = (kind === 'emprestimo') ? 'Tomado (saída)' : 'Enviada (saída)';
        dirIn.textContent  = (kind === 'emprestimo') ? 'Concedido (entrada)' : 'Recebida (entrada)';
    }
}
document.getElementById('f-kind').addEventListener('change', refreshKindFields);
refreshKindFields();
document.getElementById('f-recurring-chk').addEventListener('change', function() {
    document.getElementById('f-recurring-fields').style.display = this.checked ? '' : 'none';
});

// ===== Comissões =====
let comMode = 'fixo';
function setComMode(mode) {
    comMode = mode;
    document.getElementById('com-mode-fixo').classList.toggle('btn-primary', mode === 'fixo');
    document.getElementById('com-mode-pct').classList.toggle('btn-primary', mode === 'pct');
    document.getElementById('com-amount').placeholder = mode === 'pct' ? '0-100 (%)' : '0,00';
}
setComMode('fixo');

async function saveCommission() {
    const fd = new FormData();
    fd.append('transaction_id', document.getElementById('com-ganho').value);
    if (document.getElementById('com-user').value) fd.append('user_id', document.getElementById('com-user').value);
    fd.append('recipient', document.getElementById('com-recipient').value.trim());
    if (comMode === 'pct') fd.append('percent', document.getElementById('com-amount').value);
    else fd.append('amount', document.getElementById('com-amount').value);
    fd.append('installments', document.getElementById('com-installments').value || '1');
    fd.append('first_due_date', document.getElementById('com-due').value);
    const r = await post('financial/commissionStore', fd);
    if (r) location.reload();
}
async function payCommission(id) { const r = await post('financial/commissionPay/' + id, new FormData()); if (r) location.reload(); }
async function removeCommission(id) {
    if (!confirm('Excluir esta comissão?')) return;
    const r = await post('financial/commissionRemove/' + id, new FormData());
    if (r) location.reload();
}

async function post(url, fd) {
    fd.append('csrf_token', CSRF);
    const r = await fetch(BASE + url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(x => x.json()).catch(() => ({ error: 'Falha de rede' }));
    if (r.error) { alert(r.error); return null; }
    return r;
}

async function saveTransaction() {
    const fd = new FormData();
    const kind = document.getElementById('f-kind').value;
    fd.append('kind', kind);
    if (KIND_DIRECTIONS[kind] === 'both') fd.append('direction', document.getElementById('f-direction').value);
    fd.append('category', document.getElementById('f-category').value);
    fd.append('description', document.getElementById('f-description').value);
    fd.append('amount', document.getElementById('f-amount').value);
    fd.append('installments', document.getElementById('f-installments').value || '1');
    if (kind === 'gasto') fd.append('card_id', document.getElementById('f-card').value);
    fd.append('due_date', document.getElementById('f-due').value);
    if (kind === 'emprestimo') fd.append('loan_date', document.getElementById('f-loandate').value);
    if (kind === 'ganho' || kind === 'gasto') {
        if (document.getElementById('f-recurring-chk') && document.getElementById('f-recurring-chk').checked) {
            fd.append('recurring_cycle', document.getElementById('f-cycle').value);
            fd.append('recurring_until', document.getElementById('f-recurring-until').value);
        }
    }
    fd.append('status', document.getElementById('f-paid').checked ? 'paid' : 'pending');
    const r = await post('financial/store', fd);
    if (r) location.reload();
}

async function payTx(id) { const r = await post('financial/pay/' + id, new FormData()); if (r) location.reload(); }
async function unpayTx(id) { const r = await post('financial/unpay/' + id, new FormData()); if (r) location.reload(); }
async function removeTx(id) {
    if (!confirm('Excluir este lançamento?')) return;
    const r = await post('financial/remove/' + id, new FormData());
    if (r) location.reload();
}
async function saveCard() {
    const fd = new FormData();
    fd.append('name', document.getElementById('c-name').value.trim());
    fd.append('limit', document.getElementById('c-limit').value);
    fd.append('closing_day', document.getElementById('c-closing').value);
    fd.append('due_day', document.getElementById('c-due').value);
    const r = await post('financial/storeCard', fd);
    if (r) location.reload();
}
async function toggleCard(id) { const r = await post('financial/cardToggle/' + id, new FormData()); if (r) location.reload(); }

async function syncAsaas() {
    const btn = document.getElementById('btn-sync');
    const out = document.getElementById('sync-result');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';
    const r = await post('financial/asaasSync', new FormData());
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-cloud-arrow-down"></i> Sincronizar agora';
    if (!r) return;
    let msg = `Importados: ${r.imported} · Já existentes: ${r.skipped} · Contas: ${r.accounts}`;
    if (r.failed && r.failed.length) msg += ` · Falhas: ${r.failed.join('; ')}`;
    out.textContent = msg;
    if (r.imported > 0) setTimeout(() => location.reload(), 1500);
}
</script>
<?php require APP_PATH . '/views/layouts/footer.php'; ?>