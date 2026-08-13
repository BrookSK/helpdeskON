<?php $pageTitle = 'Métricas Sociais - ON Solutions Helpdesk'; $currentPage = 'buffer_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Helpers locais
$fmt = fn($v) => number_format((float)$v, 0, ',', '.');
$fmtPct = fn($v) => ($v >= 0 ? '+' : '') . number_format((float)$v, 1, ',', '.') . '%';

// Soma seguidores de contas diretas
$totalFollowers = 0;
foreach ($socialAccounts as $sa) $totalFollowers += (int)($sa['followers'] ?? 0);

// Soma seguidores do crescimento (7d, 30d, 90d)
$followerGrowthAgg = ['7d' => 0, '30d' => 0, '90d' => 0];
foreach ($socialFollowersGrowth as $g) {
    foreach (['7d', '30d', '90d'] as $p) {
        if (isset($g[$p]['diff'])) $followerGrowthAgg[$p] += $g[$p]['diff'];
    }
}

// Contas Buffer com metadados
$serviceMeta = [
    'instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook' => ['Facebook', 'bi-facebook', '#1877F2'],
    'facebookpage' => ['Facebook', 'bi-facebook', '#1877F2'],
    'twitter' => ['Twitter/X', 'bi-twitter-x', '#000000'],
    'x' => ['Twitter/X', 'bi-twitter-x', '#000000'],
    'linkedin' => ['LinkedIn', 'bi-linkedin', '#0A66C2'],
    'tiktok' => ['TikTok', 'bi-tiktok', '#000000'],
    'youtube' => ['YouTube', 'bi-youtube', '#FF0000'],
    'pinterest' => ['Pinterest', 'bi-pinterest', '#E60023'],
    'threads' => ['Threads', 'bi-threads', '#000000'],
    'mastodon' => ['Mastodon', 'bi-mastodon', '#6364FF'],
    'googlebusiness' => ['Google', 'bi-google', '#4285F4'],
];
$socialProviderMeta = [
    'meta_instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook_page'  => ['Facebook', 'bi-facebook', '#1877F2'],
    'linkedin_org'   => ['LinkedIn', 'bi-linkedin', '#0A66C2'],
];
?>

