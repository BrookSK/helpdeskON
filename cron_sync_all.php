<?php
/**
 * Cron Job — Sincronização completa de métricas sociais.
 *
 * Este script executa TUDO automaticamente:
 * 1) Sincroniza canais do Buffer
 * 2) Puxa métricas de posts e agregações do Buffer
 * 3) Sincroniza contas diretas (Meta Instagram, Facebook Pages, LinkedIn)
 * 4) Grava snapshot de seguidores no histórico
 *
 * Recomendado rodar a cada 30 minutos ou 1 hora via cron:
 *   */30 * * * * php /caminho/para/helpdeskON/cron_sync_all.php >> /var/log/helpdesk_sync.log 2>&1
 *
 * Também pode ser chamado via HTTP (protegido por token):
 *   GET https://seudominio.com/cron/syncAll?token=SEU_CRON_TOKEN
 */

// Evitar execução via navegador sem token
if (php_sapi_name() !== 'cli') {
    // Permite chamada HTTP com token de segurança
    $cronToken = $_GET['token'] ?? '';
    $expectedToken = null;
    // Bootstrap mínimo para ler config
    define('BASE_PATH', __DIR__);
    define('APP_PATH', BASE_PATH . '/app');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
    require_once APP_PATH . '/core/helpers.php';
    require_once APP_PATH . '/core/Database.php';
    require_once APP_PATH . '/core/Config.php';
    $expectedToken = Config::get('cron_token');
    if ($expectedToken && $cronToken !== $expectedToken) {
        http_response_code(403);
        die(json_encode(['error' => 'Token inválido']));
    }
} else {
    define('BASE_PATH', __DIR__);
    define('APP_PATH', BASE_PATH . '/app');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
    require_once APP_PATH . '/core/helpers.php';
    require_once APP_PATH . '/core/Database.php';
    require_once APP_PATH . '/core/Config.php';
}

// Autoload
spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/core/',
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

@set_time_limit(300);

$log = function($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
};

$log("=== Início da sincronização completa ===");

$errors = [];
$stats = ['buffer_channels' => 0, 'buffer_posts' => 0, 'social_updated' => 0, 'snapshots' => 0];

// ============================================================
// 1) BUFFER: Sincronizar canais e métricas
// ============================================================
// Limitar a 1x a cada 12h para não esgotar o rate limit (250 req/dia no Free)
$lastCronSync = Config::get('buffer_cron_last_sync');
$skipBuffer = ($lastCronSync && (time() - strtotime($lastCronSync)) < 43200); // 12h

