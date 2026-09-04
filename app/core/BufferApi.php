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
     *
     * Rate limiting (429): o Buffer aplica limites POR CLIENTE em janelas de
     * 15min ('15m'), 24h ('24h') e 30 dias ('30d'). Conforme a documentação
     * oficial (https://developers.buffer.com/guides/api-limits.html), ao receber
     * um 429 devemos PARAR e respeitar o header Retry-After — retentar às cegas
     * apenas agrava o bloqueio e consome tempo. Só reintentamos quando o
     * Retry-After é curto o suficiente (janela de 15m) e cabe no orçamento de
     * espera; caso contrário devolvemos o erro imediatamente para o chamador.
     */
    public function query($query, $variables = [], $maxRetries = 1)
    {
        if (empty($this->apiKey)) {
            return ['errors' => [['message' => 'Chave da API Buffer não configurada.']], 'http' => 0];
        }

        // Teto de espera por tentativa. Um Retry-After de 24h/30d pode ser
        // enorme; nunca dormimos além disso — devolvemos o erro ao usuário.
        $maxWaitSeconds = 20;

        $payload = ['query' => $query];
        if (!empty($variables)) $payload['variables'] = $variables;

        $attempt = 0;
        while ($attempt <= $maxRetries) {
            // Captura Retry-After diretamente do header (contador regressivo real).
            $retryAfter = 0;
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
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$retryAfter) {
                    if (stripos($line, 'retry-after:') === 0) {
                        $retryAfter = (int) trim(substr($line, strlen('retry-after:')));
                    }
                    return strlen($line);
                },
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['errors' => [['message' => 'Falha de conexão: ' . $curlErr]], 'http' => $httpCode];
            }

            $decoded = json_decode($response, true);

            // Detecta rate limit tanto via HTTP 429 quanto via mensagem no corpo
            // (algumas mutations respondem 200 com "too many requests" no payload).
            $isRateLimited = ($httpCode === 429);
            $window = null;
            if (is_array($decoded)) {
                if (!empty($decoded['errors'])) {
                    foreach ($decoded['errors'] as $err) {
                        if (!empty($err['extensions']['window'])) {
                            $window = $err['extensions']['window'];
                        }
                        if (isset($err['message']) && stripos($err['message'], 'too many requests') !== false) {
                            $isRateLimited = true;
                        }
                    }
                }
                $mutationMsg = $decoded['data']['createPost']['message'] ?? '';
                if ($mutationMsg && stripos($mutationMsg, 'too many requests') !== false) {
                    $isRateLimited = true;
                }
            }

            if ($isRateLimited) {
                // Só reintentamos se o Buffer indicou uma espera CURTA e ainda
                // temos tentativa disponível. Caso contrário, paramos aqui.
                if ($attempt < $maxRetries && $retryAfter > 0 && $retryAfter <= $maxWaitSeconds) {
                    $attempt++;
                    // Pequeno jitter para não sincronizar clientes que aguardam o mesmo t.
                    sleep($retryAfter + random_int(0, 2));
                    continue;
                }

                return [
                    'errors' => [['message' => $this->rateLimitMessage($window, $retryAfter)]],
                    'http' => 429,
                    'window' => $window,
                    'retry_after' => $retryAfter,
                ];
            }

            if (!is_array($decoded)) {
                return ['errors' => [['message' => 'Resposta inválida da API Buffer.']], 'http' => $httpCode];
            }

            $decoded['http'] = $httpCode;
            return $decoded;
        }

        return ['errors' => [['message' => 'Limite de requisições da API Buffer excedido. Aguarde alguns minutos e tente novamente.']], 'http' => 429];
    }

    /** Monta a mensagem de rate limit conforme a janela excedida e o tempo de espera. */
    private function rateLimitMessage($window, $retryAfter)
    {
        $labels = ['15m' => 'de 15 minutos', '24h' => 'diário', '30d' => 'mensal'];
        $windowLabel = $labels[$window] ?? '';
        $base = $windowLabel
            ? "Limite {$windowLabel} de requisições da API Buffer atingido."
            : 'Limite de requisições da API Buffer atingido.';
        $waitMsg = ($retryAfter > 0) ? ' Tente novamente em ' . $this->formatWait($retryAfter) . '.' : '';
        return $base . $waitMsg;
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
}
