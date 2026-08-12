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
}
