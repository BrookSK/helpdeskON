<?php

/**
 * Client HTTP do Asaas (Fase 5) — cobranças/assinaturas. A lógica testável está
 * em AsaasRules; aqui só as chamadas HTTP reais (não testáveis no dev).
 *
 * Multi-conta: o token é passado por conta (finance_accounts.asaas_token), pois
 * a empresa usa 3 contas Asaas distintas selecionáveis por cobrança.
 */
class AsaasApi
{
    private $token;
    private $sandbox;

    public function __construct(string $token, bool $sandbox = true)
    {
        $this->token = $token;
        $this->sandbox = $sandbox;
    }

    /** Instancia a partir de uma linha de finance_accounts. */
    public static function fromAccount(array $account): self
    {
        return new self((string)($account['asaas_token'] ?? ''), (int)($account['sandbox'] ?? 1) === 1);
    }

    public function isConfigured(): bool
    {
        return AsaasRules::isConfigured($this->token);
    }

    public function createCharge(string $customerId, float $value, ?string $dueDate, ?string $method, ?string $description): array
    {
        return $this->request('POST', '/payments', AsaasRules::chargePayload($customerId, $value, $dueDate, $method, $description));
    }

    public function createSubscription(string $customerId, float $value, string $cycle, ?string $nextDueDate, ?string $method, ?string $description): array
    {
        return $this->request('POST', '/subscriptions', AsaasRules::subscriptionPayload($customerId, $value, $cycle, $nextDueDate, $method, $description));
    }

    private function request(string $method, string $path, ?array $data = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Conta Asaas sem token configurado.'];
        }
        $url = AsaasRules::baseUrl($this->sandbox) . '/' . ltrim($path, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'access_token: ' . $this->token,
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
            return ['success' => false, 'error' => 'Falha de conexão com o Asaas: ' . $err, 'http' => $http];
        }
        $decoded = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;
        return ['success' => $ok, 'http' => $http, 'data' => is_array($decoded) ? $decoded : null, 'error' => $ok ? null : ('Asaas HTTP ' . $http)];
    }
}
