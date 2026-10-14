<?php

class Router
{
    private $url;
    private $controller;
    private $method;
    private $params;

    public function __construct()
    {
        $this->url = $this->parseUrl();
    }

    private function parseUrl()
    {
        $url = $_GET['url'] ?? 'login';
        return explode('/', filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL));
    }

    public function dispatch()
    {
        $controllerName = ucfirst($this->url[0]) . 'Controller';
        $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';

        // Em FS case-sensitive (Linux/produção), um segmento de URL em
        // minúsculas (ex.: "servicecatalog") não casa com um arquivo em
        // camelCase (ex.: "ServiceCatalogController.php"). Resolvemos o
        // controller pelo nome REAL do arquivo, ignorando diferenças de caixa,
        // para não cair no fallback (que mandava o usuário ao dashboard).
        if (!file_exists($controllerFile)) {
            $resolved = $this->resolveControllerFile($controllerName);
            if ($resolved !== null) {
                $controllerName = $resolved;
                $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';
            } else {
                $controllerName = 'LoginController';
                $controllerFile = APP_PATH . '/controllers/LoginController.php';
            }
        }

        require_once $controllerFile;
        $this->controller = new $controllerName();

        $this->method = $this->url[1] ?? 'index';
        if (!method_exists($this->controller, $this->method)) {
            $this->method = 'index';
        }

        $this->params = array_slice($this->url, 2);

        // Auditoria: registrar a ação do usuário logado (antes de executar).
        if (!empty($_SESSION['user_id'])) {
            ActivityLogger::logAction(
                $_SESSION['user_id'],
                $this->url[0],
                $this->method,
                $this->params
            );
        }

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    /**
     * Procura o arquivo de controller cujo nome bate com $wanted ignorando
     * diferenças de caixa (case-insensitive). Retorna o nome real do arquivo
     * (sem extensão) para que require_once e a instanciação funcionem em
     * sistemas de arquivos case-sensitive. Null se não houver correspondência.
     */
    private function resolveControllerFile(string $wanted): ?string
    {
        $dir = APP_PATH . '/controllers';
        $wantedLower = strtolower($wanted);
        foreach ((array) glob($dir . '/*Controller.php') as $path) {
            $base = basename($path, '.php');
            if (strtolower($base) === $wantedLower) {
                return $base;
            }
        }
        return null;
    }
}
