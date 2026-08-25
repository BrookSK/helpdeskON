<?php

/**
 * Cliente da Graph API da Meta (Facebook Pages + Instagram Professional).
 * Token configurado em Configurações (chave `meta_access_token`) ou específico por conta.
 *
 * Docs:
 * - https://developers.facebook.com/docs/instagram-platform
 * - https://developers.facebook.com/docs/instagram-platform/reference/instagram-media/insights
 */
class MetaApi
{
    private $token;
    private $version = 'v21.0';
    private $base = 'https://graph.facebook.com';

    public function __construct($token = null)
    {
        $this->token = $token ?: Config::get('meta_access_token');
    }

    public function hasToken()
    {
        return !empty($this->token);
    }

    /**
     * Verifica se o token ainda é válido fazendo uma chamada leve à API.
     * Retorna true se válido, false se expirado/inválido.
     */
    public function isTokenValid()
    {
        if (!$this->hasToken()) return false;
        $res = $this->get('me', ['fields' => 'id']);
        // HTTP 401 ou erro OAuthException indicam token expirado
        if (($res['http'] ?? 0) === 401) return false;
        if (!empty($res['error']['type']) && $res['error']['type'] === 'OAuthException') return false;
        if (!empty($res['error']['code']) && in_array($res['error']['code'], [190, 102])) return false;
        return !empty($res['id']);
    }

