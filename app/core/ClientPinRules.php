<?php

/**
 * Regras puras (sem banco/HTTP) do PIN de login por usuário.
 *
 * O PIN (users.client_pin, 4 dígitos) é o acesso rápido via /clientpin: entra
 * como o próprio usuário, com os mesmos acessos do login por senha. Vale para
 * qualquer papel. A validação de formato e a normalização ficam aqui, testáveis.
 */
class ClientPinRules
{
    public const PIN_LENGTH = 4;

    /** Formato válido do PIN do cliente: exatamente 4 dígitos. */
    public static function isValidFormat(?string $pin): bool
    {
        return is_string($pin) && preg_match('/^\d{' . self::PIN_LENGTH . '}$/', trim($pin)) === 1;
    }

    /** Normaliza o PIN (apara espaços). Retorna '' se inválido. */
    public static function normalize(?string $pin): string
    {
        $p = trim((string)$pin);
        return self::isValidFormat($p) ? $p : '';
    }

    /**
     * O PIN é por USUÁRIO: qualquer papel pode usá-lo para entrar (não apenas
     * clientes). A única exigência é ter um papel definido.
     */
    public static function roleCanUseClientPin(?string $role): bool
    {
        return is_string($role) && $role !== '';
    }

    /**
     * O usuário resolvido pelo PIN pode entrar? Precisa estar ativo e ter o PIN
     * definido (4 dígitos). Vale para qualquer papel — o PIN é por usuário.
     *
     * @param array|null $user linha de users (role, is_active, client_pin)
     */
    public static function canAuthenticate(?array $user): bool
    {
        if (!$user) return false;
        if (!self::roleCanUseClientPin($user['role'] ?? null)) return false;
        if ((int)($user['is_active'] ?? 0) !== 1) return false;
        return self::isValidFormat($user['client_pin'] ?? null);
    }

    /** Gera um PIN aleatório de PIN_LENGTH dígitos (zero-padded). O caller garante unicidade. */
    public static function generate(): string
    {
        $max = (int) str_repeat('9', self::PIN_LENGTH); // ex.: 4 dígitos -> 9999
        return str_pad((string) random_int(0, $max), self::PIN_LENGTH, '0', STR_PAD_LEFT);
    }
}
