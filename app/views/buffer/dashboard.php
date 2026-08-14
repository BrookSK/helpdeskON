<?php $pageTitle = 'Métricas Sociais'; $currentPage = 'buffer_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
$fmt = fn($v) => number_format((float)$v, 0, ',', '.');
$totalFollowers = 0;
foreach ($socialAccounts as $sa) $totalFollowers += (int)($sa['followers'] ?? 0);
$followerGrowthAgg = ['7d' => 0, '30d' => 0, '90d' => 0];
foreach ($socialFollowersGrowth as $g) {
    foreach (['7d', '30d', '90d'] as $p) {
        if (isset($g[$p]['diff'])) $followerGrowthAgg[$p] += $g[$p]['diff'];
    }
}
$providerInfo = [
    'meta_instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook_page'  => ['Facebook', 'bi-facebook', '#1877F2'],
    'linkedin_org'   => ['LinkedIn', 'bi-linkedin', '#0A66C2'],
];
$networkInfo = [
    'instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook' => ['Facebook', 'bi-facebook', '#1877F2'],
    'facebookpage' => ['Facebook', 'bi-facebook', '#1877F2'],
    'linkedin' => ['LinkedIn', 'bi-linkedin', '#0A66C2'],
    'tiktok' => ['TikTok', 'bi-tiktok', '#000'],
    'twitter' => ['Twitter/X', 'bi-twitter-x', '#000'],
    'x' => ['Twitter/X', 'bi-twitter-x', '#000'],
    'youtube' => ['YouTube', 'bi-youtube', '#FF0000'],
    'pinterest' => ['Pinterest', 'bi-pinterest', '#E60023'],
    'threads' => ['Threads', 'bi-threads', '#000'],
];
?>

<style>
/* ===== MÉTRICAS SOCIAIS v4 — PREMIUM LAYOUT ===== */

.ms-main { padding: 30px 35px 40px; }

