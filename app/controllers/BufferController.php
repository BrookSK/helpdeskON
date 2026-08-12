<?php

class BufferController extends Controller
{
    private $accessRoles = ['super_admin', 'marketing'];
    private $data;

    public function __construct()
    {
        $this->data = new BufferData();
    }

    // ===== Dashboard de métricas =====
    public function dashboard()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        // Métricas principais (totais)
        $totals = [
            'reactions' => $this->data->sumMetric('reactions'),
            'comments' => $this->data->sumMetric('comments'),
            'impressions' => $this->data->sumMetric('impressions'),
            'reach' => $this->data->sumMetric('reach'),
            'views' => $this->data->sumMetric('views'),
        ];

        $channels = $this->data->getChannels();
        $posts = $this->data->getPosts(200);

        // Métricas agregadas (30 dias) por canal, para exibir no card
        $channelMetrics = [];
        foreach ($channels as $c) {
            $channelMetrics[$c['channel_id']] = $this->data->getChannelMetrics($c['channel_id']);
        }

        $this->view('buffer/dashboard', [
            'user' => $user,
            'totals' => $totals,
            'channels' => $channels,
            'channelMetrics' => $channelMetrics,
            'posts' => $posts,
            'hasKey' => (new BufferApi())->hasKey(),
        ]);
    }

    // API: dados do dashboard filtrados por métrica/post (JSON)
    public function metrics()
    {
        $this->requireRole($this->accessRoles);
        $metric = $_GET['metric'] ?? 'reactions';
        $allowed = ['reactions', 'comments', 'impressions', 'reach', 'views', 'engagementRate', 'shares', 'saves'];
        if (!in_array($metric, $allowed)) $metric = 'reactions';

        $this->json([
            'timeline' => $this->data->metricTimeline($metric),
            'top' => $this->data->topPostsByMetric($metric, 10),
            'metric' => $metric,
        ]);
    }

    // API: sincronizar canais conectados no Buffer
    public function syncChannels()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $api = new BufferApi();
        if (!$api->hasKey()) $this->json(['error' => 'Configure a chave da API Buffer em Configurações.'], 400);

        $orgId = $api->getFirstOrganizationId();
        if (!$orgId) $this->json(['error' => 'Não foi possível obter a organização do Buffer. Verifique a chave.'], 400);

        $res = $api->getChannels($orgId);
        if (!empty($res['errors'])) {
            $this->json(['error' => $res['errors'][0]['message'] ?? 'Erro ao buscar canais'], 400);
        }
        $channels = $res['data']['channels'] ?? [];
        $this->data->syncChannels($channels, $orgId);

        $this->json(['success' => true, 'count' => count($channels), 'channels' => $this->data->getChannels()]);
    }

    // API: listar canais cacheados
    public function channels()
    {
        $this->requireRole($this->accessRoles);
        $this->json(['channels' => $this->data->getChannels()]);
    }

    // API: agendar um post no Buffer a partir de uma demanda de marketing (ou avulso)
    public function schedule()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $api = new BufferApi();
        if (!$api->hasKey()) $this->json(['error' => 'Configure a chave da API Buffer em Configurações.'], 400);

        $text = trim($_POST['text'] ?? '');
        $channelIds = $_POST['channel_ids'] ?? [];
        if (is_string($channelIds)) $channelIds = array_filter(explode(',', $channelIds));
        $marketingItemId = !empty($_POST['marketing_item_id']) ? intval($_POST['marketing_item_id']) : null;
        $imageUrl = trim($_POST['image_url'] ?? '');
        $assets = $imageUrl ? [$imageUrl] : [];

        // Data/hora -> ISO 8601 UTC
        $dueAtIso = null;
        if (!empty($_POST['due_at'])) {
            try {
                $dt = new DateTime($_POST['due_at']);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $dueAtIso = $dt->format('Y-m-d\TH:i:s.000\Z');
            } catch (\Throwable $e) { $dueAtIso = null; }
        }

        if ($text === '' || empty($channelIds)) {
            $this->json(['error' => 'Informe o texto e selecione ao menos um canal.'], 400);
        }

        $created = [];
        $errors = [];
        foreach ($channelIds as $channelId) {
            $res = $api->createPost($channelId, $text, $dueAtIso, $assets);
            $node = $res['data']['createPost']['post'] ?? null;
            $errMsg = $res['data']['createPost']['message'] ?? ($res['errors'][0]['message'] ?? null);

            if ($node) {
                $channel = null;
                foreach ($this->data->getChannels(false) as $c) {
                    if ($c['channel_id'] === $channelId) { $channel = $c; break; }
                }
                $this->data->savePost([
                    'marketing_item_id' => $marketingItemId,
                    'buffer_post_id' => $node['id'],
                    'channel_id' => $channelId,
                    'service' => $channel['service'] ?? null,
                    'text' => $node['text'] ?? $text,
                    'status' => $node['status'] ?? 'scheduled',
                    'due_at' => !empty($node['dueAt']) ? date('Y-m-d H:i:s', strtotime($node['dueAt'])) : null,
                    'external_link' => $node['externalLink'] ?? null,
                    'created_by' => $user['id'],
                ]);
                $created[] = $node['id'];
            } else {
                $errors[] = $errMsg ?: 'Falha ao criar post';
            }
        }

        if (empty($created)) {
            $this->json(['error' => 'Nenhum post criado. ' . implode('; ', $errors)], 400);
        }
        $this->json(['success' => true, 'created' => count($created), 'errors' => $errors]);
    }

    // API: sincronizar métricas dos posts enviados
    public function syncMetrics()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $api = new BufferApi();
        if (!$api->hasKey()) $this->json(['error' => 'Configure a chave da API Buffer em Configurações.'], 400);

        $orgId = $api->getFirstOrganizationId();
        if (!$orgId) $this->json(['error' => 'Não foi possível obter a organização do Buffer.'], 400);

        $after = null;
        $pages = 0;
        $postCount = 0;
        do {
            $res = $api->getSentPostsWithMetrics($orgId, [], 50, $after);
            if (!empty($res['errors'])) {
                $this->json(['error' => $res['errors'][0]['message'] ?? 'Erro ao buscar métricas'], 400);
            }
            $conn = $res['data']['posts'] ?? ['edges' => [], 'pageInfo' => []];
            foreach ($conn['edges'] as $edge) {
                $node = $edge['node'];
                $updatedAt = !empty($node['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($node['metricsUpdatedAt'])) : null;
                // Capa/thumbnail: primeiro asset com thumbnail (ou source)
                $thumb = null;
                foreach (($node['assets'] ?? []) as $asset) {
                    if (!empty($asset['thumbnail'])) { $thumb = $asset['thumbnail']; break; }
                    if (!empty($asset['source'])) { $thumb = $asset['source']; break; }
                }
                $this->data->savePost([
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
                    $this->data->saveMetric($node['id'], $metric, $updatedAt);
                }
                $postCount++;
            }
            $after = $conn['pageInfo']['endCursor'] ?? null;
            $hasNext = !empty($conn['pageInfo']['hasNextPage']);
            $pages++;
        } while ($hasNext && $pages < 20);

        // Período agregado: aceita start/end do filtro; padrão = últimos 365 dias (máximo da API)
        $startParam = $_POST['start'] ?? null;
        $endParam = $_POST['end'] ?? null;
        $startTs = $startParam ? strtotime($startParam) : strtotime('-365 days');
        $endTs = $endParam ? strtotime($endParam) : time();
        if (!$startTs) $startTs = strtotime('-365 days');
        if (!$endTs) $endTs = time();
        // A API limita a 365 dias
        if ($endTs - $startTs > 365 * 86400) $startTs = $endTs - 365 * 86400;

        $startIso = gmdate('Y-m-d\T00:00:00\Z', $startTs);
        $endIso = gmdate('Y-m-d\T23:59:59\Z', $endTs);
        $periodStart = date('Y-m-d', $startTs);
        $periodEnd = date('Y-m-d', $endTs);
        $periodDays = max(1, (int) round(($endTs - $startTs) / 86400));

        $aggChannels = 0;
        foreach ($this->data->getChannels(false) as $ch) {
            $res = $api->getAggregatedMetrics($orgId, $startIso, $endIso, [$ch['channel_id']]);
            if (!empty($res['errors'])) continue;
            $agg = $res['data']['aggregatedPostMetrics'] ?? null;
            if (!$agg) continue;
            $updatedAt = !empty($agg['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($agg['metricsUpdatedAt'])) : null;
            // Limpa o snapshot anterior deste canal para refletir exatamente o período consultado
            $this->data->clearChannelMetrics($ch['channel_id']);
            foreach (($agg['metrics'] ?? []) as $metric) {
                $this->data->saveChannelMetric($ch['channel_id'], $metric, $periodStart, $periodEnd, $updatedAt);
            }
            $aggChannels++;
        }

        $this->json([
            'success' => true,
            'posts' => $postCount,
            'channels_aggregated' => $aggChannels,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }
}
