<?php

/**
 * Configuração do banco de dados com detecção automática de ambiente.
 *
 * Regra final: branch "main" = PRODUÇÃO; qualquer outra branch = BETA.
 *
 * A branch é decidida em duas camadas, com prioridade:
 *   1) (prioridade) Domínio da requisição HTTP. Se o host contiver
 *      "plesk.page" ou "beta", assume ambiente beta imediatamente.
 *   2) (fallback) Leitura do arquivo <raiz>/.git/HEAD para descobrir
 *      a branch atual (funciona sem o Git instalado, só leitura de texto).
 *
 * Default = "main" (assume produção em caso de dúvida).
 */

return (function () {
    // Raiz do projeto: usa BASE_PATH quando disponível (bootstrap web/cron);
    // caso contrário, deriva do próprio caminho deste arquivo (config/ -> raiz).
    $rootPath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

    $branch = 'main'; // default: assume produção

    // Camada 1 (prioridade): detecção pelo domínio (HTTP host)
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if (str_contains($host, 'plesk.page') || str_contains($host, 'beta')) {
        $branch = 'beta';
    } else {
        // Camada 2 (fallback): leitura do arquivo .git/HEAD
        $headFile = $rootPath . '/.git/HEAD';
        if (file_exists($headFile)) {
            $head = trim(file_get_contents($headFile));
            if (str_starts_with($head, 'ref: refs/heads/')) {
                $branch = substr($head, strlen('ref: refs/heads/'));
            }
        }
    }

    if ($branch === 'main') {
        // ===== PRODUÇÃO =====
        return [
            'host'     => 'localhost',
            'port'     => '3306',
            'database' => 'helpdesk_on',
            'username' => 'helpdesk_on',
            'password' => 'd3boA94*ar?RnpWq',
        ];
    }

    // ===== BETA / homologação (qualquer branch diferente de main) =====
    return [
        'host'     => 'localhost',
        'port'     => '3306',
        'database' => 'helpdesk_on_beta',
        'username' => 'helpdesk_on_beta',
        'password' => 'd3boA94*ar?RnpWq',
    ];
})();
