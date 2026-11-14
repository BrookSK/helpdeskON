<?php
/**
 * Recupera uma gravação publicada sem duração e sem transcrição (linha de comando).
 *
 * 1. Arruma o vídeo com o FFmpeg (remux): o player passa a mostrar a duração
 *    real e a avançar. O arquivo original vai para storage/recovery-backup/.
 * 2. Extrai o áudio em trechos de 1 minuto e coloca na fila de transcrição.
 *    A tarefa agendada (cron_transcribe.php) transcreve e gera a minuta.
 *
 * Pode rodar de novo sem duplicar: pula o que já foi feito.
 *
 * Uso (na pasta do sistema):
 *   php recover_recording.php <host> <gravação> <caminho do ffmpeg>
 *   <gravação> = token, nome do arquivo (rec_141_xxx.webm) ou link da gravação
 * Ex.:
 *   php recover_recording.php helpdesk.onsolutionsbrasil.com.br rec_141_abc.webm ~/ffmpeg/ffmpeg
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Somente linha de comando.\n");
}

function rr_say(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . "\n";
}

function rr_fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, '[' . date('H:i:s') . '] ERRO: ' . $msg . "\n");
    exit($code);
}

$usage = "Uso: php recover_recording.php <host> <gravação> <caminho do ffmpeg>\n";
$host = strtolower(trim((string)($argv[1] ?? '')));
$recArg = (string)($argv[2] ?? '');
$ffmpeg = (string)($argv[3] ?? '');
if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host) || $recArg === '' || $ffmpeg === '') {
    fwrite(STDERR, $usage);
    exit(2);
}

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

@set_time_limit(0);

// ---------------------------------------------------------------- conferências
$token = RecordingRecovery::tokenFromArg($recArg);
if ($token === null) rr_fail("Não reconheci a gravação \"{$recArg}\". Use o nome do arquivo rec_..._xxx.webm.", 2);

if (!RecordingMedia::shellEnabled()) rr_fail('O PHP deste terminal não pode executar programas (shell_exec/exec desativados).', 2);
if (!is_file($ffmpeg)) rr_fail("FFmpeg não encontrado em {$ffmpeg}.", 2);
$version = (string)RecordingMedia::runShell(escapeshellarg($ffmpeg) . ' -version');
if (stripos($version, 'ffmpeg version') === false) rr_fail("{$ffmpeg} não respondeu como FFmpeg: " . trim(substr($version, 0, 300)), 2);
rr_say('FFmpeg: ' . trim(strtok($version, "\n")));

// Mesma trava do cron: enquanto este script trabalha, o agendamento sai na hora.
$lock = CronLock::tryAcquire(CronLock::RECORDING_QUEUE);
if ($lock === null) rr_fail('O agendamento está rodando agora. Espere 1 minuto e rode de novo.');

$recovery = new RecordingRecovery();
$rec = $recovery->findRecording($token);
if (!$rec) rr_fail("Gravação {$token} não encontrada no banco {$host}.");

$recDir = realpath(PUBLIC_PATH . '/uploads/recordings');
$video = realpath(PUBLIC_PATH . '/uploads/' . ltrim((string)$rec['file_path'], '/'));
if (!$recDir || !$video || strpos($video, $recDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($video)) {
    rr_fail('Arquivo do vídeo não encontrado: ' . $rec['file_path']);
}
rr_say('Gravação: ' . basename($video) . ' (' . round(filesize($video) / 1048576) . ' MB)');

// ---------------------------------------------------------------- 1. vídeo
$backupDir = BASE_PATH . '/storage/recovery-backup';
$backup = $backupDir . '/' . basename($video);
$duration = null;

if (is_file($backup)) {
    rr_say('1/2 Vídeo já arrumado antes (cópia original em storage/recovery-backup). Pulando.');
} else {
    $size = (int)filesize($video);
    $free = @disk_free_space(dirname($video));
    if ($free !== false && $free < $size * 1.05) {
        rr_fail('Espaço em disco insuficiente para arrumar o vídeo: precisa de ' . round($size / 1048576) . ' MB livres.');
    }
    rr_say('1/2 Arrumando o vídeo (alguns minutos, não feche o terminal)...');
    $tmp = $video . '.recover.webm';
    @unlink($tmp);
    $duration = RecordingMedia::remux($video, $tmp, true, $ffmpeg);
    if ($duration === null) {
        rr_say('    Não deu para arrumar o vídeo (o FFmpeg recusou o arquivo). Ele fica como está; sigo para o áudio.');
    } else {
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            @unlink($tmp);
            rr_fail('Não foi possível criar storage/recovery-backup.');
        }
        if (!@rename($video, $backup)) {
            @unlink($tmp);
            rr_fail('Não foi possível guardar a cópia original do vídeo.');
        }
        if (!@rename($tmp, $video)) {
            @rename($backup, $video);
            @unlink($tmp);
            rr_fail('Não foi possível trocar o vídeo pelo arrumado. O original foi mantido.');
        }
        clearstatcache();
        $recovery->updateMedia((int)$rec['id'], $duration, (int)filesize($video));
        rr_say('    Vídeo arrumado: ' . RecordingRecovery::formatDuration($duration) . '.');
    }
}

// ---------------------------------------------------------------- 2. áudio
$queued = $recovery->segmentCount((int)$rec['id']);
if ($queued > 0) {
    rr_say("2/2 Áudio já está na fila ({$queued} trechos). Pulando.");
} else {
    rr_say('2/2 Separando o áudio em trechos de 1 minuto...');
    $finalDir = BASE_PATH . '/storage/recording-audio/' . $token;
    $tmpDir = $finalDir . '.tmp';
    $rmdir = function (string $dir) {
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    };

    $list = [];
    $usedFmt = null;
    $lastOut = '';
    foreach (RecordingRecovery::AUDIO_FORMATS as $fmt) {
        $rmdir($tmpDir);
        if (!@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) rr_fail('Não foi possível criar ' . $tmpDir);
        $lastOut = (string)RecordingMedia::runShell(RecordingRecovery::extractAudioCommand($ffmpeg, $video, $tmpDir, $fmt));
        $csv = is_file($tmpDir . '/list.csv') ? (string)file_get_contents($tmpDir . '/list.csv') : '';
        $list = RecordingRecovery::parseSegmentList($csv);
        if ($list) { $usedFmt = $fmt; break; }
    }
    if (!$list) {
        $rmdir($tmpDir);
        rr_fail('O FFmpeg não conseguiu separar o áudio: ' . trim(substr($lastOut, 0, 500)));
    }

    $rmdir($finalDir); // sobra de tentativa anterior (a fila está vazia)
    @unlink($tmpDir . '/list.csv');
    if (!@rename($tmpDir, $finalDir)) rr_fail('Não foi possível mover os trechos de áudio para ' . $finalDir);

    $queued = $recovery->enqueueSegments((int)$rec['id'], 'recording-audio/' . $token, $list, $usedFmt['mime']);
    $audioEnd = end($list)['end'];
    rr_say("    {$queued} trechos na fila (áudio até " . RecordingRecovery::formatDuration($audioEnd) . ').');
}

$lock->release();
$minutes = (int)ceil($queued / TranscriptionQueueRules::BATCH_SIZE);
rr_say("Pronto. Com a tarefa agendada ativa, a transcrição termina em cerca de {$minutes} minutos.");
exit(0);
