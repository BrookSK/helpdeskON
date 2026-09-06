<?php

class LoginController extends Controller
{
    public function index()
    {
        if ($this->isLoggedIn()) {
            $this->redirect('dashboard');
        }
        $this->view('auth/login');
    }

    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            flash('error', 'Preencha todos os campos.');
            $this->redirect('login');
        }

        $userModel = new User();
        $user = $userModel->authenticate($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['user_company_id'] = $user['company_id'] ?? null;
            $_SESSION['user_is_company_owner'] = $user['is_company_owner'] ?? 0;
            unset($_SESSION['active_company_id']);
            unset($_SESSION['impersonator']);
            $this->redirect('dashboard');
        } else {
            flash('error', 'Email ou senha inválidos.');
            $this->redirect('login');
        }
    }

    public function logout()
    {
        session_destroy();
        header('Location: ' . baseUrl('login'));
        exit;
    }

    /**
     * Login como outro usuário (impersonação). Apenas super_admin.
     * Guarda a sessão original para permitir retornar.
     */
    public function loginAs($userId = null)
    {
        $this->requireLogin();

        // Só super_admin pode impersonar (e não pode impersonar durante uma impersonação)
        if (($_SESSION['user_role'] ?? '') !== 'super_admin' || !empty($_SESSION['impersonator'])) {
            $this->redirect('dashboard');
        }

        if (!$userId) {
            $this->redirect('companies');
        }

        $target = (new User())->findById($userId);
        if (!$target || !$target['is_active']) {
            flash('error', 'Usuário não encontrado ou inativo.');
            $this->redirect('companies');
        }

        // Assume a identidade do usuário alvo (empresa principal por padrão).
        // impersonateUser() guarda a sessão original do admin automaticamente.
        $this->impersonateUser($target, $target['company_id'] ?? null);

        $this->redirect('dashboard');
    }

    /**
     * "Ver como" (Multi-Empresas): entra no sistema como o usuário selecionado.
     * - Se o usuário estiver vinculado a apenas uma empresa (ou nenhuma),
     *   entra diretamente naquele contexto.
     * - Se estiver vinculado a duas ou mais empresas, exibe uma tela para
     *   escolher qual empresa deseja visualizar.
     * Apenas super_admin, e não durante uma impersonação em andamento.
     */
    public function verComo($userId = null)
    {
        $this->requireLogin();

        if (($_SESSION['user_role'] ?? '') !== 'super_admin' || !empty($_SESSION['impersonator'])) {
            $this->redirect('dashboard');
        }

        if (!$userId) {
            $this->redirect('users');
        }

        $userModel = new User();
        $target = $userModel->findById($userId);
        if (!$target || !$target['is_active']) {
            flash('error', 'Usuário não encontrado ou inativo.');
            $this->redirect('users');
        }

        $companies = $userModel->getLinkedCompanies($userId);

        // 0 ou 1 empresa: acessa normalmente
        if (count($companies) <= 1) {
            $companyId = !empty($companies) ? $companies[0]['id'] : ($target['company_id'] ?? null);
            $this->impersonateUser($target, $companyId);
            $this->redirect('dashboard');
        }

        // 2+ empresas: escolher qual visualizar
        $admin = $this->currentUser();
        $this->view('admin/ver_como_empresa', [
            'user' => $admin,
            'targetUser' => $target,
            'companies' => $companies,
        ]);
    }

    /**
     * Conclui o fluxo "Ver como" após a escolha da empresa: entra como o
     * usuário selecionado, no contexto da empresa escolhida.
     */
    public function verComoEmpresa($userId = null, $companyId = null)
    {
        $this->requireLogin();

        if (($_SESSION['user_role'] ?? '') !== 'super_admin' || !empty($_SESSION['impersonator'])) {
            $this->redirect('dashboard');
        }

        if (!$userId || !$companyId) {
            $this->redirect('users');
        }

        $userModel = new User();
        $target = $userModel->findById($userId);
        if (!$target || !$target['is_active']) {
            flash('error', 'Usuário não encontrado ou inativo.');
            $this->redirect('users');
        }

        // Garante que o usuário está realmente vinculado à empresa escolhida
        if (!$userModel->isLinkedToCompany($userId, $companyId)) {
            flash('error', 'Este usuário não está vinculado à empresa selecionada.');
            $this->redirect('users');
        }

        $this->impersonateUser($target, (int)$companyId);
        $this->redirect('dashboard');
    }

    /**
     * Aplica a identidade de um usuário à sessão, salvando a sessão original
     * do administrador para permitir o retorno. O $companyId define a empresa
     * ativa (contexto) durante a sessão — usado no fluxo Multi-Empresas.
     */
    private function impersonateUser(array $target, $companyId = null)
    {
        // Guarda dados do admin original (apenas se ainda não estiver impersonando)
        if (empty($_SESSION['impersonator'])) {
            $_SESSION['impersonator'] = [
                'user_id' => $_SESSION['user_id'],
                'user_name' => $_SESSION['user_name'],
                'user_email' => $_SESSION['user_email'],
                'user_role' => $_SESSION['user_role'],
                'user_avatar' => $_SESSION['user_avatar'] ?? null,
                'user_company_id' => $_SESSION['user_company_id'] ?? null,
                'user_is_company_owner' => $_SESSION['user_is_company_owner'] ?? 0,
            ];
        }

        $_SESSION['user_id'] = $target['id'];
        $_SESSION['user_name'] = $target['name'];
        $_SESSION['user_email'] = $target['email'];
        $_SESSION['user_role'] = $target['role'];
        $_SESSION['user_avatar'] = $target['avatar'] ?? null;
        $_SESSION['user_company_id'] = $companyId ?? ($target['company_id'] ?? null);
        $_SESSION['user_is_company_owner'] = $target['is_company_owner'] ?? 0;

        // Empresa ativa (contexto Multi-Empresas). Honrada pelo scoping de dados.
        $_SESSION['active_company_id'] = $companyId ?? ($target['company_id'] ?? null);
    }

    /**
     * Retorna para a conta de administrador original após uma impersonação.
     */
    public function returnAdmin()
    {
        if (empty($_SESSION['impersonator'])) {
            $this->redirect('dashboard');
        }

        $admin = $_SESSION['impersonator'];
        $_SESSION['user_id'] = $admin['user_id'];
        $_SESSION['user_name'] = $admin['user_name'];
        $_SESSION['user_email'] = $admin['user_email'];
        $_SESSION['user_role'] = $admin['user_role'];
        $_SESSION['user_avatar'] = $admin['user_avatar'] ?? null;
        $_SESSION['user_company_id'] = $admin['user_company_id'] ?? null;
        $_SESSION['user_is_company_owner'] = $admin['user_is_company_owner'] ?? 0;

        unset($_SESSION['impersonator']);
        unset($_SESSION['active_company_id']);

        $this->redirect('companies');
    }
}
