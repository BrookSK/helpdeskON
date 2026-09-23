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

    /**
     * Resposta de erro padronizada para a API (JSON).
     * Formato: { "success": false, "error": { "code": ..., "message": ..., ... } }
     */
    protected function jsonError(string $code, string $message, int $httpStatus, array $extra = [])
    {
        $this->json([
            'success' => false,
            'error' => array_merge(['code' => $code, 'message' => $message], $extra),
        ], $httpStatus);
    }

    /**
     * Autenticação de requisições de API por chave (header X-Api-Key).
     *
     * Diferente de requireLogin(), NÃO usa sessão: é a autenticação de sistemas
     * externos. Em caso de falha, responde JSON e encerra a requisição:
     *   - 401 missing_api_key  : header ausente/vazio
     *   - 401 invalid_api_key  : chave não corresponde a nenhuma registrada
     *   - 403 revoked_api_key  : chave existe porém está inativa/revogada
     *
     * Em caso de sucesso, atualiza last_used_at e retorna a linha de api_keys
     * (contém company_id e integration_user_id).
     */
    protected function requireApiKey(): array
    {
        $plain = $this->apiKeyFromRequest();
        if ($plain === '') {
            $this->jsonError('missing_api_key', 'Cabeçalho X-Api-Key ausente.', 401);
        }

        $model = new ApiKey();
        $key = $model->resolveByPlainKey($plain);
        if (!$key) {
            $this->jsonError('invalid_api_key', 'Chave de API inválida.', 401);
        }

        if ((int)($key['is_active'] ?? 0) !== 1 || !empty($key['revoked_at'])) {
            $this->jsonError('revoked_api_key', 'Chave de API revogada ou inativa.', 403);
        }

        $model->touchLastUsed((int)$key['id']);
        return $key;
    }

    /** Lê a chave de API do header X-Api-Key (única forma aceita na v1). */
    protected function apiKeyFromRequest(): string
    {
        // O PHP expõe headers custom como HTTP_X_API_KEY. Cobrimos também
        // getallheaders() quando disponível (servidor embutido/Apache).
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'x-api-key') {
                    $key = $value;
                    break;
                }
            }
        }
        return trim((string)$key);
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
        if (!in_array($_SESSION['user_role'], $roles)) {
            // Sem permissão: AJAX/JSON recebe 403 JSON; navegação vai ao dashboard.
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
