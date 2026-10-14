<?php

/**
 * Model do RDO (Relatório Diário). Persistência em:
 *   daily_reports              → o relatório em si.
 *   daily_report_attachments   → anexos (áudio/imagem/arquivo).
 *   daily_report_collaborators → colaboradores/prestadores do dia.
 *   daily_report_reviews       → pendências de revisão para o admin.
 *   daily_report_history       → audit trail de todas as ações.
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

    // =========================================================================
    // Relatório principal
    // =========================================================================

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT dr.*, u.name AS user_name, u.role AS user_role,
                    c.name AS company_name
             FROM daily_reports dr
             LEFT JOIN users u ON dr.user_id = u.id
             LEFT JOIN companies c ON dr.company_id = c.id
             WHERE dr.id = ?",
            [$id]
        );
    }

    /**
     * Lista relatórios com filtros opcionais.
     * $filters: user_id (escopo do dono), company_id (projeto/obra), search
     * (texto em título/atividades/ocorrências/pendências/plano), date (exata),
     * date_from, date_to, status, has_occurrence, review_status.
     */
    public function getList($filters = [])
    {
        $sql = "SELECT dr.*, u.name AS user_name, u.role AS user_role,
                       c.name AS company_name,
                       (SELECT COUNT(*) FROM daily_report_attachments a WHERE a.report_id = dr.id) AS attachment_count,
                       (SELECT COUNT(*) FROM daily_report_collaborators dc WHERE dc.report_id = dr.id) AS collaborator_count
                FROM daily_reports dr
                LEFT JOIN users u ON dr.user_id = u.id
                LEFT JOIN companies c ON dr.company_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND dr.user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['company_id'])) {
            $sql .= " AND dr.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (dr.title LIKE ? OR dr.activities LIKE ? OR dr.occurrences LIKE ?
                           OR dr.pending_tasks LIKE ? OR dr.next_day_plan LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = $like; $params[] = $like;
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
        if (!empty($filters['review_status'])) {
            $sql .= " AND dr.review_status = ?";
            $params[] = $filters['review_status'];
        }

        // Agrupa por projeto/obra (empresa): relatórios sem empresa vão para o
        // fim; dentro de cada projeto, os mais recentes primeiro.
        $sql .= " ORDER BY (dr.company_id IS NULL) ASC, c.name ASC, dr.report_date DESC, dr.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards de resumo: total, finalizado, em andamento, com ocorrência e com pendência.
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
        if (!empty($filters['company_id'])) {
            $where .= " AND company_id = ?";
            $params[] = $filters['company_id'];
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
                COALESCE(SUM(has_occurrence = 1), 0) AS ocorrencias,
                COALESCE(SUM(review_status = 'pending_review'), 0) AS pendencias
             FROM daily_reports {$where}",
            $params
        );

        return [
            'total'       => (int) ($row['total']       ?? 0),
            'finalizado'  => (int) ($row['finalizado']  ?? 0),
            'em_andamento'=> (int) ($row['em_andamento']?? 0),
            'ocorrencias' => (int) ($row['ocorrencias'] ?? 0),
            'pendencias'  => (int) ($row['pendencias']  ?? 0),
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

    // =========================================================================
    // Anexos
    // =========================================================================

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

    // =========================================================================
    // Colaboradores / prestadores
    // =========================================================================

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
                'report_id'          => $reportId,
                'collaborator_name'  => $name,
                'kind'               => RdoRules::normalizeCollaboratorKind($c['kind'] ?? 'colaborador'),
                'notes'              => isset($c['notes']) ? (trim($c['notes']) ?: null) : null,
            ]);
        }
    }

    // =========================================================================
    // Revisões (pendências de aprovação)
    // =========================================================================

    /**
     * Cria uma pendência de revisão. Retorna o id inserido.
     *
     * $data deve conter: report_id, type, requested_by.
     * Campos opcionais: payload_json, notes.
     */
    public function createReview(array $data): int
    {
        return (int) $this->db->insert('daily_report_reviews', $data);
    }

    /** Busca uma revisão pelo id. */
    public function findReview(int $reviewId): ?array
    {
        $r = $this->db->fetch(
            "SELECT r.*, u.name AS requester_name, a.name AS reviewer_name
             FROM daily_report_reviews r
             LEFT JOIN users u ON r.requested_by = u.id
             LEFT JOIN users a ON r.reviewed_by  = a.id
             WHERE r.id = ?",
            [$reviewId]
        );
        return $r ?: null;
    }

    /** Busca revisões pendentes de um relatório (status = 'pending'). */
    public function getPendingReviewsForReport(int $reportId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM daily_report_reviews WHERE report_id = ? AND status = 'pending' ORDER BY requested_at ASC",
            [$reportId]
        );
    }

    /**
     * Lista TODAS as pendências com status='pending', com dados do relatório e autor.
     * Usado na aba de revisão do admin.
     */
    public function getAllPendingReviews(): array
    {
        return $this->db->fetchAll(
            "SELECT r.*,
                    u.name  AS requester_name,
                    dr.report_date, dr.title, dr.activities, dr.status AS report_status,
                    dr.review_status,
                    ow.name AS owner_name
             FROM daily_report_reviews r
             JOIN daily_reports dr ON r.report_id = dr.id
             JOIN users u          ON r.requested_by = u.id
             JOIN users ow         ON dr.user_id = ow.id
             WHERE r.status = 'pending'
             ORDER BY r.requested_at ASC",
            []
        );
    }

    /** Atualiza campos de uma revisão (status, reviewed_by, reviewed_at, notes). */
    public function updateReview(int $reviewId, array $data): void
    {
        $this->db->update('daily_report_reviews', $data, 'id = ?', [$reviewId]);
    }

    /**
     * Verifica se o relatório ainda tem alguma revisão pendente.
     * Usado para decidir se review_status volta para 'none'.
     */
    public function hasPendingReviews(int $reportId): bool
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM daily_report_reviews WHERE report_id = ? AND status = 'pending'",
            [$reportId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    // =========================================================================
    // Histórico (audit trail)
    // =========================================================================

    /**
     * Insere uma entrada no histórico do relatório.
     *
     * $data deve conter: report_id, changed_by, action.
     * Campos opcionais: snapshot_json, review_id, notes.
     */
    public function addHistory(array $data): void
    {
        $this->db->insert('daily_report_history', $data);
    }

    /** Retorna o histórico de um relatório, mais recente primeiro. */
    public function getHistory(int $reportId): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.name AS actor_name
             FROM daily_report_history h
             LEFT JOIN users u ON h.changed_by = u.id
             WHERE h.report_id = ?
             ORDER BY h.changed_at DESC",
            [$reportId]
        );
    }

    // =========================================================================
    // Ausências (dias úteis sem relatório)
    // =========================================================================

    /**
     * Lista os profissionais internos que DEVERIAM entregar relatório diário:
     * usuários ativos cujo papel está em $roles (os papéis com acesso ao RDO,
     * resolvidos no controller via Permissions::rolesForModule('rdo')).
     *
     * @param string[] $roles Lista de papéis elegíveis.
     * @return array<int,array{id:int,name:string}> Usuários (id, name).
     */
    public function getRdoProfessionals(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        return $this->db->fetchAll(
            "SELECT id, name
             FROM users
             WHERE role IN ({$placeholders})
               AND (is_active IS NULL OR is_active = 1)
             ORDER BY name ASC",
            $roles
        );
    }

    /**
     * Retorna, para um usuário, as datas (Y-m-d) em que ele JÁ possui relatório
     * dentro do período [from, to] inclusivo. Usado para calcular ausências.
     *
     * @return string[] Datas Y-m-d.
     */
    public function getReportDatesForUser(int $userId, string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT report_date
             FROM daily_reports
             WHERE user_id = ? AND report_date >= ? AND report_date <= ?",
            [$userId, $from, $to]
        );
        return array_map(static fn($r) => (string) $r['report_date'], $rows);
    }
}
