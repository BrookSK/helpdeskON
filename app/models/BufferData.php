<?php

class BufferData
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ===== Canais =====
    public function getChannels($onlyActive = true)
    {
        $sql = "SELECT * FROM buffer_channels";
        if ($onlyActive) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql);
    }

    /** Canais de uma conta Buffer específica. */
    public function getChannelsByAccount($bufferAccountId, $onlyActive = true)
    {
        $sql = "SELECT * FROM buffer_channels WHERE buffer_account_id = ?";
        if ($onlyActive) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql, [$bufferAccountId]);
    }

    /**
     * Substitui o cache de canais pelo conjunto informado.
     * Quando $bufferAccountId é informado, só afeta os canais daquela conta Buffer.
     */
    public function syncChannels($channels, $organizationId, $bufferAccountId = null)
    {
        // Marca como inativos apenas os canais da conta em questão (ou todos, no modo legado)
        if ($bufferAccountId !== null) {
            $this->db->query("UPDATE buffer_channels SET is_active = 0 WHERE buffer_account_id = ?", [$bufferAccountId]);
        } else {
            $this->db->query("UPDATE buffer_channels SET is_active = 0");
        }
        foreach ($channels as $c) {
            $existing = $this->db->fetch("SELECT id FROM buffer_channels WHERE channel_id = ?", [$c['id']]);
            // Deriva o username do externalLink quando possível (ex: instagram.com/usuario)
            $username = null;
            if (!empty($c['externalLink'])) {
                $path = trim(parse_url($c['externalLink'], PHP_URL_PATH) ?? '', '/');
                if ($path !== '') $username = ltrim(explode('/', $path)[0], '@');
            }
            $data = [
                'organization_id' => $organizationId,
                'name' => (!empty($c['displayName']) ? $c['displayName'] : ($c['name'] ?? '')),
                'username' => $username,
                'service' => $c['service'] ?? null,
                'avatar' => $c['avatar'] ?? null,
                'external_link' => $c['externalLink'] ?? null,
                'channel_type' => $c['type'] ?? null,
                'is_disconnected' => !empty($c['isDisconnected']) ? 1 : 0,
                'is_queue_paused' => !empty($c['isQueuePaused']) ? 1 : 0,
                'is_active' => 1,
            ];
            if ($bufferAccountId !== null) $data['buffer_account_id'] = $bufferAccountId;
            if ($existing) {
                $this->db->update('buffer_channels', $data, 'channel_id = ?', [$c['id']]);
            } else {
                $data['channel_id'] = $c['id'];
                $this->db->insert('buffer_channels', $data);
            }
        }
    }

    // ===== Posts =====
    public function savePost($data)
    {
        $existing = $this->db->fetch("SELECT id FROM buffer_posts WHERE buffer_post_id = ?", [$data['buffer_post_id']]);
        if ($existing) {
            $this->db->update('buffer_posts', $data, 'buffer_post_id = ?', [$data['buffer_post_id']]);
            return $existing['id'];
        }
        return $this->db->insert('buffer_posts', $data);
    }

    public function getPosts($limit = 200)
    {
        return $this->db->fetchAll(
            "SELECT * FROM buffer_posts ORDER BY COALESCE(sent_at, due_at) DESC LIMIT " . intval($limit)
        );
    }

    public function getPostByBufferId($bufferPostId)
    {
        return $this->db->fetch("SELECT * FROM buffer_posts WHERE buffer_post_id = ?", [$bufferPostId]);
    }

    // ===== Métricas agregadas por canal =====
    /** Remove o snapshot agregado anterior de um canal. */
    public function clearChannelMetrics($channelId)
    {
        $this->db->delete('buffer_channel_metrics', 'channel_id = ?', [$channelId]);
    }

    public function saveChannelMetric($channelId, $metric, $periodStart, $periodEnd, $updatedAt = null)
    {
        $days = 30;
        if ($periodStart && $periodEnd) {
            $days = max(1, (int) round((strtotime($periodEnd) - strtotime($periodStart)) / 86400));
        }
        $data = [
            'metric_name' => $metric['name'] ?? null,
            'metric_value' => floatval($metric['value'] ?? 0),
            'metric_unit' => $metric['unit'] ?? null,
            'period_days' => $days,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'metrics_updated_at' => $updatedAt,
        ];
        $existing = $this->db->fetch(
            "SELECT id FROM buffer_channel_metrics WHERE channel_id = ? AND metric_type = ? AND period_days = ?",
            [$channelId, $metric['type'], $days]
        );
        if ($existing) {
            $this->db->update('buffer_channel_metrics', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['channel_id'] = $channelId;
            $data['metric_type'] = $metric['type'];
            $this->db->insert('buffer_channel_metrics', $data);
        }
    }

    /** Métricas agregadas mais recentes de um canal, indexadas por tipo. */
    public function getChannelMetrics($channelId)
    {
        $rows = $this->db->fetchAll(
            "SELECT metric_type, metric_name, metric_value, metric_unit, period_start, period_end
             FROM buffer_channel_metrics WHERE channel_id = ?",
            [$channelId]
        );
        $out = [];
        foreach ($rows as $r) $out[$r['metric_type']] = $r;
        return $out;
    }

    /**
     * Agrega métricas por canal a partir dos posts já sincronizados, no período dado.
     * Soma métricas de contagem e faz média das de porcentagem (ex: engagementRate).
     * Retorna: [channel_id => [metric_type => ['metric_value'=>..,'metric_unit'=>..]]]
     */
    public function aggregateChannelMetricsFromPosts($startDate, $endDate)
    {
        $rows = $this->db->fetchAll(
            "SELECT p.channel_id,
                    m.metric_type,
                    m.metric_unit,
                    SUM(m.metric_value) AS sum_value,
                    AVG(m.metric_value) AS avg_value,
                    COUNT(DISTINCT p.buffer_post_id) AS post_count
             FROM buffer_posts p
             JOIN buffer_post_metrics m ON m.buffer_post_id = p.buffer_post_id
             WHERE (
                    DATE(COALESCE(p.sent_at, p.due_at)) BETWEEN ? AND ?
                    OR COALESCE(p.sent_at, p.due_at) IS NULL
                 )
             GROUP BY p.channel_id, m.metric_type, m.metric_unit",
            [$startDate, $endDate]
        );

        $out = [];
        $postCounts = [];
        foreach ($rows as $r) {
            $cid = $r['channel_id'];
            if (!isset($out[$cid])) $out[$cid] = [];
            $isPct = ($r['metric_unit'] === 'percentage');
            $out[$cid][$r['metric_type']] = [
                'metric_type' => $r['metric_type'],
                'metric_value' => $isPct ? floatval($r['avg_value']) : floatval($r['sum_value']),
                'metric_unit' => $r['metric_unit'],
            ];
            // Guarda a maior contagem de posts vista para o canal
            $postCounts[$cid] = max($postCounts[$cid] ?? 0, intval($r['post_count']));
        }
        // Adiciona postCount sintético
        foreach ($postCounts as $cid => $pc) {
            $out[$cid]['postCount'] = ['metric_type' => 'postCount', 'metric_value' => $pc, 'metric_unit' => 'count'];
        }
        return $out;
    }

    // ===== Métricas por post =====
    public function saveMetric($bufferPostId, $metric, $updatedAt = null)
    {
        $data = [
            'metric_name' => $metric['name'] ?? null,
            'metric_value' => floatval($metric['value'] ?? 0),
            'metric_unit' => $metric['unit'] ?? null,
            'metrics_updated_at' => $updatedAt,
        ];
        $existing = $this->db->fetch(
            "SELECT id FROM buffer_post_metrics WHERE buffer_post_id = ? AND metric_type = ?",
            [$bufferPostId, $metric['type']]
        );
        if ($existing) {
            $this->db->update('buffer_post_metrics', $data, 'id = ?', [$existing['id']]);
        } else {
            $data['buffer_post_id'] = $bufferPostId;
            $data['metric_type'] = $metric['type'];
            $this->db->insert('buffer_post_metrics', $data);
        }
    }

    public function getMetricsForPost($bufferPostId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM buffer_post_metrics WHERE buffer_post_id = ?",
            [$bufferPostId]
        );
    }

    /**
     * Monta a cláusula de filtro por rede (service) e conta (nome do canal).
     * Retorna [sqlFragment, params], usando o alias do post como $pAlias.
     */
    private function socialFilterClause($network, $account, $pAlias = 'p', $bufferAccountLabel = null)
    {
        $sql = '';
        $params = [];
        if (!empty($network)) {
            $sql .= " AND LOWER($pAlias.service) = ?";
            $params[] = strtolower($network);
        }
        if (!empty($account)) {
            // Casa pelo nome do canal (buffer_channels.name) do canal do post
            $sql .= " AND EXISTS (SELECT 1 FROM buffer_channels bc WHERE bc.channel_id = $pAlias.channel_id AND bc.name = ?)";
            $params[] = $account;
        }
        if (!empty($bufferAccountLabel)) {
            // Filtra pelos canais que pertencem à conta Buffer (por label)
            $sql .= " AND EXISTS (
                        SELECT 1 FROM buffer_channels bc2
                        JOIN buffer_accounts ba ON ba.id = bc2.buffer_account_id
                        WHERE bc2.channel_id = $pAlias.channel_id AND ba.label = ?
                     )";
            $params[] = $bufferAccountLabel;
        }
        return [$sql, $params];
    }

    /** Totais agregados de uma métrica (com filtro opcional de período/rede/conta/conta Buffer). */
    public function sumMetric($type, $startDate = null, $endDate = null, $network = null, $account = null, $bufferAccount = null)
    {
        $sql = "SELECT SUM(m.metric_value) AS total
                FROM buffer_post_metrics m
                JOIN buffer_posts p ON p.buffer_post_id = m.buffer_post_id
                WHERE m.metric_type = ?";
        $params = [$type];
        if ($startDate && $endDate) {
            $sql .= " AND DATE(COALESCE(p.sent_at, p.due_at)) BETWEEN ? AND ?";
            $params[] = $startDate; $params[] = $endDate;
        }
        [$fSql, $fParams] = $this->socialFilterClause($network, $account, 'p', $bufferAccount);
        $sql .= $fSql; $params = array_merge($params, $fParams);

        $row = $this->db->fetch($sql, $params);
        return floatval($row['total'] ?? 0);
    }

    /** Posts com o valor de uma métrica, ordenados (com filtro opcional). */
    public function topPostsByMetric($type, $limit = 10, $startDate = null, $endDate = null, $network = null, $account = null, $bufferAccount = null)
    {
        $sql = "SELECT p.buffer_post_id, p.text, p.service, p.sent_at, p.external_link, p.thumbnail, m.metric_value, m.metric_unit
                FROM buffer_post_metrics m
                JOIN buffer_posts p ON p.buffer_post_id = m.buffer_post_id
                WHERE m.metric_type = ?";
        $params = [$type];
        if ($startDate && $endDate) {
            $sql .= " AND DATE(COALESCE(p.sent_at, p.due_at)) BETWEEN ? AND ?";
            $params[] = $startDate; $params[] = $endDate;
        }
        [$fSql, $fParams] = $this->socialFilterClause($network, $account, 'p', $bufferAccount);
        $sql .= $fSql; $params = array_merge($params, $fParams);
        $sql .= " ORDER BY m.metric_value DESC LIMIT " . intval($limit);

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Série temporal de uma métrica por publicação, ordenada no tempo.
     * Filtra opcionalmente por período (datas Y-m-d), rede, conta e conta Buffer.
     */
    public function metricTimeline($type, $startDate = null, $endDate = null, $network = null, $account = null, $bufferAccount = null)
    {
        $sql = "SELECT COALESCE(p.sent_at, p.due_at) AS moment, m.metric_value AS total, p.text
                FROM buffer_post_metrics m
                JOIN buffer_posts p ON p.buffer_post_id = m.buffer_post_id
                WHERE m.metric_type = ?
                  AND COALESCE(p.sent_at, p.due_at) IS NOT NULL";
        $params = [$type];
        if ($startDate && $endDate) {
            $sql .= " AND DATE(COALESCE(p.sent_at, p.due_at)) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        [$fSql, $fParams] = $this->socialFilterClause($network, $account, 'p', $bufferAccount);
        $sql .= $fSql; $params = array_merge($params, $fParams);
        $sql .= " ORDER BY moment ASC";
        return $this->db->fetchAll($sql, $params);
    }
}
