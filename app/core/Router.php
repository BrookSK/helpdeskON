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

        if (!file_exists($controllerFile)) {
            $controllerName = 'LoginController';
            $controllerFile = APP_PATH . '/controllers/LoginController.php';
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
}
