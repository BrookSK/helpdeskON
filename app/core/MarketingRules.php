<?php

/**
 * Regras de negócio puras (sem banco/HTTP) do módulo de Marketing.
 *
 * Extraídas do MarketingController para ficarem testáveis por unidade e
 * servirem de fonte única de verdade para: whitelist de status, status que o
 * perfil "marketing" NÃO pode definir, status que exigem imagem anexada, e a
 * regra de anti-retrocesso (não voltar de aprovado/agendado/publicado por um
 * salvamento comum de conteúdo).
 */
class MarketingRules
{
    /** Status válidos, em ordem de fluxo. Fonte única (usada pelo controller e model). */
    public const STATUSES = [
        'rascunho', 'ideia', 'em_producao', 'aguardando_aprovacao',
        'aprovado', 'agendado', 'publicado', 'rejeitado',
    ];

    /** Rótulos amigáveis para mensagens/notificações. */
    public const STATUS_LABELS = [
        'rascunho' => 'Rascunho',
        'ideia' => 'Ideia',
        'em_producao' => 'Em produção',
        'aguardando_aprovacao' => 'Aguardando aprovação',
        'aprovado' => 'Aprovado',
        'agendado' => 'Agendado',
        'publicado' => 'Publicado',
        'rejeitado' => 'Rejeitado',
    ];

    /** Status que somente o admin pode definir (marketing não aprova/rejeita). */
    public const ADMIN_ONLY_STATUSES = ['aprovado', 'rejeitado'];

    /**
     * Status que exigem ao menos uma imagem anexada (para um não-admin sair de
     * rascunho e seguir no fluxo).
     */
    public const IMAGE_REQUIRED_STATUSES = [
        'em_producao', 'aguardando_aprovacao', 'aprovado', 'agendado', 'publicado',
    ];

    /** Status que já passaram da aprovação (não retrocedem por salvamento comum). */
    public const POST_APPROVAL_STATUSES = ['aprovado', 'agendado', 'publicado'];

    /**
     * Um valor de status é válido para persistência?
     */
    public static function isValidStatus($value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    /**
     * Normaliza o status: retorna o valor se válido, senão o default informado.
     */
    public static function normalizeStatus($value, string $default = 'ideia'): string
    {
        return in_array($value, self::STATUSES, true) ? $value : $default;
    }

    /** Rótulo amigável de um status. */
    public static function label($status): string
    {
        return self::STATUS_LABELS[$status] ?? (string) $status;
    }

    /**
     * O perfil pode definir este status? Marketing não pode aprovar/rejeitar.
     */
    public static function canSetStatus(string $role, string $status): bool
    {
        if ($role === 'super_admin') {
            return self::isValidStatus($status);
        }
        return self::isValidStatus($status) && !in_array($status, self::ADMIN_ONLY_STATUSES, true);
    }

    /**
     * Este status exige imagem anexada para um não-admin?
     */
    public static function requiresImage(string $status): bool
    {
        return in_array($status, self::IMAGE_REQUIRED_STATUSES, true);
    }

    /**
     * A transição de $from para $to é um retrocesso a partir de um estado
     * pós-aprovação? (aprovado/agendado/publicado -> algo anterior)
     * Nesse caso, o status deve ser preservado (ignora-se o retrocesso).
     */
    public static function isBackwardFromApproved(string $from, string $to): bool
    {
        return in_array($from, self::POST_APPROVAL_STATUSES, true)
            && !in_array($to, self::POST_APPROVAL_STATUSES, true);
    }

    /**
     * Apenas demandas aprovadas ou agendadas podem ser retornadas para a fila
     * de aprovações (returnToApproval).
     */
    public static function canReturnToApproval(string $currentStatus): bool
    {
        return in_array($currentStatus, ['aprovado', 'agendado'], true);
    }

    /**
     * Um nome de arquivo/MIME representa imagem?
     */
    public static function isImageAttachment(?string $fileName, ?string $fileType = null): bool
    {
        if ($fileName && preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', $fileName)) {
            return true;
        }
        if ($fileType && strpos($fileType, 'image/') === 0) {
            return true;
        }
        return false;
    }
}
