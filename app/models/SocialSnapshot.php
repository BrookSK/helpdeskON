<?php

class SocialSnapshot
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Salva (ou atualiza) o snapshot do dia para uma entidade.
     * $data pode conter: followers, reach, impressions, views, likes, comments,
     * shares, saves, posts_count, engagement_rate, extra_json.
     */
    public function save($source, $provider, $entityKey, $accountLabel, $data, $date = null)
    {
        $date = $date ?: date('Y-m-d');
        $row = array_merge([
            'source' => $source,
            'provider' => $provider,
            'entity_key' => $entityKey,
            'account_label' => $accountLabel,
            'snapshot_date' => $date,
        ], $data);

        $existing = $this->db->fetch(
            "SELECT id FROM social_snapshots WHERE source = ? AND entity_key = ? AND snapshot_date = ?",
            [$source, $entityKey, $date]
        );
        if ($existing) {
            unset($row['source'], $row['entity_key'], $row['snapshot_date']);
            return $this->db->update('social_snapshots', $row, 'id = ?', [$existing['id']]);
        }
        return $this->db->insert('social_snapshots', $row);
    }

    /** Histórico de uma entidade, ordenado por data. */
    public function history($entityKey, $limit = 90)
    {
        return $this->db->fetchAll(
            "SELECT * FROM social_snapshots WHERE entity_key = ? ORDER BY snapshot_date ASC LIMIT " . intval($limit),
            [$entityKey]
        );
    }
}
