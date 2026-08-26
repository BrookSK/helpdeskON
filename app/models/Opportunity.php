<?php

/**
 * Model das oportunidades captadas + configuração/health do módulo.
 */
class Opportunity
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ============ Oportunidades ============

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM opportunities WHERE id = ?", [$id]);
    }

    public function findByExternal($source, $externalId)
    {
        return $this->db->fetch(
            "SELECT * FROM opportunities WHERE source = ? AND external_id = ?",
            [$source, $externalId]
        );
    }

    /**
     * Upsert por (source, external_id). Retorna 'new' | 'known'.
     * Não sobrescreve first_seen_at, status, lead_id.
     */
    public function upsert(array $n)
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->findByExternal($n['source'], $n['external_id']);

        if ($existing) {
            // União dos termos
            $prevTerms = json_decode($existing['matched_terms'] ?? '[]', true) ?: [];
            $mergedTerms = array_values(array_unique(array_merge($prevTerms, $n['matched_terms'] ?? [])));

            $this->db->update('opportunities', [
                'last_seen_at' => $now,
                'proposal_count' => $n['proposal_count'],
                'interested_count' => $n['interested_count'],
                'matched_terms' => json_encode($mergedTerms, JSON_UNESCAPED_UNICODE),
                'score' => $n['score'],
                // Atualiza dados voláteis que podem ter mudado
                'budget_min' => $n['budget_min'],
                'budget_max' => $n['budget_max'],
                'currency' => $n['currency'],
                'client_rating' => $n['client_rating'],
            ], 'id = ?', [$existing['id']]);
            return 'known';
        }

        $this->db->insert('opportunities', [
            'source' => $n['source'],
            'external_id' => $n['external_id'],
            'canonical_url' => $n['canonical_url'],
            'title' => $n['title'],
            'description' => $n['description'],
            'category' => $n['category'],
            'experience_level' => $n['experience_level'],
            'skills' => json_encode($n['skills'] ?? [], JSON_UNESCAPED_UNICODE),
            'budget_min' => $n['budget_min'],
            'budget_max' => $n['budget_max'],
            'currency' => $n['currency'],
            'published_at' => $n['published_at'],
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'proposal_count' => $n['proposal_count'],
            'interested_count' => $n['interested_count'],
            'client_name' => $n['client_name'],
            'client_rating' => $n['client_rating'],
            'score' => $n['score'],
            'status' => 'nova',
            'matched_terms' => json_encode($n['matched_terms'] ?? [], JSON_UNESCAPED_UNICODE),
            'raw_data' => json_encode($n['raw_data'] ?? null, JSON_UNESCAPED_UNICODE),
        ]);
        return 'new';
    }

    /**
     * Lista paginada com filtros. Retorna ['items'=>[], 'total'=>int].
     */
    public function getList($filters = [], $page = 1, $perPage = 50)
    {
        $where = ['1=1'];
        $params = [];

        // status: por padrão esconde ignoradas
        if (!empty($filters['status'])) {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['include_ignored'])) {
            $where[] = "o.status <> 'ignorada'";
        }
        if (!empty($filters['term'])) {
            $where[] = "JSON_SEARCH(o.matched_terms, 'one', ?) IS NOT NULL";
            $params[] = $filters['term'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'o.category = ?';
            $params[] = $filters['category'];
        } else {
            // Sem categoria específica: restringe às categorias ativas (se houver alguma).
            $activeCats = $this->getActiveCategoryNames();
            $allCats = $this->getCategories(false);
            // Só aplica o recorte se nem todas estão ativas (evita filtro desnecessário).
            // Recorte estrito: mostra apenas as categorias ativas (não inclui sem categoria),
            // alinhado com a coleta que só grava categorias selecionadas.
            if (!empty($activeCats) && count($activeCats) < count($allCats)) {
                $ph = implode(',', array_fill(0, count($activeCats), '?'));
                $where[] = "o.category IN ($ph)";
                $params = array_merge($params, $activeCats);
            }
        }
        if (!empty($filters['search'])) {
            $where[] = '(o.title LIKE ? OR o.description LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s;
        }
        if (isset($filters['budget_min']) && $filters['budget_min'] !== '') {
            $where[] = 'o.budget_min >= ?';
            $params[] = (float) $filters['budget_min'];
        }

        // Filtros de exibição vindos das Configurações de Busca (aplicados sempre).
        $settings = $this->getSettings('freelas99');
        if (!empty($settings['max_proposals']) && (int)$settings['max_proposals'] > 0) {
            // Mantém também os projetos sem contagem de propostas (NULL)
            $where[] = '(o.proposal_count IS NULL OR o.proposal_count <= ?)';
            $params[] = (int) $settings['max_proposals'];
        }
        if (!empty($settings['min_budget']) && (float)$settings['min_budget'] > 0) {
            $where[] = 'o.budget_min >= ?';
            $params[] = (float) $settings['min_budget'];
        }
        if (!empty($settings['max_age_days']) && (int)$settings['max_age_days'] > 0) {
            // Usa published_at quando existe; senão, first_seen_at (data de descoberta)
            $where[] = 'COALESCE(o.published_at, o.first_seen_at) >= (NOW() - INTERVAL ? DAY)';
            $params[] = (int) $settings['max_age_days'];
        }

        $whereSql = implode(' AND ', $where);

        // Ordenação
        $orderMap = [
            'first_seen' => 'o.first_seen_at DESC',
            'score' => 'o.score DESC, o.first_seen_at DESC',
            'proposals' => 'o.proposal_count ASC, o.first_seen_at DESC',
            'published' => 'o.published_at DESC, o.first_seen_at DESC',
        ];
        $order = $orderMap[$filters['sort'] ?? 'first_seen'] ?? $orderMap['first_seen'];

        $total = (int) ($this->db->fetch("SELECT COUNT(*) t FROM opportunities o WHERE $whereSql", $params)['t'] ?? 0);

        $perPage = min(100, max(25, (int) $perPage));
        $offset = (max(1, (int) $page) - 1) * $perPage;
        $items = $this->db->fetchAll(
            "SELECT o.*, COALESCE(u.contact_name, u.push_name) AS lead_name
             FROM opportunities o
             LEFT JOIN whatsapp_contacts u ON o.lead_id = u.id
             WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['items' => $items, 'total' => $total, 'per_page' => $perPage];
    }

    /** Contadores por status (para o cabeçalho). */
    public function counts()
    {
        $rows = $this->db->fetchAll("SELECT status, COUNT(*) t FROM opportunities GROUP BY status");
        $out = ['nova' => 0, 'vista' => 0, 'ignorada' => 0, 'convertida' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['t'];
            $out['total'] += (int) $r['t'];
        }
        return $out;
    }

    public function setStatus($id, $status)
    {
        $valid = ['nova', 'vista', 'ignorada', 'convertida'];
        if (!in_array($status, $valid)) return false;
        return $this->db->update('opportunities', ['status' => $status], 'id = ?', [$id]);
    }

    public function markConverted($id, $leadId)
    {
        return $this->db->update('opportunities', ['status' => 'convertida', 'lead_id' => $leadId], 'id = ?', [$id]);
    }

    public function distinctCategories()
    {
        $rows = $this->db->fetchAll("SELECT DISTINCT category FROM opportunities WHERE category IS NOT NULL AND category <> '' ORDER BY category");
        return array_column($rows, 'category');
    }

    // ============ Categorias monitoradas ============

    public function getCategories($onlyActive = false)
    {
        $sql = "SELECT * FROM search_categories";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql);
    }

    public function getActiveCategoryNames()
    {
        $rows = $this->db->fetchAll("SELECT name FROM search_categories WHERE active = 1 ORDER BY name ASC");
        return array_column($rows, 'name');
    }

    /**
     * DESATIVADO: não criamos mais categorias automaticamente durante a coleta.
     * As categorias são um conjunto fixo (seed) que o usuário ativa/desativa nas
     * Configurações. Mantido como no-op para compatibilidade de chamadas antigas.
     */
    public function registerCategory($name)
    {
        // Intencionalmente não faz nada — evita poluir a lista com categorias novas.
        return;
    }

    public function updateCategory($id, $data)
    {
        return $this->db->update('search_categories', $data, 'id = ?', [$id]);
    }

    public function setAllCategories($active)
    {
        return $this->db->query("UPDATE search_categories SET active = ?", [$active ? 1 : 0]);
    }

    // ============ Termos de busca ============

    public function getTerms($onlyActive = false)
    {
        $sql = "SELECT * FROM search_terms";
        if ($onlyActive) $sql .= " WHERE active = 1";
        $sql .= " ORDER BY term ASC";
        return $this->db->fetchAll($sql);
    }

    public function getActiveTermStrings()
    {
        $rows = $this->db->fetchAll("SELECT term FROM search_terms WHERE active = 1 ORDER BY term ASC");
        return array_column($rows, 'term');
    }

    public function addTerm($term)
    {
        $term = trim($term);
        if ($term === '') return false;
        try {
            return $this->db->insert('search_terms', ['term' => $term, 'active' => 1]);
        } catch (\Throwable $e) { return false; }
    }

    public function updateTerm($id, $data)
    {
        return $this->db->update('search_terms', $data, 'id = ?', [$id]);
    }

    public function deleteTerm($id)
    {
        return $this->db->delete('search_terms', 'id = ?', [$id]);
    }

    // ============ Configuração da fonte ============

    public function getSettings($source = 'freelas99')
    {
        $s = $this->db->fetch("SELECT * FROM source_settings WHERE source = ?", [$source]);
        if (!$s) {
            $this->db->insert('source_settings', ['source' => $source, 'enabled' => 1, 'schedule_minutes' => 60, 'max_pages' => 2, 'collect_general' => 0]);
            $s = $this->db->fetch("SELECT * FROM source_settings WHERE source = ?", [$source]);
        }
        return $s;
    }

    public function saveSettings($source, $data)
    {
        return $this->db->update('source_settings', $data, 'source = ?', [$source]);
    }

    // ============ Runs / Health ============

    public function createRun($trigger, $terms)
    {
        return $this->db->insert('collection_runs', [
            'trigger_type' => $trigger,
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'terms_used' => json_encode($terms, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function updateRun($id, $data)
    {
        return $this->db->update('collection_runs', $data, 'id = ?', [$id]);
    }

    public function getRun($id)
    {
        return $this->db->fetch("SELECT * FROM collection_runs WHERE id = ?", [$id]);
    }

    public function recentRuns($limit = 20)
    {
        $limit = (int) $limit;
        return $this->db->fetchAll("SELECT * FROM collection_runs ORDER BY started_at DESC LIMIT $limit");
    }

    public function getHealth($source = 'freelas99')
    {
        $h = $this->db->fetch("SELECT * FROM source_health WHERE source = ?", [$source]);
        if (!$h) {
            $this->db->insert('source_health', ['source' => $source]);
            $h = $this->db->fetch("SELECT * FROM source_health WHERE source = ?", [$source]);
        }
        return $h;
    }

    public function saveHealth($source, $data)
    {
        return $this->db->update('source_health', $data, 'source = ?', [$source]);
    }
}
