<?php
/**
 * Webhook público para receber eventos da Evolution API
 * URL: https://seudominio.com/webhook-whatsapp.php
 * 
 * Este arquivo serve como endpoint alternativo caso o roteamento
 * não funcione para webhooks (ex: Evolution API não envia headers corretos).
 * 
 * O endpoint principal é: /whatsapp/webhook (via router)
 */

session_start();

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

// Autoload
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

require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Config.php';

// Processar webhook
$controller = new WhatsappController();
$controller->webhook();
