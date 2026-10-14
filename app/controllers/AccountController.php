<?php

class AccountController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $userData = $this->userModel->findById($user['id']);
        $this->view('account/index', ['user' => $user, 'userData' => $userData]);
    }

    public function update()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('account');
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $user['id']) {
            flash('error', 'Este email já está em uso.');
            $this->redirect('account');
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];

        $this->userModel->update($user['id'], $data);

        // Atualizar sessão
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        flash('success', 'Dados atualizados com sucesso!');
        $this->redirect('account');
    }

    public function changePassword()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            flash('error', 'Preencha todos os campos de senha.');
            $this->redirect('account');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'A nova senha e a confirmação não coincidem.');
            $this->redirect('account');
        }

        if (strlen($newPassword) < 6) {
            flash('error', 'A nova senha deve ter no mínimo 6 caracteres.');
            $this->redirect('account');
        }

        $userData = $this->userModel->findById($user['id']);
        if (!password_verify($currentPassword, $userData['password'])) {
            flash('error', 'Senha atual incorreta.');
            $this->redirect('account');
        }

        $this->userModel->update($user['id'], ['password' => $newPassword]);
        flash('success', 'Senha alterada com sucesso!');
        $this->redirect('account');
    }

    /**
     * Permite ao próprio usuário (qualquer papel) definir/alterar seu PIN de
     * acesso de 4 dígitos, usado para entrar pela opção "Entrar com PIN".
     */
    public function updateClientPin()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('account');
        }

        $user = $this->currentUser();

        $pin = trim($_POST['client_pin'] ?? '');
        if (!ClientPinRules::isValidFormat($pin)) {
            flash('error', 'O PIN deve ter exatamente 4 dígitos numéricos.');
            $this->redirect('account');
        }
        if ($this->userModel->clientPinExists($pin, $user['id'])) {
            flash('error', 'Este PIN já está em uso. Escolha outro.');
            $this->redirect('account');
        }

        $saved = $this->userModel->setClientPin($user['id'], $pin);
        if ($saved === null) {
            flash('error', 'Não foi possível salvar o PIN. Tente outro.');
            $this->redirect('account');
        }

        flash('success', 'PIN de acesso atualizado com sucesso!');
        $this->redirect('account');
    }
}
