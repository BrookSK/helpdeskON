<?php
/**
 * Router para o servidor embutido do PHP (php -S) usado nos testes E2E.
 *
 * Replica o comportamento do .htaccess da raiz:
 *  - assets/uploads e arquivos reais existentes são servidos diretamente;
 *  - qualquer outra rota é enviada ao front controller public/index.php,
 *    preenchendo $_GET['url'] a partir do caminho da requisição.
 *
 * Uso (a partir da RAIZ do projeto):
 *   C:\php\php.exe -S 127.0.0.1:8199 tests-e2e/server-router.php
 */

$root = __DIR__ . '/..';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$path = ltrim($uri, '/');

// 1) assets e uploads moram dentro de /public (igual às regras do .htaccess).
if (preg_match('#^(assets|uploads)/#', $path)) {
    $real = realpath($root . '/public/' . $path);
    $baseReal = realpath($root . '/public');
    if ($real && $baseReal && strpos($real, $baseReal) === 0 && is_file($real)) {
        // O docroot do php -S é a raiz do projeto, não /public: "return false"
        // procuraria o arquivo no lugar errado. Serve direto com o tipo certo.
        $types = ['js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json',
            'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
            'webm' => 'video/webm', 'mp4' => 'video/mp4'];
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($types[$ext] ?? ((function_exists('mime_content_type') ? mime_content_type($real) : '') ?: 'application/octet-stream')));
        header('Content-Length: ' . filesize($real));
        readfile($real);
        return true;
    }
    http_response_code(404);
    return true;
}

// 2) Arquivo real existente na raiz (ex.: favicon) — serve direto.
$realRoot = realpath($root . '/' . $path);
$rootReal = realpath($root);
if ($path !== '' && $realRoot && $rootReal && strpos($realRoot, $rootReal) === 0 && is_file($realRoot)) {
    // Não deixa executar PHP arbitrário fora do front controller; só estáticos.
    if (!preg_match('#\.php$#i', $realRoot)) {
        return false;
    }
}

// 3) Front controller: mapeia o caminho para ?url=... (como o .htaccess faz).
$_GET['url'] = $path;
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
require $root . '/public/index.php';
