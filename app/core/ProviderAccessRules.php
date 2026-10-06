<?php

/**
 * Regras puras (sem banco/HTTP) do ACESSO ao sistema concedido ao prestador
 * após a assinatura. Decide o PAPEL (role) do usuário conforme a função do
 * prestador — "como desenvolvedor, ou dependendo do que for (marketing etc.)".
 */
class ProviderAccessRules
{
    /** Papéis internos válidos para um prestador no sistema. */
    public const ROLES = ['developer', 'marketing', 'attendant', 'analyst'];

    /**
     * Deriva o papel a partir do título da função (e do tipo de contratação como
     * fallback). Procura palavras-chave; default 'developer' (caso mais comum de
     * prestador na esteira). Nunca retorna papel administrativo.
     */
    public static function roleForProvider(?string $roleTitle, ?string $engagementType = null): string
    {
        $t = mb_strtolower(trim((string)$roleTitle));
        if ($t !== '') {
            if (self::hasAny($t, ['market', 'social', 'tráfego', 'trafego', 'conteúdo', 'conteudo', 'design'])) return 'marketing';
            if (self::hasAny($t, ['atend', 'suporte', 'support'])) return 'attendant';
            if (self::hasAny($t, ['analist', 'analyst', 'qa', 'test'])) return 'analyst';
            if (self::hasAny($t, ['dev', 'program', 'engenh', 'full', 'back', 'front', 'software'])) return 'developer';
        }
        return 'developer';
    }

    private static function hasAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && mb_strpos($haystack, $n) !== false) return true;
        }
        return false;
    }
}
