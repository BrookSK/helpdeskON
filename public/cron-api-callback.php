<?php
/**
 * CRON: Processa a fila de CALLBACKS da API de demandas/chamados.
 *
 * Entrega, de forma assíncrona e com retry, os avisos de MUDANÇA DE STATUS para
 * os sistemas externos que integram com a API v1 (ex.: Punta Cana). Cada linha
 * pendente de api_callback_queue vira um POST JSON para a callback_url da
 * empresa. Mesmo padrão do cron-webhook.php (FIFO, tentativas limitadas,
 * verificação do HTTP code), aqui aplicado à fila de callbacks.
 *
 * Acesse via URL:
 *   https://helpdesk.onsolutionsbrasil.com.br/cron-api-callback.php
 * Ou configure no crontab (a cada minuto):
 *   * * * * * curl -s https://helpdesk.onsolutionsbrasil.com.br/cron-api-callback.php
 *
 * Semântica de entrega:
 *  - Sucesso: HTTP 2xx e sem erro de cURL -> status 'sent'.
 *  - Falha: incrementa attempts; volta a 'pending' até 3 tentativas; na 3ª
 *    tentativa sem sucesso, marca 'failed'.
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

@set_time_limit(120);
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// Best-effort: se a tabela ainda não existir (migration 135 não aplicada),
// encerra sem quebrar (mesma postura defensiva do resto do projeto).
try {
    $pending = $db->fetchAll(
        "SELECT * FROM api_callback_queue
          WHERE status = 'pending' AND attempts < 3
          ORDER BY created_at ASC
          LIMIT 20"
    );
} catch (\Throwable $e) {
    echo "Fila de callbacks indisponível (migration 135 aplicada?).\n";
    exit;
}

if (empty($pending)) {
    echo "Nenhum callback pendente na fila.\n";
    exit;
}

echo "Processando " . count($pending) . " callback(s)...\n";

foreach ($pending as $item) {
    $url = (string)$item['callback_url'];
    echo "Callback ticket #{$item['ticket_id']} -> {$url} ... ";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $item['payload'],
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'User-Agent: helpdeskON-callback/1',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    $success = ($httpCode >= 200 && $httpCode < 300 && $error === '');

    if ($success) {
        $db->update('api_callback_queue', [
            'status'         => 'sent',
            'sent_at'        => date('Y-m-d H:i:s'),
            'attempts'       => (int)$item['attempts'] + 1,
            'last_http_code' => $httpCode,
            'error_message'  => null,
        ], 'id = ?', [$item['id']]);
        echo "OK (HTTP {$httpCode})\n";
    } else {
        $errorMsg = $error !== '' ? $error : ('HTTP ' . $httpCode);
        $newAttempts = (int)$item['attempts'] + 1;
        $newStatus = $newAttempts >= 3 ? 'failed' : 'pending';

        $db->update('api_callback_queue', [
            'status'         => $newStatus,
            'attempts'       => $newAttempts,
            'last_http_code' => $httpCode ?: null,
            'error_message'  => substr($errorMsg, 0, 255),
        ], 'id = ?', [$item['id']]);
        echo "FALHA ({$errorMsg})" . ($newStatus === 'failed' ? ' [MAX TENTATIVAS]' : '') . "\n";
    }

    // Pequena pausa entre envios para não saturar o destino.
    usleep(300000); // 0,3s
}

echo "Processamento concluído.\n";
