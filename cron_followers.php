<?php
/**
 * Cron Job — Snapshot diário de seguidores (Meta / LinkedIn).
 *
 * Este script deve ser executado diariamente via cron do servidor.
 * Ele atualiza as métricas de todas as contas sociais e grava
 * o total de seguidores na tabela de histórico.
 *
 * Exemplo de agendamento (crontab):
 *   0 6 * * * php /caminho/para/helpdeskON/cron_followers.php >> /var/log/helpdesk_cron.log 2>&1
 *
 * Também pode ser chamado via HTTP (protegido por token):
 *   GET https://seudominio.com/social/snapshotFollowers?token=SEU_CRON_TOKEN
 *
 * Para configurar o token de segurança, acesse Configurações no painel
 * e defina a chave "cron_token" (tabela settings). Se não definido, qualquer
 * chamada GET ao endpoint será aceita (menos seguro, mas funcional).
 */

// Evitar execução via navegador sem intenção
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Execute via CLI ou use o endpoint HTTP com token.');
}

// Bootstrap da aplicação
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/core/',
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
    ];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

// ===== Execução =====
echo "[" . date('Y-m-d H:i:s') . "] Iniciando snapshot de seguidores...\n";

$meta = new MetaApi();
$linkedin = new LinkedInApi();
$accountsModel = new SocialAccount();
$errors = [];
$updated = 0;

$since = strtotime('-30 days');
$until = time();

foreach ($accountsModel->all(true) as $acc) {
    try {
        if ($acc['provider'] === 'meta_instagram') {
            $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : $meta;
            if (!$api->hasToken()) { $errors[] = $acc['display_name'] . ': Meta sem token'; continue; }

            $info = $api->getInstagramAccount($acc['external_id']);
            if (!empty($info['error'])) { $errors[] = 'IG ' . $acc['display_name'] . ': ' . ($info['error']['message'] ?? 'erro'); continue; }

            $accountsModel->saveMetrics($acc['id'], [
                'followers' => $info['followers_count'] ?? $acc['followers'],
                'follows' => $info['follows_count'] ?? $acc['follows'],
                'media_count' => $info['media_count'] ?? $acc['media_count'],
            ]);
            $updated++;

        } elseif ($acc['provider'] === 'facebook_page') {
            $api = $acc['access_token'] ? new MetaApi($acc['access_token']) : $meta;
            if (!$api->hasToken()) { $errors[] = $acc['display_name'] . ': Meta sem token'; continue; }

            $info = $api->getFacebookPage($acc['external_id']);
            if (!empty($info['error'])) { $errors[] = 'FB ' . $acc['display_name'] . ': ' . ($info['error']['message'] ?? 'erro'); continue; }

            $accountsModel->saveMetrics($acc['id'], [
                'followers' => $info['followers_count'] ?? ($info['fan_count'] ?? $acc['followers']),
            ]);
            $updated++;

        } elseif ($acc['provider'] === 'linkedin_org') {
            $api = $acc['access_token'] ? new LinkedInApi($acc['access_token']) : $linkedin;
            if (!$api->hasToken()) { $errors[] = $acc['display_name'] . ': LinkedIn sem token'; continue; }

            $followers = $api->getFollowerCount($acc['external_id']);
            if ($followers !== null) {
                $accountsModel->saveMetrics($acc['id'], ['followers' => $followers]);
                $updated++;
            } else {
                $errors[] = 'LinkedIn ' . $acc['display_name'] . ': não retornou seguidores';
            }
        }
    } catch (\Throwable $e) {
        $errors[] = $acc['display_name'] . ': ' . $e->getMessage();
    }
}

// Gravar snapshot do dia
$saved = $accountsModel->snapshotAllFollowers();

echo "[" . date('Y-m-d H:i:s') . "] Concluído. Contas atualizadas: {$updated}. Snapshots salvos: {$saved}.\n";
if (!empty($errors)) {
    echo "Avisos:\n";
    foreach ($errors as $e) echo "  - {$e}\n";
}

exit(empty($errors) ? 0 : 1);
