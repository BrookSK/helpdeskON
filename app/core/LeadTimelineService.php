<?php

/**
 * Timeline única do Lead. Consolida TODAS as interações (origem, e-mails manuais
 * e automáticos, aberturas, cliques, respostas, board, score, tags) num só lugar.
 */
class LeadTimelineService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add($contactId, $eventType, $description, $meta = null, $userId = null)
    {
        return $this->db->insert('lead_timeline', [
            'contact_id' => $contactId,
            'event_type' => $eventType,
            'description' => $description,
            'meta' => $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'user_id' => $userId,
        ]);
    }

    public function forContact($contactId, $limit = 100)
    {
        $limit = (int) $limit;
        $rows = $this->db->fetchAll(
            "SELECT t.*, u.name AS user_name
             FROM lead_timeline t
             LEFT JOIN users u ON t.user_id = u.id
             WHERE t.contact_id = ?
             ORDER BY t.created_at DESC
             LIMIT $limit",
            [$contactId]
        );
        foreach ($rows as &$r) {
            $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : null;
        }
        return $rows;
    }
}
