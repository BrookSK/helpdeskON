<?php

/**
 * Controller para executar tarefas agendadas via HTTP.
 * Protegido por token (configurável em Settings como `cron_token`).
 *
 * Exemplos de uso:
 *   GET /cron/syncAll?token=SEU_TOKEN
 *
 * Para configurar o cron no servidor (a cada 30 min):
 *   [asterisco]/30 * * * * curl -s "https://seudominio.com/cron/syncAll?token=SEU_TOKEN" > /dev/null
 *
 * Ou via CLI (a cada 30 min):
 *   [asterisco]/30 * * * * php /caminho/para/helpdeskON/cron_sync_all.php >> /var/log/helpdesk_sync.log 2>&1
 */
class CronController extends Controller
{
    public function __construct()
    {
        // Sem autenticação por sessão — valida por token
    }

    private function validateToken()
    {
        $token = $_GET['token'] ?? ($_POST['token'] ?? '');
        $expected = Config::get('cron_token');
        if ($expected && $token !== $expected) {
            $this->json(['error' => 'Token inválido'], 403);
        }
        if (!$expected && empty($token)) {
            // Se não há token configurado, aceita qualquer chamada (menos seguro)
            return;
        }
    }

    /**
     * GET /cron/syncAll?token=XXX
     * Executa sincronização completa: Buffer + Meta + LinkedIn + Snapshot.
     */
    public function syncAll()
    {
        $this->validateToken();
        @set_time_limit(300);

        $errors = [];
        $stats = ['buffer_channels' => 0, 'buffer_posts' => 0, 'social_updated' => 0, 'snapshots' => 0];

        // 1) Buffer sync
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
                if (!$orgId) { $errors[] = 'Buffer: organização não encontrada'; continue; }

                if ($acc['organization_id'] !== $orgId) {
                    $bufferAccounts->update($acc['id'], ['organization_id' => $orgId]);
                }

                // Canais
                $res = $api->getChannels($orgId);
                if (empty($res['errors'])) {
                    $channels = $res['data']['channels'] ?? [];
                    $bufferData->syncChannels($channels, $orgId, $acc['id']);
                    $stats['buffer_channels'] += count($channels);
                }

                // Posts
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
                } while ($hasNext && $pages < 20);

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
                $errors[] = 'Buffer: ' . $e->getMessage();
            }
        }

        // 2) Social sync (Meta + LinkedIn)
        $accountsModel = new SocialAccount();
        $socialAccounts = $accountsModel->all(true);
        $socialSince = strtotime('-30 days');
        $socialUntil = time();

        foreach ($socialAccounts as $acc) {
            try {
                if ($acc['provider'] === 'meta_instagram') {
                    $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : new MetaApi();
                    if (!$api->hasToken()) { $errors[] = 'IG ' . ($acc['display_name'] ?: '') . ': sem token'; continue; }

                    $info = $api->getInstagramAccount($acc['external_id']);
                    if (!empty($info['error'])) { $errors[] = 'IG: ' . ($info['error']['message'] ?? 'erro'); continue; }

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
                    if (!$api->hasToken()) { $errors[] = 'FB ' . ($acc['display_name'] ?: '') . ': sem token'; continue; }

                    $info = $api->getFacebookPage($acc['external_id']);
                    if (!empty($info['error'])) { $errors[] = 'FB: ' . ($info['error']['message'] ?? 'erro'); continue; }

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
                    if (!$api->hasToken()) { $errors[] = 'LinkedIn ' . ($acc['display_name'] ?: '') . ': sem token'; continue; }

                    $followers = $api->getFollowerCount($acc['external_id']);
                    $orgStats = $api->getOrganizationShareStats($acc['external_id']);
                    $totals = LinkedInApi::shareTotals($orgStats);
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

        // 3) Snapshot
        $stats['snapshots'] = $accountsModel->snapshotAllFollowers();

        $this->json([
            'success' => true,
            'stats' => $stats,
            'errors' => $errors,
        ]);
    }

    /**
     * GET /cron/captureLeads?token=XXX
     * Coleta agendada de oportunidades (99Freelas). Respeita o intervalo configurado
     * e o mesmo lock do botão manual (nunca duas coletas simultâneas).
     */
    public function captureLeads()
    {
        $this->validateToken();
        @set_time_limit(360);

        $model = new Opportunity();
        $settings = $model->getSettings('freelas99');

        if (empty($settings['enabled'])) {
            $this->json(['skipped' => true, 'reason' => 'Fonte desabilitada']);
        }

        // Respeita o intervalo: só roda se passou schedule_minutes desde a última run
        $health = $model->getHealth('freelas99');
        $interval = max(15, (int) $settings['schedule_minutes']) * 60;
        if (!empty($health['last_run_at'])) {
            $elapsed = time() - strtotime($health['last_run_at']);
            if ($elapsed < $interval) {
                $this->json(['skipped' => true, 'reason' => 'Intervalo não atingido', 'next_in_seconds' => $interval - $elapsed]);
            }
        }

        $runner = new CollectionRunner();
        if ($runner->currentLock()) {
            $this->json(['skipped' => true, 'reason' => 'Coleta já em andamento']);
        }

        $result = $runner->run('scheduled', null, 'scheduler');
        $this->json(['success' => empty($result['error']), 'result' => $result]);
    }

    /**
     * GET /cron/runSequences?token=XXX
     * Worker das sequências de follow-up: processa participantes prontos (envio,
     * espera, condições) e detecta respostas via IMAP para interromper follow-ups.
     */
    public function runSequences()
    {
        $this->validateToken();
        @set_time_limit(300);

        // 1) Detecta respostas recebidas (interrompe follow-ups) antes de disparar novos
        $replies = $this->detectReplies();

        // 2) Processa os participantes prontos
        $engine = new SequenceEngine();
        $stats = $engine->processDue(200);

        $this->json(['success' => true, 'replies_detected' => $replies, 'engine' => $stats]);
    }

    /**
     * Varre as contas IMAP em busca de respostas de leads que estão em sequência ativa,
     * e registra a resposta (o que interrompe os follow-ups pendentes).
     * @return int nº de respostas processadas
     */
    private function detectReplies()
    {
        $db = Database::getInstance();
        // Leads com participação ativa e e-mail conhecido
        $rows = $db->fetchAll(
            "SELECT DISTINCT wc.id AS contact_id, wc.lead_email
             FROM sequence_participants sp
             JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
             WHERE sp.status = 'active' AND wc.lead_email IS NOT NULL AND wc.lead_email <> ''"
        );
        if (empty($rows)) return 0;

        // Índice email->contact
        $byEmail = [];
        foreach ($rows as $r) $byEmail[mb_strtolower($r['lead_email'])] = (int) $r['contact_id'];
        if (empty($byEmail)) return 0;

        $processed = 0;
        $emailSvc = new EmailMessageService();

        // Para cada conta IMAP configurada, busca mensagens recentes desses remetentes
        $accounts = $db->fetchAll("SELECT * FROM email_accounts WHERE is_active = 1 AND imap_host IS NOT NULL AND imap_host <> ''");
        foreach ($accounts as $acc) {
            try {
                $reader = new ImapReader($acc);
                if ($reader->connect() !== true) continue;
                foreach ($byEmail as $email => $contactId) {
                    $msgs = $reader->searchFrom($email, 5);
                    if (!empty($msgs)) {
                        // Considera resposta se houver mensagem recebida após o último envio
                        $lastSent = $db->fetch(
                            "SELECT sent_at FROM email_messages WHERE contact_id = ? AND direction='outbound' ORDER BY sent_at DESC LIMIT 1",
                            [$contactId]
                        );
                        $lastSentTs = $lastSent && $lastSent['sent_at'] ? strtotime($lastSent['sent_at']) : 0;
                        $hasReply = false;
                        foreach ($msgs as $m) {
                            if (!empty($m['date']) && strtotime($m['date']) >= $lastSentTs) { $hasReply = true; break; }
                        }
                        if ($hasReply) {
                            $emailSvc->registerReply($contactId, $msgs[0]['subject'] ?? null);
                            $processed++;
                            unset($byEmail[$email]); // não reprocessa na próxima conta
                        }
                    }
                }
                $reader->disconnect();
            } catch (\Throwable $e) {
                Logger::error('detectReplies', ['account' => $acc['id'] ?? null, 'error' => $e->getMessage()]);
            }
        }
        return $processed;
    }

    /**
     * GET /cron/index
     * Página de status/info sobre os crons disponíveis.
     */
    public function index()
    {
        $this->json([
            'endpoints' => [
                'GET /cron/syncAll?token=XXX' => 'Sincronização completa (Buffer + Meta + LinkedIn + Snapshot)',
                'GET /cron/captureLeads?token=XXX' => 'Coleta agendada de oportunidades (99Freelas)',
                'GET /cron/runSequences?token=XXX' => 'Worker de follow-up: sequências + detecção de respostas',
            ],
            'tip' => 'Configure cron_token em Configurações para proteger este endpoint.',
        ]);
    }
}
