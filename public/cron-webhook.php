<?php
/**
 * CRON: Processar fila de webhooks WhatsApp
 * Envia um por vez, aguarda resposta, depois vai pro próximo.
 * Configure no crontab: * * * * * php /caminho/para/public/cron-webhook.php
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

$db = Database::getInstance();
$webhookUrl = Config::get('webhook_url');

if (empty($webhookUrl)) {
    echo "URL do webhook não configurada.\n";
    exit;
}

// Buscar mensagens pendentes (ordenadas por criação, mais antigas primeiro)
$pending = $db->fetchAll(
    "SELECT * FROM webhook_queue WHERE status = 'pending' AND attempts < 3 ORDER BY created_at ASC LIMIT 10"
);

if (empty($pending)) {
    echo "Nenhum webhook pendente na fila.\n";
    exit;
}

echo "Processando " . count($pending) . " webhook(s)...\n";

foreach ($pending as $item) {
    echo "Enviando para {$item['phone']} ({$item['name']})... ";

    $payload = json_encode([
        'phone' => $item['phone'],
        'name' => $item['name'],
        'message' => $item['message'],
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && empty($error)) {
        // Sucesso — marcar como enviado
        $db->update('webhook_queue', [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
            'attempts' => $item['attempts'] + 1,
        ], 'id = ?', [$item['id']]);
        echo "OK (HTTP {$httpCode})\n";
    } else {
        // Falha — incrementar tentativas
        $errorMsg = $error ?: "HTTP {$httpCode}";
        $newAttempts = $item['attempts'] + 1;
        $newStatus = $newAttempts >= 3 ? 'failed' : 'pending';

        $db->update('webhook_queue', [
            'status' => $newStatus,
            'attempts' => $newAttempts,
            'error_message' => substr($errorMsg, 0, 255),
        ], 'id = ?', [$item['id']]);
        echo "FALHA ({$errorMsg})" . ($newStatus === 'failed' ? ' [MAX TENTATIVAS]' : '') . "\n";
    }

    // Aguardar 2 segundos entre cada envio para não sobrecarregar o webhook
    sleep(2);
}

echo "Processamento concluído.\n";
