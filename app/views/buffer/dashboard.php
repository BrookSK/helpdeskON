<?php $pageTitle = 'Métricas Sociais'; $currentPage = 'buffer_dashboard'; ?>
<?php require APP_PATH . '/views/layouts/header.php'; ?>
<?php require APP_PATH . '/views/layouts/sidebar.php'; ?>

<?php
// Helpers
$fmt = fn($v) => number_format((float)$v, 0, ',', '.');

// Seguidores consolidados
$totalFollowers = 0;
foreach ($socialAccounts as $sa) $totalFollowers += (int)($sa['followers'] ?? 0);

// Crescimento consolidado
$followerGrowthAgg = ['7d' => 0, '30d' => 0, '90d' => 0];
foreach ($socialFollowersGrowth as $g) {
    foreach (['7d', '30d', '90d'] as $p) {
        if (isset($g[$p]['diff'])) $followerGrowthAgg[$p] += $g[$p]['diff'];
    }
}

// Metadados de redes
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
$providerInfo = [
    'meta_instagram' => ['Instagram', 'bi-instagram', '#E1306C'],
    'facebook_page'  => ['Facebook', 'bi-facebook', '#1877F2'],
    'linkedin_org'   => ['LinkedIn', 'bi-linkedin', '#0A66C2'],
];
?>

