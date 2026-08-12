<?php $pageTitle = 'Métricas Sociais - ON Solutions Helpdesk'; $currentPage = 'buffer_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-graph-up-arrow"></i> Métricas Sociais</h5>
            <small class="text-muted">Desempenho das publicações via Buffer</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="syncChannels(this)"><i class="bi bi-arrow-repeat"></i> Sincronizar canais</button>
            <button class="btn btn-primary btn-sm" onclick="syncMetrics(this)"><i class="bi bi-cloud-download"></i> Atualizar métricas</button>
        </div>
    </div>

    <?php if (!$hasKey): ?>
    <div class="alert alert-warning py-2 px-3 small">
        <i class="bi bi-exclamation-triangle"></i> Chave da API Buffer não configurada.
        <?php if (($user['role'] ?? '') === 'super_admin'): ?>
        Adicione em <a href="<?= baseUrl('settings') ?>">Configurações</a>.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cards de métricas -->
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Curtidas totais', $totals['reactions'], 'bi-heart-fill', '#e91e63'],
            ['Comentários', $totals['comments'], 'bi-chat-dots-fill', '#3f51b5'],
            ['Visualizações totais', $totals['views'], 'bi-eye-fill', '#00897b'],
            ['Impressões', $totals['impressions'], 'bi-bar-chart-fill', '#ff9800'],
            ['Alcance', $totals['reach'], 'bi-broadcast', '#9c27b0'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-6 col-md">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:10px;background:<?= $c[3] ?>1a;color:<?= $c[3] ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;">
                        <i class="bi <?= $c[2] ?>"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:1.25rem;"><?= number_format($c[1], 0, ',', '.') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= $c[0] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtro por métrica -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="small fw-medium mb-0">Métrica:</label>
        <select id="metric-filter" class="form-select form-select-sm" style="max-width:220px;" onchange="loadMetric()">
            <option value="reactions">Curtidas / Reações</option>
            <option value="comments">Comentários</option>
            <option value="views">Visualizações</option>
            <option value="impressions">Impressões</option>
            <option value="reach">Alcance</option>
            <option value="engagementRate">Taxa de engajamento</option>
            <option value="shares">Compartilhamentos</option>
            <option value="saves">Salvamentos</option>
        </select>
        <label class="small fw-medium mb-0 ms-2">Publicação:</label>
        <select id="post-filter" class="form-select form-select-sm" style="max-width:320px;" onchange="loadMetric()">
            <option value="">Todas as publicações</option>
            <?php foreach ($posts as $p): ?>
            <option value="<?= escape($p['buffer_post_id']) ?>"><?= escape(mb_strimwidth($p['text'] ?: '(sem texto)', 0, 60, '...')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row g-3">
        <!-- Gráfico de linha: variação -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem;">Variação ao longo do tempo</h6></div>
                <div class="card-body">
                    <canvas id="lineChart" style="max-height:300px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Posts com maior taxa -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><h6 class="mb-0" style="font-size:0.9rem;">Posts com maior desempenho</h6></div>
                <div class="card-body p-0">
                    <div id="top-posts" class="list-group list-group-flush" style="max-height:340px;overflow-y:auto;">
                        <div class="text-muted small text-center py-4">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const BASE = '<?= baseUrl("") ?>';
let lineChart = null;
let allTimeline = {}; // cache por métrica: {metric: timeline}

const METRIC_LABELS = {
    reactions: 'Curtidas / Reações', comments: 'Comentários', views: 'Visualizações',
    impressions: 'Impressões', reach: 'Alcance', engagementRate: 'Taxa de engajamento (%)',
    shares: 'Compartilhamentos', saves: 'Salvamentos'
};

function loadMetric() {
    const metric = document.getElementById('metric-filter').value;
    const postId = document.getElementById('post-filter').value;
    fetch(`${BASE}buffer/metrics?metric=${metric}`)
        .then(r => r.json())
        .then(data => {
            renderLine(data.timeline || [], metric);
            renderTop(data.top || [], metric, postId);
        });
}

function renderLine(timeline, metric) {
    const labels = timeline.map(t => {
        const d = new Date(t.day);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    const values = timeline.map(t => parseFloat(t.total));
    if (lineChart) lineChart.destroy();
    lineChart = new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: METRIC_LABELS[metric] || metric,
                data: values,
                borderColor: '#00BFA6',
                backgroundColor: 'rgba(0,191,166,0.12)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
    });
}

function renderTop(top, metric, postFilter) {
    const box = document.getElementById('top-posts');
    let list = top;
    if (postFilter) list = top.filter(p => p.buffer_post_id === postFilter);
    if (!list.length) { box.innerHTML = '<div class="text-muted small text-center py-4">Sem dados. Clique em "Atualizar métricas".</div>'; return; }
    box.innerHTML = list.map((p, i) => {
        const val = p.metric_unit === 'percentage' ? (parseFloat(p.metric_value).toFixed(1) + '%') : Math.round(p.metric_value).toLocaleString('pt-BR');
        const txt = (p.text || '(sem texto)').slice(0, 80);
        const link = p.external_link ? `href="${p.external_link}" target="_blank"` : '';
        return `<a ${link} class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2" style="text-decoration:none;">
            <div class="min-w-0">
                <div class="small fw-medium text-truncate">${i + 1}. ${escapeHtml(txt)}</div>
                <div class="text-muted" style="font-size:0.7rem;">${p.service || ''}</div>
            </div>
            <span class="badge bg-primary rounded-pill">${val}</span>
        </a>`;
    }).join('');
}

function syncChannels(btn) {
    btn.disabled = true;
    fetch(`${BASE}buffer/syncChannels`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false;
            if (data.error) { alert(data.error); return; }
            alert(data.count + ' canal(is) sincronizado(s).');
        }).catch(() => { btn.disabled = false; });
}

function syncMetrics(btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
    fetch(`${BASE}buffer/syncMetrics`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false; btn.innerHTML = original;
            if (data.error) { alert(data.error); return; }
            location.reload();
        }).catch(() => { btn.disabled = false; btn.innerHTML = original; });
}

function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', loadMetric);
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