if ($skipBuffer) {
    $log("1/4 — Buffer: Pulando (já sincronizado nas últimas 12h)");
} else {
$log("1/4 — Sincronizando canais do Buffer...");
$bufferAccounts = new BufferAccount();
$bufferData = new BufferData();
$allAccounts = $bufferAccounts->all(true);

$since = strtotime('-365 days');
$until = time();
$startIso = gmdate('Y-m-d\T00:00:00\Z', $since);
$endIso = gmdate('Y-m-d\T23:59:59\Z', $until);
$periodStart = date('Y-m-d', $since);
$periodEnd = date('Y-m-d', $until);

foreach ($allAccounts as $acc) {
    try {
        $api = new BufferApi($acc['api_key']);
        $orgId = $acc['organization_id'] ?: $api->getFirstOrganizationId();
        if (!$orgId) { $errors[] = 'Buffer ' . ($acc['label'] ?: 'Conta') . ': organização não encontrada'; continue; }

        // Atualiza org_id
        if ($acc['organization_id'] !== $orgId) {
            $bufferAccounts->update($acc['id'], ['organization_id' => $orgId]);
        }

        // Sync canais
        $res = $api->getChannels($orgId);
        if (empty($res['errors'])) {
            $channels = $res['data']['channels'] ?? [];
            $bufferData->syncChannels($channels, $orgId, $acc['id']);
            $stats['buffer_channels'] += count($channels);
        }

        // Sync posts com métricas
        $after = null; $pages = 0;
        do {
            $res = $api->getSentPostsWithMetrics($orgId, [], 50, $after);
            if (!empty($res['errors'])) break;
            $conn = $res['data']['posts'] ?? ['edges' => [], 'pageInfo' => []];
            foreach ($conn['edges'] as $edge) {
                $node = $edge['node'];
                $updatedAt = !empty($node['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($node['metricsUpdatedAt'])) : null;
                $thumb = null;
                foreach (($node['assets'] ?? []) as $asset) {
                    if (!empty($asset['thumbnail'])) { $thumb = $asset['thumbnail']; break; }
                    if (!empty($asset['source'])) { $thumb = $asset['source']; break; }
                }
                $bufferData->savePost([
                    'buffer_post_id' => $node['id'],
                    'channel_id' => $node['channelId'],
                    'service' => $node['channelService'] ?? null,
                    'text' => $node['text'] ?? '',
                    'status' => 'sent',
                    'due_at' => !empty($node['dueAt']) ? date('Y-m-d H:i:s', strtotime($node['dueAt'])) : null,
                    'sent_at' => !empty($node['sentAt']) ? date('Y-m-d H:i:s', strtotime($node['sentAt'])) : null,
                    'external_link' => $node['externalLink'] ?? null,
                    'thumbnail' => $thumb,
                ]);
                foreach (($node['metrics'] ?? []) as $metric) {
                    $bufferData->saveMetric($node['id'], $metric, $updatedAt);
                }
                $stats['buffer_posts']++;
            }
            $after = $conn['pageInfo']['endCursor'] ?? null;
            $hasNext = !empty($conn['pageInfo']['hasNextPage']);
            $pages++;
        } while ($hasNext && $pages < 5);

        // Agregação por canal
        $snapshotModel = new SocialSnapshot();
        foreach ($bufferData->getChannelsByAccount($acc['id'], false) as $ch) {
            $res = $api->getAggregatedMetrics($orgId, $startIso, $endIso, [$ch['channel_id']]);
            if (!empty($res['errors'])) continue;
            $agg = $res['data']['aggregatedPostMetrics'] ?? null;
            if (!$agg) continue;
            $updatedAt = !empty($agg['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($agg['metricsUpdatedAt'])) : null;
            $bufferData->clearChannelMetrics($ch['channel_id']);
            $byType = [];
            foreach (($agg['metrics'] ?? []) as $metric) {
                $bufferData->saveChannelMetric($ch['channel_id'], $metric, $periodStart, $periodEnd, $updatedAt);
                $byType[$metric['type']] = (float)($metric['value'] ?? 0);
            }
            $snapshotModel->save('buffer', strtolower($ch['service'] ?? ''), $ch['channel_id'], $ch['name'] ?? null, [
                'reach' => $byType['reach'] ?? null,
                'impressions' => $byType['impressions'] ?? null,
                'views' => $byType['views'] ?? null,
                'likes' => $byType['reactions'] ?? null,
                'comments' => $byType['comments'] ?? null,
                'shares' => $byType['shares'] ?? null,
                'saves' => $byType['saves'] ?? null,
                'posts_count' => isset($byType['postCount']) ? (int)$byType['postCount'] : null,
                'engagement_rate' => $byType['engagementRate'] ?? null,
                'extra_json' => json_encode($byType),
            ]);
        }
    } catch (\Throwable $e) {
        $errors[] = 'Buffer ' . ($acc['label'] ?: 'Conta') . ': ' . $e->getMessage();
    }
}
$log("   Buffer: {$stats['buffer_channels']} canais, {$stats['buffer_posts']} posts");
Config::set('buffer_cron_last_sync', date('Y-m-d H:i:s'));
} // end if (!$skipBuffer)

// ============================================================
// 2) SOCIAL: Sincronizar contas diretas (Meta + LinkedIn)
// ============================================================
$log("2/4 — Sincronizando contas diretas (Meta/LinkedIn)...");
$accountsModel = new SocialAccount();
$socialAccounts = $accountsModel->all(true);

$socialSince = strtotime('-30 days');
$socialUntil = time();

foreach ($socialAccounts as $acc) {
    try {
        if ($acc['provider'] === 'meta_instagram') {
            $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : new MetaApi();
            if (!$api->hasToken()) { $errors[] = 'IG ' . ($acc['display_name'] ?: $acc['external_id']) . ': Meta sem token'; continue; }

            $info = $api->getInstagramAccount($acc['external_id']);
            if (!empty($info['error'])) { $errors[] = 'IG ' . ($acc['display_name'] ?: '') . ': ' . ($info['error']['message'] ?? 'erro'); continue; }

            $ins = MetaApi::sumInsights($api->getInstagramAccountInsights($acc['external_id'], $socialSince, $socialUntil));
            $media = $api->getInstagramMedia($acc['external_id'], 25);
            $totLikes = 0; $totComments = 0; $totShares = 0; $postCount = 0;
            foreach (($media['data'] ?? []) as $m) {
                $mi = MetaApi::flattenMediaInsights($api->getInstagramMediaInsights($m['id'], $m['media_type'] ?? 'IMAGE'));
                $likes = $m['like_count'] ?? ($mi['likes'] ?? 0);
                $comments = $m['comments_count'] ?? ($mi['comments'] ?? 0);
                $shares = $mi['shares'] ?? 0;
                $totLikes += $likes; $totComments += $comments; $totShares += $shares; $postCount++;

                $accountsModel->upsertPost('meta_instagram', $m['id'], [
                    'account_id' => $acc['id'],
                    'post_type' => $m['media_type'] ?? null,
                    'caption' => $m['caption'] ?? null,
                    'permalink' => $m['permalink'] ?? null,
                    'thumbnail' => $m['thumbnail_url'] ?? ($m['media_url'] ?? null),
                    'published_at' => !empty($m['timestamp']) ? date('Y-m-d H:i:s', strtotime($m['timestamp'])) : null,
                    'likes' => $likes,
                    'comments' => $comments,
                    'shares' => $shares,
                    'saved' => $mi['saved'] ?? null,
                    'reach' => $mi['reach'] ?? null,
                    'video_views' => $mi['views'] ?? null,
                    'engagement' => $mi['total_interactions'] ?? ($likes + $comments + $shares),
                    'extra_json' => json_encode($mi),
                ]);
            }

            $followers = $info['followers_count'] ?? $acc['followers'];
            $engRate = ($followers && $postCount) ? round((($totLikes + $totComments) / max(1, $postCount)) / $followers * 100, 2) : null;

            $accountsModel->saveMetrics($acc['id'], [
                'display_name' => $info['username'] ?? $acc['display_name'],
                'username' => $info['username'] ?? $acc['username'],
                'avatar' => $info['profile_picture_url'] ?? $acc['avatar'],
                'followers' => $followers,
                'follows' => $info['follows_count'] ?? $acc['follows'],
                'media_count' => $info['media_count'] ?? $acc['media_count'],
                'reach' => $ins['reach'] ?? null,
                'impressions' => $ins['impressions'] ?? null,
                'profile_views' => $ins['profile_views'] ?? null,
                'total_likes' => $totLikes,
                'total_comments' => $totComments,
                'total_shares' => $totShares,
                'engagement_rate' => $engRate,
            ]);
            $stats['social_updated']++;

        } elseif ($acc['provider'] === 'facebook_page') {
            $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : new MetaApi();
            if (!$api->hasToken()) { $errors[] = 'FB ' . ($acc['display_name'] ?: $acc['external_id']) . ': Meta sem token'; continue; }

            $info = $api->getFacebookPage($acc['external_id']);
            if (!empty($info['error'])) { $errors[] = 'FB ' . ($acc['display_name'] ?: '') . ': ' . ($info['error']['message'] ?? 'erro'); continue; }

            $posts = $api->getFacebookPosts($acc['external_id'], $acc['access_token'], 25);
            $totLikes = 0; $totComments = 0; $totShares = 0; $postCount = 0;
            foreach (($posts['data'] ?? []) as $p) {
                $likes = $p['reactions']['summary']['total_count'] ?? ($p['likes']['summary']['total_count'] ?? 0);
                $comments = $p['comments']['summary']['total_count'] ?? 0;
                $shares = $p['shares']['count'] ?? 0;
                $totLikes += $likes; $totComments += $comments; $totShares += $shares; $postCount++;
            }

            $pageInsights = $api->getFacebookPageInsights($acc['external_id'], $socialSince, $socialUntil, $acc['access_token']);
            $insData = MetaApi::sumInsights($pageInsights);
            $impressions = $insData['page_impressions'] ?? null;

            $fbFollowers = $info['followers_count'] ?? ($info['fan_count'] ?? $acc['followers']);
            $engRate = ($fbFollowers && $postCount) ? round((($totLikes + $totComments + $totShares) / max(1, $postCount)) / $fbFollowers * 100, 2) : null;

            $accountsModel->saveMetrics($acc['id'], [
                'display_name' => $info['name'] ?? $acc['display_name'],
                'avatar' => $info['picture']['data']['url'] ?? $acc['avatar'],
                'followers' => $fbFollowers,
                'total_likes' => $totLikes,
                'total_comments' => $totComments,
                'total_shares' => $totShares,
                'impressions' => $impressions,
                'engagement_rate' => $engRate,
            ]);
            $stats['social_updated']++;

        } elseif ($acc['provider'] === 'linkedin_org') {
            $api = $acc['access_token'] ? new LinkedInApi($acc['access_token']) : new LinkedInApi();
            if (!$api->hasToken()) { $errors[] = 'LinkedIn ' . ($acc['display_name'] ?: $acc['external_id']) . ': sem token'; continue; }

            $followers = $api->getFollowerCount($acc['external_id']);
            $stats2 = $api->getOrganizationShareStats($acc['external_id']);
            $totals = LinkedInApi::shareTotals($stats2);
            $org = $api->getOrganization($acc['external_id']);

            $accountsModel->saveMetrics($acc['id'], [
                'display_name' => $org['localizedName'] ?? $acc['display_name'],
                'username' => $org['vanityName'] ?? $acc['username'],
                'avatar' => $org['logo_url'] ?? $acc['avatar'],
                'followers' => $followers,
                'impressions' => $totals['impressionCount'] ?? null,
                'total_likes' => $totals['likeCount'] ?? null,
                'total_comments' => $totals['commentCount'] ?? null,
                'total_shares' => $totals['shareCount'] ?? null,
                'engagement_rate' => isset($totals['engagement']) ? round($totals['engagement'] * 100, 2) : null,
            ]);
            $stats['social_updated']++;
        }
    } catch (\Throwable $e) {
        $errors[] = ($acc['display_name'] ?: $acc['provider']) . ': ' . $e->getMessage();
    }
}
$log("   Social: {$stats['social_updated']} contas atualizadas");

// ============================================================
// 3) SNAPSHOT: Gravar histórico de seguidores do dia
// ============================================================
$log("3/4 — Gravando snapshot de seguidores...");
$stats['snapshots'] = $accountsModel->snapshotAllFollowers();
$log("   Snapshots: {$stats['snapshots']} contas registradas");

// ============================================================
// 4) RESUMO
// ============================================================
$log("4/4 — Concluído!");
$log("   Resumo: Buffer({$stats['buffer_channels']} canais, {$stats['buffer_posts']} posts) | Social({$stats['social_updated']} contas) | Snapshots({$stats['snapshots']})");

if (!empty($errors)) {
    $log("   Avisos (" . count($errors) . "):");
    foreach ($errors as $e) $log("     - $e");
}

// Se chamado via HTTP, retorna JSON
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'errors' => $errors,
    ]);
}

exit(empty($errors) ? 0 : 1);
