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

    protected function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || !empty($_SERVER['HTTP_FETCH']);
    }

    protected function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            // Requisição AJAX/JSON recebe 401 JSON (não um redirect HTML que o
            // fetch não sabe interpretar). Navegação normal continua indo ao login.
            if ($this->isAjax()) {
                $this->json(['error' => 'Sessão expirada. Faça login novamente.'], 401);
            }
            $this->redirect('login');
        }
    }

    protected function requireRole($roles)
    {
        $this->requireLogin();
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        // Papéis com acesso total (super_admin, developer) passam em qualquer
        // requireRole — o developer é "quase super_admin". As restrições finas
        // de escopo (ex.: RDO só o próprio) ficam nas regras do módulo, não aqui.
        if (Permissions::hasFullAccess($_SESSION['user_role'] ?? null)) {
            return;
        }
        if (!in_array($_SESSION['user_role'], $roles)) {
            // Sem permissão: AJAX/JSON recebe 403 JSON; navegação vai ao dashboard.
            if ($this->isAjax()) {
                $this->json(['error' => 'Você não tem permissão para esta ação.'], 403);
            }
            $this->redirect('dashboard');
        }
    }

    /**
     * Exige que o papel do usuário tenha acesso ao MÓDULO informado, usando a
     * fonte única Permissions. Preferir este método a requireRole() com listas
     * de papéis soltas — assim o acesso não diverge do sidebar.
     */
    protected function requireModule($module)
    {
        $this->requireLogin();
        if (!Permissions::canAccess($_SESSION['user_role'] ?? null, $module)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Você não tem permissão para esta ação.'], 403);
            }
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

    /**
     * Empresa ativa (contexto) do usuário logado.
     * Em uma sessão "Ver como" Multi-Empresas, retorna a empresa escolhida.
     * Caso contrário, cai no company_id da sessão. Retorna null se não houver.
     */
    protected function activeCompanyId()
    {
        if (!empty($_SESSION['active_company_id'])) {
            return (int)$_SESSION['active_company_id'];
        }
        if (!empty($_SESSION['user_company_id'])) {
            return (int)$_SESSION['user_company_id'];
        }
        return null;
    }
}
