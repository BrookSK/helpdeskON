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

        // Contar usuários, demandas e documentos por empresa
        foreach ($companies as &$c) {
            $c['users_count'] = $this->companyModel->countUsers($c['id']);
            $c['tickets_count'] = $this->companyModel->countTickets($c['id']);
            $c['documents_count'] = $this->companyModel->countDocuments($c['id']);
        }
        unset($c);

        $this->view('admin/companies', ['user' => $user, 'companies' => $companies]);
    }

    public function details($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->redirect('companies');

        $user = $this->currentUser();
        $company = $this->companyModel->findById($id);
        if (!$company) {
            flash('error', 'Empresa não encontrada.');
            $this->redirect('companies');
        }

        $users = $this->companyModel->getUsers($id);
        $tickets = (new Ticket())->getByCompany($id);
        $documents = (new SharedDocument())->getByCompany($id);

        $this->view('admin/company_details', [
            'user' => $user,
            'company' => $company,
            'companyUsers' => $users,
            'tickets' => $tickets,
            'documents' => $documents,
        ]);
    }

    public function create()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $groups = (new WhatsappContact())->getAllGroups();
        $this->view('admin/company_form', ['user' => $user, 'editCompany' => null, 'whatsappGroups' => $groups]);
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
        $ownerName = trim($_POST['owner_name'] ?? '');
        $ownerEmail = trim($_POST['owner_email'] ?? '');
        $groupJid = trim($_POST['whatsapp_group_jid'] ?? '');

        if (empty($name)) {
            flash('error', 'Nome da empresa é obrigatório.');
            $this->redirect('companies/create');
        }

        $companyId = $this->companyModel->create([
            'name' => $name,
            'document' => $document,
            'phone' => $phone,
            'email' => $email,
            'whatsapp_group_jid' => $groupJid ?: null,
        ]);

        // Criar automaticamente o usuário responsável (dono) da empresa com acesso ao painel.
        // Uma senha aleatória é gerada e o responsável recebe um email para definir a própria senha.
        $ownerEmail = $ownerEmail ?: $email;
        if (!empty($ownerEmail)) {
            $userModel = new User();
            if (!$userModel->findByEmail($ownerEmail)) {
                $db = Database::getInstance();
                $randomPassword = bin2hex(random_bytes(8));
                $ownerId = $db->insert('users', [
                    'name' => $ownerName ?: $name,
                    'email' => $ownerEmail,
                    'password' => password_hash($randomPassword, PASSWORD_DEFAULT),
                    'phone' => $phone,
                    'role' => 'client',
                    'company_id' => $companyId,
                    'is_company_owner' => 1,
                    'is_active' => 1,
                ]);

                // Enviar link para o responsável definir a senha (auto-login após definir)
                $userModel->sendFirstAccessInvite($ownerId);

                flash('success', 'Empresa cadastrada! Um email foi enviado para ' . escape($ownerEmail) . ' definir a senha de acesso.');
                $this->redirect('companies');
            }
        }

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

        $groups = (new WhatsappContact())->getAllGroups();
        $this->view('admin/company_form', ['user' => $user, 'editCompany' => $editCompany, 'whatsappGroups' => $groups]);
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
        $groupJid = trim($_POST['whatsapp_group_jid'] ?? '');

        if (empty($name)) {
            flash('error', 'Nome é obrigatório.');
            $this->redirect('companies/edit/' . $id);
        }

        $this->companyModel->update($id, [
            'name' => $name,
            'document' => $document,
            'phone' => $phone,
            'email' => $email,
            'whatsapp_group_jid' => $groupJid ?: null,
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
