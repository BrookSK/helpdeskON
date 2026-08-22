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
     * Implementa retry automático com backoff em caso de rate limiting (429).
     * Se o limite diário/mensal foi atingido, informa ao usuário sem retry infinito.
     */
    public function query($query, $variables = [], $maxRetries = 3)
    {
        if (empty($this->apiKey)) {
            return ['errors' => [['message' => 'Chave da API Buffer não configurada.']], 'http' => 0];
        }

        $payload = ['query' => $query];
        if (!empty($variables)) $payload['variables'] = $variables;

        $attempt = 0;
        while ($attempt <= $maxRetries) {
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
                CURLOPT_HEADER => true,
            ]);
            $fullResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($fullResponse === false) {
                return ['errors' => [['message' => 'Falha de conexão: ' . $curlErr]], 'http' => $httpCode];
            }

            $headers = substr($fullResponse, 0, $headerSize);
            $response = substr($fullResponse, $headerSize);

            // Rate limiting via HTTP 429
            if ($httpCode === 429) {
                // Verificar qual janela foi excedida (via extensions.window no body)
                $decoded = json_decode($response, true);
                $window = $decoded['errors'][0]['extensions']['window'] ?? null;

                // Se é limite de 24h ou 30d, não adianta ficar tentando — informar ao usuário
                if ($window === '24h' || $window === '30d') {
                    $retryAfter = $this->parseRetryAfter($headers);
                    $waitMsg = $retryAfter ? " Tente novamente em " . $this->formatWait($retryAfter) . "." : '';
                    $windowLabel = $window === '24h' ? 'diário' : 'mensal';
                    return ['errors' => [['message' => "Limite {$windowLabel} da API Buffer atingido.{$waitMsg}"]], 'http' => 429, 'window' => $window];
                }

                // Limite de 15min ou desconhecido: tentar novamente com backoff
                if ($attempt < $maxRetries) {
                    $retryAfter = $this->parseRetryAfter($headers);
                    $wait = $retryAfter ? min($retryAfter, 15) : min(pow(2, $attempt + 1), 10);
                    $attempt++;
                    sleep($wait);
                    continue;
                }

                $decoded = $decoded ?? json_decode($response, true);
                if (is_array($decoded)) { $decoded['http'] = $httpCode; return $decoded; }
                return ['errors' => [['message' => 'Rate limit excedido. Aguarde alguns minutos e tente novamente.']], 'http' => 429];
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return ['errors' => [['message' => 'Resposta inválida da API Buffer.']], 'http' => $httpCode];
            }

            // Rate limiting via mensagem de erro no corpo (HTTP 200 mas com erro de rate limit)
            $isRateLimited = false;
            if (!empty($decoded['errors'])) {
                foreach ($decoded['errors'] as $err) {
                    if (isset($err['message']) && stripos($err['message'], 'too many requests') !== false) {
                        $isRateLimited = true;
                        break;
                    }
                }
            }
            $mutationMsg = $decoded['data']['createPost']['message'] ?? '';
            if ($mutationMsg && stripos($mutationMsg, 'too many requests') !== false) {
                $isRateLimited = true;
            }

            if ($isRateLimited && $attempt < $maxRetries) {
                $attempt++;
                sleep(pow(2, $attempt) + 1);
                continue;
            }

            $decoded['http'] = $httpCode;
            return $decoded;
        }

        return ['errors' => [['message' => 'Limite de requisições excedido. Aguarde alguns minutos e tente novamente.']], 'http' => 429];
    }

    /** Extrai o valor do header Retry-After (em segundos). */
    private function parseRetryAfter($headers)
    {
        if (preg_match('/Retry-After:\s*(\d+)/i', $headers, $m)) {
            return intval($m[1]);
        }
        return null;
    }

    /** Formata segundos em texto legível. */
    private function formatWait($seconds)
    {
        if ($seconds < 60) return "{$seconds} segundos";
        if ($seconds < 3600) return ceil($seconds / 60) . " minutos";
        return ceil($seconds / 3600) . " hora(s)";
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
        return $this->query($q, ['input' => $input], 2);
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
}
