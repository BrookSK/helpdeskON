<?php

/**
 * Model das sequências de follow-up e seus participantes.
 */
class EmailSequence
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all()
    {
        return $this->db->fetchAll(
            "SELECT s.*, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM sequence_participants p WHERE p.sequence_id = s.id) AS total_participants,
                    (SELECT COUNT(*) FROM sequence_participants p WHERE p.sequence_id = s.id AND p.status='active') AS active_participants
             FROM email_sequences s
             LEFT JOIN users u ON s.created_by = u.id
             ORDER BY s.created_at DESC"
        );
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM email_sequences WHERE id = ?", [$id]);
    }

    public function create($data)
    {
        return $this->db->insert('email_sequences', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('email_sequences', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('email_sequences', 'id = ?', [$id]);
    }

    public function participants($sequenceId)
    {
        return $this->db->fetchAll(
            "SELECT sp.*, COALESCE(wc.contact_name, wc.push_name) AS lead_name, wc.lead_email,
                    ls.score, ls.classification
             FROM sequence_participants sp
             JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
             LEFT JOIN lead_score ls ON ls.contact_id = wc.id
             WHERE sp.sequence_id = ?
             ORDER BY sp.created_at DESC",
            [$sequenceId]
        );
    }

    public function stats($sequenceId)
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) t FROM sequence_participants WHERE sequence_id = ? GROUP BY status",
            [$sequenceId]
        );
        $out = ['active' => 0, 'finished' => 0, 'stopped' => 0, 'paused' => 0, 'failed' => 0, 'total' => 0];
        foreach ($rows as $r) { $out[$r['status']] = (int) $r['t']; $out['total'] += (int) $r['t']; }
        return $out;
    }
}
