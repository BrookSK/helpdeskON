<?php
/**
 * CRON: Processa a fila de WEBHOOKS DE ENTRADA do WhatsApp.
 *
 * As requisições chegam no endpoint público /whatsapp/incoming/{token} e já são
 * processadas inline (envio imediato). Este cron é a REDE DE SEGURANÇA: reenvia
 * as que ficaram pendentes (received/queued) — por exemplo, se o envio inline
 * falhou, a instância estava fora do ar, ou chegaram muitas de uma vez.
 *
 * Reusa a MESMA lógica de envio do endpoint: WhatsappController::processWebhookRequest().
 * Assim não há duplicação de regra entre inline e cron.
 *
 * Acesse via URL:
 *   https://helpdesk.onsolutionsbrasil.com.br/cron-webhook-incoming.php
 * Ou no crontab (a cada minuto):
 *   * * * * * curl -s https://helpdesk.onsolutionsbrasil.com.br/cron-webhook-incoming.php
 *
 * Semântica: cada request é tentada; processWebhookRequest() marca sent/failed/
 * skipped e incrementa attempts. pendingForProcessing() só traz attempts < 3.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

// Autoload igual ao de public/index.php (core, controllers, models).
spl_autoload_register(function ($class) {
    foreach ([APP_PATH . '/core/', APP_PATH . '/controllers/', APP_PATH . '/models/'] as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once APP_PATH . '/core/Logger.php';
Logger::register();
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

date_default_timezone_set('America/Sao_Paulo');
@set_time_limit(180);
header('Content-Type: text/plain; charset=utf-8');

$reqModel = new WhatsappWebhookRequest();

// Best-effort: se a tabela ainda não existir (migration 161 não aplicada),
// encerra sem quebrar.
try {
    $pending = $reqModel->pendingForProcessing(20, 3);
} catch (\Throwable $e) {
    echo "Fila de webhooks de entrada indisponível (migration 161 aplicada?).\n";
    exit;
}

if (empty($pending)) {
    echo "Nenhuma requisição de webhook pendente.\n";
    exit;
}

echo "Processando " . count($pending) . " requisição(ões)...\n";

$controller = new WhatsappController();

foreach ($pending as $item) {
    echo "Request #{$item['id']} (webhook {$item['webhook_id']}) ... ";
    try {
        $res = $controller->processWebhookRequest((int)$item['id']);
        echo "enviados {$res['sent']}/{$res['total']}\n";
    } catch (\Throwable $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
        Logger::error('[cron-webhook-incoming] falha', ['request_id' => $item['id'], 'error' => $e->getMessage()]);
    }
    // Pausa curta entre requisições para não saturar a Evolution API.
    usleep(300000); // 0,3s
}

echo "Processamento concluído.\n";
