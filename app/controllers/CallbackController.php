<?php

/**
 * Controller para receber callbacks OAuth (LinkedIn, Meta).
 * Recebe o authorization code, troca pelo access token e salva nas configurações.
 */
class CallbackController extends Controller
{
    /**
     * GET /callback?code=XXX&state=linkedin
     * Recebe o redirect do OAuth e troca o code pelo token.
     */
    public function index()
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? 'linkedin'; // identifica qual provedor
        $error = $_GET['error'] ?? '';
        $errorDesc = $_GET['error_description'] ?? '';

        if ($error) {
            $this->showResult(false, "Autorização negada: {$errorDesc}");
            return;
        }

        if (empty($code)) {
            $this->showResult(false, "Nenhum código de autorização recebido.");
            return;
        }

        if ($state === 'meta') {
            $this->handleMetaCallback($code);
        } else {
            $this->handleLinkedinCallback($code);
        }
    }

    private function handleLinkedinCallback($code)
    {
        $clientId = Config::get('linkedin_client_id');
        $clientSecret = Config::get('linkedin_client_secret');
        $redirectUri = $this->getRedirectUri();

        if (!$clientId || !$clientSecret) {
            $this->showResult(false, "Configure o Client ID e Client Secret do LinkedIn em Configurações antes de autorizar.");
            return;
        }

        // Trocar o code pelo access token
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $ch = curl_init('https://www.linkedin.com/oauth/v2/accessToken');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            $errMsg = $data['error_description'] ?? ($data['error'] ?? 'Erro desconhecido ao trocar o código pelo token.');
            $this->showResult(false, "Falha ao obter token do LinkedIn: {$errMsg}");
            return;
        }

        $token = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? null; // segundos

        // Salvar o token no próximo slot disponível
        $this->saveTokenToNextSlot('linkedin', $token);

        $expDays = $expiresIn ? round($expiresIn / 86400) : '?';
        $this->showResult(true, "Token do LinkedIn obtido com sucesso! Validade: ~{$expDays} dias. Já foi salvo nas configurações.");
    }

    private function handleMetaCallback($code)
    {
        $appId = Config::get('meta_app_id');
        $appSecret = Config::get('meta_app_secret');
        $redirectUri = $this->getRedirectUri() . '?state=meta';

        if (!$appId || !$appSecret) {
            $this->showResult(false, "Configure o App ID e App Secret da Meta em Configurações antes de autorizar.");
            return;
        }

        // Trocar o code por token de curta duração
        $url = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);

        if (empty($data['access_token'])) {
            $errMsg = $data['error']['message'] ?? 'Erro ao trocar code pelo token.';
            $this->showResult(false, "Falha Meta: {$errMsg}");
            return;
        }

        // Converter para token de longa duração
        $shortToken = $data['access_token'];
        $url2 = "https://graph.facebook.com/v21.0/oauth/access_token?" . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        $ch = curl_init($url2);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $resp2 = curl_exec($ch);
        curl_close($ch);
        $data2 = json_decode($resp2, true);

        $finalToken = $data2['access_token'] ?? $shortToken;
        $expiresIn = $data2['expires_in'] ?? null;

        $this->saveTokenToNextSlot('meta', $finalToken);

        $expDays = $expiresIn ? round($expiresIn / 86400) : '?';
        $this->showResult(true, "Token da Meta obtido com sucesso (longa duração)! Validade: ~{$expDays} dias. Já foi salvo nas configurações.");
    }

    /**
     * Salva o token no próximo slot disponível (meta_access_token, meta_access_token_2, ...).
     */
    private function saveTokenToNextSlot($provider, $token)
    {
        if ($provider === 'meta') {
            $baseKey = 'meta_access_token';
        } else {
            $baseKey = 'linkedin_access_token';
        }

        // Verifica se o token já existe em algum slot (evita duplicata)
        $existing = Config::get($baseKey);
        if ($existing === $token) return;
        for ($i = 2; $i <= 20; $i++) {
            if (Config::get($baseKey . '_' . $i) === $token) return;
        }

        // Encontra o primeiro slot vazio
        if (empty(Config::get($baseKey))) {
            Config::set($baseKey, $token);
            return;
        }
        for ($i = 2; $i <= 20; $i++) {
            $key = $baseKey . '_' . $i;
            if (empty(Config::get($key))) {
                Config::set($key, $token);
                return;
            }
        }
        // Se todos estão cheios, sobrescreve o principal
        Config::set($baseKey, $token);
    }

    private function getRedirectUri()
    {
        return baseUrl('callback');
    }

    private function showResult($success, $message)
    {
        $icon = $success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
        $color = $success ? '#16a34a' : '#dc2626';
        $title = $success ? 'Autorização concluída' : 'Erro na autorização';

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">';
        echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8f9fa;}'
            . '.box{background:#fff;border-radius:16px;padding:40px;text-align:center;max-width:480px;box-shadow:0 4px 24px rgba(0,0,0,0.08);}'
            . '.icon{font-size:3rem;color:' . $color . ';margin-bottom:16px;}'
            . '.msg{font-size:1rem;color:#333;margin-bottom:24px;}'
            . 'a{display:inline-block;padding:10px 24px;background:#00997D;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;}'
            . 'a:hover{background:#007d66;}</style></head><body>';
        echo '<div class="box"><div class="icon"><i class="bi ' . $icon . '"></i></div>';
        echo '<h2 style="font-size:1.3rem;margin-bottom:8px;">' . $title . '</h2>';
        echo '<p class="msg">' . htmlspecialchars($message) . '</p>';
        echo '<a href="' . baseUrl('settings') . '">Voltar para Configurações</a>';
        echo '</div></body></html>';
        exit;
    }
}
