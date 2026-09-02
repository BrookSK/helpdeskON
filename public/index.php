<?php
session_start();

// Garantir limites de POST adequados para conteúdo rico (descrições com imagens)
@ini_set('post_max_size', '50M');
@ini_set('upload_max_filesize', '10M');
@ini_set('max_input_vars', '5000');

// Fuso horário padrão da aplicação (Brasil). Sem isso, o PHP usa o fuso do
// php.ini (geralmente UTC), o que fazia os horários do acompanhamento ficarem
// deslocados em relação ao relógio local ("voltando no tempo").
date_default_timezone_set('America/Sao_Paulo');

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

// Logger centralizado + handlers globais de erro (log aparece no painel do servidor)
require_once APP_PATH . '/core/Logger.php';
Logger::register();

// Carregar helpers
require_once APP_PATH . '/core/helpers.php';

// Carregar configurações do banco
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';
require_once APP_PATH . '/core/Mailer.php';

// Inicializar roteador
$router = new Router();
$router->dispatch();
