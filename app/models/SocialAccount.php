<?php

class SocialAccount
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all($onlyActive = true)
    {
        $sql = "SELECT * FROM social_accounts";
        if ($onlyActive) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY provider, display_name";
        return $this->db->fetchAll($sql);
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM social_accounts WHERE id = ?", [$id]);
    }

    public function findByExternal($provider, $externalId)
    {
        return $this->db->fetch(
            "SELECT * FROM social_accounts WHERE provider = ? AND external_id = ?",
            [$provider, $externalId]
        );
    }

    public function create($data)
    {
        return $this->db->insert('social_accounts', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('social_accounts', $data, 'id = ?', [$id]);
    }

    /** Cria ou atualiza pelo par provider+external_id, retorna o id. */
    public function upsert($provider, $externalId, $data)
    {
        $existing = $this->findByExternal($provider, $externalId);
        if ($existing) {
            $this->update($existing['id'], $data);
            return $existing['id'];
        }
        $data['provider'] = $provider;
        $data['external_id'] = $externalId;
        return $this->create($data);
    }

    public function delete($id)
    {
        return $this->db->delete('social_accounts', 'id = ?', [$id]);
    }

    public function saveMetrics($id, $metrics)
    {
        $metrics['metrics_updated_at'] = date('Y-m-d H:i:s');
        return $this->update($id, $metrics);
    }

    // ===== Publicações =====
    public function upsertPost($provider, $externalPostId, $data)
    {
        $existing = $this->db->fetch(
            "SELECT id FROM social_posts WHERE provider = ? AND external_post_id = ?",
            [$provider, $externalPostId]
        );
        $data['metrics_updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $this->db->update('social_posts', $data, 'id = ?', [$existing['id']]);
            return $existing['id'];
        }
        $data['provider'] = $provider;
        $data['external_post_id'] = $externalPostId;
        return $this->db->insert('social_posts', $data);
    }

    public function getPosts($accountId, $limit = 30)
    {
        return $this->db->fetchAll(
            "SELECT * FROM social_posts WHERE account_id = ? ORDER BY published_at DESC, id DESC LIMIT " . intval($limit),
            [$accountId]
        );
    }

    public function getAllPosts($limit = 60)
    {
        return $this->db->fetchAll(
            "SELECT sp.*, sa.display_name AS account_name, sa.provider AS account_provider
             FROM social_posts sp
             JOIN social_accounts sa ON sa.id = sp.account_id
             ORDER BY sp.published_at DESC, sp.id DESC
             LIMIT " . intval($limit)
        );
    }

    // ===== Histórico de Seguidores =====

    /**
     * Grava (ou atualiza) o snapshot diário de seguidores de uma conta.
     * Usa INSERT ... ON DUPLICATE KEY UPDATE para garantir 1 registro/dia/conta.
     */
    public function saveFollowersSnapshot($accountId, $followers, $follows = null, $extraJson = null, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $sql = "INSERT INTO social_followers_history (account_id, snapshot_date, followers, follows, extra_json)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE followers = VALUES(followers), follows = VALUES(follows), extra_json = VALUES(extra_json)";
        return $this->db->query($sql, [$accountId, $date, (int)$followers, $follows, $extraJson]);
    }

    /**
     * Retorna o snapshot de seguidores de uma conta em uma data específica.
     */
    public function getFollowersOn($accountId, $date)
    {
        return $this->db->fetch(
            "SELECT * FROM social_followers_history WHERE account_id = ? AND snapshot_date = ? LIMIT 1",
            [$accountId, $date]
        );
    }

    /**
     * Retorna o snapshot mais recente anterior ou igual a uma data (útil quando não há registro exato no dia).
     */
    public function getFollowersClosest($accountId, $date)
    {
        return $this->db->fetch(
            "SELECT * FROM social_followers_history WHERE account_id = ? AND snapshot_date <= ? ORDER BY snapshot_date DESC LIMIT 1",
            [$accountId, $date]
        );
    }

    /**
     * Retorna o histórico de seguidores de uma conta em um intervalo de datas.
     */
    public function getFollowersHistory($accountId, $startDate, $endDate)
    {
        return $this->db->fetchAll(
            "SELECT snapshot_date, followers, follows FROM social_followers_history
             WHERE account_id = ? AND snapshot_date BETWEEN ? AND ?
             ORDER BY snapshot_date ASC",
            [$accountId, $startDate, $endDate]
        );
    }

    /**
     * Compara seguidores atuais com períodos anteriores (7d, 30d, 90d).
     * Retorna array com dados de crescimento absoluto e percentual.
     */
    public function getFollowersGrowth($accountId)
    {
        $today = date('Y-m-d');
        $current = $this->getFollowersClosest($accountId, $today);
        $currentFollowers = $current ? (int)$current['followers'] : null;

        $periods = [
            '7d'  => date('Y-m-d', strtotime('-7 days')),
            '30d' => date('Y-m-d', strtotime('-30 days')),
            '90d' => date('Y-m-d', strtotime('-90 days')),
        ];

        $growth = [
            'current' => $currentFollowers,
            'snapshot_date' => $current['snapshot_date'] ?? null,
        ];

        foreach ($periods as $label => $refDate) {
            $ref = $this->getFollowersClosest($accountId, $refDate);
            $refFollowers = $ref ? (int)$ref['followers'] : null;

            $diff = ($currentFollowers !== null && $refFollowers !== null) ? $currentFollowers - $refFollowers : null;
            $pct = ($diff !== null && $refFollowers > 0) ? round($diff / $refFollowers * 100, 2) : null;

            $growth[$label] = [
                'previous' => $refFollowers,
                'diff' => $diff,
                'pct' => $pct,
                'ref_date' => $ref['snapshot_date'] ?? null,
            ];
        }

        return $growth;
    }

    /**
     * Grava snapshots de todas as contas ativas que tenham valor de seguidores.
     * Retorna quantidade de contas processadas.
     */
    public function snapshotAllFollowers()
    {
        $accounts = $this->all(true);
        $saved = 0;
        $today = date('Y-m-d');

        foreach ($accounts as $acc) {
            $followers = $acc['followers'] ?? null;
            if ($followers === null || $followers === '') continue;

            $extra = [];
            if (!empty($acc['impressions'])) $extra['impressions'] = (int)$acc['impressions'];
            if (!empty($acc['reach'])) $extra['reach'] = (int)$acc['reach'];
            if (!empty($acc['profile_views'])) $extra['profile_views'] = (int)$acc['profile_views'];

            $this->saveFollowersSnapshot(
                $acc['id'],
                (int)$followers,
                $acc['follows'] ?? null,
                !empty($extra) ? json_encode($extra) : null,
                $today
            );
            $saved++;
        }

        return $saved;
    }
}
