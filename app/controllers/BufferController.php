<?php

class BufferController extends Controller
{
    private $accessRoles = ['super_admin', 'marketing'];
    private $data;
    private $accountsModel;

    public function __construct()
    {
        $this->data = new BufferData();
        $this->accountsModel = new BufferAccount();
        // Garante que a chave legada (setting) seja importada como conta
        $this->accountsModel->ensureLegacyKeyImported();
    }

    // ===== Dashboard de métricas =====
    public function dashboard()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        // Período do filtro (padrão: últimos 365 dias, para não zerar)
        $periodStart = !empty($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-d', strtotime('-365 days'));
        $periodEnd = !empty($_GET['end']) ? substr($_GET['end'], 0, 10) : date('Y-m-d');

        // Filtro por rede social, perfil e conta Buffer (aplica na tela inteira)
        $fNetwork = !empty($_GET['network']) ? trim($_GET['network']) : null;
        $fAccount = !empty($_GET['account']) ? trim($_GET['account']) : null;
        $fBufferAccount = !empty($_GET['buffer_account']) ? trim($_GET['buffer_account']) : null;

        // Métricas principais (totais) — respeitam período + filtro de rede/perfil/conta Buffer
        $totals = [
            'reactions' => $this->data->sumMetric('reactions', $periodStart, $periodEnd, $fNetwork, $fAccount, $fBufferAccount),
            'comments' => $this->data->sumMetric('comments', $periodStart, $periodEnd, $fNetwork, $fAccount, $fBufferAccount),
            'impressions' => $this->data->sumMetric('impressions', $periodStart, $periodEnd, $fNetwork, $fAccount, $fBufferAccount),
            'reach' => $this->data->sumMetric('reach', $periodStart, $periodEnd, $fNetwork, $fAccount, $fBufferAccount),
            'views' => $this->data->sumMetric('views', $periodStart, $periodEnd, $fNetwork, $fAccount, $fBufferAccount),
        ];

        $channels = $this->data->getChannels();
        $posts = $this->data->getPosts(200);

        // 1) Agregação local (posts já sincronizados) — fonte rápida
        $channelMetrics = $this->data->aggregateChannelMetricsFromPosts($periodStart, $periodEnd);

        // 2) Agregação server-side por canal, usando a API key da conta Buffer de cada canal.
        $startIso = gmdate('Y-m-d\T00:00:00\Z', strtotime($periodStart));
        $endIso = gmdate('Y-m-d\T23:59:59\Z', strtotime($periodEnd));
        $accountsById = [];
        foreach ($this->accountsModel->all(true) as $ba) $accountsById[$ba['id']] = $ba;

        foreach ($channels as $c) {
            $cid = $c['channel_id'];
            // Se já temos métricas locais relevantes, mantém
            $hasLocal = !empty($channelMetrics[$cid]) && (($channelMetrics[$cid]['postCount']['metric_value'] ?? 0) > 0);
            if ($hasLocal) continue;

            // Cache do mesmo período evita chamada repetida
            $cached = $this->data->getChannelMetrics($cid);
            $cachedMatchesPeriod = !empty($cached)
                && (($cached['postCount']['period_start'] ?? null) === $periodStart)
                && (($cached['postCount']['period_end'] ?? null) === $periodEnd)
                && (($cached['postCount']['metric_value'] ?? 0) > 0);
            if ($cachedMatchesPeriod) { $channelMetrics[$cid] = $cached; continue; }

            // Descobre a conta Buffer do canal
            $ba = $accountsById[$c['buffer_account_id'] ?? null] ?? null;
            if (!$ba) continue;
            $api = new BufferApi($ba['api_key']);
            $orgId = $ba['organization_id'] ?: $api->getFirstOrganizationId();
            if (!$orgId) continue;

            $res = $api->getAggregatedMetrics($orgId, $startIso, $endIso, [$cid]);
            if (!empty($res['errors'])) continue;
            $agg = $res['data']['aggregatedPostMetrics'] ?? null;
            if (!$agg || empty($agg['metrics'])) continue;

            $map = [];
            foreach ($agg['metrics'] as $metric) {
                $map[$metric['type']] = [
                    'metric_type' => $metric['type'],
                    'metric_value' => floatval($metric['value'] ?? 0),
                    'metric_unit' => $metric['unit'] ?? 'count',
                ];
            }
            if (($map['postCount']['metric_value'] ?? 0) > 0) {
                $channelMetrics[$cid] = $map;
                $updatedAt = !empty($agg['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($agg['metricsUpdatedAt'])) : null;
                $this->data->clearChannelMetrics($cid);
                foreach ($agg['metrics'] as $metric) {
                    $this->data->saveChannelMetric($cid, $metric, $periodStart, $periodEnd, $updatedAt);
                }
            }
        }

        // ===== Contas sociais diretas (Meta/LinkedIn) — exibidas na mesma página =====
        $socialModel = new SocialAccount();
        $socialAccounts = $socialModel->all(false);
        $socialPostsByAccount = [];
        $socialFollowersGrowth = [];
        foreach ($socialAccounts as $sa) {
            $socialPostsByAccount[$sa['id']] = $socialModel->getPosts($sa['id'], 12);
            $socialFollowersGrowth[$sa['id']] = $socialModel->getFollowersGrowth($sa['id']);
        }

        // ===== Mescla contas (prioridade API > Buffer, sem duplicatas) =====
        // Mapeia redes das contas diretas para identificar duplicatas
        $directNetworks = []; // ['instagram' => ['username1', 'username2'], ...]
        foreach ($socialAccounts as $sa) {
            $netMap = ['meta_instagram' => 'instagram', 'facebook_page' => 'facebook', 'linkedin_org' => 'linkedin'];
            $net = $netMap[$sa['provider']] ?? $sa['provider'];
            $uname = strtolower($sa['username'] ?? ($sa['display_name'] ?? ''));
            $directNetworks[$net][] = $uname;
        }

        // Filtra canais Buffer: remove os que já existem como conta direta (mesma rede + mesmo username)
        $filteredChannels = [];
        foreach ($channels as $ch) {
            $svc = strtolower($ch['service'] ?? '');
            $chUser = strtolower($ch['username'] ?? ($ch['name'] ?? ''));
            $isDuplicate = false;
            if (isset($directNetworks[$svc])) {
                foreach ($directNetworks[$svc] as $directUser) {
                    if ($directUser && $chUser && (strpos($directUser, $chUser) !== false || strpos($chUser, $directUser) !== false)) {
                        $isDuplicate = true;
                        break;
                    }
                }
            }
            if (!$isDuplicate) {
                $filteredChannels[] = $ch;
            }
        }

        $this->view('buffer/dashboard', [
            'user' => $user,
            'totals' => $totals,
            'channels' => $filteredChannels,
            'channelMetrics' => $channelMetrics,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'filterNetwork' => $fNetwork,
            'filterAccount' => $fAccount,
            'filterBufferAccount' => (!empty($_GET['buffer_account']) ? trim($_GET['buffer_account']) : null),
            'bufferAccounts' => $this->accountsModel->all(false),
            'posts' => $posts,
            'hasKey' => !empty($this->accountsModel->all(true)),
            // Dados sociais diretos
            'socialAccounts' => $socialAccounts,
            'socialPostsByAccount' => $socialPostsByAccount,
            'socialFollowersGrowth' => $socialFollowersGrowth,
            'metaConfigured' => (new MetaApi())->hasToken(),
            'linkedinConfigured' => (new LinkedInApi())->hasToken(),
            'metaTokenExpired' => $this->isTokenExpired('meta'),
            'linkedinTokenExpired' => $this->isTokenExpired('linkedin'),
            'isAdmin' => ($user['role'] ?? '') === 'super_admin',
        ]);
    }

    // API: dados do dashboard filtrados por métrica/post (JSON)
    public function metrics()
    {
        $this->requireRole($this->accessRoles);
        $metric = $_GET['metric'] ?? 'reactions';
        $allowed = ['reactions', 'comments', 'impressions', 'reach', 'views', 'engagementRate', 'shares', 'saves'];
        if (!in_array($metric, $allowed)) $metric = 'reactions';

        $start = !empty($_GET['start']) ? substr($_GET['start'], 0, 10) : null;
        $end = !empty($_GET['end']) ? substr($_GET['end'], 0, 10) : null;
        $network = !empty($_GET['network']) ? trim($_GET['network']) : null;
        $account = !empty($_GET['account']) ? trim($_GET['account']) : null;
        $bufferAccount = !empty($_GET['buffer_account']) ? trim($_GET['buffer_account']) : null;

        $this->json([
            'timeline' => $this->data->metricTimeline($metric, $start, $end, $network, $account, $bufferAccount),
            'top' => $this->data->topPostsByMetric($metric, 10, $start, $end, $network, $account, $bufferAccount),
            'metric' => $metric,
        ]);
    }

    /**
     * GET /buffer/comparison?start=YYYY-MM-DD&end=YYYY-MM-DD
     * Retorna totais do período selecionado VS período anterior equivalente.
     * Ex: se o período é 30 dias, compara com os 30 dias anteriores.
     * Usado para exibir variação (crescimento/queda) no dashboard.
     */
    public function comparison()
    {
        $this->requireRole($this->accessRoles);

        $start = !empty($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-d', strtotime('-30 days'));
        $end = !empty($_GET['end']) ? substr($_GET['end'], 0, 10) : date('Y-m-d');
        $network = !empty($_GET['network']) ? trim($_GET['network']) : null;
        $account = !empty($_GET['account']) ? trim($_GET['account']) : null;

        // Calcula período anterior com a mesma duração
        $days = (strtotime($end) - strtotime($start)) / 86400;
        $prevEnd = date('Y-m-d', strtotime($start . ' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . intval($days) . ' days'));

        $metrics = ['reactions', 'comments', 'impressions', 'reach', 'views'];
        $current = [];
        $previous = [];

        foreach ($metrics as $m) {
            $current[$m] = $this->data->sumMetric($m, $start, $end, $network, $account);
            $previous[$m] = $this->data->sumMetric($m, $prevStart, $prevEnd, $network, $account);
        }

        // Calcula variação
        $comparison = [];
        foreach ($metrics as $m) {
            $cur = (float)$current[$m];
            $prev = (float)$previous[$m];
            $diff = $cur - $prev;
            $pct = $prev > 0 ? round($diff / $prev * 100, 1) : ($cur > 0 ? 100 : 0);
            $comparison[$m] = [
                'current' => $cur,
                'previous' => $prev,
                'diff' => $diff,
                'pct' => $pct,
            ];
        }

        // Seguidores consolidados (soma de todas as contas diretas)
        $socialModel = new SocialAccount();
        $accounts = $socialModel->all(true);
        $totalFollowers = 0;
        $totalFollowersPrev = 0;
        foreach ($accounts as $acc) {
            $totalFollowers += (int)($acc['followers'] ?? 0);
            $closest = $socialModel->getFollowersClosest($acc['id'], $prevEnd);
            $totalFollowersPrev += $closest ? (int)$closest['followers'] : 0;
        }
        $followersDiff = $totalFollowers - $totalFollowersPrev;
        $followersPct = $totalFollowersPrev > 0 ? round($followersDiff / $totalFollowersPrev * 100, 1) : ($totalFollowers > 0 ? 100 : 0);

        $comparison['followers'] = [
            'current' => $totalFollowers,
            'previous' => $totalFollowersPrev,
            'diff' => $followersDiff,
            'pct' => $followersPct,
        ];

        $this->json([
            'success' => true,
            'period' => ['start' => $start, 'end' => $end, 'days' => $days],
            'previous_period' => ['start' => $prevStart, 'end' => $prevEnd],
            'comparison' => $comparison,
        ]);
    }

    // API: sincronizar canais de TODAS as contas Buffer conectadas
    public function syncChannels()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        // Cache: não sincronizar se já sincronizou nas últimas 6 horas
        $forceSync = !empty($_POST['force']);
        $lastSync = Config::get('buffer_channels_last_sync');
        if (!$forceSync && $lastSync && (time() - strtotime($lastSync)) < 21600) {
            $this->json(['success' => true, 'count' => 0, 'cached' => true, 'channels' => $this->data->getChannels()]);
            return;
        }

        $accounts = $this->accountsModel->all(true);
        if (empty($accounts)) $this->json(['error' => 'Nenhuma chave da API Buffer configurada.'], 400);

        $total = 0;
        $errors = [];
        foreach ($accounts as $acc) {
            $api = new BufferApi($acc['api_key']);
            $orgId = $api->getFirstOrganizationId();
            if (!$orgId) { $errors[] = ($acc['label'] ?: 'Conta') . ': organização não encontrada'; continue; }
            // Salva a organização na conta para reuso
            if ($acc['organization_id'] !== $orgId) {
                $this->accountsModel->update($acc['id'], ['organization_id' => $orgId]);
            }
            $res = $api->getChannels($orgId);
            if (!empty($res['errors'])) { $errors[] = ($acc['label'] ?: 'Conta') . ': ' . ($res['errors'][0]['message'] ?? 'erro'); continue; }
            $channels = $res['data']['channels'] ?? [];
            $this->data->syncChannels($channels, $orgId, $acc['id']);
            $total += count($channels);
        }

        Config::set('buffer_channels_last_sync', date('Y-m-d H:i:s'));
        $this->json(['success' => true, 'count' => $total, 'errors' => $errors, 'channels' => $this->data->getChannels()]);
    }

    // ===== Gestão de contas Buffer (múltiplas API keys) =====
    public function addAccount()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $key = trim($_POST['api_key'] ?? '');
        $label = trim($_POST['label'] ?? '');
        if ($key === '') $this->json(['error' => 'Informe a API key.'], 400);
        if ($this->accountsModel->findByKey($key)) $this->json(['error' => 'Esta API key já está cadastrada.'], 400);

        // Valida a chave e já pega a organização
        $api = new BufferApi($key);
        $orgId = $api->getFirstOrganizationId();
        if (!$orgId) $this->json(['error' => 'API key inválida ou sem organização no Buffer.'], 400);

        $id = $this->accountsModel->create([
            'label' => $label ?: 'Conta Buffer',
            'api_key' => $key,
            'organization_id' => $orgId,
        ]);

        // Já sincroniza os canais dessa conta
        $res = $api->getChannels($orgId);
        $channels = $res['data']['channels'] ?? [];
        $this->data->syncChannels($channels, $orgId, $id);

        $this->json(['success' => true, 'id' => $id, 'channels' => count($channels)]);
    }

    public function deleteAccount($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        // Remove canais dessa conta
        Database::getInstance()->delete('buffer_channels', 'buffer_account_id = ?', [$id]);
        $this->accountsModel->delete($id);
        $this->json(['success' => true]);
    }

    public function deleteChannel($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        Database::getInstance()->delete('buffer_channels', 'id = ?', [$id]);
        $this->json(['success' => true]);
    }

    // Lista contas Buffer (JSON) — para a tela de Configurações
    public function accounts()
    {
        $this->requireRole($this->accessRoles);
        $rows = $this->accountsModel->all(false);
        // Mascara a key
        foreach ($rows as &$r) {
            $k = $r['api_key'];
            $r['api_key_masked'] = strlen($k) > 8 ? substr($k, 0, 4) . '••••' . substr($k, -4) : '••••';
            unset($r['api_key']);
        }
        unset($r);
        $this->json(['accounts' => $rows]);
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

        // Mapear canais para suas contas Buffer (para usar a API key correta por canal)
        $allChannels = $this->data->getChannels(false);
        $channelMap = [];
        foreach ($allChannels as $c) {
            $channelMap[$c['channel_id']] = $c;
        }

        // Carregar todas as contas Buffer ativas
        $accounts = $this->accountsModel->all(true);
        $accountMap = [];
        foreach ($accounts as $acc) {
            $accountMap[$acc['id']] = $acc;
        }

        // Determinar a API key padrão (fallback)
        $defaultApiKey = Config::get('buffer_api_key');
        if (empty($defaultApiKey) && !empty($accounts)) {
            $defaultApiKey = $accounts[0]['api_key'] ?? '';
        }
        if (empty($defaultApiKey)) {
            $this->json(['error' => 'Configure a chave da API Buffer em Configurações.'], 400);
        }

        // Log de entrada para diagnóstico
        $this->logBufferResponse(['_input' => [
            'text' => substr($text, 0, 50),
            'channels' => $channelIds,
            'dueAtIso' => $dueAtIso,
            'assets' => $assets,
            'accounts_count' => count($accounts),
        ]], 'INPUT');

        $created = [];
        $errors = [];
        foreach ($channelIds as $idx => $channelId) {
            // Delay entre chamadas para evitar rate limiting da API Buffer
            if ($idx > 0) usleep(1000000); // 1s entre cada canal

            // Usar a API key da conta vinculada ao canal (ou a padrão)
            $apiKey = $defaultApiKey;
            if (isset($channelMap[$channelId]) && !empty($channelMap[$channelId]['buffer_account_id'])) {
                $accId = $channelMap[$channelId]['buffer_account_id'];
                if (isset($accountMap[$accId]) && !empty($accountMap[$accId]['api_key'])) {
                    $apiKey = $accountMap[$accId]['api_key'];
                }
            }
            $api = new BufferApi($apiKey);

            $res = $api->createPost($channelId, $text, $dueAtIso, $assets);

            // Log para diagnóstico de erros da API Buffer
            $this->logBufferResponse($res, $channelId);

            // Se é rate limit, salvar na fila local para enviar depois
            if (($res['http'] ?? 0) === 429) {
                // Salvar todos os canais restantes na fila
                foreach (array_slice($channelIds, $idx) as $queuedChannelId) {
                    $this->data->savePost([
                        'marketing_item_id' => $marketingItemId,
                        'buffer_post_id' => 'queued_' . uniqid(),
                        'channel_id' => $queuedChannelId,
                        'service' => $channelMap[$queuedChannelId]['service'] ?? null,
                        'text' => $text,
                        'status' => 'queued',
                        'due_at' => $dueAtIso ? date('Y-m-d H:i:s', strtotime($dueAtIso)) : null,
                        'created_by' => $user['id'],
                    ]);
                }
                $this->json([
                    'success' => true,
                    'created' => count($created),
                    'queued' => count($channelIds) - $idx,
                    'message' => 'Limite da API Buffer atingido. ' . (count($created) ? count($created) . ' post(s) agendado(s). ' : '') . 'Os demais foram salvos na fila e serão enviados automaticamente quando o limite resetar.',
                ]);
            }

            $node = $res['data']['createPost']['post'] ?? null;
            $errMsg = $res['data']['createPost']['message'] ?? ($res['errors'][0]['message'] ?? null);

            // Rate limit detectado na mensagem: salvar na fila
            if ($errMsg && stripos($errMsg, 'too many requests') !== false) {
                foreach (array_slice($channelIds, $idx) as $queuedChannelId) {
                    $this->data->savePost([
                        'marketing_item_id' => $marketingItemId,
                        'buffer_post_id' => 'queued_' . uniqid(),
                        'channel_id' => $queuedChannelId,
                        'service' => $channelMap[$queuedChannelId]['service'] ?? null,
                        'text' => $text,
                        'status' => 'queued',
                        'due_at' => $dueAtIso ? date('Y-m-d H:i:s', strtotime($dueAtIso)) : null,
                        'created_by' => $user['id'],
                    ]);
                }
                $this->json([
                    'success' => true,
                    'created' => count($created),
                    'queued' => count($channelIds) - $idx,
                    'message' => 'Limite da API Buffer atingido. Os posts foram salvos na fila e serão enviados automaticamente.',
                ]);
            }

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
            $this->json([
                'error' => 'Nenhum post criado. ' . implode('; ', $errors),
                'debug' => [
                    'channels_tried' => count($channelIds),
                    'api_key_length' => strlen($defaultApiKey ?? ''),
                    'has_accounts' => count($accounts),
                ],
            ], 400);
        }
        $this->json(['success' => true, 'created' => count($created), 'errors' => $errors]);
    }

    // API: Limpar fila de posts pendentes (queued)
    public function clearQueue()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $db = Database::getInstance();
        $count = $db->fetch("SELECT COUNT(*) as t FROM buffer_posts WHERE status = 'queued'");
        $db->query("DELETE FROM buffer_posts WHERE status = 'queued'");
        $this->json(['success' => true, 'deleted' => $count['t'] ?? 0]);
    }

    // API: sincronizar métricas dos posts enviados
    public function syncMetrics()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        // Cache: não sincronizar métricas se já sincronizou nas últimas 6 horas
        $forceSync = !empty($_POST['force']);
        $lastSync = Config::get('buffer_metrics_last_sync');
        if (!$forceSync && $lastSync && (time() - strtotime($lastSync)) < 21600) {
            $this->json(['success' => true, 'posts' => 0, 'cached' => true]);
            return;
        }

        $accounts = $this->accountsModel->all(true);
        if (empty($accounts)) $this->json(['error' => 'Nenhuma chave da API Buffer configurada.'], 400);

        @set_time_limit(300);

        // Período agregado: aceita start/end do filtro; padrão = últimos 365 dias (máximo da API)
        $startParam = $_POST['start'] ?? null;
        $endParam = $_POST['end'] ?? null;
        $startTs = $startParam ? strtotime($startParam) : strtotime('-365 days');
        $endTs = $endParam ? strtotime($endParam) : time();
        if (!$startTs) $startTs = strtotime('-365 days');
        if (!$endTs) $endTs = time();
        if ($endTs - $startTs > 365 * 86400) $startTs = $endTs - 365 * 86400;

        $startIso = gmdate('Y-m-d\T00:00:00\Z', $startTs);
        $endIso = gmdate('Y-m-d\T23:59:59\Z', $endTs);
        $periodStart = date('Y-m-d', $startTs);
        $periodEnd = date('Y-m-d', $endTs);

        $postCount = 0;
        $aggChannels = 0;
        $errors = [];

        foreach ($accounts as $acc) {
            $api = new BufferApi($acc['api_key']);
            $orgId = $acc['organization_id'] ?: $api->getFirstOrganizationId();
            if (!$orgId) { $errors[] = ($acc['label'] ?: 'Conta') . ': organização não encontrada'; continue; }

            // 1) Posts enviados com métricas
            $after = null; $pages = 0;
            do {
                $res = $api->getSentPostsWithMetrics($orgId, [], 50, $after);
                if (!empty($res['errors'])) { $errors[] = ($acc['label'] ?: 'Conta') . ': ' . ($res['errors'][0]['message'] ?? 'erro'); break; }
                $conn = $res['data']['posts'] ?? ['edges' => [], 'pageInfo' => []];
                foreach ($conn['edges'] as $edge) {
                    $node = $edge['node'];
                    $updatedAt = !empty($node['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($node['metricsUpdatedAt'])) : null;
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

            // 2) Agregação por canal (só os canais desta conta)
            $snapshotModel = new SocialSnapshot();
            foreach ($this->data->getChannelsByAccount($acc['id'], false) as $ch) {
                $res = $api->getAggregatedMetrics($orgId, $startIso, $endIso, [$ch['channel_id']]);
                if (!empty($res['errors'])) continue;
                $agg = $res['data']['aggregatedPostMetrics'] ?? null;
                if (!$agg) continue;
                $updatedAt = !empty($agg['metricsUpdatedAt']) ? date('Y-m-d H:i:s', strtotime($agg['metricsUpdatedAt'])) : null;
                $this->data->clearChannelMetrics($ch['channel_id']);
                $byType = [];
                foreach (($agg['metrics'] ?? []) as $metric) {
                    $this->data->saveChannelMetric($ch['channel_id'], $metric, $periodStart, $periodEnd, $updatedAt);
                    $byType[$metric['type']] = (float)($metric['value'] ?? 0);
                }
                // Snapshot histórico (todos os dados) para comparação
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
                $aggChannels++;
            }
        }

        Config::set('buffer_metrics_last_sync', date('Y-m-d H:i:s'));
        $this->json([
            'success' => true,
            'posts' => $postCount,
            'channels_aggregated' => $aggChannels,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'errors' => $errors,
        ]);
    }

    /**
     * Verifica se um token (meta/linkedin) está expirado, usando cache de 1h para não
     * chamar APIs externas a cada page load. Retorna true se expirado.
     */
    private function isTokenExpired($provider)
    {
        if ($provider === 'meta') {
            $api = new MetaApi();
        } else {
            $api = new LinkedInApi();
        }
        if (!$api->hasToken()) return false;

        // Cache: verifica se já validamos nas últimas 1h
        $cacheKey = $provider . '_token_status';
        $cacheTimeKey = $provider . '_token_checked_at';
        $cachedStatus = Config::get($cacheKey);
        $cachedAt = Config::get($cacheTimeKey);

        if ($cachedAt && (time() - strtotime($cachedAt)) < 3600) {
            return $cachedStatus === 'expired';
        }

        // Validar o token via API
        $valid = $api->isTokenValid();
        Config::set($cacheKey, $valid ? 'valid' : 'expired');
        Config::set($cacheTimeKey, date('Y-m-d H:i:s'));

        return !$valid;
    }

    /**
     * Log de diagnóstico das respostas da API Buffer.
     */
    private function logBufferResponse($res, $channelId)
    {
        try {
            $logFile = PUBLIC_PATH . '/uploads/buffer_api.log';
            $entry = '[' . date('Y-m-d H:i:s') . '] channel=' . $channelId
                . ' http=' . ($res['http'] ?? '?')
                . ' response=' . json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            file_put_contents($logFile, $entry, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignora
        }
    }
}
