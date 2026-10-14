<?php

/**
 * Regras puras (sem rede) da integração ClickSign (Fase 4).
 *
 * Isola o que é testável sem bater na API: base URL por ambiente, montagem de
 * payloads (documento/signatário/lista), validação da assinatura HMAC do webhook
 * e a interpretação do evento recebido (o que significa para o nosso contrato).
 *
 * A chamada HTTP real fica em ClickSignApi (não testável no ambiente de dev).
 */
class ClickSignRules
{
    /** Base URL por ambiente. Sandbox para testes, produção para valer. */
    public const BASE_PROD = 'https://app.clicksign.com';
    public const BASE_SANDBOX = 'https://sandbox.clicksign.com';

    public static function baseUrl(bool $sandbox): string
    {
        return $sandbox ? self::BASE_SANDBOX : self::BASE_PROD;
    }

    /**
     * Monta a URL de um endpoint da API v1 com o access_token em query string
     * (forma de autenticação da ClickSign v1).
     */
    public static function endpoint(bool $sandbox, string $path, string $accessToken): string
    {
        $base = self::baseUrl($sandbox);
        $path = '/' . ltrim($path, '/');
        $sep = (strpos($path, '?') !== false) ? '&' : '?';
        return $base . $path . $sep . 'access_token=' . rawurlencode($accessToken);
    }

    /**
     * Payload para criar um documento a partir de conteúdo (base64 data URI).
     * O "path" é o caminho/nome lógico do arquivo dentro da conta ClickSign.
     */
    public static function documentPayload(string $path, string $contentBase64DataUri): array
    {
        return [
            'document' => [
                'path' => $path,
                'content_base64' => $contentBase64DataUri,
            ],
        ];
    }

    /** Payload para criar um signatário. */
    public static function signerPayload(string $email, string $name, ?string $phone = null, string $auth = 'email'): array
    {
        $signer = [
            'email' => $email,
            'name' => $name,
            'auths' => [$auth],
        ];
        if ($phone !== null && trim($phone) !== '') {
            $signer['phone_number'] = preg_replace('/\D+/', '', $phone);
        }
        return ['signer' => $signer];
    }

    /** Payload para vincular (adicionar) um signatário a um documento. */
    public static function listPayload(string $documentKey, string $signerKey, string $signAs = 'sign'): array
    {
        return [
            'list' => [
                'document_key' => $documentKey,
                'signer_key' => $signerKey,
                'sign_as' => $signAs,
            ],
        ];
    }

    /**
     * Valida a assinatura HMAC do webhook. A ClickSign assina o corpo cru com o
     * secret (HMAC-SHA256) e envia no header. Comparação em tempo constante.
     */
    public static function verifyWebhookSignature(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $signatureHeader === '') return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        // O header pode vir como "sha256=..." ou só o hash.
        $received = trim($signatureHeader);
        if (stripos($received, 'sha256=') === 0) {
            $received = substr($received, 7);
        }
        return hash_equals($expected, $received);
    }

    /**
     * Interpreta o nome do evento do webhook e devolve a AÇÃO interna:
     *  - 'signed'   : o documento foi finalizado/assinado por todos (auto_close / close / sign do último)
     *  - 'ignore'   : evento informativo que não muda o contrato
     *
     * A ClickSign emite, entre outros: 'upload', 'add_signer', 'sign',
     * 'auto_close' (finalizou/fechou o documento), 'deadline', 'cancel'.
     * Consideramos o contrato "assinado" quando o documento fecha (auto_close/close).
     */
    public static function interpretEvent(?string $eventName): string
    {
        $e = strtolower(trim((string)$eventName));
        if (in_array($e, ['auto_close', 'close', 'document_closed'], true)) return 'signed';
        if ($e === 'cancel') return 'cancelled';
        return 'ignore';
    }

    /** A integração está configurada? (precisa de token). */
    public static function isConfigured(?string $accessToken): bool
    {
        return is_string($accessToken) && trim($accessToken) !== '';
    }
}
