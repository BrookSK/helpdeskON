<?php

/**
 * Fonte ÚNICA de verdade para autorização por papel (role) no helpdeskON.
 *
 * Antes, a decisão de "quem vê o quê" estava espalhada e podia divergir entre
 * três lugares: o requireRole() de cada método de controller, os if() de papel
 * no sidebar e checagens inline. Esta classe centraliza o mapa papel -> módulos,
 * de forma pura (sem banco/HTTP) e testável por unidade.
 *
 * Regras de negócio (definidas com o cliente):
 *  - super_admin: acessa tudo.
 *  - developer:   acessa tudo que o super_admin acessa, EXCETO ver o RDO de
 *                 outras pessoas (no RDO ele só vê o próprio). O acesso ao
 *                 MÓDULO rdo é liberado; o escopo "só o meu" é aplicado no
 *                 model/controller via RdoRules::canViewReportOf().
 *  - marketing/comercial/analyst/attendant/whatsapp_agent: acesso segmentado.
 *  - client: apenas a área do cliente.
 *  - whatsapp (Chat) é liberado para TODOS os papéis (inclusive client).
 */
class Permissions
{
    /** Todos os papéis conhecidos do sistema (enum users.role). */
    public const ROLES = [
        'super_admin', 'developer', 'attendant', 'analyst',
        'comercial', 'marketing', 'whatsapp_agent', 'client',
    ];

    /**
     * Módulos (chaves de acesso) usados por controllers e sidebar.
     * Não confundir com "currentPage" da view — aqui é a unidade de permissão.
     */
    public const MODULES = [
        'dashboard',            // painel inicial (todo logado)
        'account',              // minha conta (todo logado)
        'notifications',        // notificações (todo logado)
        'whatsapp',             // WhatsApp Chat (todos)
        'rdo',                  // Relatório Diário (equipe interna)
        // Operacional / suporte
        'tickets',              // Demandas (equipe)
        'tickets_create',       // Nova Demanda (super_admin)
        'planning',             // Planejamento
        'documents',            // Documentos
        'performance_operacional',
        // Comercial / CRM
        'agenda',
        'performance_comercial',
        'crm',
        'crm_apollo',
        'crm_prospecting',
        'sequences',
        'linkedin',
        'leadcapture',
        'leadcapture_admin',
        // Prospecção / marketing / social
        'prospection',
        'marketing',
        'social',
        'videocall',            // Gravações
        // Administração
        'companies',
        'users',
        'settings',
        // Área do cliente
        'client_tickets',
        'client_schedule',
        'subusers',
    ];

    /**
     * Módulos liberados por papel (exceto super_admin/developer, que recebem
     * tudo via allowedModules()). 'whatsapp' aparece em todos de propósito.
     */
    private const ROLE_MODULES = [
        'marketing' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'marketing', 'prospection', 'social', 'videocall',
        ],
        'comercial' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'agenda', 'performance_comercial',
            'prospection', 'videocall',
            'crm', 'crm_apollo', 'crm_prospecting', 'sequences', 'linkedin', 'leadcapture',
        ],
        'attendant' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'documents', 'performance_operacional',
            'prospection', 'videocall',
            'crm', 'sequences', 'linkedin', 'leadcapture',
        ],
        'analyst' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'documents', 'videocall',
        ],
        'whatsapp_agent' => [
            'dashboard', 'account', 'notifications', 'whatsapp', 'rdo',
            'tickets', 'planning', 'videocall',
            'crm', 'sequences', 'linkedin', 'leadcapture',
        ],
        'client' => [
            'dashboard', 'account', 'notifications', 'whatsapp',
            'client_tickets', 'client_schedule', 'documents', 'subusers',
        ],
    ];

    /**
     * Papéis que enxergam TODOS os módulos. O developer é "quase super_admin":
     * acessa todos os módulos; a única diferença (RDO só o dele) é uma regra de
     * ESCOPO de dados, não de acesso ao módulo — aplicada em RdoRules.
     */
    private const FULL_ACCESS_ROLES = ['super_admin', 'developer'];

    /** Lista de módulos que um papel pode acessar. */
    public static function allowedModules(?string $role): array
    {
        if ($role !== null && in_array($role, self::FULL_ACCESS_ROLES, true)) {
            return self::MODULES;
        }
        if ($role === null) {
            return [];
        }
        return self::ROLE_MODULES[$role] ?? [];
    }

    /** O papel pode acessar o módulo? */
    public static function canAccess(?string $role, string $module): bool
    {
        if ($role !== null && in_array($role, self::FULL_ACCESS_ROLES, true)) {
            // Acesso total, mas só a módulos reconhecidos (evita "true" para lixo).
            return in_array($module, self::MODULES, true);
        }
        return in_array($module, self::allowedModules($role), true);
    }

    /** Este papel tem acesso irrestrito a módulos (super_admin/developer)? */
    public static function hasFullAccess(?string $role): bool
    {
        return $role !== null && in_array($role, self::FULL_ACCESS_ROLES, true);
    }

    /** O papel é um valor válido do enum users.role? */
    public static function isValidRole($role): bool
    {
        return is_string($role) && in_array($role, self::ROLES, true);
    }

    /**
     * Retorna a lista de papéis que podem acessar um módulo. Útil para passar
     * ao requireRole() legado sem duplicar a matriz.
     */
    public static function rolesForModule(string $module): array
    {
        $roles = [];
        foreach (self::ROLES as $role) {
            if (self::canAccess($role, $module)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}
