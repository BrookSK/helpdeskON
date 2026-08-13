<?php

/**
 * Cliente da API do LinkedIn (Marketing / Community Management) para páginas de organização.
 * Token OAuth em Configurações (chave `linkedin_access_token`) com escopos:
 * r_organization_social, r_organization_followers, rw_organization_admin.
 *
 * Observação: analytics de PERFIL PESSOAL não são expostos pela API — apenas Company Pages.
 *
 * Docs: https://learn.microsoft.com/linkedin/marketing/
 */
class LinkedInApi
{
    private $token;
    private $base = 'https://api.linkedin.com';
    private $version = '202405';

    public function __construct($token = null)
    {
        $this->token = $token ?: Config::get('linkedin_access_token');
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
        $res = $this->get('rest/me');
        // HTTP 401 = token expirado ou inválido
        if (($res['http'] ?? 0) === 401) return false;
        if (($res['http'] ?? 0) === 403) return false;
        if (!empty($res['serviceErrorCode'])) return false;
        return ($res['http'] ?? 0) >= 200 && ($res['http'] ?? 0) < 300;
    }

    private function get($path, $params = [])
    {
        $url = $this->base . '/' . ltrim($path, '/');
        if (!empty($params)) $url .= '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: ' . $this->version,
            ],
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
            return ['error' => ['message' => 'Resposta inválida do LinkedIn'], 'http' => $code];
        }
        $data['http'] = $code;
        return $data;
    }

    private function normalizeOrgId($orgId)
    {
        if (preg_match('/(\d+)/', (string) $orgId, $m)) return $m[1];
        return $orgId;
    }

    /** Dados básicos da organização (nome, logo, vanityName). */
    public function getOrganization($orgId)
    {
        $orgId = $this->normalizeOrgId($orgId);
        return $this->get('rest/organizations/' . $orgId, [
            'fields' => 'id,localizedName,vanityName',
        ]);
    }

    /** Total de seguidores da organização. */
    public function getFollowerCount($orgId)
    {
        $orgId = $this->normalizeOrgId($orgId);
        $urn = 'urn:li:organization:' . $orgId;
        $res = $this->get('rest/networkSizes/' . rawurlencode($urn), ['edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER']);
        return $res['firstDegreeSize'] ?? null;
    }

    /** Estatísticas agregadas de página (impressões, cliques, engajamento) — lifetime. */
    public function getOrganizationShareStats($orgId)
    {
        $orgId = $this->normalizeOrgId($orgId);
        return $this->get('rest/organizationalEntityShareStatistics', [
            'q' => 'organizationalEntity',
            'organizationalEntity' => 'urn:li:organization:' . $orgId,
        ]);
    }

    /** Posts recentes da organização (URNs das publicações). */
    public function getOrganizationPosts($orgId, $count = 20)
    {
        $orgId = $this->normalizeOrgId($orgId);
        return $this->get('rest/posts', [
            'q' => 'author',
            'author' => 'urn:li:organization:' . $orgId,
            'count' => $count,
            'sortBy' => 'LAST_MODIFIED',
        ]);
    }

    /** Interações sociais (curtidas + comentários) de uma publicação pelo seu URN. */
    public function getSocialActions($shareUrn)
    {
        return $this->get('rest/socialActions/' . rawurlencode($shareUrn));
    }

    /** Extrai os totais de share statistics de uma resposta. */
    public static function shareTotals($statsResponse)
    {
        return $statsResponse['elements'][0]['totalShareStatistics'] ?? [];
    }
}