    private function get($path, $params = [])
    {
        $params['access_token'] = $this->token;
        $url = $this->base . '/' . $this->version . '/' . ltrim($path, '/') . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['error' => ['message' => 'Falha de conexão: ' . $err], 'http' => $code];
        }
        $data = json_decode($resp, true);
        if (!is_array($data)) {
            return ['error' => ['message' => 'Resposta inválida da Meta'], 'http' => $code];
        }
        $data['http'] = $code;
        return $data;
    }

    /** Lista páginas do Facebook + conta IG vinculada. */
    public function getPages()
    {
        return $this->get('me/accounts', [
            'fields' => 'id,name,access_token,followers_count,fan_count,picture{url},'
                . 'instagram_business_account{id,username,name,profile_picture_url,followers_count,follows_count,media_count}',
            'limit' => 50,
        ]);
    }

    // ===== Instagram =====
    public function getInstagramAccount($igUserId)
    {
        return $this->get($igUserId, [
            'fields' => 'id,username,name,profile_picture_url,followers_count,follows_count,media_count',
        ]);
    }

    /** Insights de conta IG (alcance, impressões, visitas ao perfil) no período. */
    public function getInstagramAccountInsights($igUserId, $since = null, $until = null)
    {
        $params = ['metric' => 'reach,impressions,profile_views,accounts_engaged', 'period' => 'day'];
        if ($since) $params['since'] = $since;
        if ($until) $params['until'] = $until;
        return $this->get($igUserId . '/insights', $params);
    }

    /** Últimas mídias do IG com contagem de curtidas e comentários. */
    public function getInstagramMedia($igUserId, $limit = 25)
    {
        return $this->get($igUserId . '/media', [
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count',
            'limit' => $limit,
        ]);
    }

    /** Insights de uma mídia específica do IG (reach, impressions, saved, shares, video_views). */
    public function getInstagramMediaInsights($mediaId, $mediaType = 'IMAGE')
    {
        // Métricas variam por tipo de mídia
        $metrics = 'reach,saved,likes,comments,shares,total_interactions';
        if (in_array(strtoupper($mediaType), ['VIDEO', 'REELS'])) {
            $metrics = 'reach,saved,likes,comments,shares,total_interactions,views';
        }
        return $this->get($mediaId . '/insights', ['metric' => $metrics]);
    }

    // ===== Facebook Page =====
    public function getFacebookPage($pageId)
    {
        return $this->get($pageId, ['fields' => 'id,name,fan_count,followers_count,picture{url},link']);
    }

    /** Posts recentes da página com reações, comentários e compartilhamentos. */
    public function getFacebookPosts($pageId, $pageToken = null, $limit = 25)
    {
        $params = [
            'fields' => 'id,message,created_time,permalink_url,full_picture,'
                . 'shares,likes.summary(true),comments.summary(true),reactions.summary(true)',
            'limit' => $limit,
        ];
        if ($pageToken) $params['access_token'] = $pageToken;
        return $this->get($pageId . '/posts', $params);
    }

    /** Insights de página do Facebook (impressões e alcance) no período. */
    public function getFacebookPageInsights($pageId, $since = null, $until = null, $pageToken = null)
    {
        $params = [
            'metric' => 'page_impressions,page_post_engagements,page_fan_adds',
            'period' => 'day',
        ];
        if ($since) $params['since'] = $since;
        if ($until) $params['until'] = $until;
        if ($pageToken) $params['access_token'] = $pageToken;
        return $this->get($pageId . '/insights', $params);
    }

    /** Soma valores de uma resposta de insights (retorna [metric => soma]). */
    public static function sumInsights($insightsResponse)
    {
        $out = [];
        foreach (($insightsResponse['data'] ?? []) as $metric) {
            $name = $metric['name'] ?? null;
            if (!$name) continue;
            $sum = 0;
            foreach (($metric['values'] ?? []) as $v) {
                $sum += (float) ($v['value'] ?? 0);
            }
            $out[$name] = $sum;
        }
        return $out;
    }

    /** Achata insights de uma mídia (valor único por métrica) em [metric => valor]. */
    public static function flattenMediaInsights($insightsResponse)
    {
        $out = [];
        foreach (($insightsResponse['data'] ?? []) as $metric) {
            $name = $metric['name'] ?? null;
            if (!$name) continue;
            $val = $metric['values'][0]['value'] ?? 0;
            $out[$name] = is_array($val) ? array_sum($val) : (float) $val;
        }
        return $out;
    }

    // ===== PUBLICAÇÃO DE CONTEÚDO =====

    /**
     * Publica uma imagem no Instagram (Content Publishing API).
     * Requer: conta business/creator, token com permissão instagram_content_publish.
     *
     * @param string $igUserId ID da conta IG business
     * @param string $imageUrl URL pública da imagem (JPEG)
     * @param string $caption Texto/legenda do post
     * @param string|null $pageToken Token da página (se diferente do token principal)
     * @return array Resultado com 'id' do post publicado ou 'error'
     */
    public function publishInstagramImage($igUserId, $imageUrl, $caption, $pageToken = null)
    {
        // Etapa 1: Criar container de mídia
        $containerResult = $this->post($igUserId . '/media', [
            'image_url' => $imageUrl,
            'caption' => $caption,
        ], $pageToken);

        if (!empty($containerResult['error']) || empty($containerResult['id'])) {
            return ['error' => $containerResult['error']['message'] ?? 'Falha ao criar container de mídia no Instagram', 'raw' => $containerResult];
        }

        $containerId = $containerResult['id'];

        // Aguardar processamento (Instagram pode levar alguns segundos)
        sleep(3);

        // Etapa 2: Publicar o container
        $publishResult = $this->post($igUserId . '/media_publish', [
            'creation_id' => $containerId,
        ], $pageToken);

        if (!empty($publishResult['error']) || empty($publishResult['id'])) {
            return ['error' => $publishResult['error']['message'] ?? 'Falha ao publicar no Instagram', 'raw' => $publishResult];
        }

        return ['success' => true, 'id' => $publishResult['id']];
    }

    /**
     * Publica um carrossel no Instagram.
     * @param string $igUserId
     * @param array $imageUrls Lista de URLs públicas de imagens
     * @param string $caption
     * @param string|null $pageToken
     */
    public function publishInstagramCarousel($igUserId, $imageUrls, $caption, $pageToken = null)
    {
        $childIds = [];
        foreach ($imageUrls as $url) {
            $child = $this->post($igUserId . '/media', [
                'image_url' => $url,
                'is_carousel_item' => true,
            ], $pageToken);
            if (!empty($child['id'])) {
                $childIds[] = $child['id'];
            }
        }

        if (empty($childIds)) {
            return ['error' => 'Nenhuma imagem do carrossel foi processada'];
        }

        sleep(3);

        $container = $this->post($igUserId . '/media', [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
        ], $pageToken);

        if (empty($container['id'])) {
            return ['error' => $container['error']['message'] ?? 'Falha ao criar carrossel'];
        }

        sleep(3);

        $publish = $this->post($igUserId . '/media_publish', [
            'creation_id' => $container['id'],
        ], $pageToken);

        if (empty($publish['id'])) {
            return ['error' => $publish['error']['message'] ?? 'Falha ao publicar carrossel'];
        }

        return ['success' => true, 'id' => $publish['id']];
    }

    /**
     * Publica um post na página do Facebook.
     * @param string $pageId
     * @param string $message Texto do post
     * @param string|null $imageUrl URL da imagem (opcional)
     * @param string|null $pageToken
     */
    public function publishFacebookPost($pageId, $message, $imageUrl = null, $pageToken = null)
    {
        if (!empty($imageUrl)) {
            // Post com foto
            $result = $this->post($pageId . '/photos', [
                'url' => $imageUrl,
                'caption' => $message,
            ], $pageToken);
        } else {
            // Post só texto
            $result = $this->post($pageId . '/feed', [
                'message' => $message,
            ], $pageToken);
        }

        if (!empty($result['error'])) {
            return ['error' => $result['error']['message'] ?? 'Falha ao publicar no Facebook'];
        }

        return ['success' => true, 'id' => $result['id'] ?? $result['post_id'] ?? null];
    }

    /**
     * Requisição POST à Graph API.
     */
    private function post($path, $params = [], $tokenOverride = null)
    {
        $params['access_token'] = $tokenOverride ?: $this->token;
        $url = $this->base . '/' . $this->version . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);
        if (!is_array($data)) return ['error' => ['message' => 'Resposta inválida'], 'http' => $code];
        $data['http'] = $code;
        return $data;
    }
}