<style>
/* ===== Dashboard Métricas v2 ===== */
.metrics-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.metrics-title h5 { font-size: 1.15rem; font-weight: 700; margin: 0; color: #1a1a2e; }
.metrics-title small { font-size: 0.75rem; color: #8a929b; }
.metrics-actions { display: flex; flex-wrap: wrap; gap: 6px; }

/* Filtro de período */
.period-bar { background: #fff; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.period-presets { display: flex; gap: 4px; }
.period-presets .btn { font-size: 0.72rem; padding: 4px 12px; border-radius: 20px; }
.period-presets .btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* Cards de resumo */
.summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; margin-bottom: 24px; }
.summary-card { background: #fff; border-radius: 14px; padding: 18px 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative; overflow: hidden; }
.summary-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.summary-card.sc-followers::before { background: #0A66C2; }
.summary-card.sc-reactions::before { background: #E1306C; }
.summary-card.sc-comments::before { background: #3f51b5; }
.summary-card.sc-views::before { background: #00897b; }
.summary-card.sc-impressions::before { background: #ff9800; }
.summary-card.sc-reach::before { background: #9c27b0; }
.sc-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 10px; }
.sc-value { font-size: 1.5rem; font-weight: 800; color: #1a1a2e; line-height: 1.1; }
.sc-label { font-size: 0.7rem; color: #8a929b; margin-top: 2px; font-weight: 500; }
.sc-change { display: inline-flex; align-items: center; gap: 2px; font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-top: 8px; }
.sc-change.positive { background: #e8f5e9; color: #15803d; }
.sc-change.negative { background: #fef2f2; color: #dc2626; }
.sc-change.neutral { background: #f3f4f6; color: #6b7280; }
.sc-prev { font-size: 0.62rem; color: #aab; margin-top: 4px; }

/* Gráfico e Top Posts */
.chart-section { margin-bottom: 24px; }
.chart-card, .top-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
.chart-card .card-header, .top-card .card-header { background: transparent; border-bottom: 1px solid #f0f2f4; padding: 14px 18px; }
.chart-card .card-header h6, .top-card .card-header h6 { font-size: 0.85rem; font-weight: 700; margin: 0; color: #2b3440; }

/* Contas conectadas - layout de linhas */
.accounts-section { margin-bottom: 24px; }
.accounts-section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.accounts-section-head h6 { font-size: 0.95rem; font-weight: 700; color: #1a1a2e; margin: 0; }
.accounts-table { background: #fff; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
.account-row { display: flex; align-items: center; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #f0f2f4; transition: background .1s; }
.account-row:last-child { border-bottom: none; }
.account-row:hover { background: #f9fafb; }
.ar-identity { display: flex; align-items: center; gap: 12px; min-width: 200px; flex-shrink: 0; }
.ar-avatar { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; overflow: hidden; position: relative; }
.ar-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.ar-badge { position: absolute; bottom: -2px; right: -2px; width: 18px; height: 18px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.55rem; border: 2px solid #fff; }
.ar-info { min-width: 0; }
.ar-name { font-size: 0.88rem; font-weight: 600; color: #2b3440; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.ar-sub { font-size: 0.72rem; color: #8a929b; }
.ar-metrics { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; }
.ar-metric { text-align: center; padding: 8px 12px; background: #f7f9fa; border-radius: 10px; min-width: 72px; }
.ar-mval { display: block; font-size: 1rem; font-weight: 700; color: #2b3440; line-height: 1.2; }
.ar-mlbl { display: block; font-size: 0.62rem; color: #8a929b; margin-top: 2px; }
.ar-source { flex-shrink: 0; }
.badge-source { font-size: 0.65rem; font-weight: 600; padding: 4px 10px; border-radius: 12px; }
.badge-source.buffer { background: #f3f4f6; color: #666; }
.badge-source.direct { background: #e8f5e9; color: #16a34a; }

/* Posts inline (row abaixo da conta) */
.account-posts-row { display: flex; gap: 10px; padding: 10px 20px 16px 76px; border-bottom: 1px solid #f0f2f4; overflow-x: auto; }
.sp-mini { display: block; width: 80px; height: 80px; border-radius: 10px; overflow: hidden; position: relative; flex-shrink: 0; background: #eef1f4; text-decoration: none; transition: transform .15s; }
.sp-mini:hover { transform: scale(1.05); }
.sp-mini img { width: 100%; height: 100%; object-fit: cover; }
.sp-mini i { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 1.4rem; }
.sp-mini-stats { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.65); display: flex; gap: 6px; justify-content: center; padding: 3px 0; font-size: 0.6rem; color: #fff; opacity: 0; transition: opacity .15s; }
.sp-mini:hover .sp-mini-stats { opacity: 1; }
.sp-mini-stats i { color: #fff; font-size: 0.55rem; }

/* Followers Growth Consolidated */
.followers-consolidated { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 24px; }
.fc-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.fc-header h6 { font-size: 0.9rem; font-weight: 700; margin: 0; }
.fc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
.fc-item { text-align: center; padding: 14px 8px; border-radius: 12px; background: #f7f9fa; }
.fc-item.current { background: linear-gradient(135deg, #e0f7f4, #b2f2e8); }
.fc-val { font-size: 1.3rem; font-weight: 800; color: #1a1a2e; }
.fc-diff { font-size: 0.72rem; font-weight: 700; }
.fc-diff.pos { color: #16a34a; }
.fc-diff.neg { color: #dc2626; }
.fc-lbl { font-size: 0.65rem; color: #8a929b; margin-top: 4px; }

/* Top posts list */
.top-post-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f5f5f5; text-decoration: none; color: inherit; transition: background .1s; }
.top-post-item:hover { background: #f9fafb; }
.top-post-cover { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #eef1f4; }
.top-post-info { flex: 1; min-width: 0; }
.top-post-text { font-size: 0.78rem; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #333; }
.top-post-meta { font-size: 0.65rem; color: #8a929b; margin-top: 2px; }

/* Social posts grid - removido, usando sp-mini agora */

/* Token alert */
.token-alert-bar { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 10px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
.token-alert-bar i { color: #d97706; font-size: 1.2rem; }

/* Responsive */
@media (max-width: 768px) {
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
    .fc-grid { grid-template-columns: repeat(2, 1fr); }
    .account-row { flex-direction: column; align-items: flex-start; gap: 8px; }
    .ar-identity { min-width: auto; }
    .ar-metrics { width: 100%; }
    .account-posts-row { padding-left: 16px; }
}
</style>

<div class="main-content">
    <!-- Header -->
    <div class="metrics-header">
        <div class="metrics-title">
            <h5><i class="bi bi-graph-up-arrow"></i> Métricas Sociais</h5>
            <small>Desempenho consolidado de todas as redes (Buffer + Meta + LinkedIn)</small>
        </div>
        <div class="metrics-actions">
            <button class="btn btn-outline-secondary btn-sm" onclick="syncChannels(this)"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
            <button class="btn btn-primary btn-sm" onclick="syncMetrics(this)"><i class="bi bi-cloud-download"></i> Atualizar métricas</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="importMeta(this)"><i class="bi bi-download"></i> Importar Meta</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="openLinkedinModal()"><i class="bi bi-linkedin"></i> Add LinkedIn</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="syncSocial(this)"><i class="bi bi-arrow-repeat"></i> Atualizar redes</button>
            <button class="btn btn-outline-success btn-sm" onclick="snapshotFollowers(this)" title="Salvar snapshot diário"><i class="bi bi-camera"></i> Snapshot</button>
        </div>
    </div>

    <?php if (!$hasKey && empty($socialAccounts)): ?>
    <div class="alert alert-warning py-2 px-3 small mb-3">
        <i class="bi bi-exclamation-triangle"></i> Nenhuma integração configurada.
        <?php if ($isAdmin): ?>Adicione chaves do Buffer ou tokens da Meta/LinkedIn em <a href="<?= baseUrl('settings') ?>">Configurações</a>.<?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Token expiry alert -->
    <?php $metaTokenExpired = $metaTokenExpired ?? false; $linkedinTokenExpired = $linkedinTokenExpired ?? false; ?>
    <?php if ($metaTokenExpired || $linkedinTokenExpired): ?>
    <div class="token-alert-bar small">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <strong>Token expirado:</strong>
            <?php if ($metaTokenExpired && $linkedinTokenExpired): ?>Meta e LinkedIn.
            <?php elseif ($metaTokenExpired): ?>Meta (Instagram/Facebook).
            <?php else: ?>LinkedIn.<?php endif; ?>
            Dados de seguidores não estão sendo atualizados.
        </span>
        <?php if ($isAdmin): ?><a href="<?= baseUrl('settings') ?>" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-key"></i> Reconectar</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Período + Presets -->
    <div class="period-bar">
        <div class="period-presets">
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(7)">7d</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(30)">30d</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(90)">90d</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(365)">1 ano</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <input type="date" id="period-start" class="form-control form-control-sm" style="width:140px;" value="<?= escape($periodStart) ?>">
            <span class="text-muted small">até</span>
            <input type="date" id="period-end" class="form-control form-control-sm" style="width:140px;" value="<?= escape($periodEnd) ?>">
        </div>
        <select id="filter-network" class="form-select form-select-sm" style="width:155px;">
            <option value="">Todas as redes</option>
        </select>
        <select id="filter-account" class="form-select form-select-sm" style="width:180px;">
            <option value="">Todos os perfis</option>
        </select>
        <button class="btn btn-sm btn-primary" onclick="applyFilter()"><i class="bi bi-funnel"></i> Aplicar</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="clearFilter()"><i class="bi bi-x-lg"></i></button>
    </div>

    <!-- Cards de resumo com comparação -->
    <div class="summary-grid" id="summary-grid">
        <?php
        $summaryCards = [
            ['sc-followers', 'Seguidores', $totalFollowers, 'bi-people-fill', '#0A66C2'],
            ['sc-reactions', 'Curtidas', $totals['reactions'], 'bi-heart-fill', '#E1306C'],
            ['sc-comments', 'Comentários', $totals['comments'], 'bi-chat-dots-fill', '#3f51b5'],
            ['sc-views', 'Visualizações', $totals['views'], 'bi-eye-fill', '#00897b'],
            ['sc-impressions', 'Impressões', $totals['impressions'], 'bi-bar-chart-fill', '#ff9800'],
            ['sc-reach', 'Alcance', $totals['reach'], 'bi-broadcast', '#9c27b0'],
        ];
        foreach ($summaryCards as $sc):
        ?>
        <div class="summary-card <?= $sc[0] ?>">
            <div class="sc-icon" style="background: <?= $sc[4] ?>1a; color: <?= $sc[4] ?>;">
                <i class="bi <?= $sc[3] ?>"></i>
            </div>
            <div class="sc-value"><?= $fmt($sc[2]) ?></div>
            <div class="sc-label"><?= $sc[1] ?></div>
            <div class="sc-change neutral" data-metric="<?= str_replace('sc-', '', $sc[0]) ?>">
                <i class="bi bi-dash"></i> —
            </div>
            <div class="sc-prev" data-metric-prev="<?= str_replace('sc-', '', $sc[0]) ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Seguidores: crescimento consolidado -->
    <?php if ($totalFollowers > 0): ?>
    <div class="followers-consolidated">
        <div class="fc-header">
            <i class="bi bi-graph-up-arrow" style="color: var(--primary); font-size: 1.2rem;"></i>
            <h6>Crescimento de Seguidores (todas as contas)</h6>
        </div>
        <div class="fc-grid">
            <div class="fc-item current">
                <div class="fc-val"><?= $fmt($totalFollowers) ?></div>
                <div class="fc-lbl">Total atual</div>
            </div>
            <?php
            $growthLabels = ['7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias'];
            foreach ($growthLabels as $gKey => $gLabel):
                $diff = $followerGrowthAgg[$gKey];
                $cls = $diff > 0 ? 'pos' : ($diff < 0 ? 'neg' : '');
                $arrow = $diff > 0 ? '+' : '';
            ?>
            <div class="fc-item">
                <div class="fc-diff <?= $cls ?>"><?= $arrow . $fmt($diff) ?></div>
                <div class="fc-lbl"><?= $gLabel ?></div>
            </div>
            <?php endforeach; ?>
            <?php
            // Por conta individual
            foreach ($socialAccounts as $sa):
                $pm = $socialProviderMeta[$sa['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
                $gData = $socialFollowersGrowth[$sa['id']] ?? null;
                if (!$gData || $gData['current'] === null) continue;
            ?>
            <div class="fc-item">
                <div class="fc-val" style="font-size:1rem;"><?= $fmt($gData['current']) ?></div>
                <?php if (isset($gData['30d']['diff']) && $gData['30d']['diff'] !== null):
                    $d30 = $gData['30d']['diff'];
                ?>
                <div class="fc-diff <?= $d30 >= 0 ? 'pos' : 'neg' ?>"><?= ($d30 >= 0 ? '+' : '') . $fmt($d30) ?></div>
                <?php endif; ?>
                <div class="fc-lbl"><i class="bi <?= $pm[1] ?>" style="color:<?= $pm[2] ?>;"></i> <?= escape($sa['display_name'] ?: $sa['external_id']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gráfico + Top Posts -->
    <div class="chart-section">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <select id="metric-filter" class="form-select form-select-sm" style="max-width:200px;" onchange="loadMetric()">
                <option value="reactions">Curtidas / Reações</option>
                <option value="comments">Comentários</option>
                <option value="views">Visualizações</option>
                <option value="impressions">Impressões</option>
                <option value="reach">Alcance</option>
                <option value="engagementRate">Taxa de engajamento</option>
                <option value="shares">Compartilhamentos</option>
                <option value="saves">Salvamentos</option>
            </select>
            <select id="post-filter" class="form-select form-select-sm" style="max-width:300px;" onchange="loadMetric()">
                <option value="">Todas as publicações</option>
                <?php foreach ($posts as $p): ?>
                <option value="<?= escape($p['buffer_post_id']) ?>"><?= escape(mb_strimwidth($p['text'] ?: '(sem texto)', 0, 55, '...')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="chart-card">
                    <div class="card-header"><h6><i class="bi bi-activity"></i> Variação ao longo do tempo</h6></div>
                    <div class="card-body" style="padding:16px;">
                        <div style="position:relative;height:280px;">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="top-card">
                    <div class="card-header"><h6><i class="bi bi-trophy"></i> Top posts por desempenho</h6></div>
                    <div id="top-posts" style="max-height:320px;overflow-y:auto;">
                        <div class="text-muted small text-center py-4">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contas conectadas (Buffer + Diretas unificado) -->
    <div class="accounts-section">
        <div class="accounts-section-head">
            <h6><i class="bi bi-share"></i> Todas as contas conectadas</h6>
            <span class="text-muted small"><?= count($channels) + count($socialAccounts) ?> conta(s)</span>
        </div>
        <div class="accounts-table">
            <?php
            // === Buffer channels ===
            foreach ($channels as $ch):
                $svc = strtolower($ch['service'] ?? '');
                $meta = $serviceMeta[$svc] ?? ['Outro', 'bi-globe', '#607d8b'];
                $connected = empty($ch['is_disconnected']);
                $cm = $channelMetrics[$ch['channel_id']] ?? [];
                $getM = function($t) use ($cm) { return isset($cm[$t]) ? (float)$cm[$t]['metric_value'] : 0; };
                $hasMetrics = $getM('postCount') > 0 || $getM('reactions') > 0 || $getM('impressions') > 0;
            ?>
            <div class="account-row" data-network="<?= escape($svc) ?>" data-source="buffer">
                <div class="ar-identity">
                    <div class="ar-avatar" style="background: <?= $meta[2] ?>1a; color: <?= $meta[2] ?>;">
                        <?php if (!empty($ch['avatar'])): ?>
                        <img src="<?= escape($ch['avatar']) ?>" alt="">
                        <?php else: ?><i class="bi <?= $meta[1] ?>"></i><?php endif; ?>
                        <span class="ar-badge" style="background: <?= $meta[2] ?>;"><i class="bi <?= $meta[1] ?>"></i></span>
                    </div>
                    <div class="ar-info">
                        <div class="ar-name"><?= escape($ch['name']) ?></div>
                        <div class="ar-sub"><?php if (!empty($ch['username'])): ?>@<?= escape($ch['username']) ?><?php else: ?><?= $meta[0] ?><?php endif; ?></div>
                    </div>
                </div>
                <div class="ar-metrics">
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($getM('postCount')) ?></span><span class="ar-mlbl">Posts</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($getM('reactions')) ?></span><span class="ar-mlbl">Curtidas</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($getM('comments')) ?></span><span class="ar-mlbl">Coment.</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($getM('impressions')) ?></span><span class="ar-mlbl">Impressões</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($getM('reach')) ?></span><span class="ar-mlbl">Alcance</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= number_format($getM('engagementRate'), 1, ',', '.') ?>%</span><span class="ar-mlbl">Engaj.</span></div>
                </div>
                <div class="ar-source"><span class="badge-source buffer">Buffer</span></div>
            </div>
            <?php endforeach; ?>

            <?php
            // === Contas diretas (Meta / LinkedIn) ===
            foreach ($socialAccounts as $acc):
                $pm = $socialProviderMeta[$acc['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
                $netKey = ['meta_instagram' => 'instagram', 'facebook_page' => 'facebook', 'linkedin_org' => 'linkedin'][$acc['provider']] ?? $acc['provider'];
                $svAcc = fn($k) => ($acc[$k] !== null && $acc[$k] !== '') ? (float)$acc[$k] : 0;
                $accPosts = $socialPostsByAccount[$acc['id']] ?? [];
            ?>
            <div class="account-row" data-network="<?= escape($netKey) ?>" data-source="direct">
                <div class="ar-identity">
                    <div class="ar-avatar" style="background: <?= $pm[2] ?>1a; color: <?= $pm[2] ?>;">
                        <?php if (!empty($acc['avatar'])): ?>
                        <img src="<?= escape($acc['avatar']) ?>" alt="">
                        <?php else: ?><i class="bi <?= $pm[1] ?>"></i><?php endif; ?>
                        <span class="ar-badge" style="background: <?= $pm[2] ?>;"><i class="bi <?= $pm[1] ?>"></i></span>
                    </div>
                    <div class="ar-info">
                        <div class="ar-name"><?= escape($acc['display_name'] ?: $acc['external_id']) ?></div>
                        <div class="ar-sub"><?php if (!empty($acc['username'])): ?>@<?= escape($acc['username']) ?><?php else: ?><?= $pm[0] ?><?php endif; ?></div>
                    </div>
                </div>
                <div class="ar-metrics">
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('followers')) ?></span><span class="ar-mlbl">Seguidores</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('total_likes')) ?></span><span class="ar-mlbl">Curtidas</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('total_comments')) ?></span><span class="ar-mlbl">Coment.</span></div>
                    <?php if ($acc['provider'] === 'meta_instagram'): ?>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('reach')) ?></span><span class="ar-mlbl">Alcance</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('impressions')) ?></span><span class="ar-mlbl">Impressões</span></div>
                    <?php else: ?>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('impressions')) ?></span><span class="ar-mlbl">Impressões</span></div>
                    <div class="ar-metric"><span class="ar-mval"><?= $fmt($svAcc('total_shares')) ?></span><span class="ar-mlbl">Compart.</span></div>
                    <?php endif; ?>
                    <div class="ar-metric"><span class="ar-mval"><?= number_format($svAcc('engagement_rate'), 1, ',', '.') ?>%</span><span class="ar-mlbl">Engaj.</span></div>
                </div>
                <div class="ar-source"><span class="badge-source direct" style="color: <?= $pm[2] ?>; background: <?= $pm[2] ?>1a;">API</span></div>
            </div>
            <?php if (!empty($accPosts)): ?>
            <div class="account-posts-row">
                <?php foreach (array_slice($accPosts, 0, 8) as $p): ?>
                <a class="sp-mini" <?= $p['permalink'] ? 'href="' . escape($p['permalink']) . '" target="_blank" rel="noopener"' : '' ?>>
                    <?php if (!empty($p['thumbnail'])): ?>
                    <img src="<?= escape($p['thumbnail']) ?>" alt="" onerror="this.parentNode.style.display='none'">
                    <?php else: ?>
                    <i class="bi bi-image"></i>
                    <?php endif; ?>
                    <div class="sp-mini-stats">
                        <span><i class="bi bi-heart-fill"></i> <?= $fmt($p['likes'] ?? 0) ?></span>
                        <span><i class="bi bi-chat-fill"></i> <?= $fmt($p['comments'] ?? 0) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($channels) && empty($socialAccounts)): ?>
    <div class="alert alert-light border small text-center">
        Nenhuma conta conectada. Use os botões acima para conectar Buffer, Meta ou LinkedIn.
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<?php require APP_PATH . '/views/buffer/_social_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const BASE = '<?= baseUrl("") ?>';
const ACTIVE_NETWORK = '<?= escape($filterNetwork ?? '') ?>';
const ACTIVE_ACCOUNT = '<?= escape($filterAccount ?? '') ?>';
let lineChart = null;

const METRIC_LABELS = {
    reactions: 'Curtidas / Reações', comments: 'Comentários', views: 'Visualizações',
    impressions: 'Impressões', reach: 'Alcance', engagementRate: 'Taxa de engajamento (%)',
    shares: 'Compartilhamentos', saves: 'Salvamentos'
};

// ===== Período presets =====
function setPeriod(days) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days);
    document.getElementById('period-start').value = start.toISOString().slice(0, 10);
    document.getElementById('period-end').value = end.toISOString().slice(0, 10);
    applyFilter();
}

// ===== Filtros =====
function applyFilter() {
    const params = new URLSearchParams();
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    const net = document.getElementById('filter-network').value;
    const acc = document.getElementById('filter-account').value;
    if (start) params.set('start', start);
    if (end) params.set('end', end);
    if (net) params.set('network', net);
    if (acc) params.set('account', acc);
    window.location = BASE + 'buffer/dashboard?' + params.toString();
}

function clearFilter() {
    window.location = BASE + 'buffer/dashboard';
}

// ===== Comparação (carrega via AJAX) =====
function loadComparison() {
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    const params = new URLSearchParams({ start, end });
    if (ACTIVE_NETWORK) params.set('network', ACTIVE_NETWORK);
    if (ACTIVE_ACCOUNT) params.set('account', ACTIVE_ACCOUNT);

    fetch(BASE + 'buffer/comparison?' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.comparison) return;
            const c = data.comparison;
            Object.keys(c).forEach(metric => {
                const el = document.querySelector(`[data-metric="${metric}"]`);
                const prevEl = document.querySelector(`[data-metric-prev="${metric}"]`);
                if (!el) return;
                const d = c[metric];
                const isPos = d.pct >= 0;
                const cls = d.pct > 0 ? 'positive' : (d.pct < 0 ? 'negative' : 'neutral');
                const arrow = d.pct > 0 ? 'bi-arrow-up-short' : (d.pct < 0 ? 'bi-arrow-down-short' : 'bi-dash');
                const sign = d.pct >= 0 ? '+' : '';
                el.className = 'sc-change ' + cls;
                el.innerHTML = `<i class="bi ${arrow}"></i> ${sign}${d.pct.toFixed(1)}%`;
                if (prevEl) {
                    prevEl.textContent = `Anterior: ${Math.round(d.previous).toLocaleString('pt-BR')}`;
                }
            });
        }).catch(() => {});
}

// ===== Gráfico =====
function loadMetric() {
    const metric = document.getElementById('metric-filter').value;
    const start = document.getElementById('period-start').value;
    const end = document.getElementById('period-end').value;
    const params = new URLSearchParams({ metric });
    if (start) params.set('start', start);
    if (end) params.set('end', end);
    if (ACTIVE_NETWORK) params.set('network', ACTIVE_NETWORK);
    if (ACTIVE_ACCOUNT) params.set('account', ACTIVE_ACCOUNT);
    fetch(BASE + 'buffer/metrics?' + params.toString())
        .then(r => r.json())
        .then(data => {
            renderLine(data.timeline || [], metric);
            renderTop(data.top || [], metric);
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
    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height || 280);
    gradient.addColorStop(0, 'rgba(0,191,166,0.22)');
    gradient.addColorStop(1, 'rgba(0,191,166,0.01)');

    lineChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: METRIC_LABELS[metric] || metric,
                data: values,
                borderColor: '#00BFA6',
                borderWidth: 2.5,
                backgroundColor: gradient,
                fill: true,
                cubicInterpolationMode: 'monotone',
                tension: 0.4,
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
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1a1a2e', padding: 10, displayColors: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#9aa4ae', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#9aa4ae', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } }
            }
        }
    });
}

function renderTop(top, metric) {
    const box = document.getElementById('top-posts');
    if (!top.length) { box.innerHTML = '<div class="text-muted small text-center py-4">Sem dados no período.</div>'; return; }
    box.innerHTML = top.slice(0, 10).map((p, i) => {
        const val = p.metric_unit === 'percentage' ? (parseFloat(p.metric_value).toFixed(1) + '%') : Math.round(p.metric_value).toLocaleString('pt-BR');
        const txt = (p.text || '(sem texto)').slice(0, 70);
        const link = p.external_link ? `href="${p.external_link}" target="_blank"` : '';
        const cover = p.thumbnail
            ? `<img src="${p.thumbnail}" class="top-post-cover" alt="" onerror="this.parentNode.innerHTML='<div class=\\'top-post-cover\\' style=\\'display:flex;align-items:center;justify-content:center;color:#bbb;\\'><i class=\\'bi bi-image\\'></i></div>'">`
            : `<div class="top-post-cover" style="display:flex;align-items:center;justify-content:center;color:#bbb;"><i class="bi bi-image"></i></div>`;
        return `<a ${link} class="top-post-item">
            ${cover}
            <div class="top-post-info">
                <div class="top-post-text">${i + 1}. ${escHtml(txt)}</div>
                <div class="top-post-meta">${p.service || ''} &middot; ${val}</div>
            </div>
            <span class="badge bg-primary rounded-pill" style="font-size:0.72rem;">${val}</span>
        </a>`;
    }).join('');
}

// ===== Ações =====
function syncChannels(btn) {
    const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(BASE + 'buffer/syncChannels', { method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => { btn.disabled = false; btn.innerHTML = o; if (d.error) { alert(d.error); return; } location.reload(); })
        .catch(() => { btn.disabled = false; btn.innerHTML = o; });
}

function syncMetrics(btn) {
    const o = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Atualizando...';
    const fd = new FormData();
    fd.append('start', document.getElementById('period-start').value);
    fd.append('end', document.getElementById('period-end').value);
    fetch(BASE + 'buffer/syncMetrics', { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
        .then(r => r.json()).then(d => { btn.disabled = false; btn.innerHTML = o; if (d.error) { alert(d.error); return; } location.reload(); })
        .catch(() => { btn.disabled = false; btn.innerHTML = o; });
}

function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

// ===== Filtros de rede (client-side) =====
function buildFilters() {
    const cards = document.querySelectorAll('.account-card');
    const nets = new Set();
    const accs = new Set();
    cards.forEach(el => {
        if (el.dataset.network) nets.add(el.dataset.network);
    });
    const netSel = document.getElementById('filter-network');
    nets.forEach(n => {
        const opt = document.createElement('option');
        opt.value = n;
        opt.textContent = n.charAt(0).toUpperCase() + n.slice(1);
        if (n === ACTIVE_NETWORK) opt.selected = true;
        netSel.appendChild(opt);
    });
}

// ===== Init =====
document.addEventListener('DOMContentLoaded', () => {
    loadMetric();
    loadComparison();
    buildFilters();
});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
