<?php

class Controller
{
    protected function view($view, $data = [])
    {
        extract($data);
        $viewFile = APP_PATH . '/views/' . $view . '.php';
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die("View não encontrada: {$view}");
        }
    }

    protected function redirect($url)
    {
        header('Location: ' . baseUrl($url));
        exit;
    }

    protected function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('login');
        }
    }

    protected function requireRole($roles)
    {
        $this->requireLogin();
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        if (!in_array($_SESSION['user_role'], $roles)) {
            $this->redirect('dashboard');
        }
    }

    protected function currentUser()
    {
        if (!$this->isLoggedIn()) return null;
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
        ];
    }
}
