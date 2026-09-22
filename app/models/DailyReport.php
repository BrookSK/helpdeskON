<?php

/**
 * Model do RDO (Relatório Diário). Persistência em daily_reports +
 * daily_report_attachments + daily_report_collaborators.
 *
 * O ESCOPO de visibilidade (quem vê o quê) é responsabilidade do controller,
 * que passa $ownerId nos filtros quando o papel não tem visão global. O model
 * apenas aplica os filtros recebidos.
 */
class DailyReport
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT dr.*, u.name AS user_name, u.role AS user_role
             FROM daily_reports dr
             LEFT JOIN users u ON dr.user_id = u.id
             WHERE dr.id = ?",
            [$id]
        );
    }

    /**
     * Lista relatórios com filtros opcionais.
     * $filters: user_id (escopo do dono), search (texto em título/atividades/
     * ocorrências), date (report_date exata), date_from, date_to, status,
     * has_occurrence.
     */
    public function getList($filters = [])
    {
        $sql = "SELECT dr.*, u.name AS user_name, u.role AS user_role,
                       (SELECT COUNT(*) FROM daily_report_attachments a WHERE a.report_id = dr.id) AS attachment_count,
                       (SELECT COUNT(*) FROM daily_report_collaborators c WHERE c.report_id = dr.id) AS collaborator_count
                FROM daily_reports dr
                LEFT JOIN users u ON dr.user_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND dr.user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (dr.title LIKE ? OR dr.activities LIKE ? OR dr.occurrences LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($filters['date'])) {
            $sql .= " AND dr.report_date = ?";
            $params[] = $filters['date'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND dr.report_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND dr.report_date <= ?";
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['status']) && RdoRules::isValidStatus($filters['status'])) {
            $sql .= " AND dr.status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['has_occurrence']) && $filters['has_occurrence'] !== '') {
            $sql .= " AND dr.has_occurrence = ?";
            $params[] = (int) $filters['has_occurrence'];
        }

        $sql .= " ORDER BY dr.report_date DESC, dr.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards de resumo: total, finalizado, em andamento e com ocorrência.
     * Respeita o mesmo escopo de $filters['user_id'] (dono) que a listagem.
     */
    public function getStats($filters = [])
    {
        $where = "WHERE 1=1";
        $params = [];
        if (!empty($filters['user_id'])) {
            $where .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where .= " AND report_date >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND report_date <= ?";
            $params[] = $filters['date_to'];
        }

        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'finalizado'), 0) AS finalizado,
                COALESCE(SUM(status = 'em_andamento'), 0) AS em_andamento,
                COALESCE(SUM(has_occurrence = 1), 0) AS ocorrencias
             FROM daily_reports {$where}",
            $params
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'finalizado' => (int) ($row['finalizado'] ?? 0),
            'em_andamento' => (int) ($row['em_andamento'] ?? 0),
            'ocorrencias' => (int) ($row['ocorrencias'] ?? 0),
        ];
    }

    public function create($data)
    {
        return $this->db->insert('daily_reports', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('daily_reports', $data, 'id = ?', [$id]);
    }

    public function delete($id)
    {
        return $this->db->delete('daily_reports', 'id = ?', [$id]);
    }

    // ===== Anexos =====

    public function getAttachments($reportId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM daily_report_attachments WHERE report_id = ? ORDER BY created_at ASC",
            [$reportId]
        );
    }

    public function addAttachment($data)
    {
        return $this->db->insert('daily_report_attachments', $data);
    }

    public function findAttachment($id)
    {
        return $this->db->fetch("SELECT * FROM daily_report_attachments WHERE id = ?", [$id]);
    }

    public function deleteAttachment($id)
    {
        return $this->db->delete('daily_report_attachments', 'id = ?', [$id]);
    }

    // ===== Colaboradores / prestadores =====

    public function getCollaborators($reportId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM daily_report_collaborators WHERE report_id = ? ORDER BY id ASC",
            [$reportId]
        );
    }

    public function addCollaborator($data)
    {
        return $this->db->insert('daily_report_collaborators', $data);
    }

    /** Substitui todos os colaboradores de um relatório pelos informados. */
    public function replaceCollaborators($reportId, array $collaborators)
    {
        $this->db->delete('daily_report_collaborators', 'report_id = ?', [$reportId]);
        foreach ($collaborators as $c) {
            $name = trim($c['collaborator_name'] ?? '');
            if ($name === '') continue;
            $this->db->insert('daily_report_collaborators', [
                'report_id' => $reportId,
                'collaborator_name' => $name,
                'kind' => RdoRules::normalizeCollaboratorKind($c['kind'] ?? 'colaborador'),
                'notes' => isset($c['notes']) ? (trim($c['notes']) ?: null) : null,
            ]);
        }
    }
}
