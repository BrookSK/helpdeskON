<?php
// tests/bootstrap.php
// Prepara o ambiente de testes reaproveitando o autoload e as constantes
// que o código de produção espera (ver public/index.php), sem subir o roteador.

// Marca o ambiente como "testing" para o config/database.php apontar para o
// banco de teste (helpdesk_on_test). Definido tanto como env quanto constante.
if (getenv('APP_ENV') === false) {
    putenv('APP_ENV=testing');
}
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'testing');
}

// Constantes base usadas pelo código de produção
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

date_default_timezone_set('America/Sao_Paulo');

// Mesmo autoload de public/index.php: core, controllers, models
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

// Helpers globais de produção (baseUrl, escape, csrf_token/verify_csrf, etc.),
// para que testes de unidade possam exercitá-los diretamente.
require_once APP_PATH . '/core/helpers.php';

// Autoload do Composer (classes em tests/ via PSR-4 "Tests\" e libs de dev)
require BASE_PATH . '/vendor/autoload.php';
