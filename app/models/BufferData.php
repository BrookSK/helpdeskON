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

    /** Substitui o cache de canais pelo conjunto informado. */
    public function syncChannels($channels, $organizationId)
    {
        // Marca todos como inativos e reativa os retornados
        $this->db->query("UPDATE buffer_channels SET is_active = 0");
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

    // ===== Métricas =====
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

    /** Totais agregados de uma métrica em todos os posts. */
    public function sumMetric($type)
    {
        $row = $this->db->fetch(
            "SELECT SUM(metric_value) AS total FROM buffer_post_metrics WHERE metric_type = ?",
            [$type]
        );
        return floatval($row['total'] ?? 0);
    }

    /** Posts com o valor de uma métrica, ordenados (para "maior taxa"). */
    public function topPostsByMetric($type, $limit = 10)
    {
        return $this->db->fetchAll(
            "SELECT p.buffer_post_id, p.text, p.service, p.sent_at, p.external_link, m.metric_value, m.metric_unit
             FROM buffer_post_metrics m
             JOIN buffer_posts p ON p.buffer_post_id = m.buffer_post_id
             WHERE m.metric_type = ?
             ORDER BY m.metric_value DESC
             LIMIT " . intval($limit),
            [$type]
        );
    }

    /** Série temporal: soma de uma métrica por dia (sent_at). */
    public function metricTimeline($type)
    {
        return $this->db->fetchAll(
            "SELECT DATE(p.sent_at) AS day, SUM(m.metric_value) AS total
             FROM buffer_post_metrics m
             JOIN buffer_posts p ON p.buffer_post_id = m.buffer_post_id
             WHERE m.metric_type = ? AND p.sent_at IS NOT NULL
             GROUP BY DATE(p.sent_at)
             ORDER BY day ASC",
            [$type]
        );
    }
}
