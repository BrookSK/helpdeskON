<?php

class UsersController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function index()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $users = $this->userModel->getAll();
        $this->view('admin/users', ['user' => $user, 'users' => $users]);
    }

    public function create()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $this->view('admin/user_form', ['user' => $user, 'editUser' => null]);
    }

    public function store()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';

        if (empty($name) || empty($email) || empty($password)) {
            flash('error', 'Nome, email e senha são obrigatórios.');
            $this->redirect('users/create');
        }

        if ($this->userModel->findByEmail($email)) {
            flash('error', 'Este email já está cadastrado.');
            $this->redirect('users/create');
        }

        $this->userModel->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
            'role' => $role,
        ]);

        flash('success', 'Usuário criado com sucesso!');
        $this->redirect('users');
    }

    public function edit($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('users');

        $user = $this->currentUser();
        $editUser = $this->userModel->findById($id);
        if (!$editUser) {
            flash('error', 'Usuário não encontrado.');
            $this->redirect('users');
        }

        $this->view('admin/user_form', ['user' => $user, 'editUser' => $editUser]);
    }

    public function update($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('users/edit/' . $id);
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $id) {
            flash('error', 'Este email já está cadastrado.');
            $this->redirect('users/edit/' . $id);
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
        ];
        if (!empty($password)) {
            $data['password'] = $password;
        }

        $this->userModel->update($id, $data);
        flash('success', 'Usuário atualizado com sucesso!');
        $this->redirect('users');
    }

    public function toggleStatus($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('users');

        $this->userModel->toggleActive($id);
        flash('success', 'Status do usuário alterado.');
        $this->redirect('users');
    }

    public function delete($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('users');

        $this->userModel->delete($id);
        flash('success', 'Usuário removido com sucesso!');
        $this->redirect('users');
    }
}
