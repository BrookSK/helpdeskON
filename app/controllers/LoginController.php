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

            // Auditoria: registrar o login
            ActivityLogger::logLogin($user['id'], 'password');

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

        // Guarda dados do admin original
        $_SESSION['impersonator'] = [
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name'],
            'user_email' => $_SESSION['user_email'],
            'user_role' => $_SESSION['user_role'],
            'user_avatar' => $_SESSION['user_avatar'] ?? null,
            'user_company_id' => $_SESSION['user_company_id'] ?? null,
            'user_is_company_owner' => $_SESSION['user_is_company_owner'] ?? 0,
        ];

        // Assume a identidade do usuário alvo
        $_SESSION['user_id'] = $target['id'];
        $_SESSION['user_name'] = $target['name'];
        $_SESSION['user_email'] = $target['email'];
        $_SESSION['user_role'] = $target['role'];
        $_SESSION['user_avatar'] = $target['avatar'] ?? null;
        $_SESSION['user_company_id'] = $target['company_id'] ?? null;
        $_SESSION['user_is_company_owner'] = $target['is_company_owner'] ?? 0;

        // Auditoria: registrar o acesso via impersonação
        ActivityLogger::logLogin($target['id'], 'impersonation', $_SESSION['impersonator']['user_id'] ?? null);

        $this->redirect('dashboard');
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

        $this->redirect('companies');
    }
}
