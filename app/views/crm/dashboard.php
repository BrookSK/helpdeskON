<?php $pageTitle = 'Dashboard CRM'; $currentPage = 'crm_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div>
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Dashboard CRM</h5>
            <small class="text-muted">Visão geral dos leads</small>
        </div>
        <a href="<?= baseUrl('crm') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Boards</a>
    </div>

    <!-- Contadores -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-3">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2196f3">
                <div class="stat-label">Leads no CRM</div>
                <div class="stat-value" style="color:#2196f3"><?= $stats['total'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#9c27b0">
                <div class="stat-label">Com Etiqueta</div>
                <div class="stat-value" style="color:#9c27b0"><?= $stats['with_label'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#ff9800">
                <div class="stat-label">Em Aberto</div>
                <div class="stat-value" style="color:#ff9800"><?= $stats['open'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2e7d32">
                <div class="stat-label">Convertidos</div>
                <div class="stat-value" style="color:#2e7d32"><?= $stats['converted'] ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label">Perdidos</div>
                <div class="stat-value" style="color:#c62828"><?= $stats['lost'] ?></div>
            </div>
        </div>
    </div>

    <!-- Valores -->
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-4">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#455a64">
                <div class="stat-label">Valor Cotado (tudo)</div>
                <div class="stat-value" style="color:#455a64;font-size:1.1rem;">R$ <?= number_format($stats['quoted_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2e7d32">
                <div class="stat-label">Valor Convertido</div>
                <div class="stat-value" style="color:#2e7d32;font-size:1.1rem;">R$ <?= number_format($stats['converted_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label">Valor Perdido</div>
                <div class="stat-value" style="color:#c62828;font-size:1.1rem;">R$ <?= number_format($stats['lost_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#7e57c2">
                <div class="stat-label">Valor Recuperação/Agendado</div>
                <div class="stat-value" style="color:#7e57c2;font-size:1.1rem;">R$ <?= number_format($stats['recovery_value'], 2, ',', '.') ?></div>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#00BFA6">
                <div class="stat-label">Ticket Médio (convertido)</div>
                <div class="stat-value" style="color:#00997D;font-size:1.1rem;">R$ <?= number_format($stats['converted'] > 0 ? $stats['converted_value'] / $stats['converted'] : 0, 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Métricas de E-mail (prospecção + sequências) -->
    <h6 class="mb-2 mt-2"><i class="bi bi-envelope"></i> E-mails de Prospecção & Follow-up</h6>
    <?php if (empty($emailModuleReady)): ?>
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> O módulo de follow-up ainda não foi ativado no banco. Rode a migration <code>065_followup_sequences_module.sql</code> para começar a registrar métricas de e-mail.</div>
    <?php endif; ?>
    <?php if (true): ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-3">
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#1565c0">
                <div class="stat-label">Enviados</div>
                <div class="stat-value" style="color:#1565c0"><?= $emailStats['sent'] ?></div>
                <small class="text-muted"><?= $emailStats['manual'] ?> manuais · <?= $emailStats['sequence'] ?> automáticos</small>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#00897b">
                <div class="stat-label">Abertos</div>
                <div class="stat-value" style="color:#00897b"><?= $emailStats['opened'] ?></div>
                <small class="text-muted"><?= $emailStats['open_rate'] ?>% de abertura</small>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#7b1fa2">
                <div class="stat-label">Cliques</div>
                <div class="stat-value" style="color:#7b1fa2"><?= $emailStats['clicked'] ?></div>
                <small class="text-muted"><?= $emailStats['click_rate'] ?>% de clique</small>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#2e7d32">
                <div class="stat-label">Responderam</div>
                <div class="stat-value" style="color:#2e7d32"><?= $emailStats['replied'] ?></div>
                <small class="text-muted"><?= $emailStats['reply_rate'] ?>% de resposta</small>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#c62828">
                <div class="stat-label">Bounces</div>
                <div class="stat-value" style="color:#c62828"><?= $emailStats['bounced'] ?></div>
                <small class="text-muted">endereços inválidos</small>
            </div>
        </div>
        <div class="col">
            <div class="card stat-card h-100" style="border-left-color:#f9a825">
                <div class="stat-label">Melhor e-mail</div>
                <?php if (!empty($emailStats['top_email'])): $te = $emailStats['top_email']; $tr = $te['sent'] > 0 ? round($te['opened']/$te['sent']*100) : 0; ?>
                <div class="fw-semibold text-truncate" style="font-size:0.82rem;" title="<?= escape($te['subject']) ?>"><?= escape($te['subject'] ?: '(sem assunto)') ?></div>
                <small class="text-muted"><?= $tr ?>% abertura · <?= (int)$te['replied'] ?> resp. · <?= (int)$te['sent'] ?> envios</small>
                <?php else: ?>
                <div class="text-muted small mt-2">Sem dados ainda</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($emailModuleReady)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white"><h6 class="mb-0">E-mails: enviados x abertos x respondidos (6 meses)</h6></div>
        <div class="card-body"><canvas id="emailTrendChart" style="max-height:260px;"></canvas></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Gráficos -->
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Distribuição dos Leads</h6></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="pieChart" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0">Evolução (últimos 6 meses)</h6></div>
                <div class="card-body">
                    <canvas id="trendChart" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trendData = <?= json_encode($trend) ?>;

// Gráfico de pizza: distribuição dos leads
new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
        labels: ['Em Aberto', 'Convertidos', 'Perdidos'],
        datasets: [{
            data: [<?= $stats['open'] ?>, <?= $stats['converted'] ?>, <?= $stats['lost'] ?>],
            backgroundColor: ['#ff9800', '#2e7d32', '#c62828'],
            borderWidth: 1,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Gráfico temporal: leads convertidos x perdidos por mês
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(d => d.label),
        datasets: [
            {
                label: 'Convertidos',
                data: trendData.map(d => d.converted),
                borderColor: '#2e7d32',
                backgroundColor: 'rgba(46,125,50,0.1)',
                tension: 0.3,
                fill: true,
            },
            {
                label: 'Perdidos',
                data: trendData.map(d => d.lost),
                borderColor: '#c62828',
                backgroundColor: 'rgba(198,40,40,0.08)',
                tension: 0.3,
                fill: true,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

<?php if (!empty($emailModuleReady)): ?>
// Gráfico temporal de e-mails
const emailTrend = <?= json_encode($emailTrend) ?>;
new Chart(document.getElementById('emailTrendChart'), {
    type: 'bar',
    data: {
        labels: emailTrend.map(d => d.label),
        datasets: [
            { label: 'Enviados', data: emailTrend.map(d => d.sent), backgroundColor: '#1565c0' },
            { label: 'Abertos', data: emailTrend.map(d => d.opened), backgroundColor: '#00897b' },
            { label: 'Respondidos', data: emailTrend.map(d => d.replied), backgroundColor: '#2e7d32' },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
<?php endif; ?>
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
