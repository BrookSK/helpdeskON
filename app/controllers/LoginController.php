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
}
