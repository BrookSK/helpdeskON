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

    /**
     * Métricas globais de e-mail para o Dashboard do CRM.
     * Opcionalmente filtra por período (Y-m-d).
     */
    public function emailDashboard($startDate = null, $endDate = null)
    {
        $where = "WHERE m.direction = 'outbound' AND m.status = 'sent'";
        $params = [];
        if ($startDate) { $where .= " AND m.sent_at >= ?"; $params[] = $startDate . ' 00:00:00'; }
        if ($endDate)   { $where .= " AND m.sent_at <= ?"; $params[] = $endDate . ' 23:59:59'; }

        $agg = $this->db->fetch(
            "SELECT
                COUNT(*) AS sent,
                SUM(CASE WHEN m.first_open_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                SUM(CASE WHEN m.first_click_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked,
                SUM(CASE WHEN m.replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                SUM(CASE WHEN m.origin='manual' THEN 1 ELSE 0 END) AS manual,
                SUM(CASE WHEN m.origin='sequence' THEN 1 ELSE 0 END) AS sequence
             FROM email_messages m $where",
            $params
        ) ?: [];

        $sent = (int) ($agg['sent'] ?? 0);
        $opened = (int) ($agg['opened'] ?? 0);
        $clicked = (int) ($agg['clicked'] ?? 0);
        $replied = (int) ($agg['replied'] ?? 0);

        // Bounces (leads com bounce) e descadastros no período não têm data — contagem global
        $bounced = (int) ($this->db->fetch("SELECT COUNT(*) t FROM whatsapp_contacts WHERE email_bounced = 1")['t'] ?? 0);

        // Melhor e-mail (maior taxa de abertura, com no mínimo relevância)
        $top = $this->db->fetch(
            "SELECT subject,
                    COUNT(*) AS sent,
                    SUM(CASE WHEN first_open_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                    SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) AS replied
             FROM email_messages m $where
             GROUP BY subject
             HAVING sent >= 1
             ORDER BY (opened / sent) DESC, replied DESC, sent DESC
             LIMIT 1",
            $params
        );

        return [
            'sent' => $sent,
            'opened' => $opened,
            'clicked' => $clicked,
            'replied' => $replied,
            'bounced' => $bounced,
            'manual' => (int) ($agg['manual'] ?? 0),
            'sequence' => (int) ($agg['sequence'] ?? 0),
            'open_rate' => $sent ? round($opened / $sent * 100, 1) : 0,
            'click_rate' => $sent ? round($clicked / $sent * 100, 1) : 0,
            'reply_rate' => $sent ? round($replied / $sent * 100, 1) : 0,
            'top_email' => $top ?: null,
        ];
    }

    /** Série mensal de e-mails enviados x respondidos (últimos N meses). */
    public function emailMonthlyTrend($months = 6)
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $r = $this->db->fetch(
                "SELECT COUNT(*) sent,
                        SUM(CASE WHEN first_open_at IS NOT NULL THEN 1 ELSE 0 END) opened,
                        SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) replied
                 FROM email_messages
                 WHERE direction='outbound' AND status='sent' AND DATE_FORMAT(sent_at,'%Y-%m') = ?",
                [$ym]
            );
            $result[] = [
                'label' => date('m/Y', strtotime($ym . '-01')),
                'sent' => (int) ($r['sent'] ?? 0),
                'opened' => (int) ($r['opened'] ?? 0),
                'replied' => (int) ($r['replied'] ?? 0),
            ];
        }
        return $result;
    }
}
