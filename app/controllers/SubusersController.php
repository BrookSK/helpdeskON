<?php

class SubusersController extends Controller
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
        $fullUser = $this->userModel->findById($user['id']);

        // Se não é dono da empresa, mostrar mensagem
        if (!$fullUser['is_company_owner']) {
            $this->view('client/subusers_restricted', ['user' => $user]);
            return;
        }

        $companyId = $fullUser['company_id'];
        $db = Database::getInstance();
        $subusers = $db->fetchAll(
            "SELECT * FROM users WHERE company_id = ? AND id != ? ORDER BY name",
            [$companyId, $user['id']]
        );

        $this->view('client/subusers', [
            'user' => $user,
            'subusers' => $subusers,
            'fullUser' => $fullUser,
        ]);
    }

    public function create()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $fullUser = $this->userModel->findById($user['id']);

        if (!$fullUser['is_company_owner']) {
            $this->redirect('dashboard');
        }

        $this->view('client/subuser_form', [
            'user' => $user,
            'editUser' => null,
        ]);
    }

    public function store()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('subusers');
        }

        $user = $this->currentUser();
        $fullUser = $this->userModel->findById($user['id']);

        if (!$fullUser['is_company_owner']) {
            $this->redirect('dashboard');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $sector = trim($_POST['sector'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            flash('error', 'Nome, email e senha são obrigatórios.');
            $this->redirect('subusers/create');
        }

        if ($this->userModel->findByEmail($email)) {
            flash('error', 'Este email já está cadastrado.');
            $this->redirect('subusers/create');
        }

        $db = Database::getInstance();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'phone' => $phone,
            'role' => 'client',
            'company_id' => $fullUser['company_id'],
            'parent_user_id' => $user['id'],
            'is_company_owner' => 0,
            'is_active' => 1,
        ]);

        // Enviar email com credenciais
        $this->sendCredentialsEmail($name, $email, $password);

        flash('success', 'Usuário criado com sucesso! Email enviado com as credenciais.');
        $this->redirect('subusers');
    }

    public function edit($id = null)
    {
        $this->requireLogin();
        if (!$id) $this->redirect('subusers');

        $user = $this->currentUser();
        $fullUser = $this->userModel->findById($user['id']);

        if (!$fullUser['is_company_owner']) {
            $this->redirect('dashboard');
        }

        $editUser = $this->userModel->findById($id);
        if (!$editUser || $editUser['company_id'] != $fullUser['company_id']) {
            flash('error', 'Usuário não encontrado.');
            $this->redirect('subusers');
        }

        $this->view('client/subuser_form', [
            'user' => $user,
            'editUser' => $editUser,
        ]);
    }

    public function update($id = null)
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('subusers');
        }

        $user = $this->currentUser();
        $fullUser = $this->userModel->findById($user['id']);

        if (!$fullUser['is_company_owner']) {
            $this->redirect('dashboard');
        }

        $editUser = $this->userModel->findById($id);
        if (!$editUser || $editUser['company_id'] != $fullUser['company_id']) {
            $this->redirect('subusers');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('subusers/edit/' . $id);
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $id) {
            flash('error', 'Email já cadastrado.');
            $this->redirect('subusers/edit/' . $id);
        }

        $data = ['name' => $name, 'email' => $email, 'phone' => $phone];
        if (!empty($password)) {
            $data['password'] = $password;
        }

        $this->userModel->update($id, $data);
        flash('success', 'Usuário atualizado!');
        $this->redirect('subusers');
    }

    public function toggleStatus($id = null)
    {
        $this->requireLogin();
        if (!$id) $this->redirect('subusers');

        $user = $this->currentUser();
        $fullUser = $this->userModel->findById($user['id']);

        if (!$fullUser['is_company_owner']) {
            $this->redirect('dashboard');
        }

        $target = $this->userModel->findById($id);
        if ($target && $target['company_id'] == $fullUser['company_id']) {
            $this->userModel->toggleActive($id);
        }

        flash('success', 'Status alterado.');
        $this->redirect('subusers');
    }

    private function sendCredentialsEmail($name, $email, $password)
    {
        $loginUrl = baseUrl('login');
        $htmlBody = Mailer::template(
            'Seu acesso foi criado!',
            "<p>Olá, <strong>" . htmlspecialchars($name) . "</strong>!</p>
            <p>Seu acesso ao sistema de Helpdesk foi criado. Use as credenciais abaixo para fazer login:</p>
            <div style='background:#f5f7fa;border-radius:8px;padding:15px;margin:15px 0;'>
                <p style='margin:5px 0;'><strong>Email:</strong> {$email}</p>
                <p style='margin:5px 0;'><strong>Senha:</strong> {$password}</p>
            </div>
            <p style='text-align:center;margin:20px 0;'>
                <a href='{$loginUrl}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                    Acessar o Sistema
                </a>
            </p>
            <p style='font-size:0.82rem;color:#999;'>Recomendamos que altere sua senha no primeiro acesso.</p>"
        );

        Mailer::send($email, 'Seu acesso ao Helpdesk - ON Solutions', $htmlBody);
    }
}
