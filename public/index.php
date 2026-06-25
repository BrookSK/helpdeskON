<?php
session_start();

// Definir constantes base
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

// Autoload simples
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

// Carregar helpers
require_once APP_PATH . '/core/helpers.php';

// Carregar configurações do banco
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';
require_once APP_PATH . '/core/Mailer.php';

// Inicializar roteador
$router = new Router();
$router->dispatch();
