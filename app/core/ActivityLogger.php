<?php

/**
 * Registra acessos (logins) e ações dos usuários na plataforma.
 *
 * Usado pelo super_admin para auditar, a partir do perfil de um usuário,
 * todos os logins e todas as ações executadas.
 *
 * Regra de ouro: NUNCA quebrar o fluxo da aplicação. Qualquer falha ao gravar
 * o log é silenciada (apenas registrada no Logger) para não afetar o usuário.
 */
class ActivityLogger
{
    /** Ações que não valem a pena registrar (polling/ruído). */
    private static $ignoredActions = [
        'getmessages', 'tasks', 'calendar', 'get', 'poll', 'notificationscount',
    ];

    /** Controllers cujas ações não devem ser registradas. */
    private static $ignoredControllers = [
        'track',
    ];

    /**
     * Registra um login bem-sucedido.
     *
     * @param int    $userId        usuário que entrou
     * @param string $type          'password' ou 'impersonation'
     * @param int|null $impersonatedBy id do super_admin, quando impersonação
     */
    public static function logLogin($userId, $type = 'password', $impersonatedBy = null)
    {
        try {
            Database::getInstance()->insert('user_login_history', [
                'user_id' => (int)$userId,
                'ip_address' => self::ip(),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                'login_type' => $type,
                'impersonated_by' => $impersonatedBy ? (int)$impersonatedBy : null,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Falha ao registrar login', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Registra uma ação (requisição) do usuário logado.
     *
     * @param int    $userId
     * @param string $controller  ex.: "tickets"
     * @param string $action      ex.: "show"
     * @param array  $params      parâmetros da rota
     */
    public static function logAction($userId, $controller, $action, array $params = [])
    {
        try {
            $controller = strtolower($controller);
            $action = strtolower($action);

            if (in_array($controller, self::$ignoredControllers, true)) {
                return;
            }
            if (in_array($action, self::$ignoredActions, true)) {
                return;
            }

            Database::getInstance()->insert('user_activity_log', [
                'user_id' => (int)$userId,
                'controller' => substr($controller, 0, 64),
                'action' => substr($action, 0, 64),
                'params' => $params ? substr(implode('/', array_map('strval', $params)), 0, 255) : null,
                'http_method' => substr($_SERVER['REQUEST_METHOD'] ?? 'GET', 0, 10),
                'path' => substr((string)($_GET['url'] ?? ''), 0, 255),
                'ip_address' => self::ip(),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Falha ao registrar ação', ['error' => $e->getMessage()]);
        }
    }

    /** Melhor esforço para obter o IP real do cliente. */
    private static function ip()
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $ip) {
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                return substr($ip, 0, 45);
            }
        }
        return null;
    }
}
