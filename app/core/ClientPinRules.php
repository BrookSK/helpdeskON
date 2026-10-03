<?php

/**
 * Regras puras (sem banco/HTTP) do PIN de login do CLIENTE (Fase 9).
 *
 * Este PIN é DIFERENTE do external_pin de EQUIPE (migration 126, usado em
 * /solicitacaoexterna para criar demanda em nome do atendente). O PIN do cliente
 * é um login simplificado do PRÓPRIO cliente, que cai na página de nova demanda
 * vinculada ao usuário dele — sem acessar dados de outro cliente.
 *
 * Para não colidir com o PIN de equipe (4 dígitos), o PIN do cliente usa 6
 * dígitos. A validação de formato e a normalização ficam aqui, testáveis.
 */
class ClientPinRules
{
    public const PIN_LENGTH = 6;

    /** Formato válido do PIN do cliente: exatamente 6 dígitos. */
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
     * Só papéis de CLIENTE podem usar o PIN de cliente. Impede que um PIN
     * atribuído a um usuário interno sirva de login simplificado (segurança).
     */
    public static function roleCanUseClientPin(?string $role): bool
    {
        return $role === 'client';
    }

    /**
     * O usuário resolvido pelo PIN pode entrar no ambiente do cliente?
     * Precisa ser client, estar ativo e ter o PIN definido.
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

    /** Gera um PIN de 6 dígitos aleatório (zero-padded). O caller garante unicidade. */
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), self::PIN_LENGTH, '0', STR_PAD_LEFT);
    }
}
