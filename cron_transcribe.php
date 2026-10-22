<?php
/**
 * Cron Job (linha de comando) — fila de transcrição das gravações.
 *
 * Faz o mesmo que GET /cron/transcribeRecordings, sem passar pela rede:
 * publica as sessões de gravação abandonadas e transcreve um lote de
 * segmentos de áudio, gerando a minuta em rascunho quando a gravação termina.
 *
 * Existe porque o domínio temporário do Plesk (*.plesk.page) não é alcançável
 * pelo próprio servidor, então a tarefa "Obter uma URL" falha com
 * "Connection refused".
 *
 * O host do ambiente é OBRIGATÓRIO: é ele que escolhe o banco em
 * config/database.php (plesk.page/beta = beta). Sem host, o fallback poderia
 * cair na produção.
 *
 * Uso (Plesk > Tarefas agendadas > Executar um script PHP, a cada minuto):
 *   php cron_transcribe.php relaxed-curie.187-110-165-203.plesk.page
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Somente linha de comando.\n");
}

$host = strtolower(trim((string)($argv[1] ?? '')));
if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
    fwrite(STDERR, "Uso: php cron_transcribe.php <host do ambiente>\n");
    exit(2);
}
// Simula a requisição do ambiente: config/database.php escolhe o banco pelo
// host, e baseUrl() monta os links das notificações com ele.
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';

date_default_timezone_set('America/Sao_Paulo');
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

spl_autoload_register(function ($class) {
    foreach ([APP_PATH . '/core/', APP_PATH . '/controllers/', APP_PATH . '/models/'] as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
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
require_once APP_PATH . '/core/Mailer.php';

@set_time_limit(900);

$cfg = require BASE_PATH . '/config/database.php';
$out = ['host' => $host, 'database' => $cfg['database'] ?? '?', 'abandoned' => 0, 'transcription' => null, 'errors' => []];

try {
    $out['abandoned'] = (new VideocallController())->finalizeAbandonedSessions();
} catch (\Throwable $e) {
    $out['errors'][] = 'abandonadas: ' . $e->getMessage();
    Logger::error('cron_transcribe: falha ao publicar sessões abandonadas', ['error' => $e->getMessage()]);
}

try {
    $transcriber = new RecordingTranscriber(new RecordingSegment(), new OpenAiClient(), function ($recToken) {
        (new VideocallController())->completeTranscription($recToken);
    });
    $out['transcription'] = $transcriber->run();
} catch (\Throwable $e) {
    $out['errors'][] = 'transcrição: ' . $e->getMessage();
    Logger::error('cron_transcribe: falha na fila de transcrição', ['error' => $e->getMessage()]);
}

echo '[' . date('Y-m-d H:i:s') . '] ' . json_encode($out, JSON_UNESCAPED_UNICODE) . "\n";
exit($out['errors'] ? 1 : 0);
