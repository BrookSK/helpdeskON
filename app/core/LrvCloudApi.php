<?php

/**
 * Client HTTP do LRV Cloud Manager (Fase 7). A lógica testável está em
 * ProvisioningRules; aqui só as chamadas HTTP reais (não testáveis no dev).
 *
 * Base URL: https://cloud.lrvweb.com.br/api/v1/ — auth via header X-API-Key.
 *
 * IMPORTANTE: a API ainda NÃO expõe criar conta de cliente, provisionar VPS, nem
 * criar aplicação/deploy (ver api-lrv-cloud-gaps.md). Os métodos desses passos
 * retornam ['available' => false] para o fluxo registrar uma pendência manual em
 * vez de simular sucesso. Os métodos do que já existe (hosting read/restart,
 * databases, domains, webhooks, tickets) fazem a chamada real.
 */
class LrvCloudApi
{
    public const BASE_URL = 'https://cloud.lrvweb.com.br/api/v1';

    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /** Instancia a partir das Settings (lrv_cloud_api_key). */
    public static function fromConfig(): self
    {
        $key = '';
        if (class_exists('Config')) {
            $key = (string) Config::get('lrv_cloud_api_key');
        }
        return new self($key);
    }

    public function isConfigured(): bool
    {
        return ProvisioningRules::isConfigured($this->apiKey);
    }

    // ================= Endpoints que JÁ EXISTEM =================

    public function listHosting(): array
    {
        return $this->request('GET', '/hosting');
    }

    public function showHosting(string $id): array
    {
        return $this->request('GET', '/hosting/show?id=' . rawurlencode($id));
    }

    public function restartHosting(string $id): array
    {
        return $this->request('POST', '/hosting/restart?id=' . rawurlencode($id));
    }

    public function createDatabase(array $payload): array
    {
        // POST /databases (v1.1): body vps_id, db_name, db_user?, db_type.
        // A senha é retornada UMA ÚNICA VEZ na resposta.
        return $this->request('POST', '/databases', $payload);
    }

    public function addDomain(string $domain, string $vpsId, string $type = 'addon'): array
    {
        return $this->request('POST', '/domains', ['domain' => $domain, 'vps_id' => (int)$vpsId, 'type' => $type]);
    }

    // ================= Endpoints de provisionamento (API v1.1) =================
    // Implementados após o LRV Cloud publicar os 5 gaps (ver api-lrv-cloud-gaps.md).

    /** POST /clients — cria cliente final vinculado à conta (revenda). Dispara client.created. */
    public function createClient(array $data): array
    {
        return $this->request('POST', '/clients', $data);
    }

    /**
     * POST /hosting — provisiona VPS (assíncrono, 202). plan por id ou nome.
     * Dispara hosting.created e, quando no ar, hosting.ready.
     */
    public function createVps(array $data): array
    {
        return $this->request('POST', '/hosting', $data);
    }

    /** POST /hosting/suspend — suspende a VPS. */
    public function suspendVps(string $id): array
    {
        return $this->request('POST', '/hosting/suspend', ['id' => (int)$id]);
    }

    /** POST /hosting/stop — desliga o container preservando dados. */
    public function stopVps(string $id): array
    {
        return $this->request('POST', '/hosting/stop', ['id' => (int)$id]);
    }

    /**
     * POST /applications/git — cria aplicação conectada a Git e enfileira deploy
     * (202). staging_subdomain=true gera subdomínio de homologação com SSL e
     * retorna staging_url. Dispara application.installed.
     */
    public function createApplication(array $data): array
    {
        return $this->request('POST', '/applications/git', $data);
    }

    /** POST /applications/git/deploy — re-dispara o deploy. Dispara application.deployed. */
    public function deployApplication(string $appId): array
    {
        return $this->request('POST', '/applications/git/deploy', ['id' => (int)$appId]);
    }

    /** GET /applications/git/show — detalhe do deploy (status, staging_url). */
    public function showApplication(string $appId): array
    {
        return $this->request('GET', '/applications/git/show?id=' . rawurlencode($appId));
    }

    /**
     * Homologação: no modelo v1.1 o staging é gerado pelo próprio deploy Git com
     * staging_subdomain=true. Mantido como método dedicado por compatibilidade
     * com o fluxo de etapas (recebe os dados do app e repassa com a flag).
     */
    public function createStaging(string $appId): array
    {
        // Sem app ainda criado não há o que promover a staging.
        if (trim($appId) === '') {
            return ['success' => false, 'available' => true, 'error' => 'Aplicação ainda não criada para gerar homologação.'];
        }
        return $this->showApplication($appId);
    }

    // ================= HTTP =================

    private function request(string $method, string $path, ?array $data = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'available' => true, 'error' => 'LRV Cloud sem API key configurada.'];
        }
        $url = self::BASE_URL . '/' . ltrim($path, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if ($data !== null && strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'available' => true, 'error' => 'Falha de conexão com o LRV Cloud: ' . $err, 'http' => $http];
        }
        $decoded = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;
        return [
            'success' => $ok,
            'available' => true,
            'http' => $http,
            'data' => is_array($decoded) ? $decoded : null,
            'error' => $ok ? null : ('LRV Cloud HTTP ' . $http),
        ];
    }
}
