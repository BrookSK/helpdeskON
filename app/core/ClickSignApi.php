<?php

/**
 * Client HTTP da ClickSign (API v1) — assinatura eletrônica (Fase 4).
 *
 * Toda a lógica testável (URLs, payloads, webhook) está em ClickSignRules. Esta
 * classe só faz as chamadas HTTP reais, que dependem de credencial e NÃO são
 * testáveis no ambiente de dev (validação manual quando o token for configurado).
 *
 * Configuração (Settings):
 *   clicksign_access_token  -> token da conta ClickSign
 *   clicksign_sandbox       -> '1' usa sandbox; senão produção
 *   clicksign_webhook_secret-> segredo p/ validar o HMAC do webhook
 */
class ClickSignApi
{
    private $accessToken;
    private $sandbox;

    public function __construct($accessToken = null, $sandbox = null)
    {
        $this->accessToken = $accessToken ?? (string) Config::get('clicksign_access_token');
        $this->sandbox = $sandbox ?? (((string) Config::get('clicksign_sandbox')) === '1');

        // Segurança (apenas para HOMOLOGAÇÃO): tokens de homologação da ClickSign
        // trazem o marcador "_hmlg_"/"_test_". Se o token é de homologação, força
        // o ambiente sandbox para não enviar token de teste à URL de produção.
        // Tokens de PRODUÇÃO não têm esses marcadores -> respeita a configuração
        // (produção quando o checkbox "sandbox" está desmarcado).
        $tok = (string) $this->accessToken;
        if (stripos($tok, '_hmlg_') !== false || stripos($tok, '_test_') !== false) {
            $this->sandbox = true;
        }
    }

    public function isConfigured(): bool
    {
        return ClickSignRules::isConfigured($this->accessToken);
    }

    /** Ambiente efetivo (sandbox x produção) usado nas chamadas. */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Cria um documento a partir de um conteúdo (ex.: HTML/base64). Retorna a
     * resposta decodificada; a chave do documento fica em data['document']['key'].
     */
    public function createDocument(string $path, string $contentBase64DataUri): array
    {
        return $this->request('POST', '/api/v1/documents', ClickSignRules::documentPayload($path, $contentBase64DataUri));
    }

    /** Cria um signatário. A chave fica em data['signer']['key']. */
    public function createSigner(string $email, string $name, ?string $phone = null, string $auth = 'email'): array
    {
        return $this->request('POST', '/api/v1/signers', ClickSignRules::signerPayload($email, $name, $phone, $auth));
    }

    /** Vincula o signatário ao documento (gera o request_signature_key). */
    public function addSigner(string $documentKey, string $signerKey, string $signAs = 'sign'): array
    {
        return $this->request('POST', '/api/v1/lists', ClickSignRules::listPayload($documentKey, $signerKey, $signAs));
    }

    /** Cancela um documento na ClickSign. */
    public function cancelDocument(string $documentKey): array
    {
        return $this->request('PATCH', '/api/v1/documents/' . rawurlencode($documentKey) . '/cancel', []);
    }

    /**
     * Dispara a NOTIFICAÇÃO de assinatura por e-mail ao signatário. Sem isso, o
     * signatário é criado/vinculado mas não recebe o e-mail para assinar.
     * Usa o request_signature_key gerado no addSigner().
     */
    public function notifySigner(string $requestSignatureKey, ?string $message = null): array
    {
        $payload = ['request_signature_key' => $requestSignatureKey];
        if ($message !== null && trim($message) !== '') {
            $payload['message'] = $message;
        }
        return $this->request('POST', '/api/v1/notifications', $payload);
    }

    /** Realiza a requisição HTTP (JSON) com o access_token em query string. */
    private function request(string $method, string $path, ?array $data = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'ClickSign não configurada (token ausente).'];
        }
        $url = ClickSignRules::endpoint($this->sandbox, $path, $this->accessToken);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if ($data !== null && strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'error' => 'Falha de conexão com a ClickSign: ' . $err, 'http' => $http];
        }
        $decoded = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;

        // Extrai a mensagem de erro que a ClickSign devolve no corpo, para o
        // operador entender o 401/403/422 (token inválido, ambiente errado, etc.).
        $detail = '';
        if (!$ok && is_array($decoded)) {
            if (!empty($decoded['errors'])) {
                $errs = $decoded['errors'];
                $detail = is_array($errs) ? implode('; ', array_map(fn($e) => is_array($e) ? json_encode($e) : (string)$e, $errs)) : (string)$errs;
            } elseif (!empty($decoded['message'])) {
                $detail = (string)$decoded['message'];
            }
        }
        if (!$ok && $detail === '' && is_string($raw)) {
            $detail = trim(strip_tags($raw)); // às vezes vem HTML (ex.: 403 do proxy)
            if (strlen($detail) > 180) $detail = substr($detail, 0, 180) . '…';
        }
        $hint = '';
        if ($http === 401 || $http === 403) {
            $env = $this->sandbox ? 'sandbox (teste)' : 'produção';
            $hint = " — verifique: (1) o token está correto; (2) o ambiente configurado é {$env} e o token é desse mesmo ambiente; (3) sua conta ClickSign tem a API v1 habilitada.";
        }

        return [
            'success' => $ok,
            'http' => $http,
            'data' => is_array($decoded) ? $decoded : null,
            'raw' => $raw,
            'error' => $ok ? null : ('ClickSign HTTP ' . $http . ($detail !== '' ? ': ' . $detail : '') . $hint),
        ];
    }
}
