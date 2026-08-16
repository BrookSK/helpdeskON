<?php

/**
 * Cliente da API Nvoip v3 (integração servidor a servidor).
 *
 * Contrato canônico: documentação oficial Nvoip API v3.
 * Fluxo de autenticação principal: client_credentials (servidor a servidor).
 *
 * Configuração em Configurações (tabela settings):
 *  - nvoip_auth_base_url          (authBaseUrl)
 *  - nvoip_base_url               (baseUrl)
 *  - nvoip_oauth_client_id        (oauthClientId)
 *  - nvoip_oauth_client_credential(oauthClientCredential)  [SECRETO]
 *  - nvoip_oauth_scopes           (oauthScopes)
 *  - nvoip_caller                 (originador autorizado da conta — informado pelo admin)
 *
 * Segredos (nunca logar, nunca retornar ao frontend):
 *  - oauthClientCredential, access_token, refresh_token.
 */
class NvoipApi
{
    private $authBaseUrl;
    private $baseUrl;
    private $clientId;
    private $clientCredential;
    private $scopes;
    private $accessToken = null;

    public function __construct()
    {
        $this->authBaseUrl = rtrim((string) Config::get('nvoip_auth_base_url'), '/');
        $this->baseUrl = rtrim((string) Config::get('nvoip_base_url'), '/');
        $this->clientId = (string) Config::get('nvoip_oauth_client_id');
        $this->clientCredential = (string) Config::get('nvoip_oauth_client_credential');
        $this->scopes = (string) Config::get('nvoip_oauth_scopes');
    }

    public function isConfigured()
    {
        return $this->authBaseUrl !== '' && $this->baseUrl !== ''
            && $this->clientId !== '' && $this->clientCredential !== '';
    }

    /** Originador autorizado configurado pelo administrador. */
    public function caller()
    {
        return (string) Config::get('nvoip_caller');
    }

    /**
     * Obtém o access token via grant_type=client_credentials.
     * POST {{authBaseUrl}}/oauth2/token  (Basic Auth: clientId:clientCredential)
     * @return array ['success'=>bool, 'token'=>?, 'expires_in'=>?, 'error'=>?, 'http'=>int]
     */
    public function authenticate()
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Nvoip não configurado.'];
        }

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'scope' => $this->scopes,
        ]);

        $ch = curl_init($this->authBaseUrl . '/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientCredential,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            Logger::error('Nvoip auth: falha de conexão', ['http' => $code, 'curl_error' => $err]);
            return ['success' => false, 'error' => 'Falha de conexão com o servidor de autenticação.', 'http' => $code];
        }
        $data = json_decode($resp, true);
        if ($code >= 400 || empty($data['access_token'])) {
            // Loga o motivo técnico (sem token/segredo) para debug no painel.
            Logger::error('Nvoip auth: token não emitido', [
                'http' => $code,
                'oauth_error' => $data['error'] ?? null,
                'oauth_error_description' => $data['error_description'] ?? null,
            ]);
            // Não expõe corpo bruto de auth ao chamador; mensagem genérica.
            return ['success' => false, 'error' => 'Falha na autenticação (HTTP ' . $code . ').', 'http' => $code];
        }

        $this->accessToken = $data['access_token'];
        return [
            'success' => true,
            'token' => $this->accessToken,
            // Usa expiração retornada pela API, se houver (não inventar duração).
            'expires_in' => $data['expires_in'] ?? null,
            'http' => $code,
        ];
    }

    /** Garante um access token em memória para as chamadas subsequentes. */
    private function token()
    {
        if ($this->accessToken) return $this->accessToken;
        $auth = $this->authenticate();
        return $auth['success'] ? $auth['token'] : null;
    }

    /**
     * Metadados do Authorization Server.
     * GET {{authBaseUrl}}/.well-known/oauth-authorization-server
     */
    public function getAuthorizationServerMetadata()
    {
        if ($this->authBaseUrl === '') return ['success' => false, 'error' => 'authBaseUrl não configurado.'];
        return $this->request('GET', $this->authBaseUrl . '/.well-known/oauth-authorization-server', null, false);
    }

    /**
     * Cria (origina) uma chamada direta.
     * POST {{baseUrl}}/calls/
     * Payload documentado: caller, called, checkDDI, transfer.
     */
    public function createCall($caller, $called, $checkDDI = true, $transfer = false)
    {
        $payload = [
            'caller' => $caller,
            'called' => $called,
            'checkDDI' => (bool) $checkDDI,
            'transfer' => (bool) $transfer,
        ];
        return $this->request('POST', $this->baseUrl . '/calls/', $payload);
    }

    /**
     * Click-to-call: chama primeiro o ramal SIP (caller) e, após o atendimento,
     * conecta ao destino (called). O ramal precisa estar registrado e apto a
     * receber a primeira perna. Endpoint indicado para uso via CRM.
     * POST {{baseUrl}}/calls/click-to-call
     * Payload documentado: caller, called.
     */
    public function clickToCall($caller, $called)
    {
        $payload = [
            'caller' => $caller,
            'called' => $called,
        ];
        return $this->request('POST', $this->baseUrl . '/calls/click-to-call', $payload);
    }

    /**
     * Consulta a situação de uma chamada pelo callId.
     * GET {{baseUrl}}/calls?callId={{callId}}
     */
    public function getCall($callId)
    {
        return $this->request('GET', $this->baseUrl . '/calls?callId=' . rawurlencode($callId));
    }

    /**
     * Histórico de ligações em uma data.
     * GET {{baseUrl}}/calls/history?date=YYYY-MM-DD&type=all
     */
    public function getHistory($date, $type = 'all')
    {
        $q = http_build_query(['date' => $date, 'type' => $type]);
        return $this->request('GET', $this->baseUrl . '/calls/history?' . $q);
    }

    /**
     * Lista usuários da conta (ou de conta gerenciada via managedAccountId).
     * GET {{baseUrl}}/users?page=0&size=50[&managedAccountId=...]
     */
    public function getUsers($page = 0, $size = 50, $managedAccountId = null)
    {
        $params = ['page' => (int) $page, 'size' => (int) $size];
        if ($managedAccountId !== null && $managedAccountId !== '') {
            $params['managedAccountId'] = $managedAccountId;
        }
        return $this->request('GET', $this->baseUrl . '/users?' . http_build_query($params));
    }

    /**
     * Executa uma requisição autenticada (Bearer) à API Nvoip.
     * @param bool $auth  Se true, injeta o Bearer token.
     * @return array ['success'=>bool, 'status'=>int, 'data'=>mixed, 'error'=>?]
     */
    private function request($method, $url, $jsonBody = null, $auth = true)
    {
        $headers = [];
        if ($auth) {
            $token = $this->token();
            if (!$token) return ['success' => false, 'error' => 'Não foi possível autenticar na Nvoip.'];
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            Logger::error('Nvoip request: falha de conexão', ['method' => $method, 'url' => $url, 'curl_error' => $err]);
            return ['success' => false, 'status' => $code, 'error' => 'Falha de conexão com a Nvoip.'];
        }
        $data = json_decode($resp, true);
        if ($data === null && $resp !== '') $data = $resp; // resposta não-JSON

        $ok = $code >= 200 && $code < 300;
        if (!$ok) {
            // Registra o erro da API (corpo pode conter mensagem útil; sem headers de auth).
            Logger::error('Nvoip request: HTTP ' . $code, [
                'method' => $method,
                'url' => $url,
                'body' => is_string($data) ? substr($data, 0, 500) : $data,
            ]);
        }

        return [
            'success' => $ok,
            'status' => $code,
            'data' => $data,
        ];
    }
}
