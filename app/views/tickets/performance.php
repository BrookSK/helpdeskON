<?php $pageTitle = 'Performance Operacional - ON Solutions Helpdesk'; $currentPage = 'performance_operacional'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$statusLabels = [
    'open' => 'Aberto',
    'in_progress' => 'Em andamento',
    'em_revisao_interna' => 'Em Revisão',
    'waiting_client' => 'Aguardando',
    'em_homologacao' => 'Homologação',
    'aprovado_producao' => 'Aprov. Produção',
    'completed' => 'Concluído',
    'denied' => 'Negado',
    'archived' => 'Arquivado',
];
$statusColors = [
    'open' => '#1565c0',
    'in_progress' => '#e65100',
    'em_revisao_interna' => '#5c6bc0',
    'waiting_client' => '#7b1fa2',
    'em_homologacao' => '#0097a7',
    'aprovado_producao' => '#8bc34a',
    'completed' => '#2e7d32',
    'denied' => '#d84315',
    'archived' => '#546e7a',
];
?>

<div class="main-content">
    <div class="top-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Performance Operacional</h5>
            <small class="text-muted">Fluxo real: criacao &rarr; admissao &rarr; tratamento &rarr; conclusao</small>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Inicio</label>
                    <input type="date" name="start" class="form-control form-control-sm" value="<?= escape($startDate) ?>">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Fim</label>
                    <input type="date" name="end" class="form-control form-control-sm" value="<?= escape($endDate) ?>">
                </div>
                <?php if (!empty($isAdmin)): ?>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-0">Atendente</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($attendants as $att): ?>
                        <option value="<?= $att['id'] ?>" <?= ($filterUserId ?? '') == $att['id'] ? 'selected' : '' ?>><?= escape($att['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12 col-md-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    <a href="<?= baseUrl('tickets/performance') ?>" class="btn btn-sm btn-outline-secondary ms-1">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <?php
    // Formata uma quantidade de horas como "Xh" ou "Yd" (dias) quando >= 24h.
    // null = sem tickets elegíveis para o cálculo -> exibe "—" (não "0h").
    $fmtDuration = function ($hours) {
        if ($hours === null) {
            return '—';
        }
        $hours = (float)$hours;
        if ($hours >= 24) {
            return rtrim(rtrim(number_format(round($hours / 24, 1), 1, '.', ''), '0'), '.') . 'd';
        }
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.') . 'h';
    };
    ?>

    <!-- Cards de Metricas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Recebidos / Admitidos</div>
                    <div class="fs-3 fw-bold text-primary"><?= $metrics['admitted'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Concluidos</div>
                    <div class="fs-3 fw-bold text-success"><?= $metrics['completed'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small" title="Todos os tickets em aberto acumulados (não concluídos/negados/arquivados) criados até o fim do período">Pendentes (acumulado)</div>
                    <div class="fs-3 fw-bold text-warning"><?= $metrics['pending'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Taxa de Conclusao</div>
                    <div class="fs-3 fw-bold" style="color:#2e7d32"><?= $metrics['completion_rate'] ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Tempos Medios (fluxo real da demanda) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Tempo Medio de Admissao</div>
                    <div class="fs-3 fw-bold" style="color:#7b1fa2"><?= $fmtDuration($metrics['avg_admission_hours']) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">criacao &rarr; admissao</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Tempo Medio de Tratamento</div>
                    <div class="fs-3 fw-bold text-info"><?= $fmtDuration($metrics['avg_treatment_hours']) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">admissao &rarr; conclusao</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="text-muted small">Tempo Medio Total</div>
                    <div class="fs-3 fw-bold" style="color:#e65100"><?= $fmtDuration($metrics['avg_total_hours']) ?></div>
                    <div class="text-muted" style="font-size:0.72rem;">criacao &rarr; conclusao</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Distribuicao por Status -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0" style="font-size:0.9rem;" title="Tickets criados dentro do período selecionado, agrupados por status">Distribuicao por Status (no periodo)</h6>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($statusDist)): ?>
                    <p class="text-muted text-center small py-3">Nenhum ticket no periodo.</p>
                    <?php else: ?>
                    <?php
                    $totalDist = array_sum(array_column($statusDist, 'total'));
                    foreach ($statusDist as $sd):
                        $pct = $totalDist > 0 ? round($sd['total'] / $totalDist * 100, 1) : 0;
                        $color = $statusColors[$sd['status']] ?? '#666';
                        $label = $statusLabels[$sd['status']] ?? $sd['status'];
                    ?>
                    <div class="d-flex align-items-center mb-2">
                        <span class="me-2" style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;"></span>
                        <span class="small flex-grow-1"><?= $label ?></span>
                        <span class="small fw-medium"><?= $sd['total'] ?></span>
                        <span class="small text-muted ms-1" style="min-width:40px;text-align:right;">(<?= $pct ?>%)</span>
                    </div>
                    <div class="progress mb-2" style="height:4px;">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabela por Profissional -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0" style="font-size:0.9rem;">Performance por Profissional</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Profissional</th>
                                    <th class="text-center" title="Recebidos / Admitidos">Admitidos</th>
                                    <th class="text-center">Concluidos</th>
                                    <th class="text-center">Pendentes</th>
                                    <th class="text-center">Atrasados</th>
                                    <th class="text-center" title="Taxa de conclusao">Conclusao</th>
                                    <th class="text-center" title="Criacao &rarr; Admissao">T. Admissao</th>
                                    <th class="text-center" title="Admissao &rarr; Conclusao">T. Tratamento</th>
                                    <th class="text-center" title="Criacao &rarr; Conclusao">T. Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byAttendant)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-3">Nenhum dado no periodo.</td></tr>
                                <?php else: ?>
                                <?php foreach ($byAttendant as $att): ?>
                                <tr>
                                    <td class="fw-medium"><?= escape($att['user_name']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= (int)$att['admitted'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-success"><?= (int)$att['completed'] ?></span></td>
                                    <td class="text-center"><span class="badge bg-warning text-dark"><?= (int)$att['pending'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ((int)$att['overdue'] > 0): ?>
                                        <span class="badge bg-danger"><?= (int)$att['overdue'] ?></span>
                                        <?php else: ?>
                                        <span class="text-muted small">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center small"><?= $att['completion_rate'] ?>%</td>
                                    <td class="text-center small"><?= $fmtDuration($att['avg_admission_hours']) ?></td>
                                    <td class="text-center small"><?= $fmtDuration($att['avg_treatment_hours']) ?></td>
                                    <td class="text-center small"><?= $fmtDuration($att['avg_total_hours']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
