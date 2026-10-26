<?php

class PlanningCard
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch(
            "SELECT pc.*, 
                    u.name as assigned_name,
                    tr.name as technical_name,
                    an.name as analyst_name,
                    cb.name as created_by_name,
                    co.name as company_name,
                    t.title as ticket_title,
                    t.category as category,
                    pred.title as predecessor_title
             FROM planning_cards pc
             LEFT JOIN users u ON pc.assigned_to = u.id
             LEFT JOIN users tr ON pc.technical_responsible_id = tr.id
             LEFT JOIN users an ON pc.analyst_id = an.id
             LEFT JOIN users cb ON pc.created_by = cb.id
             LEFT JOIN companies co ON pc.company_id = co.id
             LEFT JOIN tickets t ON pc.ticket_id = t.id
             LEFT JOIN planning_cards pred ON pc.predecessor_id = pred.id
             WHERE pc.id = ?",
            [$id]
        );
    }

    public function getAll($filters = [])
    {
        $sql = "SELECT pc.*, 
                       u.name as assigned_name,
                       co.name as company_name,
                       cb.name as created_by_name,
                       cb.role as created_by_role,
                       t.category as category,
                       t.scope_rejected_reason as scope_rejected_reason
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN tickets t ON pc.ticket_id = t.id
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN users cb ON pc.created_by = cb.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND pc.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['company_id'])) {
            $sql .= " AND pc.company_id = ?";
            $params[] = $filters['company_id'];
        }
        if (!empty($filters['assigned_to'])) {
            // Inclui também os cards SEM atendente (assigned_to IS NULL): demandas
            // novas, ainda não atribuídas, precisam aparecer para a equipe poder
            // pegá-las — senão o filtro de atendente padrão as esconderia.
            $sql .= " AND (pc.assigned_to = ? OR pc.assigned_to IS NULL)";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }
        if (!empty($filters['allowed_companies'])) {
            $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        $sql .= " ORDER BY FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.due_date IS NULL, pc.due_date ASC, pc.position ASC, pc.id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards agrupados por status (colunas do Kanban).
     *
     * @param array $filters Filtros (status, assigned_to, company_id, created_by, q, order, allowed_companies).
     * @param int   $perColumn Limite de cards por coluna (0 = sem limite). Quando > 0,
     *              cada coluna traz no máximo esse tanto; a contagem TOTAL fica em
     *              getColumnCounts() (para o botão "carregar mais" do Kanban). Evita
     *              renderizar centenas de cards de uma vez (lag/altura gigante).
     */
    public function getGroupedByStatus($filters = [], $perColumn = 0)
    {
        $allStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];

        // Filtro de status (múltipla escolha): limita as colunas retornadas.
        $statuses = $allStatuses;
        if (!empty($filters['statuses'])) {
            $selected = array_values(array_intersect($allStatuses, (array)$filters['statuses']));
            if (!empty($selected)) {
                $statuses = $selected;
            }
        }

        // Filtro de responsáveis (múltipla escolha).
        // O valor especial 'none' representa "Sem responsável" (assigned_to IS NULL).
        $assignedIds = [];
        $assignedNone = false;
        if (!empty($filters['assigned_to'])) {
            $rawAssigned = (array)$filters['assigned_to'];
            $assignedNone = in_array('none', $rawAssigned, true);
            $assignedIds = array_values(array_unique(array_filter(array_map('intval', $rawAssigned))));
        }
        if (!empty($filters['assigned_none'])) {
            $assignedNone = true;
        }

        // Filtro de empresas (múltipla escolha).
        $companyIds = [];
        if (!empty($filters['company_id'])) {
            $companyIds = array_values(array_unique(array_filter(array_map('intval', (array)$filters['company_id']))));
        }

        // Filtro de solicitantes/criadores (múltipla escolha).
        $createdByIds = [];
        if (!empty($filters['created_by'])) {
            $createdByIds = array_values(array_unique(array_filter(array_map('intval', (array)$filters['created_by']))));
        }

        $result = [];

        foreach ($statuses as $status) {
            $sql = "SELECT pc.*, 
                           u.name as assigned_name,
                           co.name as company_name,
                           cb.name as created_by_name,
                           t.category as category,
                           t.scope_rejected_reason as scope_rejected_reason,
                           t.scope_approved_at as scope_approved_at
                    FROM planning_cards pc
                    LEFT JOIN users u ON pc.assigned_to = u.id
                    LEFT JOIN companies co ON pc.company_id = co.id
                    LEFT JOIN users cb ON pc.created_by = cb.id
                    LEFT JOIN tickets t ON pc.ticket_id = t.id
                    WHERE pc.status = ?";
            $params = [$status];

            if (!empty($companyIds)) {
                $ph = implode(',', array_fill(0, count($companyIds), '?'));
                $sql .= " AND pc.company_id IN ($ph)";
                $params = array_merge($params, $companyIds);
            }
            if (!empty($assignedIds) && $assignedNone) {
                // Responsáveis selecionados OU sem responsável (assigned_to NULL).
                $ph = implode(',', array_fill(0, count($assignedIds), '?'));
                $sql .= " AND (pc.assigned_to IN ($ph) OR pc.assigned_to IS NULL)";
                $params = array_merge($params, $assignedIds);
            } elseif (!empty($assignedIds)) {
                $ph = implode(',', array_fill(0, count($assignedIds), '?'));
                $sql .= " AND pc.assigned_to IN ($ph)";
                $params = array_merge($params, $assignedIds);
            } elseif ($assignedNone) {
                // Apenas cards sem responsável.
                $sql .= " AND pc.assigned_to IS NULL";
            }
            if (!empty($createdByIds)) {
                $ph = implode(',', array_fill(0, count($createdByIds), '?'));
                $sql .= " AND pc.created_by IN ($ph)";
                $params = array_merge($params, $createdByIds);
            }
            // Busca livre por nome/descrição do card.
            if (isset($filters['q']) && trim((string)$filters['q']) !== '') {
                $term = '%' . trim((string)$filters['q']) . '%';
                $sql .= " AND (pc.title LIKE ? OR pc.description LIKE ?)";
                $params[] = $term;
                $params[] = $term;
            }
            if (!empty($filters['allowed_companies'])) {
                $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
                $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
                $params = array_merge($params, $filters['allowed_companies']);
            }

            $sql .= " ORDER BY " . $this->kanbanOrderBy($filters['order'] ?? null);

            // Limite por coluna (paginação do Kanban): só o 1º lote vem aqui.
            if ($perColumn > 0) {
                $sql .= " LIMIT " . ((int) $perColumn);
            }
            $result[$status] = $this->db->fetchAll($sql, $params);
        }

        return $result;
    }

    /**
     * Cláusula ORDER BY do Kanban conforme a opção de ordenação escolhida.
     * Centralizada para que getGroupedByStatus e getColumnCards ordenem igual.
     */
    private function kanbanOrderBy(?string $order): string
    {
        if ($order === 'overdue') {
            return "CASE WHEN pc.due_date IS NOT NULL AND pc.due_date < NOW() THEN 0 ELSE 1 END, pc.due_date ASC, FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.position ASC, pc.id ASC";
        }
        if ($order === 'newest') {
            return "pc.id DESC";
        }
        // priority (default)
        return "FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.due_date IS NULL, pc.due_date ASC, pc.position ASC, pc.id ASC";
    }

    /**
     * Monta o trecho WHERE (após "pc.status = ?") + os params dos filtros do
     * Kanban, para reaproveitar entre getGroupedByStatus, getColumnCounts e
     * getColumnCards. Recebe os filtros já normalizados pelo chamador.
     *
     * @return array{0:string,1:array} [sqlWhere, params] — params NÃO incluem o status.
     */
    private function kanbanFilterWhere(array $filters): array
    {
        $sql = '';
        $params = [];

        $assignedIds = [];
        $assignedNone = false;
        if (!empty($filters['assigned_to'])) {
            $rawAssigned = (array) $filters['assigned_to'];
            $assignedNone = in_array('none', $rawAssigned, true);
            $assignedIds = array_values(array_unique(array_filter(array_map('intval', $rawAssigned))));
        }
        if (!empty($filters['assigned_none'])) {
            $assignedNone = true;
        }

        $companyIds = [];
        if (!empty($filters['company_id'])) {
            $companyIds = array_values(array_unique(array_filter(array_map('intval', (array) $filters['company_id']))));
        }
        $createdByIds = [];
        if (!empty($filters['created_by'])) {
            $createdByIds = array_values(array_unique(array_filter(array_map('intval', (array) $filters['created_by']))));
        }

        if (!empty($companyIds)) {
            $ph = implode(',', array_fill(0, count($companyIds), '?'));
            $sql .= " AND pc.company_id IN ($ph)";
            $params = array_merge($params, $companyIds);
        }
        if (!empty($assignedIds) && $assignedNone) {
            $ph = implode(',', array_fill(0, count($assignedIds), '?'));
            $sql .= " AND (pc.assigned_to IN ($ph) OR pc.assigned_to IS NULL)";
            $params = array_merge($params, $assignedIds);
        } elseif (!empty($assignedIds)) {
            $ph = implode(',', array_fill(0, count($assignedIds), '?'));
            $sql .= " AND pc.assigned_to IN ($ph)";
            $params = array_merge($params, $assignedIds);
        } elseif ($assignedNone) {
            $sql .= " AND pc.assigned_to IS NULL";
        }
        if (!empty($createdByIds)) {
            $ph = implode(',', array_fill(0, count($createdByIds), '?'));
            $sql .= " AND pc.created_by IN ($ph)";
            $params = array_merge($params, $createdByIds);
        }
        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $term = '%' . trim((string) $filters['q']) . '%';
            $sql .= " AND (pc.title LIKE ? OR pc.description LIKE ?)";
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['allowed_companies'])) {
            $ph = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($ph))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        return [$sql, $params];
    }

    /**
     * Total de cards por status (respeitando os filtros), para o Kanban saber
     * quantos cards existem além do 1º lote renderizado (botão "carregar mais").
     *
     * @return array<string,int> status => total
     */
    public function getColumnCounts($filters = [])
    {
        $allStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        $statuses = $allStatuses;
        if (!empty($filters['statuses'])) {
            $selected = array_values(array_intersect($allStatuses, (array) $filters['statuses']));
            if (!empty($selected)) {
                $statuses = $selected;
            }
        }

        [$where, $baseParams] = $this->kanbanFilterWhere($filters);

        $counts = [];
        foreach ($statuses as $status) {
            $params = array_merge([$status], $baseParams);
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS cnt FROM planning_cards pc WHERE pc.status = ?" . $where,
                $params
            );
            $counts[$status] = (int) ($row['cnt'] ?? 0);
        }
        return $counts;
    }

    /**
     * Um lote de cards de UMA coluna (status), com offset/limit — alimenta o
     * botão "Carregar mais" do Kanban sem recarregar a página.
     */
    public function getColumnCards($status, $filters = [], $offset = 0, $limit = 50)
    {
        [$where, $baseParams] = $this->kanbanFilterWhere($filters);
        $params = array_merge([$status], $baseParams);

        $sql = "SELECT pc.*,
                       u.name as assigned_name,
                       co.name as company_name,
                       cb.name as created_by_name,
                       t.category as category,
                       t.scope_rejected_reason as scope_rejected_reason,
                       t.scope_approved_at as scope_approved_at
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN users cb ON pc.created_by = cb.id
                LEFT JOIN tickets t ON pc.ticket_id = t.id
                WHERE pc.status = ?" . $where;
        $sql .= " ORDER BY " . $this->kanbanOrderBy($filters['order'] ?? null);
        $sql .= " LIMIT " . ((int) $limit) . " OFFSET " . ((int) $offset);

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Lista os solicitantes (usuários que criaram cards), para o filtro.
     * Respeita o controle de acesso por empresa quando informado.
     */
    public function getRequesters($allowedCompanies = null)
    {
        $sql = "SELECT DISTINCT u.id, u.name
                FROM planning_cards pc
                INNER JOIN users u ON pc.created_by = u.id";
        $params = [];

        if ($allowedCompanies !== null && !in_array(0, $allowedCompanies, true)) {
            if (!empty($allowedCompanies)) {
                $ph = implode(',', array_fill(0, count($allowedCompanies), '?'));
                $sql .= " WHERE (pc.company_id IS NULL OR pc.company_id IN ($ph))";
                $params = array_merge($params, $allowedCompanies);
            }
        }

        $sql .= " ORDER BY u.name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getForCalendar($startDate, $endDate, $filters = [])
    {
        // Buscar cards que tenham qualquer data dentro do range:
        // - due_date (prazo/entrega) dentro do range
        // - start_date/end_date (range desenvolvimento) que intersecte o range
        $sql = "SELECT pc.*, 
                       u.name as assigned_name,
                       co.name as company_name
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                WHERE (
                    (pc.due_date IS NOT NULL AND pc.due_date >= ? AND pc.due_date <= ?)
                    OR (pc.start_date IS NOT NULL AND pc.end_date IS NOT NULL AND pc.start_date <= ? AND pc.end_date >= ?)
                    OR (pc.start_date IS NOT NULL AND pc.end_date IS NULL AND pc.start_date >= ? AND pc.start_date <= ?)
                )";
        $params = [$startDate, $endDate, $endDate, $startDate, $startDate, $endDate];

        if (!empty($filters['company_id'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$filters['company_id']))));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND pc.company_id IN ($ph)";
                $params = array_merge($params, $ids);
            }
        }
        if (!empty($filters['assigned_to'])) {
            $rawAssigned = (array)$filters['assigned_to'];
            $assignedNone = in_array('none', $rawAssigned, true);
            $ids = array_values(array_unique(array_filter(array_map('intval', $rawAssigned))));
            if (!empty($ids) && $assignedNone) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND (pc.assigned_to IN ($ph) OR pc.assigned_to IS NULL)";
                $params = array_merge($params, $ids);
            } elseif (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND pc.assigned_to IN ($ph)";
                $params = array_merge($params, $ids);
            } elseif ($assignedNone) {
                $sql .= " AND pc.assigned_to IS NULL";
            }
        }
        if (!empty($filters['statuses'])) {
            $sts = array_values((array)$filters['statuses']);
            if (!empty($sts)) {
                $ph = implode(',', array_fill(0, count($sts), '?'));
                $sql .= " AND pc.status IN ($ph)";
                $params = array_merge($params, $sts);
            }
        }
        // Demandas arquivadas NUNCA aparecem no calendário, mesmo que tenham
        // datas vinculadas (prazo/janela de desenvolvimento). O arquivamento as
        // tira do fluxo ativo, então não devem poluir a visualização de datas.
        $sql .= " AND pc.status <> 'archived'";
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }
        // Busca livre por nome/descrição do card.
        if (isset($filters['q']) && trim((string)$filters['q']) !== '') {
            $term = '%' . trim((string)$filters['q']) . '%';
            $sql .= " AND (pc.title LIKE ? OR pc.description LIKE ?)";
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['allowed_companies'])) {
            $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        $sql .= " ORDER BY pc.start_date ASC, pc.due_date ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards ATIVOS sem NENHUMA data definida (sem prazo, sem início e sem fim).
     * São as demandas que não aparecem no calendário por falta de data e que
     * precisam ser revisadas. Exclui concluídos/negados/arquivados (saíram do
     * fluxo, não precisam de agendamento) e respeita o escopo do usuário (os
     * mesmos filtros de empresa/responsável usados no calendário).
     *
     * @param array $filters company_id, assigned_to ('none' = sem responsável),
     *                        allowed_companies, q (busca).
     */
    public function getUndated($filters = [])
    {
        $sql = "SELECT pc.*, u.name as assigned_name, co.name as company_name
                FROM planning_cards pc
                LEFT JOIN users u ON pc.assigned_to = u.id
                LEFT JOIN companies co ON pc.company_id = co.id
                WHERE pc.due_date IS NULL
                  AND pc.start_date IS NULL
                  AND pc.end_date IS NULL
                  AND pc.status NOT IN ('completed', 'denied', 'archived')";
        $params = [];

        if (!empty($filters['company_id'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$filters['company_id']))));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND pc.company_id IN ($ph)";
                $params = array_merge($params, $ids);
            }
        }
        if (!empty($filters['assigned_to'])) {
            $rawAssigned = (array)$filters['assigned_to'];
            $assignedNone = in_array('none', $rawAssigned, true);
            $ids = array_values(array_unique(array_filter(array_map('intval', $rawAssigned))));
            if (!empty($ids) && $assignedNone) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND (pc.assigned_to IN ($ph) OR pc.assigned_to IS NULL)";
                $params = array_merge($params, $ids);
            } elseif (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $sql .= " AND pc.assigned_to IN ($ph)";
                $params = array_merge($params, $ids);
            } elseif ($assignedNone) {
                $sql .= " AND pc.assigned_to IS NULL";
            }
        }
        if (isset($filters['q']) && trim((string)$filters['q']) !== '') {
            $term = '%' . trim((string)$filters['q']) . '%';
            $sql .= " AND (pc.title LIKE ? OR pc.description LIKE ?)";
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['allowed_companies'])) {
            $placeholders = implode(',', array_fill(0, count($filters['allowed_companies']), '?'));
            $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($placeholders))";
            $params = array_merge($params, $filters['allowed_companies']);
        }

        $sql .= " ORDER BY FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards em atraso: prazo (due_date) no passado e não concluídos/arquivados.
     */
    public function getOverdue($limit = 10)
    {
        return $this->db->fetchAll(
            "SELECT pc.*, u.name as assigned_name, co.name as company_name, t.id as ticket_ref
             FROM planning_cards pc
             LEFT JOIN users u ON pc.assigned_to = u.id
             LEFT JOIN companies co ON pc.company_id = co.id
             LEFT JOIN tickets t ON pc.ticket_id = t.id
             WHERE pc.due_date IS NOT NULL
               AND pc.due_date < NOW()
               AND pc.status NOT IN ('completed', 'archived')
             ORDER BY pc.due_date ASC
             LIMIT " . intval($limit)
        );
    }

    /**
     * Cards em atraso agrupados pelo status atual.
     *
     * Facilita a leitura do dashboard: demandas em homologação já estão, na
     * prática, finalizadas (só aguardando validação), então convém separá-las
     * das que ainda dependem de desenvolvimento (aberto / em andamento).
     *
     * Retorna um array associativo status => [cards], preservando a ordem dos
     * status informados. Só devolve os status que possuem ao menos um card.
     *
     * @param array $statuses Status considerados, na ordem de exibição.
     * @param int   $limitPerStatus Limite de cards por status.
     */
    public function getOverdueGroupedByStatus(
        $statuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao'],
        $limitPerStatus = 10
    ) {
        $statuses = array_values(array_filter((array)$statuses));
        if (empty($statuses)) {
            return [];
        }

        $limit = intval($limitPerStatus);
        $grouped = [];

        foreach ($statuses as $status) {
            $rows = $this->db->fetchAll(
                "SELECT pc.*, u.name as assigned_name, co.name as company_name, t.id as ticket_ref
                 FROM planning_cards pc
                 LEFT JOIN users u ON pc.assigned_to = u.id
                 LEFT JOIN companies co ON pc.company_id = co.id
                 LEFT JOIN tickets t ON pc.ticket_id = t.id
                 WHERE pc.due_date IS NOT NULL
                   AND pc.due_date < NOW()
                   AND pc.status = ?
                 ORDER BY pc.due_date ASC
                 LIMIT " . $limit,
                [$status]
            );

            if (!empty($rows)) {
                $grouped[$status] = $rows;
            }
        }

        return $grouped;
    }

    /**
     * Cards que podem ser escolhidos como PREDECESSOR de um dado card.
     * Exclui o próprio card (não pode depender de si mesmo) e os já concluídos/
     * arquivados/negados (não faz sentido depender de algo encerrado para efeito
     * de cronograma). Respeita o escopo de empresas quando informado.
     *
     * @param int|null   $excludeId         Card a excluir da lista (o próprio).
     * @param array|null $allowedCompanies  IDs de empresas permitidas (ou null).
     */
    public function getSelectableCards($excludeId = null, $allowedCompanies = null, $search = null, $limit = 30)
    {
        $sql = "SELECT pc.id, pc.title, pc.status, pc.due_date, pc.company_id, co.name AS company_name
                FROM planning_cards pc
                LEFT JOIN companies co ON pc.company_id = co.id
                WHERE pc.status NOT IN ('completed', 'denied', 'archived')";
        $params = [];
        if ($excludeId) {
            $sql .= " AND pc.id <> ?";
            $params[] = $excludeId;
        }
        // Busca por título, nº do card (#123) ou empresa — alimenta o autocomplete.
        if ($search !== null && trim((string) $search) !== '') {
            $term = trim((string) $search);
            $sql .= " AND (pc.title LIKE ? OR co.name LIKE ?";
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
            if (ctype_digit(ltrim($term, '#'))) {
                $sql .= " OR pc.id = ?";
                $params[] = (int) ltrim($term, '#');
            }
            $sql .= ")";
        }
        if (is_array($allowedCompanies) && !in_array(0, $allowedCompanies, true)) {
            if (empty($allowedCompanies)) {
                $sql .= " AND pc.company_id IS NULL";
            } else {
                $ph = implode(',', array_fill(0, count($allowedCompanies), '?'));
                $sql .= " AND (pc.company_id IS NULL OR pc.company_id IN ($ph))";
                $params = array_merge($params, $allowedCompanies);
            }
        }
        $sql .= " ORDER BY pc.id DESC";
        $sql .= " LIMIT " . ((int) $limit);
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cards que têm $cardId como predecessor (dependentes diretos). Usado no
     * recálculo em cascata quando a data do predecessor muda.
     */
    public function getDependents($cardId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM planning_cards WHERE predecessor_id = ?",
            [$cardId]
        );
    }

    /**
     * Todos os dependentes de $cardId em cascata (dependentes diretos + os
     * deles, recursivamente), como mapa id => linha do card. Usado para
     * capturar o estado ANTES do recálculo em cascata e depois registrar no
     * histórico de cada card afetado o que mudou. Protegido contra ciclos.
     */
    public function getDependentsDeep($cardId, array &$seen = [])
    {
        $result = [];
        foreach ($this->getDependents($cardId) as $dep) {
            $depId = (int) $dep['id'];
            if (isset($seen[$depId])) {
                continue;
            }
            $seen[$depId] = true;
            $result[$depId] = $dep;
            foreach ($this->getDependentsDeep($depId, $seen) as $k => $v) {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    /**
     * Mapa [id => predecessor_id] de todos os cards, para a detecção de ciclos
     * em PlanningRules::wouldCreateCycle sem múltiplas idas ao banco.
     */
    public function getPredecessorMap()
    {
        $rows = $this->db->fetchAll("SELECT id, predecessor_id FROM planning_cards");
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r['predecessor_id'] !== null ? (int) $r['predecessor_id'] : null;
        }
        return $map;
    }

    /**
     * Recalcula em cascata as datas dos cards que dependem de $cardId (e dos
     * dependentes deles, recursivamente), a partir do end_date do predecessor.
     *
     * Para cada dependente: PlanningRules::recalcDependentDates define o novo
     * start_date/end_date preservando a duração e respeitando o due_date como
     * teto. Só grava (e desce a cascata) quando a data efetivamente muda, o que
     * naturalmente interrompe a propagação quando nada mais se desloca.
     *
     * Proteção contra ciclos pré-existentes: um conjunto de visitados impede
     * reprocessar o mesmo card (evita laço infinito mesmo com dados ruins).
     *
     * @param int   $cardId  Card cujo término acabou de mudar (o predecessor).
     * @return array Lista de cards afetados já atualizados (via findById), para
     *               o front refletir no board sem reload.
     */
    /**
     * Cache em memória (por requisição) dos recessos aplicáveis a cada empresa,
     * para a cascata não repetir a mesma query a cada dependente. Chave:
     * company_id ('global' quando null). Valor: lista de períodos.
     */
    private $recessCache = [];

    /**
     * Recessos aplicáveis a um card da empresa $companyId: globais + da empresa.
     * Tolerante a ambientes antigos sem a tabela recess_periods (retorna []).
     */
    private function recessPeriodsFor($companyId)
    {
        $key = $companyId === null ? 'global' : (string) (int) $companyId;
        if (array_key_exists($key, $this->recessCache)) {
            return $this->recessCache[$key];
        }
        $periods = [];
        try {
            $model = new RecessPeriod();
            $periods = $model->getApplicable($companyId !== null ? (int) $companyId : null);
        } catch (\Throwable $e) {
            // Tabela ainda não existe (migration 165 não aplicada) ou erro de
            // consulta: sem recesso, a cascata segue com o comportamento antigo.
            $periods = [];
        }
        $this->recessCache[$key] = $periods;
        return $periods;
    }

    public function cascadeRecalcDependents($cardId, array &$visited = [])
    {
        $affected = [];
        if (isset($visited[$cardId])) {
            return $affected;
        }
        $visited[$cardId] = true;

        $predecessor = $this->findById($cardId);
        if (!$predecessor) {
            return $affected;
        }
        $predEnd = $predecessor['end_date'] ?? null;
        if (empty($predEnd)) {
            // Sem término no predecessor não há âncora; não propaga.
            return $affected;
        }

        foreach ($this->getDependents($cardId) as $dep) {
            $lag = (int) ($dep['predecessor_lag_days'] ?? 0);
            // Recessos aplicáveis à empresa do dependente (globais + da empresa).
            $recess = $this->recessPeriodsFor($dep['company_id'] ?? null);
            $newDates = PlanningRules::recalcDependentDatesWithRecess(
                $predEnd,
                $dep['start_date'] ?? null,
                $dep['end_date'] ?? null,
                $dep['due_date'] ?? null,
                $lag,
                $recess
            );
            if ($newDates === null) {
                continue;
            }

            $before = [
                'start_date' => PlanningRules::normalizeDateTime($dep['start_date'] ?? null),
                'end_date'   => PlanningRules::normalizeDateTime($dep['end_date'] ?? null),
            ];
            $after = [
                'start_date' => PlanningRules::normalizeDateTime($newDates['start_date']),
                'end_date'   => PlanningRules::normalizeDateTime($newDates['end_date']),
            ];

            // Só grava se algo mudou de fato (evita UPDATEs e cascata inúteis).
            if ($before['start_date'] === $after['start_date'] && $before['end_date'] === $after['end_date']) {
                continue;
            }

            $this->update($dep['id'], [
                'start_date' => $newDates['start_date'],
                'end_date'   => $newDates['end_date'],
            ]);
            $affected[] = $this->findById($dep['id']);

            // Desce a cascata: os dependentes deste dependente também se ajustam.
            $deeper = $this->cascadeRecalcDependents($dep['id'], $visited);
            if (!empty($deeper)) {
                $affected = array_merge($affected, $deeper);
            }
        }

        return $affected;
    }

    public function create($data)
    {
        return $this->db->insert('planning_cards', $data);
    }

    public function update($id, $data)
    {
        return $this->db->update('planning_cards', $data, 'id = ?', [$id]);
    }

    public function updateStatus($id, $status)
    {
        return $this->db->update('planning_cards', ['status' => $status], 'id = ?', [$id]);
    }

    public function updatePosition($id, $position, $status = null)
    {
        $data = ['position' => $position];
        if ($status) {
            $data['status'] = $status;
        }
        return $this->db->update('planning_cards', $data, 'id = ?', [$id]);
    }

    /**
     * Reordena todos os cards de uma coluna (status) com posições sequenciais.
     * Recebe os IDs na ordem desejada e atribui position = 0, 1, 2, ...
     */
    public function reorderCards($ids, $status)
    {
        foreach ($ids as $position => $id) {
            $this->db->update('planning_cards', [
                'position' => $position,
                'status' => $status,
            ], 'id = ?', [$id]);
        }
    }

    public function delete($id)
    {
        return $this->db->delete('planning_cards', 'id = ?', [$id]);
    }

    /**
     * Exclui permanentemente o card e, se houver, a demanda (ticket) vinculada
     * junto com suas mensagens, anexos e notas internas.
     */
    public function deletePermanent($id, $ticketId = null)
    {
        // Remover o card de planejamento (comentários/anexos saem via cascade)
        $this->db->delete('planning_cards', 'id = ?', [$id]);

        if ($ticketId) {
            // Remover dependências do ticket antes do próprio ticket
            $this->db->delete('ticket_messages', 'ticket_id = ?', [$ticketId]);
            $this->db->delete('ticket_attachments', 'ticket_id = ?', [$ticketId]);
            $this->db->query("DELETE FROM ticket_internal_notes WHERE ticket_id = ?", [$ticketId]);
            $this->db->query("DELETE FROM notifications WHERE ticket_id = ?", [$ticketId]);
            $this->db->delete('tickets', 'id = ?', [$ticketId]);
        }

        return true;
    }

    // Criar card automaticamente a partir de um ticket
    /**
     * Cria um card de planejamento a partir de um ticket.
     *
     * @param array $ticket
     * @param int|null $companyIdOverride Quando informado (> 0), usa essa empresa
     *   no card em vez de derivar da empresa do usuário cliente. Usado pelo fluxo
     *   de solicitação externa: lá o "cliente" do ticket é o atendente dono do
     *   PIN, então a empresa vem da seleção feita pelo solicitante (quando ela
     *   existe no cadastro).
     */
    public function createFromTicket($ticket, $companyIdOverride = null)
    {
        $companyId = $companyIdOverride ?: null;
        if (!$companyId) {
            $clientUser = $this->db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
            $companyId = $clientUser['company_id'] ?? null;
        }

        return $this->create([
            'ticket_id' => $ticket['id'],
            'title' => $ticket['title'],
            'description' => '<p>' . nl2br(htmlspecialchars($ticket['description'])) . '</p>',
            'company_id' => $companyId,
            'assigned_to' => $ticket['attendant_id'] ?? null,
            'technical_responsible_id' => $ticket['technical_responsible_id'] ?? null,
            'created_by' => $ticket['client_id'],
            'priority' => $ticket['priority'],
            'status' => $ticket['status'] ?? 'open',
            'position' => 0,
        ]);
    }

    // Sincronizar status do card quando ticket muda
    public function syncFromTicket($ticketId, $status)
    {
        $card = $this->db->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$ticketId]);
        if ($card) {
            $this->updateStatus($card['id'], $status);
        }
    }

    // Comentários
    public function getComments($cardId)
    {
        return $this->db->fetchAll(
            "SELECT pc.*, u.name as user_name, u.avatar as user_avatar
             FROM planning_comments pc
             LEFT JOIN users u ON pc.user_id = u.id
             WHERE pc.card_id = ?
             ORDER BY pc.created_at ASC",
            [$cardId]
        );
    }

    public function addComment($cardId, $userId, $message)
    {
        return $this->db->insert('planning_comments', [
            'card_id' => $cardId,
            'user_id' => $userId,
            'message' => $message,
        ]);
    }

    // Histórico de alterações do card ---------------------------------------

    /**
     * Lista o histórico de alterações de um card, do mais recente ao mais
     * antigo, com o nome de quem alterou. Tolerante a ambiente sem a tabela
     * (migration 166 não aplicada): retorna [] em vez de quebrar.
     */
    public function getHistory($cardId)
    {
        try {
            return $this->db->fetchAll(
                "SELECT h.*, u.name AS user_name
                 FROM planning_card_history h
                 LEFT JOIN users u ON h.user_id = u.id
                 WHERE h.card_id = ?
                 ORDER BY h.created_at DESC, h.id DESC",
                [$cardId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Grava um lote de alterações no histórico. $changes é a saída de
     * PlanningRules::diffCardChanges (cada item com field/label/old/new).
     * $source = 'manual' (edição) ou 'cascade' (recálculo automático).
     * Silencioso se a tabela não existir (não bloqueia o salvamento do card).
     *
     * @param array $changes Lista de mudanças já formatadas para exibição.
     */
    public function logHistory($cardId, $userId, array $changes, $source = 'manual')
    {
        if (empty($changes)) {
            return;
        }
        foreach ($changes as $c) {
            try {
                $this->db->insert('planning_card_history', [
                    'card_id'   => $cardId,
                    'user_id'   => $userId,
                    'field'     => substr((string) ($c['label'] ?? $c['field'] ?? ''), 0, 64),
                    'old_value' => $c['old'] ?? null,
                    'new_value' => $c['new'] ?? null,
                    'source'    => $source === 'cascade' ? 'cascade' : 'manual',
                ]);
            } catch (\Throwable $e) {
                // Tabela ausente ou erro pontual: não impede o fluxo do card.
                return;
            }
        }
    }

    // Anexos
    public function getAttachments($cardId)
    {
        return $this->db->fetchAll(
            "SELECT pa.*, u.name as user_name
             FROM planning_attachments pa
             LEFT JOIN users u ON pa.user_id = u.id
             WHERE pa.card_id = ?
             ORDER BY pa.created_at DESC",
            [$cardId]
        );
    }

    public function addAttachment($data)
    {
        return $this->db->insert('planning_attachments', $data);
    }

    public function deleteAttachment($id)
    {
        return $this->db->delete('planning_attachments', 'id = ?', [$id]);
    }

    public function findAttachment($id)
    {
        return $this->db->fetch("SELECT * FROM planning_attachments WHERE id = ?", [$id]);
    }

    // Obter empresas que o usuário tem acesso
    public static function getUserAllowedCompanies($userId, $role)
    {
        // Super admin tem acesso a tudo
        if ($role === 'super_admin') {
            return null; // null = sem restrição
        }

        $db = Database::getInstance();

        // Usuário marcado para "sempre ver todas as empresas" — sem restrição
        $u = $db->fetch("SELECT see_all_companies FROM users WHERE id = ?", [$userId]);
        if (!empty($u['see_all_companies'])) {
            return null;
        }

        $rows = $db->fetchAll("SELECT company_id FROM user_company_access WHERE user_id = ?", [$userId]);

        if (empty($rows)) {
            // Se não tem nenhum registro, não tem acesso a nenhuma empresa específica
            // mas pode ver cards sem empresa
            return [0]; // retorna 0 para filtrar apenas cards sem company
        }

        return array_column($rows, 'company_id');
    }

    // Gerenciar acesso do usuário a empresas
    public static function setUserCompanyAccess($userId, $companyIds)
    {
        $db = Database::getInstance();
        // Remover acessos antigos
        $db->delete('user_company_access', 'user_id = ?', [$userId]);
        // Inserir novos (sem duplicatas)
        $seen = [];
        foreach ($companyIds as $companyId) {
            $companyId = (int)$companyId;
            if ($companyId && !isset($seen[$companyId])) {
                $seen[$companyId] = true;
                $db->insert('user_company_access', [
                    'user_id' => $userId,
                    'company_id' => $companyId,
                ]);
            }
        }
    }

    public static function getUserCompanyAccessIds($userId)
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT company_id FROM user_company_access WHERE user_id = ?", [$userId]);
        return array_column($rows, 'company_id');
    }

    // ========================
    // TASKS INTERNAS DO CARD
    // ========================

    public function getTasks($cardId)
    {
        $tasks = $this->db->fetchAll(
            "SELECT pt.*, u.name as created_by_name, cu.name as completed_by_name
             FROM planning_tasks pt
             LEFT JOIN users u ON pt.created_by = u.id
             LEFT JOIN users cu ON pt.completed_by = cu.id
             WHERE pt.card_id = ?
             ORDER BY pt.position ASC, pt.created_at ASC",
            [$cardId]
        );

        // Carregar imagens de cada task
        foreach ($tasks as &$task) {
            $task['images'] = $this->getTaskImages($task['id']);
        }

        return $tasks;
    }

    public function findTask($taskId)
    {
        return $this->db->fetch("SELECT * FROM planning_tasks WHERE id = ?", [$taskId]);
    }

    public function createTask($data)
    {
        return $this->db->insert('planning_tasks', $data);
    }

    public function updateTask($taskId, $data)
    {
        return $this->db->update('planning_tasks', $data, 'id = ?', [$taskId]);
    }

    public function toggleTaskComplete($taskId, $userId)
    {
        $task = $this->findTask($taskId);
        if (!$task) return false;

        if ($task['is_completed']) {
            // Desmarcar
            return $this->db->update('planning_tasks', [
                'is_completed' => 0,
                'completed_by' => null,
                'completed_at' => null,
            ], 'id = ?', [$taskId]);
        } else {
            // Marcar como concluída
            return $this->db->update('planning_tasks', [
                'is_completed' => 1,
                'completed_by' => $userId,
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
        }
    }

    public function deleteTask($taskId)
    {
        return $this->db->delete('planning_tasks', 'id = ?', [$taskId]);
    }

    // Imagens das tasks
    public function getTaskImages($taskId)
    {
        return $this->db->fetchAll(
            "SELECT pti.*, u.name as user_name
             FROM planning_task_images pti
             LEFT JOIN users u ON pti.user_id = u.id
             WHERE pti.task_id = ?
             ORDER BY pti.created_at ASC",
            [$taskId]
        );
    }

    public function addTaskImage($data)
    {
        return $this->db->insert('planning_task_images', $data);
    }

    public function findTaskImage($imageId)
    {
        return $this->db->fetch("SELECT * FROM planning_task_images WHERE id = ?", [$imageId]);
    }

    public function deleteTaskImage($imageId)
    {
        return $this->db->delete('planning_task_images', 'id = ?', [$imageId]);
    }

    public function getTaskCountForCard($cardId)
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total, SUM(is_completed) as completed FROM planning_tasks WHERE card_id = ?",
            [$cardId]
        );
        return [
            'total' => (int)($result['total'] ?? 0),
            'completed' => (int)($result['completed'] ?? 0),
        ];
    }

    /**
     * Busca TODOS os cards de planejamento vinculados à empresa do cliente.
     * Busca por múltiplos caminhos:
     * 1. Cards com company_id = empresa do cliente
     * 2. Cards vinculados a tickets criados por usuários da empresa
     * 3. Cards criados por usuários da empresa
     * Retorna apenas campos não-sensíveis.
     */
    public function getAllForClientCompany($companyId, $filters = [])
    {
        $sql = "SELECT DISTINCT pc.id, pc.title, pc.status, pc.priority,
                       pc.client_start_date, pc.client_end_date, pc.due_date,
                       co.name as company_name
                FROM planning_cards pc
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN tickets t ON pc.ticket_id = t.id
                LEFT JOIN users tc ON t.client_id = tc.id
                LEFT JOIN users cb ON pc.created_by = cb.id
                WHERE (
                    pc.company_id = ?
                    OR tc.company_id = ?
                    OR (cb.company_id = ? AND cb.role = 'client')
                )";
        $params = [$companyId, $companyId, $companyId];

        if (!empty($filters['status'])) {
            $sql .= " AND pc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND pc.priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }

        $sql .= " ORDER BY FIELD(pc.priority, 'urgent', 'high', 'medium', 'low'), pc.id DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Cronograma exibido ao cliente: apenas cards da empresa do cliente que
     * possuem cronograma definido (client_start_date). Traz os campos
     * necessários para o calendário e o gráfico de Gantt no painel do cliente.
     *
     * Expõe somente informação não sensível (título, status, prioridade e as
     * datas do cronograma do cliente) — nunca datas internas de desenvolvimento
     * (start_date/end_date) nem responsáveis.
     */
    public function getClientSchedule($companyId, $filters = [])
    {
        $sql = "SELECT DISTINCT pc.id, pc.title, pc.status, pc.priority,
                       pc.client_start_date, pc.client_end_date,
                       co.name as company_name
                FROM planning_cards pc
                LEFT JOIN companies co ON pc.company_id = co.id
                LEFT JOIN tickets t ON pc.ticket_id = t.id
                LEFT JOIN users tc ON t.client_id = tc.id
                LEFT JOIN users cb ON pc.created_by = cb.id
                WHERE pc.client_start_date IS NOT NULL
                  AND (
                    pc.company_id = ?
                    OR tc.company_id = ?
                    OR (cb.company_id = ? AND cb.role = 'client')
                  )";
        $params = [$companyId, $companyId, $companyId];

        if (!empty($filters['status'])) {
            $sql .= " AND pc.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['hide_completed'])) {
            $sql .= " AND pc.status NOT IN ('completed', 'archived')";
        }

        $sql .= " ORDER BY pc.client_start_date ASC, pc.id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Detalhe de um card para o cliente, validando que ele pertence à empresa
     * informada. Retorna apenas campos não sensíveis (sem responsáveis internos,
     * notas internas ou referências de CX Hub). Devolve null se o card não
     * existir ou não pertencer à empresa do cliente.
     */
    public function getClientCardDetail($cardId, $companyId)
    {
        return $this->db->fetch(
            "SELECT pc.id, pc.title, pc.description, pc.status, pc.priority,
                    pc.client_start_date, pc.client_end_date, pc.created_at,
                    co.name as company_name, pc.ticket_id
             FROM planning_cards pc
             LEFT JOIN companies co ON pc.company_id = co.id
             LEFT JOIN tickets t ON pc.ticket_id = t.id
             LEFT JOIN users tc ON t.client_id = tc.id
             LEFT JOIN users cb ON pc.created_by = cb.id
             WHERE pc.id = ?
               AND (
                    pc.company_id = ?
                    OR tc.company_id = ?
                    OR (cb.company_id = ? AND cb.role = 'client')
               )
             LIMIT 1",
            [$cardId, $companyId, $companyId, $companyId]
        );
    }
}
