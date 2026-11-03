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
$bankById = [];
foreach (($bankAccounts ?? []) as $ba) $bankById[(int)$ba['id']] = $ba;
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
                    <div class="text-muted small"><i class="bi bi-check-circle"></i> Recebido no mês</div>
                    <div class="fw-bold text-success"><?= finMoney($summary['received']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-success-subtle bg-opacity-25">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-hourglass-split"></i> A Receber</div>
                    <div class="fw-bold text-success-emphasis"><?= finMoney($summary['to_receive']) ?></div>
                    <?php if ($summary['overdue_receive'] > 0): ?><div class="small text-danger" style="font-size:0.65rem;">atrasado: <?= finMoney($summary['overdue_receive']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-danger bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-check2"></i> Pago no mês</div>
                    <div class="fw-bold text-danger"><?= finMoney($summary['paid']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-danger-subtle bg-opacity-25">
                <div class="card-body py-2">
                    <div class="text-muted small"><i class="bi bi-hourglass-top"></i> A Pagar</div>
                    <div class="fw-bold text-danger-emphasis"><?= finMoney($summary['to_pay']) ?></div>
                    <?php if ($summary['overdue_pay'] > 0): ?><div class="small text-danger" style="font-size:0.65rem;">atrasado: <?= finMoney($summary['overdue_pay']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-secondary bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small" title="Soma do saldo atual das contas bancárias ativas"><i class="bi bi-wallet2"></i> Saldo em contas (hoje)</div>
                    <div class="fw-bold <?= $summary['accounts_balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= finMoney($summary['accounts_balance']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100 border-0 bg-secondary bg-opacity-10">
                <div class="card-body py-2">
                    <div class="text-muted small" title="Saldo em contas + a receber − a pagar até o fim do mês"><i class="bi bi-calendar-check"></i> Projetado (fim de <?= escape(date('m/Y', strtotime($filters['month'] . '-01'))) ?>)</div>
                    <div class="fw-bold <?= $summary['projected'] >= 0 ? '' : 'text-danger' ?>"><?= finMoney($summary['projected']) ?></div>
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
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contas" type="button"><i class="bi bi-bank"></i> Contas (<?= count($bankAccounts) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-categorias" type="button"><i class="bi bi-tags"></i> Categorias (<?= count($categories) ?>)</button></li>
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
                            <select id="f-category" class="form-select form-select-sm">
                                <option value="">— Sem categoria —</option>
                                <?php foreach ($categories as $cat): if ((int)$cat['active'] !== 1) continue; ?>
                                <option value="<?= (int)$cat['id'] ?>" data-kind="<?= escape($cat['kind']) ?>"><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="small text-muted" style="font-size:0.65rem;">Cadastre em <i class="bi bi-tags"></i> Categorias.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Conta</label>
                            <select id="f-account" class="form-select form-select-sm">
                                <option value="">— Sem conta —</option>
                                <?php foreach ($bankAccounts as $ba): if ((int)$ba['active'] !== 1) continue; ?>
                                <option value="<?= (int)$ba['id'] ?>"><?= escape($ba['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Pessoa (equipe)</label>
                            <select id="f-user" class="form-select form-select-sm">
                                <option value="">— Ninguém —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= escape($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                            <label class="form-label small">Cartão</label>
                            <select id="f-card" class="form-select form-select-sm">
                                <option value="">Sem cartão (débito/pix)</option>
                                <?php foreach ($cards as $c): if ((int)$c['active'] !== 1) continue; ?>
                                <option value="<?= (int)$c['id'] ?>" data-closing="<?= (int)($c['closing_day'] ?? 0) ?>" data-due="<?= (int)($c['due_day'] ?? 0) ?>"><?= escape($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2" id="f-loandate-wrap" style="display:none;">
                            <label class="form-label small" id="f-loandate-label">Data do empréstimo</label>
                            <input id="f-loandate" type="date" class="form-control form-control-sm" title="Dia em que o dinheiro entrou ou saiu">
                        </div>
                        <div class="col-md-2" id="f-principal-wrap" style="display:none;">
                            <label class="form-label small" id="f-principal-label">Valor recebido (R$)</label>
                            <input id="f-principal" class="form-control form-control-sm" placeholder="Vazio = mesmo valor" inputmode="decimal" title="Quanto entrou ou saiu no dia (sem juros). Vazio = o valor total do empréstimo.">
                        </div>
                        <div class="col-12" id="f-loan-hint-wrap" style="display:none;">
                            <div class="small text-primary" style="font-size:0.75rem;" id="f-loan-hint"></div>
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
                                <span class="small text-muted text-nowrap">até</span>
                                <input id="f-recurring-until" type="month" class="form-control form-control-sm" title="Último mês da repetição (incluído). Vazio = para sempre.">
                            </div>
                            <div class="small text-muted" style="font-size:0.65rem;" id="f-recurring-hint">Último mês incluído. Vazio = repete para sempre. Os próximos meses aparecem como "Previsto".</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small" id="f-due-label">Vencimento *</label>
                            <input id="f-due" type="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                            <div class="small text-primary" style="font-size:0.7rem;" id="f-invoice-hint"></div>
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

            <?php
            // ===== Filtros e relatório =====
            $qs = function (array $override = []) use ($filters) {
                $base = $filters['mode'] === 'range'
                    ? ['from' => $filters['from'], 'to' => $filters['to']]
                    : ['month' => $filters['month']];
                foreach (['kind', 'direction', 'status', 'source', 'q', 'group'] as $k) if ($filters[$k] !== '') $base[$k] = $filters[$k];
                foreach (['category_id', 'account_id', 'card_id', 'user_id'] as $k) if (!empty($filters[$k])) $base[$k] = $filters[$k];
                return http_build_query(array_merge($base, $override));
            };
            $sel = fn($a, $b) => ((string)$a === (string)$b) ? 'selected' : '';
            $groupLabels = ['category' => 'Categoria', 'kind' => 'Tipo', 'account' => 'Conta', 'card' => 'Cartão',
                            'person' => 'Pessoa', 'month' => 'Mês', 'status' => 'Situação'];
            ?>
            <div class="card mb-3">
                <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <strong><i class="bi bi-funnel"></i> Filtros e relatório</strong>
                    <div class="d-flex gap-1">
                        <?php if ($filters['mode'] === 'month'):
                            $prev = date('Y-m', strtotime($filters['month'] . '-01 -1 month'));
                            $next = date('Y-m', strtotime($filters['month'] . '-01 +1 month')); ?>
                        <a class="btn btn-sm btn-outline-secondary py-0" href="<?= baseUrl('financial?' . $qs(['month' => $prev])) ?>" title="Mês anterior"><i class="bi bi-chevron-left"></i></a>
                        <a class="btn btn-sm btn-outline-secondary py-0" href="<?= baseUrl('financial?' . $qs(['month' => $next])) ?>" title="Próximo mês"><i class="bi bi-chevron-right"></i></a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-success py-0" href="<?= baseUrl('financial/export?' . $qs()) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar planilha</a>
                    </div>
                </div>
                <div class="card-body py-2">
                    <form method="get" action="<?= baseUrl('financial') ?>" id="filter-form" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Período</label>
                            <select class="form-select form-select-sm" id="flt-mode" onchange="toggleFilterMode()">
                                <option value="month" <?= $sel($filters['mode'], 'month') ?>>Um mês</option>
                                <option value="range" <?= $sel($filters['mode'], 'range') ?>>Intervalo de datas</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="flt-month-wrap">
                            <label class="form-label small mb-0">Mês</label>
                            <input type="month" class="form-control form-control-sm" name="month" value="<?= escape($filters['month']) ?>">
                        </div>
                        <div class="col-md-2 flt-range-wrap">
                            <label class="form-label small mb-0">De</label>
                            <input type="date" class="form-control form-control-sm" name="from" value="<?= escape($filters['from']) ?>">
                        </div>
                        <div class="col-md-2 flt-range-wrap">
                            <label class="form-label small mb-0">Até</label>
                            <input type="date" class="form-control form-control-sm" name="to" value="<?= escape($filters['to']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Tipo</label>
                            <select class="form-select form-select-sm" name="kind">
                                <option value="">Todos</option>
                                <?php foreach ($kindLabel as $k => $meta): ?>
                                <option value="<?= $k ?>" <?= $sel($filters['kind'], $k) ?>><?= $meta['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Entrada/saída</label>
                            <select class="form-select form-select-sm" name="direction">
                                <option value="">Ambas</option>
                                <option value="in" <?= $sel($filters['direction'], 'in') ?>>Só entradas</option>
                                <option value="out" <?= $sel($filters['direction'], 'out') ?>>Só saídas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Situação</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">Todas</option>
                                <option value="paid" <?= $sel($filters['status'], 'paid') ?>>Pagos</option>
                                <option value="pending" <?= $sel($filters['status'], 'pending') ?>>Em aberto</option>
                                <option value="overdue" <?= $sel($filters['status'], 'overdue') ?>>Atrasados</option>
                                <option value="projected" <?= $sel($filters['status'], 'projected') ?>>Previstos (recorrentes)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Categoria</label>
                            <select class="form-select form-select-sm" name="category_id">
                                <option value="">Todas</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>" <?= $sel($filters['category_id'], $cat['id']) ?>><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Conta</label>
                            <select class="form-select form-select-sm" name="account_id">
                                <option value="">Todas</option>
                                <?php foreach ($bankAccounts as $ba): ?>
                                <option value="<?= (int)$ba['id'] ?>" <?= $sel($filters['account_id'], $ba['id']) ?>><?= escape($ba['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Cartão</label>
                            <select class="form-select form-select-sm" name="card_id">
                                <option value="">Todos</option>
                                <?php foreach ($cards as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $sel($filters['card_id'], $c['id']) ?>><?= escape($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Pessoa</label>
                            <select class="form-select form-select-sm" name="user_id">
                                <option value="">Todas</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= $sel($filters['user_id'], $u['id']) ?>><?= escape($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Buscar</label>
                            <input class="form-control form-control-sm" name="q" value="<?= escape($filters['q']) ?>" placeholder="Descrição, categoria...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Agrupar relatório por</label>
                            <select class="form-select form-select-sm" name="group">
                                <option value="">Sem relatório</option>
                                <?php foreach ($groupLabels as $g => $gl): ?>
                                <option value="<?= $g ?>" <?= $sel($filters['group'], $g) ?>><?= $gl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i> Filtrar</button>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= baseUrl('financial?month=' . urlencode($filters['month'])) ?>" title="Limpar filtros">Limpar</a>
                        </div>
                    </form>

                    <?php // Totais da lista filtrada ?>
                    <div class="d-flex flex-wrap gap-3 small mt-2 pt-2 border-top">
                        <span class="text-muted">
                            <?= $filters['mode'] === 'range'
                                ? escape(date('d/m/Y', strtotime($filters['from'])) . ' a ' . date('d/m/Y', strtotime($filters['to'])))
                                : escape(date('m/Y', strtotime($filters['month'] . '-01'))) ?>
                            · <?= (int)$listTotals['count'] ?> lançamento(s)<?= $hasFilters ? ' com filtro' : '' ?>
                        </span>
                        <span>Entradas: <strong class="text-success"><?= finMoney($listTotals['in']) ?></strong></span>
                        <span>Saídas: <strong class="text-danger"><?= finMoney($listTotals['out']) ?></strong></span>
                        <span>Saldo: <strong class="<?= $listTotals['net'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= finMoney($listTotals['net']) ?></strong></span>
                        <span class="text-muted">Pago: <?= finMoney($listTotals['paid_out']) ?> saídas / <?= finMoney($listTotals['paid_in']) ?> entradas</span>
                        <span class="text-muted">Em aberto: <?= finMoney($listTotals['open_out']) ?> saídas / <?= finMoney($listTotals['open_in']) ?> entradas</span>
                    </div>

                    <?php if (!empty($report)): ?>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><?= escape($groupLabels[$filters['group']] ?? 'Grupo') ?></th>
                                    <th class="text-center">Qtd.</th>
                                    <th class="text-end">Entradas</th><th class="text-end">Saídas</th><th class="text-end">Saldo</th>
                                    <th class="text-end">Pago</th><th class="text-end">Em aberto</th><th class="text-end">% das saídas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report as $g): $pct = $listTotals['out'] > 0 ? round($g['out'] / $listTotals['out'] * 100, 1) : 0; ?>
                                <tr>
                                    <td class="fw-medium"><?= escape($g['label']) ?></td>
                                    <td class="text-center"><?= (int)$g['count'] ?></td>
                                    <td class="text-end text-success"><?= finMoney($g['in']) ?></td>
                                    <td class="text-end text-danger"><?= finMoney($g['out']) ?></td>
                                    <td class="text-end fw-bold <?= $g['net'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= finMoney($g['net']) ?></td>
                                    <td class="text-end"><?= finMoney($g['paid_in'] + $g['paid_out']) ?></td>
                                    <td class="text-end"><?= finMoney($g['open_in'] + $g['open_out']) ?></td>
                                    <td class="text-end">
                                        <?= $pct ?>%
                                        <div class="progress" style="height:3px;"><div class="progress-bar bg-danger" style="width:<?= min(100, $pct) ?>%"></div></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>Total</td><td class="text-center"><?= (int)$listTotals['count'] ?></td>
                                    <td class="text-end text-success"><?= finMoney($listTotals['in']) ?></td>
                                    <td class="text-end text-danger"><?= finMoney($listTotals['out']) ?></td>
                                    <td class="text-end"><?= finMoney($listTotals['net']) ?></td>
                                    <td class="text-end"><?= finMoney($listTotals['paid_in'] + $listTotals['paid_out']) ?></td>
                                    <td class="text-end"><?= finMoney($listTotals['open_in'] + $listTotals['open_out']) ?></td>
                                    <td class="text-end">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Vencimento</th><th>Tipo</th><th>Categoria</th><th>Descrição</th>
                                <th>Conta</th><th>Origem</th><th>Parcelas</th><th class="text-end">Valor</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                            <tr><td colspan="10" class="text-center text-muted py-4">Nenhum lançamento neste filtro.</td></tr>
                            <?php else: foreach ($transactions as $t):
                                $meta = $kindLabel[$t['kind']] ?? $kindLabel['gasto'];
                                $st = $statusLabel[$t['status']] ?? $statusLabel['pending'];
                            ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= escape($t['due_date'] ?? '—') ?>
                                    <?php if (!empty($t['purchase_date']) && $t['purchase_date'] !== $t['due_date']): ?>
                                    <div class="text-muted" style="font-size:0.65rem;" title="Data da compra no cartão">compra <?= escape($t['purchase_date']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= $meta['cls'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?></span></td>
                                <td><?= escape($t['category_name'] ?? $t['category'] ?? '—') ?></td>
                                <td>
                                    <?= escape($t['description'] ?? '—') ?>
                                    <?php if (!empty($t['person_name'])): ?>
                                    <div class="small text-muted" style="font-size:0.7rem;"><i class="bi bi-person"></i> <?= escape($t['person_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?php if (!empty($t['bank_account_name'])): ?>
                                        <i class="bi bi-bank"></i> <?= escape($t['bank_account_name']) ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
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
                                    <?php if (!empty($t['installment_no'])): ?><span class="badge bg-secondary" title="Parcela <?= (int)$t['installment_no'] ?> de <?= (int)$t['installments'] ?>"><?= (int)$t['installment_no'] ?>/<?= (int)$t['installments'] ?></span>
                                    <?php elseif ((int)$t['installments'] > 1): ?><?= (int)$t['installments'] ?>x
                                    <?php elseif (!empty($t['recurring_cycle'])): ?><span class="badge bg-info" title="Recorrente (<?= $t['recurring_cycle'] === 'yearly' ? 'anual' : 'mensal' ?><?= $t['recurring_until'] ? ' até ' . escape($t['recurring_until']) : '' ?>)"><i class="bi bi-arrow-repeat"></i> rec.</span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-nowrap <?= $t['direction'] === 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?= $t['direction'] === 'in' ? '+' : '−' ?> <?= finMoney($t['amount']) ?>
                                    <?php if (!empty($t['installment_no'])): ?>
                                    <div class="small text-muted fw-normal" style="font-size:0.65rem;">de <?= finMoney($t['total_amount']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($t['is_projected'])): ?>
                                    <span class="badge bg-light text-dark border" title="Ocorrência futura de um lançamento recorrente. Ainda não foi gravada.">Previsto</span>
                                    <?php else: ?>
                                    <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if (!empty($t['is_projected'])): ?>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar esta ocorrência" onclick="payProjected(<?= (int)$t['projected_from'] ?>, '<?= escape($t['due_date']) ?>')"><i class="bi bi-check-lg"></i></button>
                                    <span class="small text-muted" style="font-size:0.65rem;" title="Para mudar valor ou conta, edite a ocorrência mais recente: as próximas seguem ela.">recorrente</span>
                                    <?php else: ?>
                                    <?php
                                    $editPayload = [
                                        'id'            => (int)$t['id'],
                                        'kind'          => $t['kind'],
                                        'description'   => $t['description'],
                                        'amount'        => (float)$t['amount'],
                                        'category_id'   => $t['category_id'] ?? null,
                                        'account_id'    => $t['account_id'] ?? null,
                                        'user_id'       => $t['user_id'] ?? null,
                                        'card_id'       => $t['card_id'] ?? null,
                                        'due_date'      => $t['due_date'],
                                        'purchase_date' => $t['purchase_date'] ?? null,
                                        'editable'      => FinancialRules::editableFields($t),
                                    ];
                                    ?>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar" data-tx="<?= escape(json_encode($editPayload)) ?>" onclick="openEditTx(this)"><i class="bi bi-pencil"></i></button>
                                    <?php if (!empty($t['installment_id'])): // linha de parcela ?>
                                        <?php if ($t['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar esta parcela" onclick="payInstallment(<?= (int)$t['installment_id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                        <?php elseif ($t['status'] === 'paid' && $t['source'] !== 'asaas'): ?>
                                        <button class="btn btn-sm btn-outline-warning py-0 px-1" title="Estornar esta parcela" onclick="unpayInstallment(<?= (int)$t['installment_id'] ?>)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        <?php endif; ?>
                                    <?php elseif ($t['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar" onclick="payTx(<?= (int)$t['id'] ?>)"><i class="bi bi-check-lg"></i></button>
                                    <?php elseif ($t['source'] !== 'asaas'): ?>
                                    <button class="btn btn-sm btn-outline-warning py-0 px-1" title="Estornar" onclick="unpayTx(<?= (int)$t['id'] ?>)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <?php endif; ?>
                                    <?php if ($t['source'] !== 'asaas'): ?>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir" onclick="removeTx(<?= (int)$t['id'] ?>, <?= (int)$t['installments'] ?>)"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                    <?php endif; // is_projected ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // ================= CONTAS BANCÁRIAS ================= ?>
        <div class="tab-pane fade" id="tab-contas">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Nova conta bancária</strong> <span class="small text-muted">— saldo inicial + entradas pagas − saídas pagas = saldo atual</span></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3"><label class="form-label small">Nome *</label><input id="ba-name" class="form-control form-control-sm" placeholder="Ex.: Nubank PJ"></div>
                        <div class="col-md-3"><label class="form-label small">Banco</label><input id="ba-bank" class="form-control form-control-sm" placeholder="Ex.: Nubank, Inter"></div>
                        <div class="col-md-3"><label class="form-label small">Saldo inicial (R$)</label><input id="ba-opening" class="form-control form-control-sm" placeholder="0,00" inputmode="decimal"></div>
                        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-primary" onclick="saveAccount()"><i class="bi bi-plus-lg"></i> Salvar conta</button></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Nome</th><th>Banco</th><th class="text-end">Saldo inicial</th><th class="text-end">Saldo atual</th><th class="text-end">Previsto</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($bankAccounts)): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhuma conta bancária. Cadastre acima.</td></tr>
                            <?php else: foreach ($bankAccounts as $ba): ?>
                            <tr>
                                <td class="fw-medium"><i class="bi bi-bank"></i> <?= escape($ba['name']) ?></td>
                                <td class="small text-muted"><?= escape($ba['bank'] ?? '—') ?></td>
                                <td class="text-end"><?= finMoney($ba['opening_balance']) ?></td>
                                <td class="text-end fw-bold <?= (float)$ba['balance'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= finMoney($ba['balance']) ?></td>
                                <td class="text-end <?= (float)$ba['projected'] >= 0 ? 'text-muted' : 'text-danger' ?>"><?= finMoney($ba['projected']) ?></td>
                                <td><?= ((int)$ba['active'] === 1) ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Inativa</span>' ?></td>
                                <td class="text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar (nome, banco, saldo inicial)" data-acc="<?= escape(json_encode(['id' => (int)$ba['id'], 'name' => $ba['name'], 'bank' => $ba['bank'], 'opening_balance' => (float)$ba['opening_balance']])) ?>" onclick="openEditAccount(this)"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Ativar/Inativar" onclick="toggleAccount(<?= (int)$ba['id'] ?>)"><i class="bi bi-power"></i></button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir (desvincula lançamentos)" onclick="removeAccount(<?= (int)$ba['id'] ?>)"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // ================= CATEGORIAS ================= ?>
        <div class="tab-pane fade" id="tab-categorias">
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Nova categoria</strong> <span class="small text-muted">— depois selecionável no lançamento</span></div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label small">Nome *</label><input id="cat-name" class="form-control form-control-sm" placeholder="Ex.: Infra, Vendas, Ferramentas"></div>
                        <div class="col-md-3">
                            <label class="form-label small">Aparece em</label>
                            <select id="cat-kind" class="form-select form-select-sm">
                                <option value="ambos">Ganhos e gastos</option>
                                <option value="ganho">Só ganhos</option>
                                <option value="gasto">Só gastos</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-primary" onclick="saveCategory()"><i class="bi bi-plus-lg"></i> Salvar categoria</button></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Nome</th><th>Aparece em</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($categories)): ?><tr><td colspan="4" class="text-center text-muted py-4">Nenhuma categoria. Cadastre acima.</td></tr>
                            <?php else:
                                $catKindLabel = ['ambos' => 'Ganhos e gastos', 'ganho' => 'Só ganhos', 'gasto' => 'Só gastos'];
                                foreach ($categories as $cat): ?>
                            <tr>
                                <td class="fw-medium"><i class="bi bi-tag"></i> <?= escape($cat['name']) ?></td>
                                <td class="small text-muted"><?= escape($catKindLabel[$cat['kind']] ?? $cat['kind']) ?></td>
                                <td><?= ((int)$cat['active'] === 1) ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Inativa</span>' ?></td>
                                <td class="text-nowrap">
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Ativar/Inativar" onclick="toggleCategory(<?= (int)$cat['id'] ?>)"><i class="bi bi-power"></i></button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Excluir (desvincula lançamentos)" onclick="removeCategory(<?= (int)$cat['id'] ?>)"><i class="bi bi-trash"></i></button>
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
                        <div class="col-md-3">
                            <label class="form-label small">Conta p/ pagar a fatura</label>
                            <select id="c-account" class="form-select form-select-sm">
                                <option value="">— Sem conta —</option>
                                <?php foreach ($bankAccounts as $ba): if ((int)$ba['active'] !== 1) continue; ?>
                                <option value="<?= (int)$ba['id'] ?>"><?= escape($ba['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-primary" onclick="saveCard()"><i class="bi bi-plus-lg"></i> Salvar cartão</button></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Nome</th><th>Conta vinculada</th><th class="text-end">Limite</th><th class="text-end">Em aberto (todas as faturas)</th><th>Fechamento</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($cards)): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhum cartão.</td></tr>
                            <?php else: foreach ($cards as $c):
                                $pend = $cardPending[(int)$c['id']] ?? 0.0;
                                $usage = ((float)$c['limit'] > 0) ? round($pend / (float)$c['limit'] * 100, 1) : null;
                            ?>
                            <tr>
                                <td class="fw-medium"><i class="bi bi-credit-card"></i> <?= escape($c['name']) ?></td>
                                <td class="small text-muted">
                                    <?php $cAcc = isset($c['account_id']) ? ($bankById[(int)$c['account_id']] ?? null) : null; ?>
                                    <?php if ($cAcc): ?><i class="bi bi-bank"></i> <?= escape($cAcc['name']) ?><?php else: ?>—<?php endif; ?>
                                </td>
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

            <?php
            // Faturas: compras à vista + parcelas, agrupadas pelo mês de vencimento.
            $monthNames = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
            $fmtYm = function ($ym) use ($monthNames) { return ($monthNames[(int)substr($ym, 5, 2)] ?? '') . '/' . substr($ym, 0, 4); };
            ?>
            <div class="card mt-3">
                <div class="card-header py-2"><strong><i class="bi bi-receipt"></i> Faturas</strong> <span class="small text-muted">— cada compra entra na fatura conforme o dia de fechamento; o mês é o do vencimento (quando o dinheiro sai da conta)</span></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Cartão</th><th>Fatura (vencimento)</th><th class="text-center">Itens</th><th class="text-end">Total</th><th class="text-end">Em aberto</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            <?php $anyInvoice = false; foreach ($cards as $c):
                                foreach (($invoices[(int)$c['id']] ?? []) as $ym => $inv): $anyInvoice = true;
                                    $open = (float)$inv['pending'] > 0;
                                    $dueDay = (int)($c['due_day'] ?? 0);
                                    $isCurrent = ($ym === date('Y-m'));
                            ?>
                            <tr class="<?= $isCurrent ? 'table-warning' : '' ?>">
                                <td class="fw-medium"><i class="bi bi-credit-card"></i> <?= escape($c['name']) ?></td>
                                <td class="text-nowrap"><?= escape($fmtYm($ym)) ?><?= $dueDay ? ' <span class="small text-muted">(vence dia ' . $dueDay . ')</span>' : '' ?></td>
                                <td class="text-center"><?= (int)$inv['items'] ?></td>
                                <td class="text-end"><?= finMoney($inv['total']) ?></td>
                                <td class="text-end fw-bold <?= $open ? 'text-danger' : 'text-muted' ?>"><?= finMoney($inv['pending']) ?></td>
                                <td><?= $open ? '<span class="badge bg-warning">Em aberto</span>' : '<span class="badge bg-success">Paga</span>' ?></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary py-0 px-1" title="Ver lançamentos deste mês" href="<?= baseUrl('financial?month=' . urlencode($ym) . '&card_id=' . (int)$c['id']) ?>"><i class="bi bi-list-ul"></i></a>
                                    <?php if ($open): ?>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1" title="Pagar a fatura inteira" onclick="payInvoice(<?= (int)$c['id'] ?>, '<?= escape($ym) ?>', '<?= escape($c['name'] . ' ' . $fmtYm($ym)) ?>')"><i class="bi bi-check2-all"></i> Pagar</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endforeach; ?>
                            <?php if (!$anyInvoice): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhuma compra no cartão ainda.</td></tr><?php endif; ?>
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

<?php // ================= MODAL: EDITAR CONTA ================= ?>
<div class="modal fade" id="editAccModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-bank"></i> Editar conta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small">Nome *</label>
                <input id="ea-name" class="form-control form-control-sm mb-2">
                <label class="form-label small">Banco</label>
                <input id="ea-bank" class="form-control form-control-sm mb-2">
                <label class="form-label small">Saldo inicial (R$)</label>
                <input id="ea-opening" class="form-control form-control-sm" inputmode="decimal">
                <div class="small text-muted mt-1" style="font-size:0.7rem;">Valor que a conta tinha antes do primeiro lançamento registrado aqui. Pode ser negativo.</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveEditAccount()"><i class="bi bi-check-lg"></i> Salvar</button>
            </div>
        </div>
    </div>
</div>

<?php // ================= MODAL: EDITAR LANÇAMENTO ================= ?>
<div class="modal fade" id="editTxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-pencil"></i> Editar lançamento <span id="e-id" class="text-muted"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="e-restriction"></div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small">Descrição</label>
                        <input id="e-description" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Categoria</label>
                        <select id="e-category" class="form-select form-select-sm">
                            <option value="">— Sem categoria —</option>
                            <?php foreach ($categories as $cat): if ((int)$cat['active'] !== 1) continue; ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= escape($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Conta</label>
                        <select id="e-account" class="form-select form-select-sm">
                            <option value="">— Sem conta —</option>
                            <?php foreach ($bankAccounts as $ba): if ((int)$ba['active'] !== 1) continue; ?>
                            <option value="<?= (int)$ba['id'] ?>"><?= escape($ba['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Pessoa (equipe)</label>
                        <select id="e-user" class="form-select form-select-sm">
                            <option value="">— Ninguém —</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= escape($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Valor (R$)</label>
                        <input id="e-amount" class="form-control form-control-sm" inputmode="decimal">
                    </div>
                    <div class="col-6" id="e-card-wrap">
                        <label class="form-label small">Cartão</label>
                        <select id="e-card" class="form-select form-select-sm">
                            <option value="">Sem cartão (débito/pix)</option>
                            <?php foreach ($cards as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-closing="<?= (int)($c['closing_day'] ?? 0) ?>" data-due="<?= (int)($c['due_day'] ?? 0) ?>"><?= escape($c['name']) ?><?= ((int)$c['active'] !== 1) ? ' (inativo)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small" id="e-date-label">Vencimento</label>
                        <input id="e-date" type="date" class="form-control form-control-sm">
                        <div class="small text-primary" style="font-size:0.7rem;" id="e-invoice-hint"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveEditTx()"><i class="bi bi-check-lg"></i> Salvar</button>
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
const RECURRING_KINDS = <?= json_encode(FinancialRules::RECURRING_KINDS) ?>;
function refreshKindFields() {
    const kind = document.getElementById('f-kind').value;
    const dirWrap = document.getElementById('f-direction-wrap');
    const dirOut = document.getElementById('f-dir-out-opt');
    const dirIn = document.getElementById('f-dir-in-opt');
    const canParcel = KIND_PARCEL[kind] === true;
    document.getElementById('f-card-wrap').style.display = kind === 'gasto' ? '' : 'none';
    document.getElementById('f-loandate-wrap').style.display = kind === 'emprestimo' ? '' : 'none';
    document.getElementById('f-recurring-wrap').style.display = RECURRING_KINDS.includes(kind) ? '' : 'none';
    const d = KIND_DIRECTIONS[kind];
    dirWrap.style.display = d === 'both' ? '' : 'none';
    if (d === 'both') {
        dirOut.textContent = (kind === 'emprestimo') ? 'Tomado (saída)' : 'Enviada (saída)';
        dirIn.textContent  = (kind === 'emprestimo') ? 'Concedido (entrada)' : 'Recebida (entrada)';
    }
    filterCategoriesByKind(kind);
}

// Mostra só as categorias compatíveis com o tipo: 'ambos' sempre; 'ganho'/'gasto'
// apenas quando o lançamento é do mesmo tipo. Tipos sem categoria dedicada
// (salario/emprestimo/transferencia) veem apenas as 'ambos'.
function filterCategoriesByKind(kind) {
    const sel = document.getElementById('f-category');
    if (!sel) return;
    let resetNeeded = false;
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return; // "— Sem categoria —" sempre visível
        const catKind = opt.getAttribute('data-kind') || 'ambos';
        const show = (catKind === 'ambos') || (catKind === kind);
        opt.hidden = !show;
        if (!show && opt.selected) resetNeeded = true;
    });
    if (resetNeeded) sel.value = '';
}
document.getElementById('f-kind').addEventListener('change', refreshKindFields);
refreshKindFields();
// Mostra quantas vezes vai repetir com o mês final escolhido.
function refreshRecurringHint() {
    const hint = document.getElementById('f-recurring-hint');
    const until = document.getElementById('f-recurring-until').value; // YYYY-MM
    const due = document.getElementById('f-due').value;              // YYYY-MM-DD
    const yearly = document.getElementById('f-cycle').value === 'yearly';
    if (!until || !due) {
        hint.textContent = 'Último mês incluído. Vazio = repete para sempre. Os próximos meses aparecem como "Previsto".';
        return;
    }
    const [dy, dm] = due.split('-').map(Number);
    const [uy, um] = until.split('-').map(Number);
    const months = (uy * 12 + um) - (dy * 12 + dm);
    if (months < 0) { hint.textContent = 'O mês final é antes do vencimento: só este lançamento será criado.'; return; }
    const times = (yearly ? Math.floor(months / 12) : months) + 1;
    hint.textContent = `Repete ${times}x no total (de ${String(dm).padStart(2, '0')}/${dy} até ${String(um).padStart(2, '0')}/${uy}, incluído).`;
}
['f-recurring-until', 'f-cycle', 'f-due'].forEach(id => {
    document.getElementById(id).addEventListener('change', refreshRecurringHint);
    document.getElementById(id).addEventListener('input', refreshRecurringHint);
});
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
    fd.append('category_id', document.getElementById('f-category').value);
    fd.append('account_id', document.getElementById('f-account').value);
    fd.append('user_id', document.getElementById('f-user').value);
    fd.append('description', document.getElementById('f-description').value);
    fd.append('amount', document.getElementById('f-amount').value);
    fd.append('installments', document.getElementById('f-installments').value || '1');
    if (kind === 'gasto') fd.append('card_id', document.getElementById('f-card').value);
    fd.append('due_date', document.getElementById('f-due').value);
    if (kind === 'emprestimo') {
        fd.append('loan_date', document.getElementById('f-loandate').value);
        fd.append('loan_principal', document.getElementById('f-principal').value);
    }
    if (RECURRING_KINDS.includes(kind)) {
        if (document.getElementById('f-recurring-chk') && document.getElementById('f-recurring-chk').checked) {
            fd.append('recurring_cycle', document.getElementById('f-cycle').value);
            fd.append('recurring_until', document.getElementById('f-recurring-until').value);
        }
    }
    fd.append('status', document.getElementById('f-paid').checked ? 'paid' : 'pending');
    const r = await post('financial/store', fd);
    if (r) location.reload();
}

// ===== Ciclo da fatura do cartão (mesma regra de CardInvoiceRules.php) =====
const MONTHS = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
function lastDay(y, m) { return new Date(y, m, 0).getDate(); } // m = 1..12
function clampDay(y, m, d) { return Math.max(1, Math.min(d, lastDay(y, m))); }
function addMonths(y, m, n) { const idx = y * 12 + (m - 1) + n; return [Math.floor(idx / 12), (idx % 12) + 1]; }
function pad(n) { return String(n).padStart(2, '0'); }
// Retorna {due:'YYYY-MM-DD', label:'out/2026'} ou null quando o cartão não tem ciclo.
function invoiceFor(purchase, closing, due) {
    if (!purchase || !(closing >= 1 && closing <= 31) || !(due >= 1 && due <= 31)) return null;
    const [y, m, d] = purchase.split('-').map(Number);
    const closeThis = clampDay(y, m, closing);
    const [cy, cm] = d < closeThis ? [y, m] : addMonths(y, m, 1);
    const [dy, dm] = due > closing ? [cy, cm] : addMonths(cy, cm, 1);
    const dd = clampDay(dy, dm, due);
    return { due: `${dy}-${pad(dm)}-${pad(dd)}`, label: `${MONTHS[dm - 1]}/${dy}`, dueDay: `${pad(dd)}/${pad(dm)}` };
}
function cardCycle(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return null;
    return { closing: Number(opt.dataset.closing || 0), due: Number(opt.dataset.due || 0) };
}
function renderInvoiceHint(selectId, dateId, labelId, hintId, dateLabel) {
    const sel = document.getElementById(selectId);
    const label = document.getElementById(labelId);
    const hint = document.getElementById(hintId);
    const wrapVisible = sel && sel.offsetParent !== null;
    const cyc = (sel && wrapVisible) ? cardCycle(sel) : null;
    if (!cyc) { label.textContent = dateLabel; hint.textContent = ''; return; }
    label.textContent = 'Data da compra *';
    const inv = invoiceFor(document.getElementById(dateId).value, cyc.closing, cyc.due);
    hint.textContent = inv
        ? `Cai na fatura de ${inv.label} (vence ${inv.dueDay})`
        : 'Cartão sem dia de fechamento/vencimento: vence na data da compra.';
}
// Rótulo da data principal: vencimento (à vista) ou vencimento da 1ª parcela.
function dueLabel() {
    const n = parseInt(document.getElementById('f-installments').value || '1', 10);
    return n > 1 ? 'Vencimento da 1ª parcela *' : 'Vencimento *';
}
function refreshNewInvoiceHint() {
    renderInvoiceHint('f-card', 'f-due', 'f-due-label', 'f-invoice-hint', dueLabel());
    refreshLoanHint();
}
// Empréstimo: explica o movimento inicial e as parcelas no sentido de cada um.
function refreshLoanHint() {
    const kind = document.getElementById('f-kind').value;
    const isLoan = kind === 'emprestimo';
    document.getElementById('f-principal-wrap').style.display = isLoan ? '' : 'none';
    document.getElementById('f-loan-hint-wrap').style.display = isLoan ? '' : 'none';
    if (!isLoan) return;
    const concedido = document.getElementById('f-direction').value === 'in';
    document.getElementById('f-loandate-label').textContent = concedido ? 'Data do empréstimo (dinheiro sai)' : 'Data do empréstimo (dinheiro entra)';
    document.getElementById('f-principal-label').textContent = concedido ? 'Valor emprestado (R$)' : 'Valor recebido (R$)';
    const loanDate = document.getElementById('f-loandate').value;
    const n = parseInt(document.getElementById('f-installments').value || '1', 10);
    const parcelas = n > 1 ? `${n} parcelas` : 'o pagamento';
    let msg = concedido
        ? `A empresa emprestou: ${parcelas} serão ENTRADAS (a pessoa devolvendo).`
        : `A empresa pegou emprestado: ${parcelas} serão SAÍDAS (a empresa pagando).`;
    msg += loanDate
        ? (concedido ? ' No dia do empréstimo, o valor emprestado SAI da conta.' : ' No dia do empréstimo, o valor recebido ENTRA na conta.')
        : ' Preencha a data do empréstimo para registrar também o dinheiro que entrou/saiu no dia.';
    document.getElementById('f-loan-hint').textContent = msg;
}
['f-card', 'f-due', 'f-kind', 'f-installments', 'f-direction', 'f-loandate'].forEach(id => {
    document.getElementById(id).addEventListener('change', refreshNewInvoiceHint);
    document.getElementById(id).addEventListener('input', refreshNewInvoiceHint);
});
refreshNewInvoiceHint();

// ===== Editar lançamento =====
let editTx = null;
function openEditTx(btn) {
    editTx = JSON.parse(btn.dataset.tx);
    const can = f => editTx.editable.includes(f);
    document.getElementById('e-id').textContent = '#' + editTx.id;
    document.getElementById('e-description').value = editTx.description || '';
    document.getElementById('e-category').value = editTx.category_id ? String(editTx.category_id) : '';
    document.getElementById('e-account').value = editTx.account_id ? String(editTx.account_id) : '';
    document.getElementById('e-user').value = editTx.user_id ? String(editTx.user_id) : '';
    document.getElementById('e-user').disabled = !can('user_id');
    document.getElementById('e-amount').value = String(editTx.amount).replace('.', ',');
    document.getElementById('e-card').value = editTx.card_id ? String(editTx.card_id) : '';
    // No cartão, a data editável é a da COMPRA. Lançamento antigo sem data de
    // compra fica vazio: só recalcula a fatura se você preencher.
    document.getElementById('e-date').value = editTx.card_id ? (editTx.purchase_date || '') : (editTx.due_date || '');

    document.getElementById('e-description').disabled = !can('description');
    document.getElementById('e-category').disabled = !can('category_id');
    document.getElementById('e-account').disabled = !can('account_id');
    document.getElementById('e-amount').disabled = !can('amount');
    document.getElementById('e-date').disabled = !can('date');
    document.getElementById('e-card').disabled = !can('card_id');
    document.getElementById('e-card-wrap').style.display = editTx.kind === 'gasto' ? '' : 'none';

    let msg = '';
    if (!can('amount')) msg = 'Lançamento importado, parcelado ou já pago: valor, data e cartão ficam travados. Para mudar, exclua e lance de novo.';
    else if (editTx.card_id && !editTx.purchase_date) msg = 'Lançamento antigo no cartão: informe a data da compra para o sistema colocar na fatura certa.';
    document.getElementById('e-restriction').textContent = msg;

    refreshEditInvoiceHint();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editTxModal')).show();
}
function refreshEditInvoiceHint() {
    const label = document.getElementById('e-date-label');
    const hint = document.getElementById('e-invoice-hint');
    const cyc = (editTx && editTx.kind === 'gasto') ? cardCycle(document.getElementById('e-card')) : null;
    if (!cyc) { label.textContent = 'Vencimento'; hint.textContent = ''; return; }
    label.textContent = 'Data da compra';
    const inv = invoiceFor(document.getElementById('e-date').value, cyc.closing, cyc.due);
    hint.textContent = inv ? `Cai na fatura de ${inv.label} (vence ${inv.dueDay})`
        : (document.getElementById('e-date').value ? 'Cartão sem dia de fechamento/vencimento: vence na data da compra.' : '');
}
['e-card', 'e-date'].forEach(id => document.getElementById(id).addEventListener('change', refreshEditInvoiceHint));

async function saveEditTx() {
    if (!editTx) return;
    const can = f => editTx.editable.includes(f);
    const fd = new FormData();
    if (can('description')) fd.append('description', document.getElementById('e-description').value);
    if (can('category_id')) fd.append('category_id', document.getElementById('e-category').value);
    if (can('account_id')) fd.append('account_id', document.getElementById('e-account').value);
    if (can('user_id')) fd.append('user_id', document.getElementById('e-user').value);
    if (can('amount')) fd.append('amount', document.getElementById('e-amount').value);
    if (can('card_id')) fd.append('card_id', document.getElementById('e-card').value);
    if (can('date') && document.getElementById('e-date').value) fd.append('date', document.getElementById('e-date').value);
    const r = await post('financial/update/' + editTx.id, fd);
    if (r) location.reload();
}

// ===== Pagar fatura do cartão =====
async function payInvoice(cardId, ym, label) {
    if (!confirm(`Pagar a fatura ${label}? Todas as compras e parcelas dessa fatura serão marcadas como pagas.`)) return;
    const r = await post('financial/payInvoice/' + cardId + '/' + ym, new FormData());
    if (r) location.reload();
}

async function payTx(id) { const r = await post('financial/pay/' + id, new FormData()); if (r) location.reload(); }
async function unpayTx(id) { const r = await post('financial/unpay/' + id, new FormData()); if (r) location.reload(); }
// ===== Filtros: alterna entre "um mês" e "intervalo de datas" =====
function toggleFilterMode() {
    const range = document.getElementById('flt-mode').value === 'range';
    document.getElementById('flt-month-wrap').style.display = range ? 'none' : '';
    document.querySelectorAll('.flt-range-wrap').forEach(el => el.style.display = range ? '' : 'none');
    // Campo escondido não vai na URL (senão o intervalo "ganha" do mês).
    document.querySelector('#filter-form [name=month]').disabled = range;
    document.querySelectorAll('#filter-form [name=from], #filter-form [name=to]').forEach(el => el.disabled = !range);
}
toggleFilterMode();
// Não manda campos vazios na URL.
document.getElementById('filter-form').addEventListener('submit', function () {
    this.querySelectorAll('select, input').forEach(el => { if (!el.disabled && el.name && el.value === '') el.disabled = true; });
});

async function payProjected(fromId, date) {
    if (!confirm('Marcar esta ocorrência prevista como paga?')) return;
    const r = await post('financial/payProjected/' + fromId + '/' + date, new FormData());
    if (r) location.reload();
}
async function payInstallment(id) { const r = await post('financial/payInstallment/' + id, new FormData()); if (r) location.reload(); }
async function unpayInstallment(id) { const r = await post('financial/unpayInstallment/' + id, new FormData()); if (r) location.reload(); }
async function removeTx(id, installments) {
    const msg = installments > 1
        ? `Excluir este lançamento? Ele é parcelado: TODAS as ${installments} parcelas serão excluídas.`
        : 'Excluir este lançamento?';
    if (!confirm(msg)) return;
    const r = await post('financial/remove/' + id, new FormData());
    if (r) location.reload();
}
async function saveCard() {
    const fd = new FormData();
    fd.append('name', document.getElementById('c-name').value.trim());
    fd.append('limit', document.getElementById('c-limit').value);
    fd.append('closing_day', document.getElementById('c-closing').value);
    fd.append('due_day', document.getElementById('c-due').value);
    fd.append('account_id', document.getElementById('c-account').value);
    const r = await post('financial/storeCard', fd);
    if (r) location.reload();
}
async function toggleCard(id) { const r = await post('financial/cardToggle/' + id, new FormData()); if (r) location.reload(); }

// ===== Contas bancárias =====
async function saveAccount() {
    const fd = new FormData();
    fd.append('name', document.getElementById('ba-name').value.trim());
    fd.append('bank', document.getElementById('ba-bank').value.trim());
    fd.append('opening_balance', document.getElementById('ba-opening').value);
    const r = await post('financial/storeAccount', fd);
    if (r) location.reload();
}
let editAccId = null;
function openEditAccount(btn) {
    const a = JSON.parse(btn.dataset.acc);
    editAccId = a.id;
    document.getElementById('ea-name').value = a.name || '';
    document.getElementById('ea-bank').value = a.bank || '';
    document.getElementById('ea-opening').value = Number(a.opening_balance).toFixed(2).replace('.', ',');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editAccModal')).show();
}
async function saveEditAccount() {
    if (!editAccId) return;
    const fd = new FormData();
    fd.append('name', document.getElementById('ea-name').value.trim());
    fd.append('bank', document.getElementById('ea-bank').value.trim());
    fd.append('opening_balance', document.getElementById('ea-opening').value);
    const r = await post('financial/accountUpdate/' + editAccId, fd);
    if (r) location.reload();
}
async function toggleAccount(id) { const r = await post('financial/accountToggle/' + id, new FormData()); if (r) location.reload(); }
async function removeAccount(id) {
    if (!confirm('Excluir esta conta? Os lançamentos ligados a ela ficam sem conta (não são apagados).')) return;
    const r = await post('financial/accountRemove/' + id, new FormData());
    if (r) location.reload();
}

// ===== Categorias =====
async function saveCategory() {
    const fd = new FormData();
    fd.append('name', document.getElementById('cat-name').value.trim());
    fd.append('kind', document.getElementById('cat-kind').value);
    const r = await post('financial/storeCategory', fd);
    if (r) location.reload();
}
async function toggleCategory(id) { const r = await post('financial/categoryToggle/' + id, new FormData()); if (r) location.reload(); }
async function removeCategory(id) {
    if (!confirm('Excluir esta categoria? Os lançamentos ligados a ela ficam sem categoria (não são apagados).')) return;
    const r = await post('financial/categoryRemove/' + id, new FormData());
    if (r) location.reload();
}

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