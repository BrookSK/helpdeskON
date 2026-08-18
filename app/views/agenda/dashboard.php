<?php $pageTitle = 'Dashboard Comercial'; $currentPage = 'agenda_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Totais globais (soma de todos os usuários visíveis)
$totals = [
    'total_meetings' => 0, 'agendada' => 0, 'confirmada' => 0, 'realizada' => 0,
    'convertida' => 0, 'remarcada' => 0, 'cancelada' => 0, 'unique_contacts' => 0,
    'messages_sent' => 0, 'messages_received' => 0, 'contacts_messaged' => 0,
    'contacts_contacted' => 0, 'contacts_replied' => 0, 'contacts_no_reply' => 0,
    'emails_sent' => 0, 'emails_failed' => 0, 'emails_total' => 0, 'emails_unique_contacts' => 0,
];
foreach ($tableData as $row) {
    foreach ($totals as $k => &$v) $v += $row[$k] ?? 0;
}
unset($v);
$conversionRate = $totals['realizada'] > 0 ? round(($totals['convertida'] / $totals['realizada']) * 100, 1) : 0;
?>

<div class="main-content">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-bar-chart-line"></i> Dashboard Comercial</h5>
            <small class="text-muted">Performance de reuniões e contatos</small>
        </div>
        <a href="<?= baseUrl('agenda') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Agenda</a>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                <div>
                    <label class="form-label small mb-0">Início</label>
                    <input type="date" name="start" class="form-control form-control-sm" value="<?= escape($startDate) ?>">
                </div>
                <div>
                    <label class="form-label small mb-0">Fim</label>
                    <input type="date" name="end" class="form-control form-control-sm" value="<?= escape($endDate) ?>">
                </div>
                <?php if ($isAdmin): ?>
                <div>
                    <label class="form-label small mb-0">Usuário</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($comerciais as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterUserId == $c['id'] ? 'selected' : '' ?>><?= escape($c['name']) ?> (<?= roleLabel($c['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-7 g-3 mb-4">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#1565c0">
                <div class="stat-label">Reuniões</div>
                <div class="stat-value" style="color:#1565c0"><?= $totals['total_meetings'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2e7d32">
                <div class="stat-label">Realizadas</div>
                <div class="stat-value" style="color:#2e7d32"><?= $totals['realizada'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#6a1b9a">
                <div class="stat-label">Convertidas</div>
                <div class="stat-value" style="color:#6a1b9a"><?= $totals['convertida'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#e65100">
                <div class="stat-label">Remarcadas</div>
                <div class="stat-value" style="color:#e65100"><?= $totals['remarcada'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label">Canceladas</div>
                <div class="stat-value" style="color:#c62828"><?= $totals['cancelada'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#00897b">
                <div class="stat-label">Taxa Conversão</div>
                <div class="stat-value" style="color:#00897b"><?= $conversionRate ?>%</div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#455a64">
                <div class="stat-label">Contatos Alcançados</div>
                <div class="stat-value" style="color:#455a64"><?= $totals['contacts_contacted'] ?></div>
            </div>
        </div>
    </div>

    <!-- Cards de mensagens -->
    <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#1976d2">
                <div class="stat-label">Mensagens Enviadas</div>
                <div class="stat-value" style="color:#1976d2"><?= number_format($totals['messages_sent'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#7b1fa2">
                <div class="stat-label">Mensagens Recebidas</div>
                <div class="stat-value" style="color:#7b1fa2"><?= number_format($totals['messages_received'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2e7d32">
                <div class="stat-label">Contatos Responderam</div>
                <div class="stat-value" style="color:#2e7d32"><?= $totals['contacts_replied'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label">Sem Resposta</div>
                <div class="stat-value" style="color:#c62828"><?= $totals['contacts_no_reply'] ?></div>
            </div>
        </div>
    </div>

    <!-- Cards de e-mail prospecção -->
    <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#e65100">
                <div class="stat-label"><i class="bi bi-envelope"></i> E-mails Enviados</div>
                <div class="stat-value" style="color:#e65100"><?= $totals['emails_sent'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label"><i class="bi bi-envelope-x"></i> E-mails Falharam</div>
                <div class="stat-value" style="color:#c62828"><?= $totals['emails_failed'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#00897b">
                <div class="stat-label"><i class="bi bi-envelope-check"></i> Leads Prospectados (email)</div>
                <div class="stat-value" style="color:#00897b"><?= $totals['emails_unique_contacts'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#455a64">
                <div class="stat-label"><i class="bi bi-envelope-paper"></i> Total E-mails</div>
                <div class="stat-value" style="color:#455a64"><?= $totals['emails_total'] ?></div>
            </div>
        </div>
    </div>

    <!-- Tabela comparativa -->
    <?php if (count($tableData) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-people"></i> Comparativo por Pessoa</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Pessoa</th>
                            <th class="text-center">Reuniões</th>
                            <th class="text-center">Realizadas</th>
                            <th class="text-center" style="color:#6a1b9a">Convertidas</th>
                            <th class="text-center">Remarcadas</th>
                            <th class="text-center">Canceladas</th>
                            <th class="text-center">Taxa Conv.</th>
                            <th class="text-center">Contatos</th>
                            <th class="text-center">Msg Env.</th>
                            <th class="text-center">Msg Rec.</th>
                            <th class="text-center">Responderam</th>
                            <th class="text-center">S/ Resposta</th>
                            <th class="text-center" style="color:#e65100">Emails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableData as $row): ?>
                        <?php $rate = $row['realizada'] > 0 ? round(($row['convertida'] / $row['realizada']) * 100, 1) : 0; ?>
                        <tr>
                            <td class="fw-medium"><?= escape($row['user_name']) ?></td>
                            <td class="text-center"><?= $row['total_meetings'] ?></td>
                            <td class="text-center"><?= $row['realizada'] ?></td>
                            <td class="text-center fw-bold" style="color:#6a1b9a"><?= $row['convertida'] ?></td>
                            <td class="text-center"><?= $row['remarcada'] ?></td>
                            <td class="text-center"><?= $row['cancelada'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= $rate >= 50 ? 'bg-success' : ($rate >= 25 ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= $rate ?>%</span>
                            </td>
                            <td class="text-center"><?= $row['unique_contacts'] ?></td>
                            <td class="text-center"><?= number_format($row['messages_sent'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= number_format($row['messages_received'], 0, ',', '.') ?></td>
                            <td class="text-center text-success"><?= $row['contacts_replied'] ?></td>
                            <td class="text-center text-danger"><?= $row['contacts_no_reply'] ?></td>
                            <td class="text-center fw-medium" style="color:#e65100"><?= $row['emails_sent'] ?? 0 ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">Nenhum dado encontrado para o período selecionado.</div>
    <?php endif; ?>

    <!-- Gráficos -->
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Evolução Mensal de Reuniões</h6></div>
                <div class="card-body">
                    <canvas id="trendChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Distribuição de Status</h6></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="pieChart" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card { border-left: 4px solid #ddd; padding: 12px 16px; }
.stat-label { font-size: 0.72rem; color: #667; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
.stat-value { font-size: 1.4rem; font-weight: 700; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trendData = <?= json_encode($trend) ?>;

// Gráfico temporal
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(d => d.label),
        datasets: [
            {
                label: 'Realizadas',
                data: trendData.map(d => d.realizada),
                borderColor: '#2e7d32',
                backgroundColor: 'rgba(46,125,50,0.1)',
                tension: 0.3, fill: true,
            },
            {
                label: 'Convertidas',
                data: trendData.map(d => d.convertida),
                borderColor: '#6a1b9a',
                backgroundColor: 'rgba(106,27,154,0.1)',
                tension: 0.3, fill: true,
            },
            {
                label: 'Canceladas',
                data: trendData.map(d => d.cancelada),
                borderColor: '#c62828',
                backgroundColor: 'rgba(198,40,40,0.06)',
                tension: 0.3, fill: true,
            },
            {
                label: 'Remarcadas',
                data: trendData.map(d => d.remarcada),
                borderColor: '#e65100',
                backgroundColor: 'rgba(230,81,0,0.06)',
                tension: 0.3, fill: true,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

// Gráfico de pizza
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Agendadas', 'Confirmadas', 'Realizadas', 'Convertidas', 'Remarcadas', 'Canceladas'],
        datasets: [{
            data: [
                <?= $totals['agendada'] ?>, <?= $totals['confirmada'] ?>,
                <?= $totals['realizada'] ?>, <?= $totals['convertida'] ?>,
                <?= $totals['remarcada'] ?>, <?= $totals['cancelada'] ?>
            ],
            backgroundColor: ['#1565c0', '#00897b', '#2e7d32', '#6a1b9a', '#e65100', '#c62828'],
            borderWidth: 1,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
    }
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
