<?php $pageTitle = 'Métricas Sociais - ON Solutions Helpdesk'; $currentPage = 'buffer_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<style>
.channel-card { border: 1px solid #eef0f2; border-radius: 12px; transition: box-shadow .15s, transform .15s; }
.channel-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); transform: translateY(-1px); }
.channel-avatar { position: relative; width: 46px; height: 46px; border-radius: 50%; background: #e0e0e0; color: #555; font-weight: 600; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: visible; }
.channel-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
.channel-service { position: absolute; right: -3px; bottom: -3px; width: 20px; height: 20px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; border: 2px solid #fff; }
.channel-status { font-size: 0.62rem; font-weight: 600; padding: 3px 8px; }
.channel-status.connected { background: #e6f7f0; color: #1b7a54; }
.channel-status.disconnected { background: #fdecea; color: #c0392b; }
.channel-service-badge { font-size: 0.62rem; font-weight: 600; padding: 3px 8px; }
.channel-metrics { border-top: 1px solid #f0f2f4; padding-top: 8px; margin-top: 2px; }
.channel-metrics-title { font-size: 0.64rem; font-weight: 600; color: #99a2ab; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
.channel-metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.channel-metric { background: #f7f9fa; border-radius: 8px; padding: 6px 4px; text-align: center; }
.cm-val { font-size: 0.9rem; font-weight: 700; color: #2b3440; line-height: 1.1; }
.cm-lbl { font-size: 0.6rem; color: #8a929b; margin-top: 2px; }
.top-post-cover { width: 46px; height: 46px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.top-post-cover-empty { display: flex; align-items: center; justify-content: center; background: #eef1f4; color: #b0b8c0; font-size: 1.1rem; }
/* Lista de posts: sem rolagem lateral, com quebra de linha */
#top-posts { overflow-x: hidden; }
.top-post-item { overflow: hidden; }
.top-post-info { flex: 1 1 auto; min-width: 0; }
.top-post-text {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><i class="bi bi-graph-up-arrow"></i> Métricas Sociais</h5>
            <small class="text-muted">Desempenho das publicações via Buffer</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-0" style="font-size:0.68rem;">De</label>
                <input type="date" id="period-start" class="form-control form-control-sm" style="max-width:150px;" value="<?= escape($periodStart) ?>">
            </div>
            <div>
                <label class="form-label small mb-0" style="font-size:0.68rem;">Até</label>
                <input type="date" id="period-end" class="form-control form-control-sm" style="max-width:150px;" value="<?= escape($periodEnd) ?>">
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="applyPeriod()"><i class="bi bi-funnel"></i> Aplicar período</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="syncChannels(this)"><i class="bi bi-arrow-repeat"></i> Sincronizar canais</button>
            <button class="btn btn-primary btn-sm" onclick="syncMetrics(this)"><i class="bi bi-cloud-download"></i> Atualizar métricas</button>
            <span class="vr d-none d-lg-inline"></span>
            <button class="btn btn-outline-secondary btn-sm" onclick="importMeta(this)"><i class="bi bi-download"></i> Importar da Meta</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="openLinkedinModal()"><i class="bi bi-linkedin"></i> Add LinkedIn</button>
            <button class="btn btn-outline-primary btn-sm" onclick="syncSocial(this)"><i class="bi bi-arrow-repeat"></i> Atualizar redes</button>
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

    <!-- Contas conectadas -->
    <?php
    // Ícone e cor por rede social
    $serviceMeta = [
        'instagram' => ['bi-instagram', '#E1306C'],
        'facebook' => ['bi-facebook', '#1877F2'],
        'facebookpage' => ['bi-facebook', '#1877F2'],
        'twitter' => ['bi-twitter-x', '#000000'],
        'x' => ['bi-twitter-x', '#000000'],
        'linkedin' => ['bi-linkedin', '#0A66C2'],
        'tiktok' => ['bi-tiktok', '#000000'],
        'youtube' => ['bi-youtube', '#FF0000'],
        'pinterest' => ['bi-pinterest', '#E60023'],
        'threads' => ['bi-threads', '#000000'],
        'mastodon' => ['bi-mastodon', '#6364FF'],
        'googlebusiness' => ['bi-google', '#4285F4'],
    ];
    ?>
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-semibold mb-0" style="font-size:0.9rem;"><i class="bi bi-share"></i> Contas conectadas</h6>
        <span class="text-muted small" id="channels-count"><?= count($channels) ?> conta(s)</span>
    </div>
    <div class="row g-3 mb-4" id="channels-cards">
        <?php if (empty($channels)): ?>
        <div class="col-12">
            <div class="alert alert-light border small mb-0">
                Nenhuma conta conectada. Clique em <strong>Sincronizar canais</strong> para buscar os perfis do Buffer.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($channels as $ch):
            $svc = strtolower($ch['service'] ?? '');
            $meta = $serviceMeta[$svc] ?? ['bi-globe', '#607d8b'];
            $connected = empty($ch['is_disconnected']);
            $initials = strtoupper(mb_substr($ch['name'] ?? '?', 0, 1));
            $svcLabel = ucfirst($svc ?: 'Canal');
        ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100 channel-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="channel-avatar">
                            <?php if (!empty($ch['avatar'])): ?>
                            <img src="<?= escape($ch['avatar']) ?>" alt="" onerror="this.parentNode.textContent='<?= escape($initials) ?>'">
                            <?php else: ?><?= escape($initials) ?><?php endif; ?>
                            <span class="channel-service" style="background:<?= $meta[1] ?>;">
                                <i class="bi <?= $meta[0] ?>"></i>
                            </span>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-truncate" style="font-size:0.85rem;" title="<?= escape($ch['name']) ?>"><?= escape($ch['name']) ?></div>
                            <div class="text-muted text-truncate" style="font-size:0.72rem;">
                                <?php if (!empty($ch['username'])): ?>@<?= escape($ch['username']) ?><?php else: ?><?= escape($svcLabel) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge rounded-pill channel-service-badge" style="background:<?= $meta[1] ?>1a;color:<?= $meta[1] ?>;">
                            <i class="bi <?= $meta[0] ?>"></i> <?= escape($svcLabel) ?>
                        </span>
                        <?php if ($connected): ?>
                        <span class="badge rounded-pill channel-status connected"><i class="bi bi-check-circle-fill"></i> Conectado</span>
                        <?php else: ?>
                        <span class="badge rounded-pill channel-status disconnected"><i class="bi bi-exclamation-circle-fill"></i> Reconectar</span>
                        <?php endif; ?>
                    </div>

                    <?php
                    $cm = $channelMetrics[$ch['channel_id']] ?? [];
                    $fmt = function($v) { return number_format((float)$v, 0, ',', '.'); };
                    // Sempre retorna um número (0 quando não há valor no período)
                    $getM = function($t) use ($cm) { return isset($cm[$t]) ? (float)$cm[$t]['metric_value'] : 0; };
                    ?>
                    <?php $periodLabel = date('d/m/Y', strtotime($periodStart)) . ' – ' . date('d/m/Y', strtotime($periodEnd)); ?>
                    <div class="channel-metrics">
                        <div class="channel-metrics-title"><i class="bi bi-graph-up"></i> <?= escape($periodLabel) ?></div>
                        <div class="channel-metrics-grid">
                            <?php
                            // Métricas fixas exibidas sempre (mostram 0 quando não há dados no período)
                            $metricList = [
                                ['postCount', 'Posts', 'bi-collection'],
                                ['impressions', 'Impressões', 'bi-eye'],
                                ['reach', 'Alcance', 'bi-broadcast'],
                                ['reactions', 'Reações', 'bi-heart'],
                                ['comments', 'Coment.', 'bi-chat'],
                                ['shares', 'Compart.', 'bi-share'],
                            ];
                            foreach ($metricList as $m):
                            ?>
                            <div class="channel-metric">
                                <div class="cm-val"><?= $fmt($getM($m[0])) ?></div>
                                <div class="cm-lbl"><i class="bi <?= $m[2] ?>"></i> <?= $m[1] ?></div>
                            </div>
                            <?php endforeach; ?>
                            <div class="channel-metric">
                                <div class="cm-val"><?= number_format((float)$getM('engagementRate'), 1, ',', '.') ?>%</div>
                                <div class="cm-lbl"><i class="bi bi-activity"></i> Engaj.</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($ch['external_link'])): ?>
                    <a href="<?= escape($ch['external_link']) ?>" target="_blank" rel="noopener" class="d-block text-truncate mt-2" style="font-size:0.7rem;">
                        <i class="bi bi-box-arrow-up-right"></i> Ver perfil
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php require APP_PATH . '/views/buffer/_social_section.php'; ?>

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
                    <div style="position:relative;height:300px;">
                        <canvas id="lineChart"></canvas>
                    </div>
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
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    const params = new URLSearchParams({ metric });
    if (start) params.set('start', start);
    if (end) params.set('end', end);
    fetch(`${BASE}buffer/metrics?${params.toString()}`)
        .then(r => r.json())
        .then(data => {
            renderLine(data.timeline || [], metric);
            renderTop(data.top || [], metric, postId);
        });
}

function renderLine(timeline, metric) {
    const labels = timeline.map(t => {
        const raw = t.moment || t.day;
        const d = new Date((raw || '').replace(' ', 'T'));
        return isNaN(d) ? (raw || '') : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    const values = timeline.map(t => parseFloat(t.total));
    if (lineChart) lineChart.destroy();
    const canvas = document.getElementById('lineChart');
    const ctx = canvas.getContext('2d');
    // Gradiente suave sob a curva
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height || 300);
    gradient.addColorStop(0, 'rgba(0,191,166,0.25)');
    gradient.addColorStop(1, 'rgba(0,191,166,0.01)');

    lineChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: METRIC_LABELS[metric] || metric,
                data: values,
                borderColor: '#00BFA6',
                borderWidth: 3,
                backgroundColor: gradient,
                fill: true,
                cubicInterpolationMode: 'monotone',
                tension: 0.45,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: '#00BFA6',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 10,
                    displayColors: false,
                    titleFont: { size: 12 },
                    bodyFont: { size: 13, weight: '600' },
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { color: '#9aa4ae', font: { size: 11 } },
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#9aa4ae', font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                }
            }
        }
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
        const cover = p.thumbnail
            ? `<img src="${p.thumbnail}" class="top-post-cover" alt="" onerror="this.style.display='none'">`
            : `<div class="top-post-cover top-post-cover-empty"><i class="bi bi-image"></i></div>`;
        return `<a ${link} class="list-group-item list-group-item-action d-flex align-items-center gap-2 top-post-item" style="text-decoration:none;">
            ${cover}
            <div class="top-post-info">
                <div class="small fw-medium top-post-text">${i + 1}. ${escapeHtml(txt)}</div>
                <div class="text-muted" style="font-size:0.7rem;">${p.service || ''}</div>
            </div>
            <span class="badge bg-primary rounded-pill flex-shrink-0">${val}</span>
        </a>`;
    }).join('');
}

function syncChannels(btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sincronizando...';
    fetch(`${BASE}buffer/syncChannels`, { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false; btn.innerHTML = original;
            if (data.error) { alert(data.error); return; }
            location.reload();
        }).catch(() => { btn.disabled = false; btn.innerHTML = original; });
}

function syncMetrics(btn) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
    const fd = new FormData();
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    if (start) fd.append('start', start);
    if (end) fd.append('end', end);
    fetch(`${BASE}buffer/syncMetrics`, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(data => {
            btn.disabled = false; btn.innerHTML = original;
            if (data.error) { alert(data.error); return; }
            // Recarrega preservando o período para ver os números agregados
            const params = new URLSearchParams();
            if (start) params.set('start', start);
            if (end) params.set('end', end);
            window.location = `${BASE}buffer/dashboard?${params.toString()}`;
        }).catch(() => { btn.disabled = false; btn.innerHTML = original; });
}

function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

// Recarrega o dashboard aplicando o período escolhido (sem chamar a API)
function applyPeriod() {
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    const params = new URLSearchParams();
    if (start) params.set('start', start);
    if (end) params.set('end', end);
    window.location = `${BASE}buffer/dashboard?${params.toString()}`;
}

document.addEventListener('DOMContentLoaded', loadMetric);
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
