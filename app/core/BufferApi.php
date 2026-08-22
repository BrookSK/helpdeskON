<?php

/**
 * Cliente da API GraphQL do Buffer (https://developers.buffer.com).
 * Endpoint único: https://api.buffer.com — autenticação via Bearer token (API key).
 */
class BufferApi
{
    private $apiKey;
    private $endpoint = 'https://api.buffer.com';

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: Config::get('buffer_api_key');
    }

    public function hasKey()
    {
        return !empty($this->apiKey);
    }

    /**
     * Executa uma query/mutation GraphQL.
     * Retorna ['data' => ..., 'errors' => ..., 'http' => code].
     */
    public function query($query, $variables = [])
    {
        if (empty($this->apiKey)) {
            return ['errors' => [['message' => 'Chave da API Buffer não configurada.']], 'http' => 0];
        }

        $payload = ['query' => $query];
        if (!empty($variables)) $payload['variables'] = $variables;

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Extrai o nome da operação GraphQL para facilitar identificação nos logs
        $opName = 'unknown';
        if (preg_match('/(?:query|mutation)\s+(\w+)/', $query, $m)) {
            $opName = $m[1];
        }

        if ($response === false) {
            Logger::error('BufferApi connection failure', [
                'operation' => $opName,
                'curl_error' => $curlErr,
                'http_code' => $httpCode,
            ]);
            return ['errors' => [['message' => 'Falha de conexão: ' . $curlErr]], 'http' => $httpCode];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            Logger::error('BufferApi invalid response', [
                'operation' => $opName,
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 300),
            ]);
            return ['errors' => [['message' => 'Resposta inválida da API Buffer.']], 'http' => $httpCode];
        }
        $decoded['http'] = $httpCode;

        // Loga erros GraphQL ou HTTP não-2xx
        if (!empty($decoded['errors']) || $httpCode >= 400) {
            Logger::warning('BufferApi error response', [
                'operation' => $opName,
                'http_code' => $httpCode,
                'errors' => $decoded['errors'] ?? null,
                'has_data' => isset($decoded['data']),
            ]);
        }

        return $decoded;
    }

    /** Conta + organizações do dono da chave. */
    public function getAccount()
    {
        return $this->query('query { account { id email name organizations { id name } } }');
    }

    /** Primeira organização (a maioria das contas tem só uma). */
    public function getFirstOrganizationId()
    {
        $res = $this->getAccount();
        return $res['data']['account']['organizations'][0]['id'] ?? null;
    }

    /** Lista canais (perfis sociais) de uma organização.
     * Observação: a API do Buffer NÃO expõe contagem de seguidores nem username. */
    public function getChannels($organizationId)
    {
        $q = 'query GetChannels($input: ChannelsInput!) {
            channels(input: $input) {
                id
                name
                displayName
                service
                serviceId
                avatar
                type
                descriptor
                externalLink
                isDisconnected
                isQueuePaused
            }
        }';
        return $this->query($q, ['input' => ['organizationId' => $organizationId]]);
    }

    /**
     * Cria um post agendado num canal.
     * $dueAtIso: ISO 8601 UTC (ex: 2026-03-10T15:00:00.000Z). Se vazio, entra na fila.
     * $assets: lista de URLs públicas de imagem (opcional).
     */
    public function createPost($channelId, $text, $dueAtIso = null, $assets = [])
    {
        $input = [
            'text' => $text,
            'channelId' => $channelId,
            'schedulingType' => 'automatic',
        ];
        if ($dueAtIso) {
            $input['mode'] = 'customScheduled';
            $input['dueAt'] = $dueAtIso;
        } else {
            $input['mode'] = 'addToQueue';
        }
        if (!empty($assets)) {
            $input['assets'] = array_map(fn($url) => ['image' => ['url' => $url]], $assets);
        }

        $q = 'mutation CreatePost($input: CreatePostInput!) {
            createPost(input: $input) {
                ... on PostActionSuccess { post { id text status dueAt channelId externalLink } }
                ... on MutationError { message }
            }
        }';
        return $this->query($q, ['input' => $input]);
    }

    /** Exclui um post. */
    public function deletePost($postId)
    {
        $q = 'mutation DeletePost($input: DeletePostInput!) {
            deletePost(input: $input) {
                ... on DeletePostSuccess { id }
                ... on MutationError { message }
            }
        }';
        return $this->query($q, ['input' => ['id' => $postId]]);
    }

    /** Posts enviados de uma organização, com métricas, paginados. */
    public function getSentPostsWithMetrics($organizationId, $channelIds = [], $first = 50, $after = null)
    {
        $filter = ['status' => ['sent']];
        if (!empty($channelIds)) $filter['channelIds'] = $channelIds;

        $input = ['organizationId' => $organizationId, 'filter' => $filter,
                  'sort' => [['field' => 'dueAt', 'direction' => 'desc']]];

        $q = 'query GetPostsWithMetrics($first: Int, $after: String, $input: PostsInput!) {
            posts(first: $first, after: $after, input: $input) {
                edges { node {
                    id text dueAt sentAt channelId channelService externalLink
                    assets { thumbnail source mimeType }
                    metrics { type name value unit }
                    metricsUpdatedAt
                } }
                pageInfo { hasNextPage endCursor }
            }
        }';
        $vars = ['first' => $first, 'input' => $input];
        if ($after) $vars['after'] = $after;
        return $this->query($q, $vars);
    }

    /** Métricas agregadas do período (server-side). */
    public function getAggregatedMetrics($organizationId, $startIso, $endIso, $channelIds = [])
    {
        $input = [
            'organizationId' => $organizationId,
            'startDateTime' => $startIso,
            'endDateTime' => $endIso,
        ];
        if (!empty($channelIds)) $input['channelIds'] = $channelIds;

        $q = 'query AggregatePostMetrics($input: AggregatedPostMetricsInput!) {
            aggregatedPostMetrics(input: $input) {
                metrics { type name value unit }
                metricsUpdatedAt
            }
        }';
        return $this->query($q, ['input' => $input]);
    }

    /**
     * Busca um post individual pelo ID do Buffer.
     * Útil para verificar o status atual de um post agendado.
     */
    public function getPost($postId)
    {
        $q = 'query GetPost($id: ID!) {
            post(id: $id) {
                id text status dueAt sentAt channelId channelService externalLink
            }
        }';
        return $this->query($q, ['id' => $postId]);
    }

    /**
     * Lista posts de uma organização filtrando por status.
     * $statuses: array de status (ex: ['scheduled', 'error', 'sending']).
     */
    public function getPostsByStatus($organizationId, $statuses = ['scheduled'], $channelIds = [], $first = 50)
    {
        $filter = ['status' => $statuses];
        if (!empty($channelIds)) $filter['channelIds'] = $channelIds;

        $input = ['organizationId' => $organizationId, 'filter' => $filter,
                  'sort' => [['field' => 'dueAt', 'direction' => 'asc']]];

        $q = 'query GetPostsByStatus($first: Int, $input: PostsInput!) {
            posts(first: $first, input: $input) {
                edges { node {
                    id text status dueAt sentAt channelId channelService externalLink
                } }
                pageInfo { hasNextPage endCursor }
            }
        }';
        return $this->query($q, ['first' => $first, 'input' => $input]);
    }
}
