<?php

/**
 * Cliente da API Apollo.io (REST v1).
 *
 * Documentação: https://docs.apollo.io/reference/apollo-api
 *
 * Autenticação: chave passada no header `x-api-key` em toda requisição.
 * Base URL padrão: https://api.apollo.io/api/v1
 *
 * Configuração (tabela settings):
 *  - apollo_api_key   (x-api-key)  [SECRETO — nunca retornar ao frontend]
 *  - apollo_base_url  (opcional; default https://api.apollo.io/api/v1)
 *
 * Convenções desta classe:
 *  - Todos os métodos retornam: ['success'=>bool, 'status'=>int, 'data'=>mixed, 'error'=>?]
 *  - Filtros de array (ex.: person_titles[]) são enviados como arrays no corpo JSON.
 */
class ApolloApi
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) Config::get('apollo_api_key');
        $base = (string) Config::get('apollo_base_url');
        $this->baseUrl = rtrim($base !== '' ? $base : 'https://api.apollo.io/api/v1', '/');
    }

    public function isConfigured()
    {
        return $this->apiKey !== '';
    }

    // =====================================================
    // AUTENTICAÇÃO / SAÚDE
    // =====================================================

    /**
     * Verifica se a chave é válida.
     * GET /auth/health
     */
    public function health()
    {
        return $this->request('GET', '/auth/health');
    }

    /**
     * Perfil da API (limites/uso).
     * GET /users/api_profile
     */
    public function apiProfile()
    {
        return $this->request('GET', '/users/api_profile');
    }

    // =====================================================
    // PESSOAS (PROSPECTS)
    // =====================================================

    /**
     * People API Search — busca de novos prospects (não retorna e-mail/telefone).
     * POST /mixed_people/search
     *
     * Aceita todos os filtros documentados. Passe apenas os preenchidos.
     * Filtros suportados (chaves de $filters):
     *  - q_keywords (string)
     *  - person_titles (array), include_similar_titles (bool)
     *  - person_seniorities (array)
     *  - person_locations (array)
     *  - organization_locations (array)
     *  - q_organization_domains_list (array)
     *  - contact_email_status (array): verified|unverified|likely_to_engage|unavailable
     *  - organization_ids (array)
     *  - organization_num_employees_ranges (array de "min,max")
     *  - revenue_range: ['min'=>int,'max'=>int]
     *  - currently_using_all_of_technology_uids (array)
     *  - currently_using_any_of_technology_uids (array)
     *  - currently_not_using_any_of_technology_uids (array)
     *  - q_organization_job_titles (array)
     *  - organization_job_locations (array)
     *  - organization_num_jobs_range: ['min'=>int,'max'=>int]
     *  - organization_job_posted_at_range: ['min'=>'Y-m-d','max'=>'Y-m-d']
     *  - page (int), per_page (int)
     */
    public function searchPeople($filters = [])
    {
        $payload = $this->buildPeopleSearchPayload($filters);
        return $this->request('POST', '/mixed_people/search', $payload);
    }

    /**
     * People Enrichment — enriquece 1 pessoa (revela e-mail/telefone conforme flags).
     * POST /people/match
     *
     * $params aceita (documentado):
     *  - first_name, last_name, name, email, hashed_email
     *  - organization_name, domain
     *  - id (apollo person id), linkedin_url
     *  - reveal_personal_emails (bool)
     *  - reveal_phone_number (bool)  -> exige webhook_url
     *  - webhook_url (string)
     */
    public function enrichPerson($params = [])
    {
        return $this->request('POST', '/people/match', $this->cleanPayload($params));
    }

    /**
     * Bulk People Enrichment — enriquece até 10 pessoas.
     * POST /people/bulk_match
     *
     * @param array $details  Lista (até 10) de objetos de identificação da pessoa.
     * @param array $options  reveal_personal_emails, reveal_phone_number, webhook_url,
     *                        run_waterfall_email, run_waterfall_phone
     */
    public function bulkEnrichPeople(array $details, array $options = [])
    {
        $payload = array_merge($this->cleanPayload($options), [
            'details' => array_values($details),
        ]);
        return $this->request('POST', '/people/bulk_match', $payload);
    }

    /**
     * Get Complete Person Info — detalhes completos de uma pessoa.
     * GET /people/{id}
     */
    public function getPerson($apolloId)
    {
        return $this->request('GET', '/people/' . rawurlencode($apolloId));
    }

    /**
     * Poll Webhook Result — consulta resultado assíncrono (telefone/waterfall).
     * GET /people/match/poll?request_id=...
     */
    public function pollWebhookResult($requestId)
    {
        return $this->request('GET', '/people/match/poll?request_id=' . rawurlencode($requestId));
    }

    // =====================================================
    // ORGANIZAÇÕES (EMPRESAS)
    // =====================================================

    /**
     * Organization Search — busca empresas.
     * POST /mixed_companies/search
     *
     * Filtros suportados (chaves de $filters):
     *  - q_organization_name (string)
     *  - q_organization_keyword_tags (array)  (keywords)
     *  - q_organization_domains_list (array)
     *  - organization_locations (array)
     *  - organization_not_locations (array)
     *  - organization_num_employees_ranges (array de "min,max")
     *  - revenue_range: ['min'=>int,'max'=>int]
     *  - currently_using_any_of_technology_uids (array)
     *  - organization_ids (array)
     *  - latest_funding_amount_range: ['min'=>int,'max'=>int]
     *  - total_funding_range: ['min'=>int,'max'=>int]
     *  - latest_funding_date_range: ['min'=>'Y-m-d','max'=>'Y-m-d']
     *  - q_organization_job_titles (array)
     *  - organization_job_locations (array)
     *  - organization_num_jobs_range: ['min'=>int,'max'=>int]
     *  - organization_job_posted_at_range: ['min'=>'Y-m-d','max'=>'Y-m-d']
     *  - page (int), per_page (int)
     */
    public function searchOrganizations($filters = [])
    {
        $payload = $this->buildOrganizationSearchPayload($filters);
        return $this->request('POST', '/mixed_companies/search', $payload);
    }

    /**
     * Organization Enrichment — enriquece dados de uma empresa por domínio.
     * GET /organizations/enrich?domain=...
     */
    public function enrichOrganization($domain)
    {
        return $this->request('GET', '/organizations/enrich?domain=' . rawurlencode($domain));
    }

    /**
     * Get a list of email accounts / opcional: usados por outras integrações.
     * Mantido para completude do módulo.
     * GET /organizations/{id}/job_postings
     */
    public function getOrganizationJobPostings($organizationId)
    {
        return $this->request('GET', '/organizations/' . rawurlencode($organizationId) . '/job_postings');
    }

    // =====================================================
    // MONTAGEM DE PAYLOADS
    // =====================================================

    /**
     * Constrói o corpo da busca de pessoas a partir de filtros amigáveis,
     * convertendo apenas o que foi preenchido para as chaves oficiais do Apollo.
     */
    private function buildPeopleSearchPayload($f)
    {
        $p = [];

        // Arrays simples (parâmetros [] do Apollo)
        $arrayMap = [
            'person_titles' => 'person_titles',
            'person_seniorities' => 'person_seniorities',
            'person_locations' => 'person_locations',
            'organization_locations' => 'organization_locations',
            'q_organization_domains_list' => 'q_organization_domains_list',
            'contact_email_status' => 'contact_email_status',
            'organization_ids' => 'organization_ids',
            'organization_num_employees_ranges' => 'organization_num_employees_ranges',
            'currently_using_all_of_technology_uids' => 'currently_using_all_of_technology_uids',
            'currently_using_any_of_technology_uids' => 'currently_using_any_of_technology_uids',
            'currently_not_using_any_of_technology_uids' => 'currently_not_using_any_of_technology_uids',
            'q_organization_job_titles' => 'q_organization_job_titles',
            'organization_job_locations' => 'organization_job_locations',
        ];
        foreach ($arrayMap as $in => $out) {
            $vals = $this->toArray($f[$in] ?? null);
            if (!empty($vals)) $p[$out] = $vals;
        }

        // Strings simples
        if (!empty($f['q_keywords'])) $p['q_keywords'] = trim($f['q_keywords']);

        // Booleano de títulos similares (só envia quando explicitamente false)
        if (isset($f['include_similar_titles'])) {
            $p['include_similar_titles'] = filter_var($f['include_similar_titles'], FILTER_VALIDATE_BOOLEAN);
        }

        // Faixas min/max
        $this->applyRange($p, 'revenue_range', $f['revenue_range'] ?? null);
        $this->applyRange($p, 'organization_num_jobs_range', $f['organization_num_jobs_range'] ?? null);
        $this->applyRange($p, 'organization_job_posted_at_range', $f['organization_job_posted_at_range'] ?? null);

        // Paginação
        $p['page'] = max(1, intval($f['page'] ?? 1));
        $p['per_page'] = min(100, max(1, intval($f['per_page'] ?? 25)));

        return $p;
    }

    private function buildOrganizationSearchPayload($f)
    {
        $p = [];

        if (!empty($f['q_organization_name'])) $p['q_organization_name'] = trim($f['q_organization_name']);

        $arrayMap = [
            'q_organization_keyword_tags' => 'q_organization_keyword_tags',
            'q_organization_domains_list' => 'q_organization_domains_list',
            'organization_locations' => 'organization_locations',
            'organization_not_locations' => 'organization_not_locations',
            'organization_num_employees_ranges' => 'organization_num_employees_ranges',
            'currently_using_any_of_technology_uids' => 'currently_using_any_of_technology_uids',
            'organization_ids' => 'organization_ids',
            'q_organization_job_titles' => 'q_organization_job_titles',
            'organization_job_locations' => 'organization_job_locations',
        ];
        foreach ($arrayMap as $in => $out) {
            $vals = $this->toArray($f[$in] ?? null);
            if (!empty($vals)) $p[$out] = $vals;
        }

        $this->applyRange($p, 'revenue_range', $f['revenue_range'] ?? null);
        $this->applyRange($p, 'latest_funding_amount_range', $f['latest_funding_amount_range'] ?? null);
        $this->applyRange($p, 'total_funding_range', $f['total_funding_range'] ?? null);
        $this->applyRange($p, 'latest_funding_date_range', $f['latest_funding_date_range'] ?? null);
        $this->applyRange($p, 'organization_num_jobs_range', $f['organization_num_jobs_range'] ?? null);
        $this->applyRange($p, 'organization_job_posted_at_range', $f['organization_job_posted_at_range'] ?? null);

        $p['page'] = max(1, intval($f['page'] ?? 1));
        $p['per_page'] = min(100, max(1, intval($f['per_page'] ?? 25)));

        return $p;
    }

    /**
     * Aplica uma faixa min/max no payload no formato do Apollo (chave[min]/chave[max]),
     * enviada como objeto aninhado. Só inclui os limites informados.
     */
    private function applyRange(&$payload, $key, $range)
    {
        if (empty($range) || !is_array($range)) return;
        $out = [];
        if (isset($range['min']) && $range['min'] !== '') $out['min'] = $range['min'];
        if (isset($range['max']) && $range['max'] !== '') $out['max'] = $range['max'];
        if (!empty($out)) $payload[$key] = $out;
    }

    /**
     * Normaliza um valor em array de strings não-vazias.
     * Aceita array direto ou string separada por vírgula/; quebra de linha.
     */
    private function toArray($value)
    {
        if ($value === null || $value === '') return [];
        if (is_array($value)) {
            $arr = $value;
        } else {
            $arr = preg_split('/[,;\n]+/', (string) $value);
        }
        $arr = array_map('trim', $arr);
        $arr = array_filter($arr, fn($v) => $v !== '');
        return array_values($arr);
    }

    /** Remove chaves com valores vazios/nulos de um payload. */
    private function cleanPayload($params)
    {
        $out = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') continue;
            $out[$k] = $v;
        }
        return $out;
    }

    // =====================================================
    // TRANSPORTE
    // =====================================================

    /**
     * Executa uma requisição autenticada à API Apollo (forma compacta).
     * @return array ['success'=>bool, 'status'=>int, 'data'=>mixed, 'error'=>?]
     */
    private function request($method, $path, $jsonBody = null)
    {
        $full = $this->call($method, $path, $jsonBody);
        return [
            'success' => $full['success'],
            'status' => $full['status'],
            'data' => $full['data'] ?? null,
            'error' => $full['error'] ?? null,
        ];
    }

    /**
     * Executa a requisição e retorna o detalhe completo (para diagnóstico):
     * método, url, payload enviado, status HTTP, corpo da resposta, erro.
     * NUNCA inclui a API key.
     * @return array
     */
    private function call($method, $path, $jsonBody = null)
    {
        $url = strpos($path, 'http') === 0 ? $path : $this->baseUrl . $path;
        $detail = [
            'method' => $method,
            'url' => $url,
            'request' => $jsonBody,
            'success' => false,
            'status' => 0,
            'data' => null,
            'error' => null,
        ];

        if (!$this->isConfigured()) {
            $detail['error'] = 'Apollo não configurado. Informe a API key em Configurações.';
            return $detail;
        }

        $headers = [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'x-api-key: ' . $this->apiKey,
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
        }
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $detail['status'] = $code;

        if ($resp === false) {
            Logger::error('Apollo request: falha de conexão', ['method' => $method, 'url' => $url, 'curl_error' => $err]);
            $detail['error'] = 'Falha de conexão com a Apollo: ' . $err;
            return $detail;
        }

        $data = json_decode($resp, true);
        if ($data === null && $resp !== '') $data = $resp;
        $detail['data'] = $data;

        $ok = $code >= 200 && $code < 300;
        $detail['success'] = $ok;
        if (!$ok) {
            Logger::error('Apollo request: HTTP ' . $code, [
                'method' => $method,
                'url' => $url,
                'body' => is_string($data) ? substr($data, 0, 500) : $data,
            ]);
            $apiMsg = is_array($data) ? ($data['error'] ?? $data['message'] ?? null) : null;
            $detail['error'] = $apiMsg ?: ('Erro na Apollo (HTTP ' . $code . ').');
        }

        return $detail;
    }

    // =====================================================
    // DIAGNÓSTICO
    // =====================================================

    /**
     * Roda uma bateria de chamadas representativas a todos os endpoints
     * e retorna o detalhe de cada uma (request, response, erro) para debug.
     * As chamadas usam parâmetros mínimos para minimizar consumo de créditos.
     *
     * @return array Lista de ['label','endpoint','method','url','request','status','success','response','error']
     */
    public function runDiagnostics()
    {
        $results = [];

        // Ordem: dos endpoints mais baratos/seguros para os que consomem crédito.
        $steps = [];

        // 1) Saúde da chave (0 créditos)
        $steps[] = ['Auth Health', fn() => $this->call('GET', '/auth/health')];

        // 2) Perfil da API (0 créditos)
        $steps[] = ['API Profile', fn() => $this->call('GET', '/users/api_profile')];

        // 3) People Search (0 créditos) — filtro mínimo, 1 resultado
        $peopleSearch = null;
        $steps[] = ['People Search', function () use (&$peopleSearch) {
            $peopleSearch = $this->call('POST', '/mixed_people/search', [
                'person_titles' => ['ceo'],
                'page' => 1,
                'per_page' => 1,
            ]);
            return $peopleSearch;
        }];

        // 4) Organization Search (1 crédito/página) — filtro mínimo, 1 resultado
        $orgSearch = null;
        $steps[] = ['Organization Search', function () use (&$orgSearch) {
            $orgSearch = $this->call('POST', '/mixed_companies/search', [
                'q_organization_name' => 'apollo',
                'page' => 1,
                'per_page' => 1,
            ]);
            return $orgSearch;
        }];

        // 5) Organization Enrichment (por domínio conhecido)
        $steps[] = ['Organization Enrichment', fn() => $this->call('GET', '/organizations/enrich?domain=apollo.io')];

        // 6) People Enrichment (identificação genérica; pode não casar, mas valida o endpoint)
        $steps[] = ['People Enrichment', fn() => $this->call('POST', '/people/match', [
            'first_name' => 'Tim',
            'last_name' => 'Zheng',
            'domain' => 'apollo.io',
        ])];

        // Executa os passos capturando cada detalhe
        foreach ($steps as [$label, $fn]) {
            try {
                $d = $fn();
            } catch (\Throwable $e) {
                $d = ['method' => '-', 'url' => '-', 'request' => null, 'status' => 0, 'success' => false, 'data' => null, 'error' => 'Exceção: ' . $e->getMessage()];
            }
            $results[] = $this->formatDiagStep($label, $d);
        }

        // 7) Get Complete Person Info — usa um id retornado pela busca de pessoas, se houver
        $personId = null;
        if (is_array($peopleSearch['data'] ?? null)) {
            $people = $peopleSearch['data']['people'] ?? ($peopleSearch['data']['contacts'] ?? []);
            $personId = $people[0]['id'] ?? null;
        }
        if ($personId) {
            $d = $this->call('GET', '/people/' . rawurlencode($personId));
            $results[] = $this->formatDiagStep('Get Complete Person Info', $d);
        } else {
            $results[] = [
                'label' => 'Get Complete Person Info',
                'method' => 'GET', 'endpoint' => '/people/{id}', 'url' => $this->baseUrl . '/people/{id}',
                'request' => null, 'status' => null, 'success' => false,
                'response' => null, 'error' => 'Ignorado: nenhuma pessoa retornada na busca para obter um id.',
                'skipped' => true,
            ];
        }

        // 8) Organization Job Postings — usa um id de organização, se houver
        $orgId = null;
        if (is_array($orgSearch['data'] ?? null)) {
            $orgs = $orgSearch['data']['organizations'] ?? ($orgSearch['data']['accounts'] ?? []);
            $orgId = $orgs[0]['id'] ?? null;
        }
        if ($orgId) {
            $d = $this->call('GET', '/organizations/' . rawurlencode($orgId) . '/job_postings');
            $results[] = $this->formatDiagStep('Organization Job Postings', $d);
        } else {
            $results[] = [
                'label' => 'Organization Job Postings',
                'method' => 'GET', 'endpoint' => '/organizations/{id}/job_postings', 'url' => $this->baseUrl . '/organizations/{id}/job_postings',
                'request' => null, 'status' => null, 'success' => false,
                'response' => null, 'error' => 'Ignorado: nenhuma empresa retornada na busca para obter um id.',
                'skipped' => true,
            ];
        }

        return $results;
    }

    /** Normaliza um detalhe de chamada em uma linha de diagnóstico. */
    private function formatDiagStep($label, $d)
    {
        // Limita o tamanho do corpo da resposta para exibição/cópia
        $response = $d['data'] ?? null;
        if (is_string($response) && strlen($response) > 4000) {
            $response = substr($response, 0, 4000) . '… [truncado]';
        }
        return [
            'label' => $label,
            'method' => $d['method'] ?? '-',
            'endpoint' => isset($d['url']) ? str_replace($this->baseUrl, '', $d['url']) : '-',
            'url' => $d['url'] ?? '-',
            'request' => $d['request'] ?? null,
            'status' => $d['status'] ?? null,
            'success' => (bool)($d['success'] ?? false),
            'response' => $response,
            'error' => $d['error'] ?? null,
        ];
    }
}
