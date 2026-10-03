<?php

/**
 * Criptografia simétrica para o cofre de credenciais (Fase 6).
 *
 * Nunca guardamos a senha/token em texto puro: usamos AES-256-GCM com uma chave
 * do sistema. O ciphertext é "base64(iv|tag|cipher)" — tudo testável por
 * round-trip (encrypt -> decrypt).
 *
 * A chave vem de Settings 'credentials_key' (ou do APP_KEY). Em produção, deve
 * ser um segredo forte e estável; se mudar, as credenciais antigas não decifram.
 */
class CredentialCrypto
{
    private const CIPHER = 'aes-256-gcm';

    /** Deriva uma chave de 32 bytes a partir do segredo informado. */
    public static function deriveKey(string $secret): string
    {
        // hash raw sha256 -> 32 bytes exatos p/ AES-256.
        return hash('sha256', $secret, true);
    }

    /** Segredo do sistema (Settings credentials_key, com fallback). */
    public static function systemSecret(): string
    {
        $s = '';
        if (class_exists('Config')) {
            $s = (string) Config::get('credentials_key');
        }
        if ($s === '') {
            // Fallback estável para dev/teste (NÃO ideal p/ produção; configure a key).
            $s = 'helpdeskon-credentials-default-key';
        }
        return $s;
    }

    /**
     * Criptografa um texto. Retorna base64(iv|tag|cipher) ou '' se o texto for
     * vazio. Usa a chave do sistema por padrão.
     */
    public static function encrypt(?string $plaintext, ?string $secret = null): string
    {
        $plaintext = (string) $plaintext;
        if ($plaintext === '') return '';
        $key = self::deriveKey($secret ?? self::systemSecret());
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLen);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) return '';
        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * Descriptografa. Retorna o texto puro, ou null se falhar (chave errada,
     * dado corrompido/adulterado — o GCM detecta via tag).
     */
    public static function decrypt(?string $encoded, ?string $secret = null): ?string
    {
        $encoded = (string) $encoded;
        if ($encoded === '') return null;
        $raw = base64_decode($encoded, true);
        if ($raw === false) return null;
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $tagLen = 16; // GCM tag
        if (strlen($raw) < $ivLen + $tagLen) return null;
        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, $tagLen);
        $cipher = substr($raw, $ivLen + $tagLen);
        $key = self::deriveKey($secret ?? self::systemSecret());
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /**
     * Máscara para exibir que há um segredo sem revelá-lo (listagens).
     * Nunca retorna o valor real.
     */
    public static function mask(?string $encoded): string
    {
        return ($encoded !== null && $encoded !== '') ? '••••••••' : '';
    }
}
