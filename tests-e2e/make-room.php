<?php
/**
 * Cria uma sala de vídeo PÚBLICA para os testes E2E e imprime o token (só o
 * token no stdout). A sala é criada direto no banco local (mesmo que a app usa),
 * evitando depender de login para o fluxo público de /videocall/room/{token}.
 *
 * Uso:  C:\php\php.exe tests-e2e/make-room.php
 * Saída: <token>
 */

// Bootstrap mínimo do app (constantes + autoload + Database), sem subir o router.
// Força o host LOCAL para o config/database.php escolher o mesmo banco/credenciais
// que a app usa quando servida em 127.0.0.1 (usuário root local). Sem isto, o CLI
// sem HTTP host cairia na conexão de produção (usuário helpdesk_on).
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
date_default_timezone_set('America/Sao_Paulo');

spl_autoload_register(function ($class) {
    foreach (['core', 'controllers', 'models'] as $d) {
        $f = APP_PATH . "/{$d}/{$class}.php";
        if (is_file($f)) { require_once $f; return; }
    }
});

try {
    $model = new VideoRoom();
    $token = $model->create([
        'title' => 'E2E — Sala de teste',
        'created_by' => null,
        'max_participants' => 8,
        'allow_recording' => 1,
        'allow_presentation' => 1,
        'status' => 'active',
        'visibility' => 'public',
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
    ]);
    echo $token;
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRO ao criar sala: ' . $e->getMessage());
    exit(1);
}