<style>
/* ===== Métricas Sociais v3 — Clean & Intuitive ===== */
.ms-page { padding: 0; }
.ms-header { margin-bottom: 20px; }
.ms-header h4 { font-size: 1.2rem; font-weight: 700; color: #1a1a2e; margin: 0; }
.ms-header p { font-size: 0.8rem; color: #8a929b; margin: 4px 0 0; }

/* Admin actions - discrete */
.ms-admin-bar { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
.ms-admin-bar .btn { font-size: 0.7rem; }

/* Period selector */
.ms-period { background: #fff; border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.ms-period-label { font-size: 0.75rem; font-weight: 600; color: #555; }
.ms-presets { display: flex; gap: 4px; }
.ms-presets .btn { font-size: 0.72rem; padding: 5px 14px; border-radius: 20px; font-weight: 500; }
.ms-presets .btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }

/* Summary cards */
.ms-summary { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 12px; margin-bottom: 24px; }
.ms-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); border-left: 4px solid transparent; }
.ms-card-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 8px; }
.ms-card-value { font-size: 1.6rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
.ms-card-label { font-size: 0.72rem; color: #8a929b; margin-top: 4px; }
.ms-card-change { display: inline-flex; align-items: center; gap: 2px; font-size: 0.68rem; font-weight: 600; margin-top: 6px; padding: 2px 8px; border-radius: 10px; }
.ms-card-change.up { background: #ecfdf5; color: #059669; }
.ms-card-change.down { background: #fef2f2; color: #dc2626; }
.ms-card-change.neutral { background: #f3f4f6; color: #6b7280; }
.ms-card-prev { font-size: 0.6rem; color: #aab; margin-top: 3px; }

/* Growth section */
.ms-growth { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 24px; }
.ms-growth-title { font-size: 0.88rem; font-weight: 700; color: #1a1a2e; margin-bottom: 14px; }
.ms-growth-title i { color: var(--primary); }
.ms-growth-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.ms-growth-item { text-align: center; padding: 12px 16px; border-radius: 10px; background: #f8faf9; min-width: 90px; }
.ms-growth-item.highlight { background: linear-gradient(135deg, #e0f7f4, #b2f2e8); }
.ms-gi-val { font-size: 1.2rem; font-weight: 800; color: #1a1a2e; }
.ms-gi-diff { font-size: 0.72rem; font-weight: 700; }
.ms-gi-diff.pos { color: #059669; }
.ms-gi-diff.neg { color: #dc2626; }
.ms-gi-label { font-size: 0.62rem; color: #8a929b; margin-top: 3px; }

/* Chart section */
.ms-chart-section { margin-bottom: 24px; }
.ms-chart-box { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden; }
.ms-chart-header { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.ms-chart-header h6 { font-size: 0.85rem; font-weight: 700; color: #2b3440; margin: 0; }
.ms-chart-body { padding: 16px; }

/* Top posts */
.ms-top-box { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden; }
.ms-top-header { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
.ms-top-header h6 { font-size: 0.85rem; font-weight: 700; color: #2b3440; margin: 0; }
.ms-top-list { max-height: 360px; overflow-y: auto; }
.ms-top-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f8f8f8; text-decoration: none; color: inherit; transition: background .1s; }
.ms-top-item:hover { background: #fafbfc; }
.ms-top-thumb { width: 46px; height: 46px; border-radius: 8px; object-fit: cover; background: #eef1f4; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #ccc; overflow: hidden; }
.ms-top-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ms-top-info { flex: 1; min-width: 0; }
.ms-top-text { font-size: 0.78rem; font-weight: 500; color: #333; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ms-top-meta { font-size: 0.65rem; color: #8a929b; margin-top: 2px; }
.ms-top-badge { font-size: 0.72rem; font-weight: 700; color: var(--primary); flex-shrink: 0; }

/* Accounts section */
.ms-accounts { margin-bottom: 24px; }
.ms-accounts-title { font-size: 0.92rem; font-weight: 700; color: #1a1a2e; margin-bottom: 14px; }
.ms-accounts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
.ms-acc-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); border: 1px solid #f0f2f4; }
.ms-acc-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.ms-acc-avatar { width: 42px; height: 42px; border-radius: 10px; overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.ms-acc-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.ms-acc-network { position: absolute; bottom: -2px; right: -2px; width: 18px; height: 18px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.5rem; border: 2px solid #fff; }
.ms-acc-name { font-size: 0.85rem; font-weight: 600; color: #2b3440; }
.ms-acc-handle { font-size: 0.7rem; color: #8a929b; }
.ms-acc-metrics { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 6px; }
.ms-acc-metric { text-align: center; padding: 8px 4px; background: #f8faf9; border-radius: 8px; }
.ms-am-val { font-size: 0.95rem; font-weight: 700; color: #2b3440; }
.ms-am-lbl { font-size: 0.58rem; color: #8a929b; margin-top: 1px; }

/* Posts thumbnails */
.ms-acc-posts { display: flex; gap: 6px; margin-top: 10px; overflow-x: auto; padding-bottom: 4px; }
.ms-acc-post-thumb { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #eef1f4; position: relative; }
.ms-acc-post-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Token alert */
.ms-alert { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; font-size: 0.78rem; }
.ms-alert i { color: #d97706; font-size: 1.1rem; flex-shrink: 0; }

/* Responsive */
@media (max-width: 768px) {
    .ms-summary { grid-template-columns: repeat(2, 1fr); }
    .ms-accounts-grid { grid-template-columns: 1fr; }
    .ms-growth-grid { flex-direction: column; }
}
</style>

<div class="main-content ms-page">
    <!-- Header -->
    <div class="ms-header">
        <h4><i class="bi bi-bar-chart-line"></i> Métricas Sociais</h4>
        <p>Acompanhe o desempenho de todas as redes sociais em um só lugar</p>
    </div>

    <!-- Admin actions (só pra admin, discreto) -->
    <?php if ($isAdmin): ?>
    <div class="ms-admin-bar">
        <button class="btn btn-outline-secondary btn-sm" onclick="syncChannels(this)"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
        <button class="btn btn-primary btn-sm" onclick="syncMetrics(this)"><i class="bi bi-cloud-download"></i> Atualizar métricas</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="importMeta(this)"><i class="bi bi-download"></i> Importar Meta</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="openLinkedinModal()"><i class="bi bi-linkedin"></i> Add LinkedIn</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="syncSocial(this)"><i class="bi bi-arrow-repeat"></i> Atualizar redes</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="snapshotFollowers(this)"><i class="bi bi-camera"></i> Snapshot</button>
    </div>
    <?php endif; ?>

    <!-- Token alert -->
    <?php $metaTokenExpired = $metaTokenExpired ?? false; $linkedinTokenExpired = $linkedinTokenExpired ?? false; ?>
    <?php if ($metaTokenExpired || $linkedinTokenExpired): ?>
    <div class="ms-alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <?php if ($metaTokenExpired && $linkedinTokenExpired): ?>Os tokens da Meta e LinkedIn expiraram.
            <?php elseif ($metaTokenExpired): ?>O token da Meta expirou — seguidores do Instagram/Facebook não atualizam.
            <?php else: ?>O token do LinkedIn expirou — seguidores do LinkedIn não atualizam.<?php endif; ?>
        </span>
        <?php if ($isAdmin): ?><a href="<?= baseUrl('settings') ?>" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-key"></i> Reconectar</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Período -->
    <div class="ms-period">
        <span class="ms-period-label">Período:</span>
        <div class="ms-presets">
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(7)">7 dias</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(30)">30 dias</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(90)">3 meses</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(180)">6 meses</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="setPeriod(365)">1 ano</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <input type="date" id="period-start" class="form-control form-control-sm" style="width:135px;" value="<?= escape($periodStart) ?>">
            <span class="text-muted">—</span>
            <input type="date" id="period-end" class="form-control form-control-sm" style="width:135px;" value="<?= escape($periodEnd) ?>">
        </div>
        <button class="btn btn-sm btn-primary" onclick="applyFilter()"><i class="bi bi-check-lg"></i> Aplicar</button>
    </div>

    <!-- ==================== RESUMO GERAL ==================== -->
    <div class="ms-summary" id="summary-cards">
        <?php
        $cards = [
            ['followers', 'Seguidores', $totalFollowers, 'bi-people-fill', '#0A66C2', '#0A66C2'],
            ['reactions', 'Curtidas', $totals['reactions'], 'bi-heart-fill', '#E1306C', '#E1306C'],
            ['comments', 'Comentários', $totals['comments'], 'bi-chat-dots-fill', '#3f51b5', '#3f51b5'],
            ['views', 'Visualizações', $totals['views'], 'bi-play-circle-fill', '#00897b', '#00897b'],
            ['impressions', 'Impressões', $totals['impressions'], 'bi-eye-fill', '#ff9800', '#ff9800'],
            ['reach', 'Alcance', $totals['reach'], 'bi-megaphone-fill', '#9c27b0', '#9c27b0'],
        ];
        foreach ($cards as $c):
        ?>
        <div class="ms-card" style="border-left-color: <?= $c[5] ?>;">
            <div class="ms-card-icon" style="background: <?= $c[4] ?>15; color: <?= $c[4] ?>;">
                <i class="bi <?= $c[3] ?>"></i>
            </div>
            <div class="ms-card-value"><?= $fmt($c[2]) ?></div>
            <div class="ms-card-label"><?= $c[1] ?></div>
            <div class="ms-card-change neutral" data-metric="<?= $c[0] ?>"><i class="bi bi-dash"></i> —</div>
            <div class="ms-card-prev" data-metric-prev="<?= $c[0] ?>">vs período anterior</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ==================== CRESCIMENTO DE SEGUIDORES ==================== -->
    <?php if ($totalFollowers > 0): ?>
    <div class="ms-growth">
        <div class="ms-growth-title"><i class="bi bi-trending-up"></i> Como estamos crescendo</div>
        <div class="ms-growth-grid">
            <div class="ms-growth-item highlight">
                <div class="ms-gi-val"><?= $fmt($totalFollowers) ?></div>
                <div class="ms-gi-label">Seguidores hoje</div>
            </div>
            <?php
            $glabels = ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 3 meses'];
            foreach ($glabels as $gk => $gl):
                $gv = $followerGrowthAgg[$gk];
                $cls = $gv > 0 ? 'pos' : ($gv < 0 ? 'neg' : '');
                $sign = $gv > 0 ? '+' : '';
            ?>
            <div class="ms-growth-item">
                <div class="ms-gi-diff <?= $cls ?>"><?= $sign . $fmt($gv) ?></div>
                <div class="ms-gi-label"><?= $gl ?></div>
            </div>
            <?php endforeach; ?>

            <?php // Breakdown por conta
            foreach ($socialAccounts as $sa):
                $pi = $providerInfo[$sa['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
                $gd = $socialFollowersGrowth[$sa['id']] ?? null;
                if (!$gd || $gd['current'] === null) continue;
                $d30 = $gd['30d']['diff'] ?? null;
            ?>
            <div class="ms-growth-item">
                <div class="ms-gi-val" style="font-size:1rem;"><?= $fmt($gd['current']) ?></div>
                <?php if ($d30 !== null): ?>
                <div class="ms-gi-diff <?= $d30 >= 0 ? 'pos' : 'neg' ?>"><?= ($d30 >= 0 ? '+' : '') . $fmt($d30) ?></div>
                <?php endif; ?>
                <div class="ms-gi-label"><i class="bi <?= $pi[1] ?>" style="color:<?= $pi[2] ?>;"></i> <?= escape($sa['display_name'] ?: '') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== DESEMPENHO DOS POSTS ==================== -->
    <div class="ms-chart-section">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="ms-chart-box">
                    <div class="ms-chart-header">
                        <h6><i class="bi bi-activity"></i> Desempenho ao longo do tempo</h6>
                        <select id="metric-filter" class="form-select form-select-sm" style="width:180px;" onchange="loadMetric()">
                            <option value="reactions">Curtidas</option>
                            <option value="comments">Comentários</option>
                            <option value="views">Visualizações</option>
                            <option value="impressions">Impressões</option>
                            <option value="reach">Alcance</option>
                            <option value="engagementRate">Engajamento %</option>
                            <option value="shares">Compartilhamentos</option>
                        </select>
                    </div>
                    <div class="ms-chart-body">
                        <div style="position:relative;height:260px;">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="ms-top-box">
                    <div class="ms-top-header">
                        <h6><i class="bi bi-trophy"></i> Melhores publicações</h6>
                    </div>
                    <div class="ms-top-list" id="top-posts">
                        <div class="text-muted small text-center py-4">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== NOSSAS REDES ==================== -->
    <div class="ms-accounts">
        <div class="ms-accounts-title"><i class="bi bi-globe2"></i> Nossas redes</div>
        <div class="ms-accounts-grid">
            <?php
            // ===== Contas diretas PRIMEIRO (prioridade API) =====
            foreach ($socialAccounts as $acc):
                $pi = $providerInfo[$acc['provider']] ?? ['Rede', 'bi-globe', '#607d8b'];
                $svAcc = fn($k) => ($acc[$k] !== null && $acc[$k] !== '') ? (float)$acc[$k] : 0;
                $accPosts = $socialPostsByAccount[$acc['id']] ?? [];
            ?>
            <div class="ms-acc-card">
                <div class="ms-acc-header">
                    <div class="ms-acc-avatar" style="background: <?= $pi[2] ?>15; color: <?= $pi[2] ?>;">
                        <?php if (!empty($acc['avatar'])): ?>
                        <img src="<?= escape($acc['avatar']) ?>" alt="">
                        <?php else: ?><i class="bi <?= $pi[1] ?>"></i><?php endif; ?>
                        <span class="ms-acc-network" style="background: <?= $pi[2] ?>;"><i class="bi <?= $pi[1] ?>"></i></span>
                    </div>
                    <div>
                        <div class="ms-acc-name"><?= escape($acc['display_name'] ?: $acc['external_id']) ?></div>
                        <div class="ms-acc-handle">
                            <i class="bi <?= $pi[1] ?>" style="color:<?= $pi[2] ?>;"></i>
                            <?php if (!empty($acc['username'])): ?>@<?= escape($acc['username']) ?><?php else: ?><?= $pi[0] ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ms-acc-metrics">
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('followers')) ?></div><div class="ms-am-lbl">Seguidores</div></div>
                    <?php if ($acc['provider'] === 'meta_instagram'): ?>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('follows')) ?></div><div class="ms-am-lbl">Seguindo</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('media_count')) ?></div><div class="ms-am-lbl">Posts</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('reach')) ?></div><div class="ms-am-lbl">Alcance</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('impressions')) ?></div><div class="ms-am-lbl">Impressões</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('profile_views')) ?></div><div class="ms-am-lbl">Visitas</div></div>
                    <?php endif; ?>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('total_likes')) ?></div><div class="ms-am-lbl">Curtidas</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('total_comments')) ?></div><div class="ms-am-lbl">Comentários</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($svAcc('total_shares')) ?></div><div class="ms-am-lbl">Compart.</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= number_format($svAcc('engagement_rate'), 1, ',', '.') ?>%</div><div class="ms-am-lbl">Engaj.</div></div>
                </div>
                <?php if (!empty($accPosts)): ?>
                <div class="ms-acc-posts">
                    <?php foreach (array_slice($accPosts, 0, 8) as $p): ?>
                    <a class="ms-acc-post-thumb" <?= !empty($p['permalink']) ? 'href="' . escape($p['permalink']) . '" target="_blank"' : '' ?>>
                        <?php if (!empty($p['thumbnail'])): ?><img src="<?= escape($p['thumbnail']) ?>" alt=""><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php
            // ===== Canais Buffer (só os que não são duplicados) =====
            foreach ($channels as $ch):
                $svc = strtolower($ch['service'] ?? '');
                $ni = $networkInfo[$svc] ?? ['Outro', 'bi-globe', '#607d8b'];
                $cm = $channelMetrics[$ch['channel_id']] ?? [];
                $getM = function($t) use ($cm) { return isset($cm[$t]) ? (float)$cm[$t]['metric_value'] : 0; };
            ?>
            <div class="ms-acc-card">
                <div class="ms-acc-header">
                    <div class="ms-acc-avatar" style="background: <?= $ni[2] ?>15; color: <?= $ni[2] ?>;">
                        <?php if (!empty($ch['avatar'])): ?>
                        <img src="<?= escape($ch['avatar']) ?>" alt="">
                        <?php else: ?><i class="bi <?= $ni[1] ?>"></i><?php endif; ?>
                        <span class="ms-acc-network" style="background: <?= $ni[2] ?>;"><i class="bi <?= $ni[1] ?>"></i></span>
                    </div>
                    <div>
                        <div class="ms-acc-name"><?= escape($ch['name']) ?></div>
                        <div class="ms-acc-handle">
                            <i class="bi <?= $ni[1] ?>" style="color:<?= $ni[2] ?>;"></i>
                            <?php if (!empty($ch['username'])): ?>@<?= escape($ch['username']) ?><?php else: ?><?= $ni[0] ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="ms-acc-metrics">
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('postCount')) ?></div><div class="ms-am-lbl">Posts</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('reactions')) ?></div><div class="ms-am-lbl">Curtidas</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('comments')) ?></div><div class="ms-am-lbl">Comentários</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('shares')) ?></div><div class="ms-am-lbl">Compart.</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('impressions')) ?></div><div class="ms-am-lbl">Impressões</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('reach')) ?></div><div class="ms-am-lbl">Alcance</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= $fmt($getM('views')) ?></div><div class="ms-am-lbl">Visualiz.</div></div>
                    <div class="ms-acc-metric"><div class="ms-am-val"><?= number_format($getM('engagementRate'), 1, ',', '.') ?>%</div><div class="ms-am-lbl">Engaj.</div></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($channels) && empty($socialAccounts)): ?>
    <div class="alert alert-light border small text-center py-4">
        <i class="bi bi-plug"></i> Nenhuma rede social conectada ainda.
        <?php if ($isAdmin): ?>Acesse <a href="<?= baseUrl('settings') ?>">Configurações</a> para conectar suas redes.<?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<?php require APP_PATH . '/views/buffer/_social_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const BASE = '<?= baseUrl("") ?>';
let lineChart = null;

// ===== Período =====
function setPeriod(days) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days);
    document.getElementById('period-start').value = start.toISOString().slice(0,10);
    document.getElementById('period-end').value = end.toISOString().slice(0,10);
    applyFilter();
}

function applyFilter() {
    const p = new URLSearchParams();
    const s = document.getElementById('period-start').value;
    const e = document.getElementById('period-end').value;
    if (s) p.set('start', s);
    if (e) p.set('end', e);
    window.location = BASE + 'buffer/dashboard?' + p.toString();
}

// ===== Comparação =====
function loadComparison() {
    const s = document.getElementById('period-start').value;
    const e = document.getElementById('period-end').value;
    fetch(BASE + 'buffer/comparison?start=' + s + '&end=' + e)
        .then(r => r.json())
        .then(data => {
            if (!data.comparison) return;
            Object.keys(data.comparison).forEach(m => {
                const el = document.querySelector('[data-metric="' + m + '"]');
                const prevEl = document.querySelector('[data-metric-prev="' + m + '"]');
                if (!el) return;
                const d = data.comparison[m];
                const cls = d.pct > 0 ? 'up' : (d.pct < 0 ? 'down' : 'neutral');
                const arrow = d.pct > 0 ? 'bi-arrow-up-short' : (d.pct < 0 ? 'bi-arrow-down-short' : 'bi-dash');
                const sign = d.pct >= 0 ? '+' : '';
                el.className = 'ms-card-change ' + cls;
                el.innerHTML = '<i class="bi ' + arrow + '"></i> ' + sign + d.pct.toFixed(1) + '%';
                if (prevEl) prevEl.textContent = 'Antes: ' + Math.round(d.previous).toLocaleString('pt-BR');
            });
        }).catch(() => {});
}

// ===== Gráfico =====
function loadMetric() {
    const metric = document.getElementById('metric-filter').value;
    const s = document.getElementById('period-start').value;
    const e = document.getElementById('period-end').value;
    fetch(BASE + 'buffer/metrics?metric=' + metric + '&start=' + s + '&end=' + e)
        .then(r => r.json())
        .then(data => {
            renderChart(data.timeline || [], metric);
            renderTop(data.top || []);
        });
}

function renderChart(timeline, metric) {
    const labels = timeline.map(t => {
        const d = new Date(((t.moment||t.day)||'').replace(' ','T'));
        return isNaN(d) ? '' : d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});
    });
    const values = timeline.map(t => parseFloat(t.total));
    if (lineChart) lineChart.destroy();
    const ctx = document.getElementById('lineChart').getContext('2d');
    const grad = ctx.createLinearGradient(0,0,0,260);
    grad.addColorStop(0,'rgba(0,191,166,0.18)');
    grad.addColorStop(1,'rgba(0,191,166,0)');

    lineChart = new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: { labels, datasets: [{ data: values, borderColor:'#00BFA6', borderWidth:2.5, backgroundColor:grad, fill:true, tension:0.4, pointRadius:0, pointHoverRadius:5, pointHoverBackgroundColor:'#00BFA6' }] },
        options: {
            responsive:true, maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{legend:{display:false},tooltip:{backgroundColor:'#1a1a2e',padding:10,displayColors:false}},
            scales:{
                y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'},ticks:{color:'#9aa',font:{size:11}}},
                x:{grid:{display:false},ticks:{color:'#9aa',font:{size:10},maxRotation:0,autoSkip:true,maxTicksLimit:10}}
            }
        }
    });
}

function renderTop(top) {
    const box = document.getElementById('top-posts');
    if (!top.length) { box.innerHTML = '<div class="text-muted small text-center py-4">Sem dados nesse período.</div>'; return; }
    box.innerHTML = top.slice(0,10).map((p,i) => {
        const val = p.metric_unit==='percentage' ? parseFloat(p.metric_value).toFixed(1)+'%' : Math.round(p.metric_value).toLocaleString('pt-BR');
        const txt = (p.text||'(sem texto)').slice(0,65);
        const link = p.external_link ? 'href="'+p.external_link+'" target="_blank"' : '';
        const thumb = p.thumbnail ? '<img src="'+p.thumbnail+'" alt="">' : '<i class="bi bi-image"></i>';
        return '<a '+link+' class="ms-top-item"><div class="ms-top-thumb">'+thumb+'</div><div class="ms-top-info"><div class="ms-top-text">'+(i+1)+'. '+esc(txt)+'</div><div class="ms-top-meta">'+(p.service||'')+'</div></div><span class="ms-top-badge">'+val+'</span></a>';
    }).join('');
}

function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

// ===== Ações admin =====
function syncChannels(b){act(b,'buffer/syncChannels');}
function syncMetrics(b){
    const fd=new FormData();fd.append('start',document.getElementById('period-start').value);fd.append('end',document.getElementById('period-end').value);
    const o=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
    fetch(BASE+'buffer/syncMetrics',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(()=>{b.disabled=false;b.innerHTML=o;location.reload();}).catch(()=>{b.disabled=false;b.innerHTML=o;});
}
function act(b,url){const o=b.innerHTML;b.disabled=true;b.innerHTML='<span class="spinner-border spinner-border-sm"></span>';fetch(BASE+url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(()=>{b.disabled=false;b.innerHTML=o;location.reload();}).catch(()=>{b.disabled=false;b.innerHTML=o;});}

// ===== Init =====
document.addEventListener('DOMContentLoaded', () => { loadMetric(); loadComparison(); });
</script>

<?php require APP_PATH . '/views/layouts/footer.php'; ?>
