<?php

/**
 * Regras puras (sem banco/HTTP) de acesso a uma demanda (ticket).
 *
 * Centraliza "quem pode ver / agir numa demanda", de forma testável, para
 * fechar os IDOR onde ações (comentar, ler mensagens, anexar, mudar status)
 * não validavam o dono/empresa do ticket.
 *
 * Papéis de equipe (super_admin, developer, attendant, whatsapp_agent, analyst,
 * comercial, marketing) têm acesso amplo — o escopo fino por empresa da equipe
 * é tratado à parte (allowed_companies). O foco aqui é conter o CLIENTE ao seu
 * próprio ticket ou aos da sua empresa (quando for dono da empresa).
 */
class TicketAccess
{
    /** Papéis internos (equipe) — não são 'client'. */
    public const TEAM_ROLES = [
        'super_admin', 'developer', 'attendant', 'whatsapp_agent',
        'analyst', 'comercial', 'marketing',
    ];

    /** Status válidos de uma demanda. */
    public const STATUSES = [
        'open', 'in_progress', 'em_revisao_interna', 'waiting_client',
        'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived',
    ];

    public static function isTeam(?string $role): bool
    {
        return $role !== null && in_array($role, self::TEAM_ROLES, true);
    }

    public static function isValidStatus($status): bool
    {
        return is_string($status) && in_array($status, self::STATUSES, true);
    }

    /**
     * O usuário pode VER/AGIR sobre a demanda?
     *
     * @param string|null $viewerRole   papel de quem acessa
     * @param int         $viewerId     id de quem acessa
     * @param int         $ticketClientId  dono (client_id) da demanda
     * @param bool        $viewerIsOwner   quem acessa é dono da empresa (is_company_owner)
     * @param int|null    $viewerCompanyId empresa do visualizador
     * @param int|null    $ticketOwnerCompanyId empresa do dono da demanda
     */
    public static function canAccess(
        ?string $viewerRole,
        int $viewerId,
        int $ticketClientId,
        bool $viewerIsOwner = false,
        ?int $viewerCompanyId = null,
        ?int $ticketOwnerCompanyId = null
    ): bool {
        // Equipe interna vê as demandas (escopo por empresa da equipe é aplicado
        // fora daqui, na listagem/allowed_companies).
        if (self::isTeam($viewerRole)) {
            return true;
        }
        // Cliente dono da própria demanda.
        if ($viewerId > 0 && $viewerId === $ticketClientId) {
            return true;
        }
        // Dono da empresa vê demandas de membros da MESMA empresa.
        if ($viewerRole === 'client' && $viewerIsOwner
            && $viewerCompanyId !== null && $ticketOwnerCompanyId !== null
            && $viewerCompanyId === $ticketOwnerCompanyId) {
            return true;
        }
        return false;
    }

    /**
     * O cliente pode mudar o status da demanda para $newStatus?
     * Regra de negócio existente: o cliente só age quando a demanda está em
     * homologação, aprovando (aprovado_producao) ou reprovando (denied).
     */
    public static function clientCanChangeStatus(?string $currentStatus, ?string $newStatus): bool
    {
        return $currentStatus === 'em_homologacao'
            && in_array($newStatus, ['aprovado_producao', 'denied'], true);
    }
}
