<?php

/**
 * Staging dos leads capturados via Apollo.io.
 * Guarda os prospects pesquisados/enriquecidos e controla a importação
 * para "Meus Leads" (whatsapp_contacts).
 */
class ApolloLead
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById($id)
    {
        return $this->db->fetch("SELECT * FROM apollo_leads WHERE id = ?", [$id]);
    }

    public function findByApolloId($apolloId)
    {
        return $this->db->fetch("SELECT * FROM apollo_leads WHERE apollo_id = ?", [$apolloId]);
    }

    public function update($id, array $data)
    {
        return $this->db->update('apollo_leads', $data, 'id = ?', [$id]);
    }

    /**
     * Cria ou atualiza (upsert) um lead a partir do payload do Apollo.
     * Retorna o id local do registro.
     */
    public function upsertFromApollo(array $person, $userId = null)
    {
        $apolloId = $person['id'] ?? null;
        if (!$apolloId) return null;

        $org = $person['organization'] ?? ($person['account'] ?? []);
        $data = [
            'apollo_id' => $apolloId,
            'first_name' => $person['first_name'] ?? null,
            'last_name' => $person['last_name'] ?? null,
            'full_name' => $person['name'] ?? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')) ?: null,
            'title' => $person['title'] ?? null,
            'seniority' => $person['seniority'] ?? null,
            'email' => $this->extractEmail($person),
            'email_status' => $person['email_status'] ?? null,
            'phone' => $this->extractPhone($person),
            'linkedin_url' => $person['linkedin_url'] ?? null,
            'organization_name' => $org['name'] ?? ($person['organization_name'] ?? null),
            'organization_domain' => $org['primary_domain'] ?? ($org['domain'] ?? null),
            'organization_website' => $org['website_url'] ?? null,
            'organization_industry' => $org['industry'] ?? null,
            'city' => $person['city'] ?? null,
            'state' => $person['state'] ?? null,
            'country' => $person['country'] ?? null,
            'raw_json' => json_encode($person, JSON_UNESCAPED_UNICODE),
        ];

        // Considera "enriquecido" quando já veio e-mail real ou telefone
        $hasRealEmail = !empty($data['email']) && stripos($data['email'], 'email_not_unlocked') === false;
        if ($hasRealEmail || !empty($data['phone'])) {
            $data['is_enriched'] = 1;
        }

        $existing = $this->findByApolloId($apolloId);
        if ($existing) {
            // Não sobrescreve com nulos: mantém dados já revelados
            $update = array_filter($data, fn($v) => $v !== null && $v !== '');
            unset($update['apollo_id']);
            $this->db->update('apollo_leads', $update, 'id = ?', [$existing['id']]);
            return $existing['id'];
        }

        $data['created_by'] = $userId;
        return $this->db->insert('apollo_leads', $data);
    }

    /**
     * Extrai o e-mail real (revelado) do payload do Apollo, ignorando
     * placeholders de e-mail bloqueado. Verifica os vários campos possíveis.
     */
    private function extractEmail(array $person)
    {
        $isReal = function ($e) {
            return !empty($e)
                && stripos($e, 'email_not_unlocked') === false
                && stripos($e, 'domain.com') === false
                && filter_var($e, FILTER_VALIDATE_EMAIL);
        };
        if ($isReal($person['email'] ?? null)) return $person['email'];
        if (!empty($person['contact']) && $isReal($person['contact']['email'] ?? null)) {
            return $person['contact']['email'];
        }
        foreach (['personal_emails', 'contact_emails'] as $key) {
            if (!empty($person[$key]) && is_array($person[$key])) {
                foreach ($person[$key] as $item) {
                    $val = is_array($item) ? ($item['email'] ?? null) : $item;
                    if ($isReal($val)) return $val;
                }
            }
        }
        // Mantém o placeholder original (ex.: email_not_unlocked) apenas se nada real veio,
        // para o controller saber que ainda está bloqueado.
        return $person['email'] ?? null;
    }

    /**
     * Extrai o primeiro telefone disponível do payload do Apollo.
     */
    private function extractPhone(array $person)
    {
        if (!empty($person['phone_numbers']) && is_array($person['phone_numbers'])) {
            foreach ($person['phone_numbers'] as $ph) {
                if (!empty($ph['sanitized_number'])) return $ph['sanitized_number'];
                if (!empty($ph['raw_number'])) return $ph['raw_number'];
            }
        }
        return $person['sanitized_phone'] ?? ($person['phone'] ?? null);
    }

    /**
     * Lista os leads capturados (staging), com filtros simples.
     * $filters: ['search'=>, 'status'=>'imported'|'pending'|'enriched', 'limit'=>, 'offset'=>]
     */
    public function getList($filters = [])
    {
        $sql = "SELECT a.*, u.name AS imported_by_name
                FROM apollo_leads a
                LEFT JOIN users u ON a.imported_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (a.full_name LIKE ? OR a.email LIKE ? OR a.organization_name LIKE ? OR a.title LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }
        if (($filters['status'] ?? '') === 'imported') {
            $sql .= " AND a.imported_at IS NOT NULL";
        } elseif (($filters['status'] ?? '') === 'pending') {
            $sql .= " AND a.imported_at IS NULL";
        } elseif (($filters['status'] ?? '') === 'enriched') {
            $sql .= " AND a.is_enriched = 1";
        }

        $sql .= " ORDER BY a.created_at DESC";
        $limit = min(200, max(1, intval($filters['limit'] ?? 50)));
        $offset = max(0, intval($filters['offset'] ?? 0));
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    public function countList($filters = [])
    {
        $sql = "SELECT COUNT(*) AS t FROM apollo_leads a WHERE 1=1";
        $params = [];
        if (!empty($filters['search'])) {
            $sql .= " AND (a.full_name LIKE ? OR a.email LIKE ? OR a.organization_name LIKE ? OR a.title LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }
        if (($filters['status'] ?? '') === 'imported') {
            $sql .= " AND a.imported_at IS NOT NULL";
        } elseif (($filters['status'] ?? '') === 'pending') {
            $sql .= " AND a.imported_at IS NULL";
        } elseif (($filters['status'] ?? '') === 'enriched') {
            $sql .= " AND a.is_enriched = 1";
        }
        return (int)($this->db->fetch($sql, $params)['t'] ?? 0);
    }

    /**
     * Marca o lead como importado, vinculando ao contato do CRM.
     */
    public function markImported($id, $contactId, $userId)
    {
        return $this->db->update('apollo_leads', [
            'contact_id' => $contactId,
            'imported_at' => date('Y-m-d H:i:s'),
            'imported_by' => $userId,
        ], 'id = ?', [$id]);
    }
}