/* HERO HEADER */
.ms-hero {
    background: linear-gradient(135deg, #e0f7f4 0%, #f0faf8 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #1a1a2e;
    position: relative;
    overflow: hidden;
    border: 1px solid #c8ede6;
}
.ms-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(0,191,166,0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.ms-hero h2 { font-size: 1.6rem; font-weight: 800; margin: 0 0 6px; color: #1a1a2e; }
.ms-hero p { font-size: 0.9rem; color: #5a6b7b; margin: 0; }

/* ACTION BUTTONS */
.ms-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 28px;
}
.ms-actions .btn {
    font-size: 0.82rem;
    padding: 10px 18px;
    border-radius: 10px;
    font-weight: 500;
}

/* PERIOD BAR */
.ms-period-bar {
    background: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 28px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
}
.ms-period-bar label { font-size: 0.85rem; font-weight: 600; color: #2b3440; margin: 0; }
.ms-period-presets { display: flex; gap: 6px; }
.ms-period-presets .btn {
    font-size: 0.8rem;
    padding: 8px 18px;
    border-radius: 24px;
    font-weight: 600;
    border: 2px solid #e0e0e0;
    color: #555;
    background: #fff;
    transition: all .2s;
}
.ms-period-presets .btn:hover, .ms-period-presets .btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.ms-period-bar input[type="date"] {
    font-size: 0.85rem;
    padding: 8px 12px;
    border-radius: 10px;
    border: 2px solid #e8e8e8;
    width: 150px;
}

/* SUMMARY CARDS - PREMIUM */
.ms-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
.ms-kpi {
    background: #fff;
    border-radius: 16px;
    padding: 22px 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.ms-kpi:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.ms-kpi-accent { position: absolute; top: 0; left: 0; right: 0; height: 4px; border-radius: 16px 16px 0 0; }
.ms-kpi-icon { font-size: 1.4rem; margin-bottom: 12px; }
.ms-kpi-value { font-size: 2rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
.ms-kpi-label { font-size: 0.82rem; color: #6b7280; margin-top: 6px; font-weight: 500; }
.ms-kpi-change { display: inline-flex; align-items: center; gap: 3px; font-size: 0.75rem; font-weight: 700; margin-top: 10px; padding: 4px 10px; border-radius: 20px; }
.ms-kpi-change.up { background: #ecfdf5; color: #059669; }
.ms-kpi-change.down { background: #fef2f2; color: #dc2626; }
.ms-kpi-change.flat { background: #f3f4f6; color: #6b7280; }
.ms-kpi-prev { font-size: 0.7rem; color: #9ca3af; margin-top: 4px; }

/* GROWTH SECTION */
.ms-section { margin-bottom: 32px; }
.ms-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ms-section-title i { color: var(--primary); }

.ms-growth-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
.ms-growth-card {
    background: #fff;
    border-radius: 14px;
    padding: 28px 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    text-align: center;
    min-width: 110px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.ms-growth-card.main {
    background: linear-gradient(135deg, var(--primary), #00897b);
    color: #fff;
}
.ms-gc-val { font-size: 2.2rem; font-weight: 800; line-height: 1.1; }
.ms-gc-diff { font-size: 1.8rem; font-weight: 800; margin-top: 4px; }
.ms-gc-diff.pos { color: #059669; }
.ms-gc-diff.neg { color: #dc2626; }
.ms-growth-card.main .ms-gc-diff { color: rgba(255,255,255,0.85); }
.ms-gc-label { font-size: 0.92rem; color: #6b7280; margin-top: 8px; font-weight: 500; }
.ms-growth-card.main .ms-gc-label { color: rgba(255,255,255,0.8); }

/* CHART & TOP POSTS */
.ms-chart-row { margin-bottom: 32px; }
.ms-chart-row .row { align-items: stretch; }
.ms-box {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.ms-box-header {
    padding: 18px 22px;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.ms-box-header h5 { font-size: 1rem; font-weight: 700; color: #2b3440; margin: 0; }
.ms-box-body { padding: 20px 22px; flex: 1; }

.ms-top-list { flex: 1; overflow-y: auto; }
.ms-top-item { display: flex; align-items: center; gap: 14px; padding: 14px 20px; border-bottom: 1px solid #f5f5f5; text-decoration: none; color: inherit; transition: background .1s; }
.ms-top-item:hover { background: #fafbfc; }
.ms-top-thumb { width: 60px; height: 60px; border-radius: 10px; overflow: hidden; background: #eef1f4; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #ccc; }
.ms-top-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ms-top-info { flex: 1; min-width: 0; }
.ms-top-text { font-size: 0.92rem; font-weight: 500; color: #333; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.ms-top-meta { font-size: 0.78rem; color: #8a929b; margin-top: 4px; }
.ms-top-val { font-size: 1rem; font-weight: 700; color: var(--primary); flex-shrink: 0; }

/* ACCOUNTS / NOSSAS REDES */
.ms-networks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
}
.ms-net-card {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    border: 1px solid #f0f2f4;
    transition: box-shadow .2s, transform .2s;
}
.ms-net-card:hover { box-shadow: 0 8px 28px rgba(0,0,0,0.07); transform: translateY(-2px); }
.ms-net-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.ms-net-avatar { width: 54px; height: 54px; border-radius: 14px; overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
.ms-net-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
.ms-net-badge { position: absolute; bottom: -3px; right: -3px; width: 22px; height: 22px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; border: 2px solid #fff; }
.ms-net-name { font-size: 1.05rem; font-weight: 700; color: #1a1a2e; }
.ms-net-handle { font-size: 0.82rem; color: #8a929b; margin-top: 2px; }

/* Métrica principal (destaque) */
.ms-net-highlight {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    background: linear-gradient(135deg, #f0faf8, #e8f5f2);
    border-radius: 12px;
    margin-bottom: 14px;
}
.ms-net-highlight-val { font-size: 1.8rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
.ms-net-highlight-lbl { font-size: 0.82rem; color: #5a6b7b; font-weight: 500; }

/* Métricas secundárias em grid 2 colunas */
.ms-net-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.ms-net-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f8faf9;
    border-radius: 10px;
}
.ms-ns-icon { font-size: 0.9rem; color: #8a929b; flex-shrink: 0; width: 20px; text-align: center; }
.ms-ns-val { font-size: 1rem; font-weight: 700; color: #2b3440; }
.ms-ns-lbl { font-size: 0.72rem; color: #8a929b; }

/* Posts row */
.ms-net-posts { display: flex; gap: 8px; margin-top: 16px; overflow-x: auto; padding-bottom: 4px; }
.ms-net-post { width: 64px; height: 64px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: #eef1f4; }
.ms-net-post img { width: 100%; height: 100%; object-fit: cover; }

/* Delete button */
/* Alert bar */
.ms-alert-bar { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; }
.ms-alert-bar i { color: #d97706; font-size: 1.3rem; }

/* Responsive */
@media (max-width: 1200px) {
    .ms-kpi-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 992px) {
    .ms-main { padding: 20px 18px; }
    .ms-hero { padding: 24px 20px; }
    .ms-hero h2 { font-size: 1.3rem; }
    .ms-kpi-grid { grid-template-columns: repeat(3, 1fr); }
    .ms-growth-cards { grid-template-columns: repeat(2, 1fr); }
    .ms-networks-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 576px) {
    .ms-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .ms-growth-cards { grid-template-columns: 1fr; }
    .ms-networks-grid { grid-template-columns: 1fr; }
    .ms-period-presets { flex-wrap: wrap; }
}
</style>

<div class="main-content ms-main">

    <!-- ============ HERO HEADER ============ -->
    <div class="ms-hero">
        <h2><i class="bi bi-bar-chart-line"></i> Métricas Sociais</h2>
        <p>Acompanhe o desempenho de todas as suas redes sociais em um só lugar</p>
    </div>

    <!-- ============ ACTIONS (admin only) ============ -->
    <?php if ($isAdmin): ?>
    <div class="ms-actions">
        <button class="btn btn-outline-secondary" onclick="syncChannels(this)"><i class="bi bi-arrow-repeat"></i> Sincronizar contas</button>
        <button class="btn btn-primary" onclick="syncMetrics(this)"><i class="bi bi-cloud-download"></i> Atualizar métricas</button>
        <button class="btn btn-outline-secondary" onclick="importMeta(this)"><i class="bi bi-download"></i> Importar Meta</button>
        <button class="btn btn-outline-secondary" onclick="openLinkedinModal()"><i class="bi bi-linkedin"></i> Add LinkedIn</button>
        <button class="btn btn-outline-secondary" onclick="syncSocial(this)"><i class="bi bi-arrow-repeat"></i> Atualizar redes</button>
        <button class="btn btn-outline-secondary" onclick="snapshotFollowers(this)"><i class="bi bi-camera"></i> Snapshot</button>
    </div>
    <?php endif; ?>

    <!-- Token alert -->
    <?php $metaTokenExpired = $metaTokenExpired ?? false; $linkedinTokenExpired = $linkedinTokenExpired ?? false; ?>
    <?php if ($metaTokenExpired || $linkedinTokenExpired): ?>
    <div class="ms-alert-bar">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <?php if ($metaTokenExpired && $linkedinTokenExpired): ?>Os tokens da Meta e LinkedIn expiraram — dados de seguidores não atualizam.
            <?php elseif ($metaTokenExpired): ?>O token da Meta expirou — seguidores do Instagram/Facebook não atualizam.
            <?php else: ?>O token do LinkedIn expirou — seguidores do LinkedIn não atualizam.<?php endif; ?>
        </span>
        <?php if ($isAdmin): ?><a href="<?= baseUrl('settings') ?>" class="btn btn-warning ms-auto"><i class="bi bi-key"></i> Reconectar</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============ PERÍODO ============ -->
    <div class="ms-period-bar">
        <label>Período:</label>
        <div class="ms-period-presets">
            <button class="btn" onclick="setPeriod(7)">7 dias</button>
            <button class="btn" onclick="setPeriod(30)">30 dias</button>
            <button class="btn" onclick="setPeriod(90)">3 meses</button>
            <button class="btn" onclick="setPeriod(180)">6 meses</button>
            <button class="btn" onclick="setPeriod(365)">1 ano</button>
        </div>
        <input type="date" id="period-start" value="<?= escape($periodStart) ?>">
        <span style="color:#999;">—</span>
        <input type="date" id="period-end" value="<?= escape($periodEnd) ?>">
        <button class="btn btn-primary" onclick="applyFilter()" style="padding:8px 20px;border-radius:10px;font-size:0.85rem;"><i class="bi bi-check2"></i> Aplicar</button>
    </div>

    <!-- ============ KPI CARDS ============ -->
    <div class="ms-kpi-grid">
        <?php
        $kpis = [
            ['followers', 'Seguidores', $totalFollowers, 'bi-people-fill', '#0A66C2'],
            ['reactions', 'Curtidas', $totals['reactions'], 'bi-heart-fill', '#E1306C'],
            ['comments', 'Comentários', $totals['comments'], 'bi-chat-dots-fill', '#3f51b5'],
            ['views', 'Visualizações', $totals['views'], 'bi-play-circle-fill', '#00897b'],
            ['impressions', 'Impressões', $totals['impressions'], 'bi-eye-fill', '#ff9800'],
            ['reach', 'Alcance', $totals['reach'], 'bi-megaphone-fill', '#9c27b0'],
        ];
        foreach ($kpis as $kpi):
        ?>
        <div class="ms-kpi">
            <div class="ms-kpi-accent" style="background: <?= $kpi[4] ?>;"></div>
            <div class="ms-kpi-icon" style="color: <?= $kpi[4] ?>;"><i class="bi <?= $kpi[3] ?>"></i></div>
            <div class="ms-kpi-value"><?= $fmt($kpi[2]) ?></div>
            <div class="ms-kpi-label"><?= $kpi[1] ?></div>
            <div class="ms-kpi-change flat" data-metric="<?= $kpi[0] ?>"><i class="bi bi-dash"></i> —</div>
            <div class="ms-kpi-prev" data-metric-prev="<?= $kpi[0] ?>">vs período anterior</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ CRESCIMENTO DE SEGUIDORES ============ -->
    <?php if ($totalFollowers > 0): ?>
    <div class="ms-section">
        <div class="ms-section-title"><i class="bi bi-trending-up"></i> Como estamos crescendo</div>
        <div class="ms-growth-cards">
            <div class="ms-growth-card main">
                <div class="ms-gc-val"><?= $fmt($totalFollowers) ?></div>
                <div class="ms-gc-label">Seguidores hoje</div>
            </div>
            <?php
            $glabels = ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 3 meses'];
            foreach ($glabels as $gk => $gl):
                $gv = $followerGrowthAgg[$gk];
                $sign = $gv > 0 ? '+' : '';
                $cls = $gv > 0 ? 'pos' : ($gv < 0 ? 'neg' : '');
            ?>
            <div class="ms-growth-card">
                <div class="ms-gc-diff <?= $cls ?>"><?= $sign . $fmt($gv) ?></div>
                <div class="ms-gc-label"><?= $gl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ GRÁFICO + TOP POSTS ============ -->
    <div class="ms-chart-row">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="ms-box">
                    <div class="ms-box-header">
                        <h5><i class="bi bi-activity"></i> Desempenho ao longo do tempo</h5>
                        <select id="metric-filter" class="form-select" style="width:180px;font-size:0.85rem;" onchange="loadMetric()">
                            <option value="reactions">Curtidas</option>
                            <option value="comments">Comentários</option>
                            <option value="views">Visualizações</option>
                            <option value="impressions">Impressões</option>
                            <option value="reach">Alcance</option>
                            <option value="engagementRate">Engajamento</option>
                            <option value="shares">Compartilhamentos</option>
                        </select>
                    </div>
                    <div class="ms-box-body" style="flex:1;display:flex;align-items:stretch;">
                        <div style="position:relative;width:100%;min-height:280px;">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="ms-box">
                    <div class="ms-box-header">
                        <h5><i class="bi bi-trophy"></i> Melhores publicações</h5>
                    </div>
                    <div class="ms-top-list" id="top-posts">
                        <div class="text-muted text-center py-4">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ NOSSAS REDES ============ -->
    <div class="ms-section">
        <div class="ms-section-title"><i class="bi bi-globe2"></i> Nossas redes</div>
        <div class="ms-networks-grid">
            <?php
            // API direta primeiro
            foreach ($socialAccounts as $acc):
                $pi = $providerInfo[$acc['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
                $svAcc = fn($k) => ($acc[$k] !== null && $acc[$k] !== '') ? (float)$acc[$k] : 0;
                $accPosts = $socialPostsByAccount[$acc['id']] ?? [];
            ?>
            <div class="ms-net-card">
                <div class="ms-net-head">
                    <div class="ms-net-avatar" style="background: <?= $pi[2] ?>12; color: <?= $pi[2] ?>;">
                        <?php if (!empty($acc['avatar'])): ?><img src="<?= escape($acc['avatar']) ?>" alt=""><?php else: ?><i class="bi <?= $pi[1] ?>"></i><?php endif; ?>
                        <span class="ms-net-badge" style="background: <?= $pi[2] ?>;"><i class="bi <?= $pi[1] ?>"></i></span>
                    </div>
                    <div>
                        <div class="ms-net-name"><?= escape($acc['display_name'] ?: $acc['external_id']) ?></div>
                        <div class="ms-net-handle"><i class="bi <?= $pi[1] ?>" style="color:<?= $pi[2] ?>;"></i> <?php if (!empty($acc['username'])): ?>@<?= escape($acc['username']) ?><?php else: ?><?= $pi[0] ?><?php endif; ?></div>
                    </div>
                </div>

                <!-- Métrica principal -->
                <div class="ms-net-highlight">
                    <div>
                        <div class="ms-net-highlight-val"><?= $fmt($svAcc('followers')) ?></div>
                        <div class="ms-net-highlight-lbl">Seguidores</div>
                    </div>
                    <?php if ($acc['provider'] === 'meta_instagram' && $svAcc('media_count') > 0): ?>
                    <div style="margin-left:auto;text-align:center;">
                        <div style="font-size:1.3rem;font-weight:700;color:#2b3440;"><?= $fmt($svAcc('media_count')) ?></div>
                        <div style="font-size:0.72rem;color:#8a929b;">Publicações</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Métricas secundárias -->
                <div class="ms-net-stats">
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-heart-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('total_likes')) ?></div><div class="ms-ns-lbl">Curtidas</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-chat-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('total_comments')) ?></div><div class="ms-ns-lbl">Comentários</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-share-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('total_shares')) ?></div><div class="ms-ns-lbl">Compart.</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-activity"></i></span>
                        <div><div class="ms-ns-val"><?= number_format($svAcc('engagement_rate'), 1, ',', '.') ?>%</div><div class="ms-ns-lbl">Engajamento</div></div>
                    </div>
                    <?php if ($acc['provider'] === 'meta_instagram'): ?>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-eye-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('impressions')) ?></div><div class="ms-ns-lbl">Impressões</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-megaphone-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('reach')) ?></div><div class="ms-ns-lbl">Alcance</div></div>
                    </div>
                    <?php else: ?>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-eye-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($svAcc('impressions')) ?></div><div class="ms-ns-lbl">Impressões</div></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($accPosts)): ?>
                <div class="ms-net-posts">
                    <?php foreach (array_slice($accPosts, 0, 8) as $p): ?>
                    <a class="ms-net-post" <?= !empty($p['permalink']) ? 'href="' . escape($p['permalink']) . '" target="_blank"' : '' ?>>
                        <?php if (!empty($p['thumbnail'])): ?><img src="<?= escape($p['thumbnail']) ?>" alt=""><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php
            // Buffer channels (não duplicados)
            foreach ($channels as $ch):
                $svc = strtolower($ch['service'] ?? '');
                $ni = $networkInfo[$svc] ?? ['Outro', 'bi-globe', '#607d8b'];
                $cm = $channelMetrics[$ch['channel_id']] ?? [];
                $getM = function($t) use ($cm) { return isset($cm[$t]) ? (float)$cm[$t]['metric_value'] : 0; };
            ?>
            <div class="ms-net-card">
                <div class="ms-net-head">
                    <div class="ms-net-avatar" style="background: <?= $ni[2] ?>12; color: <?= $ni[2] ?>;">
                        <?php if (!empty($ch['avatar'])): ?><img src="<?= escape($ch['avatar']) ?>" alt=""><?php else: ?><i class="bi <?= $ni[1] ?>"></i><?php endif; ?>
                        <span class="ms-net-badge" style="background: <?= $ni[2] ?>;"><i class="bi <?= $ni[1] ?>"></i></span>
                    </div>
                    <div>
                        <div class="ms-net-name"><?= escape($ch['name']) ?></div>
                        <div class="ms-net-handle"><i class="bi <?= $ni[1] ?>" style="color:<?= $ni[2] ?>;"></i> <?php if (!empty($ch['username'])): ?>@<?= escape($ch['username']) ?><?php else: ?><?= $ni[0] ?><?php endif; ?></div>
                    </div>
                </div>

                <!-- Métrica principal -->
                <div class="ms-net-highlight">
                    <div>
                        <div class="ms-net-highlight-val"><?= $fmt($getM('postCount')) ?></div>
                        <div class="ms-net-highlight-lbl">Publicações</div>
                    </div>
                    <div style="margin-left:auto;text-align:center;">
                        <div style="font-size:1.3rem;font-weight:700;color:#2b3440;"><?= number_format($getM('engagementRate'), 1, ',', '.') ?>%</div>
                        <div style="font-size:0.72rem;color:#8a929b;">Engajamento</div>
                    </div>
                </div>

                <!-- Métricas secundárias -->
                <div class="ms-net-stats">
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-heart-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('reactions')) ?></div><div class="ms-ns-lbl">Curtidas</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-chat-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('comments')) ?></div><div class="ms-ns-lbl">Comentários</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-share-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('shares')) ?></div><div class="ms-ns-lbl">Compart.</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-eye-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('impressions')) ?></div><div class="ms-ns-lbl">Impressões</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-megaphone-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('reach')) ?></div><div class="ms-ns-lbl">Alcance</div></div>
                    </div>
                    <div class="ms-net-stat">
                        <span class="ms-ns-icon"><i class="bi bi-play-circle-fill"></i></span>
                        <div><div class="ms-ns-val"><?= $fmt($getM('views')) ?></div><div class="ms-ns-lbl">Visualizações</div></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($channels) && empty($socialAccounts)): ?>
    <div class="ms-box" style="padding:40px;text-align:center;">
        <i class="bi bi-plug" style="font-size:2.5rem;color:#ccc;"></i>
        <p style="font-size:1rem;color:#666;margin-top:12px;">Nenhuma rede social conectada ainda.</p>
        <?php if ($isAdmin): ?><a href="<?= baseUrl('settings') ?>" class="btn btn-primary mt-2">Conectar redes</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<?php require APP_PATH . '/views/buffer/_social_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const BASE = '<?= baseUrl("") ?>';
let lineChart = null;

function setPeriod(days) {
    const end = new Date(), start = new Date();
    start.setDate(end.getDate() - days);
    document.getElementById('period-start').value = start.toISOString().slice(0,10);
    document.getElementById('period-end').value = end.toISOString().slice(0,10);
    applyFilter();
}
function applyFilter() {
    const p = new URLSearchParams();
    const s = document.getElementById('period-start').value, e = document.getElementById('period-end').value;
    if (s) p.set('start', s); if (e) p.set('end', e);
    window.location = BASE + 'buffer/dashboard?' + p.toString();
}

function loadComparison() {
    const s = document.getElementById('period-start').value, e = document.getElementById('period-end').value;
    fetch(BASE + 'buffer/comparison?start=' + s + '&end=' + e).then(r=>r.json()).then(data => {
        if (!data.comparison) return;
        Object.keys(data.comparison).forEach(m => {
            const el = document.querySelector('[data-metric="'+m+'"]');
            const prevEl = document.querySelector('[data-metric-prev="'+m+'"]');
            if (!el) return;
            const d = data.comparison[m];
            const cls = d.pct > 0 ? 'up' : (d.pct < 0 ? 'down' : 'flat');
            const arrow = d.pct > 0 ? 'bi-arrow-up-short' : (d.pct < 0 ? 'bi-arrow-down-short' : 'bi-dash');
            el.className = 'ms-kpi-change ' + cls;
            el.innerHTML = '<i class="bi '+arrow+'"></i> '+(d.pct>=0?'+':'')+d.pct.toFixed(1)+'%';
            if (prevEl) prevEl.textContent = 'Antes: ' + Math.round(d.previous).toLocaleString('pt-BR');
        });
    }).catch(()=>{});
}

function loadMetric() {
    const metric = document.getElementById('metric-filter').value;
    const s = document.getElementById('period-start').value, e = document.getElementById('period-end').value;
    fetch(BASE+'buffer/metrics?metric='+metric+'&start='+s+'&end='+e).then(r=>r.json()).then(data=>{
        renderChart(data.timeline||[]); renderTop(data.top||[]);
    });
}
function renderChart(timeline) {
    const labels = timeline.map(t=>{const d=new Date(((t.moment||t.day)||'').replace(' ','T'));return isNaN(d)?'':d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});});
    const values = timeline.map(t=>parseFloat(t.total));
    if (lineChart) lineChart.destroy();
    const ctx = document.getElementById('lineChart').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,280); grad.addColorStop(0,'rgba(0,191,166,0.18)'); grad.addColorStop(1,'rgba(0,191,166,0)');
    lineChart = new Chart(document.getElementById('lineChart'),{type:'line',data:{labels,datasets:[{data:values,borderColor:'#00BFA6',borderWidth:2.5,backgroundColor:grad,fill:true,tension:0.4,pointRadius:0,pointHoverRadius:5,pointHoverBackgroundColor:'#00BFA6'}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:'#1a1a2e',padding:12,displayColors:false,titleFont:{size:13},bodyFont:{size:14,weight:'600'}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'},ticks:{color:'#9aa',font:{size:12}}},x:{grid:{display:false},ticks:{color:'#9aa',font:{size:11},maxRotation:0,autoSkip:true,maxTicksLimit:10}}}}});
}
function renderTop(top) {
    const box = document.getElementById('top-posts');
    if (!top.length){box.innerHTML='<div class="text-muted text-center py-4" style="font-size:0.9rem;">Sem dados nesse período.</div>';return;}
    box.innerHTML = top.slice(0,8).map((p,i)=>{
        const val = p.metric_unit==='percentage'?parseFloat(p.metric_value).toFixed(1)+'%':Math.round(p.metric_value).toLocaleString('pt-BR');
        const txt = (p.text||'(sem texto)').slice(0,60);
        const link = p.external_link?'href="'+p.external_link+'" target="_blank"':'';
        const thumb = p.thumbnail?'<img src="'+p.thumbnail+'" alt="">':'<i class="bi bi-image" style="font-size:1.2rem;"></i>';
        return '<a '+link+' class="ms-top-item"><div class="ms-top-thumb">'+thumb+'</div><div class="ms-top-info"><div class="ms-top-text">'+(i+1)+'. '+esc(txt)+'</div><div class="ms-top-meta">'+(p.service||'')+'</div></div><span class="ms-top-val">'+val+'</span></a>';
    }).join('');
}
function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

// Admin actions
function syncChannels(b){act(b,'buffer/syncChannels');}
function syncMetrics(b){const fd=new FormData();fd.append('start',document.getElementById('period-start').value);fd.append('end',document.getElementById('period-end').value);const o=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm"></span> Atualizando...';fetch(BASE+'buffer/syncMetrics',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(()=>{location.reload();}).catch(()=>{b.disabled=false;b.innerHTML=o;});}
function act(b,url){const o=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm"></span>';fetch(BASE+url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(()=>{location.reload();}).catch(()=>{b.disabled=false;b.innerHTML=o;});}

document.addEventListener('DOMContentLoaded',()=>{loadMetric();loadComparison();});
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
