<?php

class CompaniesController extends Controller
{
    private $companyModel;

    public function __construct()
    {
        $this->companyModel = new Company();
    }

    public function index()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $companies = $this->companyModel->getAll();

        // Contar usuários por empresa
        $db = Database::getInstance();
        foreach ($companies as &$c) {
            $count = $db->fetch("SELECT COUNT(*) as total FROM users WHERE company_id = ?", [$c['id']]);
            $c['users_count'] = $count['total'] ?? 0;
        }

        $this->view('admin/companies', ['user' => $user, 'companies' => $companies]);
    }

    public function create()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $this->view('admin/company_form', ['user' => $user, 'editCompany' => null]);
    }

    public function store()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('companies');
        }

        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name)) {
            flash('error', 'Nome da empresa é obrigatório.');
            $this->redirect('companies/create');
        }

        $this->companyModel->create([
            'name' => $name,
            'document' => $document,
            'phone' => $phone,
            'email' => $email,
        ]);

        flash('success', 'Empresa cadastrada com sucesso!');
        $this->redirect('companies');
    }

    public function edit($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('companies');

        $user = $this->currentUser();
        $editCompany = $this->companyModel->findById($id);
        if (!$editCompany) {
            flash('error', 'Empresa não encontrada.');
            $this->redirect('companies');
        }

        $this->view('admin/company_form', ['user' => $user, 'editCompany' => $editCompany]);
    }

    public function update($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('companies');
        }

        $name = trim($_POST['name'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name)) {
            flash('error', 'Nome é obrigatório.');
            $this->redirect('companies/edit/' . $id);
        }

        $this->companyModel->update($id, [
            'name' => $name,
            'document' => $document,
            'phone' => $phone,
            'email' => $email,
        ]);

        flash('success', 'Empresa atualizada!');
        $this->redirect('companies');
    }

    public function delete($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('companies');

        $this->companyModel->delete($id);
        flash('success', 'Empresa removida.');
        $this->redirect('companies');
    }
}
