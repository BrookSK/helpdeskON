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
        $companyName = trim($_POST['company_name'] ?? '');
        $companyId = $_POST['company_id'] ?? '';
        $isOwner = isset($_POST['is_company_owner']) ? 1 : 0;

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('users/create');
        }

        if ($this->userModel->findByEmail($email)) {
            flash('error', 'Este email já está cadastrado.');
            $this->redirect('users/create');
        }

        // Criar empresa se necessário
        $finalCompanyId = null;
        if ($role === 'client') {
            if (!empty($companyName) && empty($companyId)) {
                $companyModel = new Company();
                $finalCompanyId = $companyModel->create(['name' => $companyName, 'email' => $email, 'phone' => $phone]);
            } elseif (!empty($companyId)) {
                $finalCompanyId = (int)$companyId;
            }
        }

        $db = Database::getInstance();
        // Se nenhuma senha for informada, gera uma aleatória e envia convite de primeiro acesso
        $sendInvite = empty($password);
        $rawPassword = $sendInvite ? bin2hex(random_bytes(8)) : $password;
        $seeAll = isset($_POST['see_all_companies']) ? 1 : 0;
        $commission = ($role === 'comercial') ? floatval($_POST['commission_percent'] ?? 0) : 0;
        $commissionProspection = ($role === 'comercial') ? floatval($_POST['commission_prospection_percent'] ?? 0) : 0;
        $commissionClosing = ($role === 'comercial') ? floatval($_POST['commission_closing_percent'] ?? 0) : 0;
        $userId = $db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($rawPassword, PASSWORD_DEFAULT),
            'phone' => $phone,
            'role' => $role,
            'company_id' => $finalCompanyId,
            'is_company_owner' => ($role === 'client' && $isOwner) ? 1 : 0,
            'see_all_companies' => (in_array($role, ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']) && $seeAll) ? 1 : 0,
            'commission_percent' => $commission,
            'commission_prospection_percent' => $commissionProspection,
            'commission_closing_percent' => $commissionClosing,
            'sip_user' => trim($_POST['sip_user'] ?? '') ?: null,
            'sip_password' => trim($_POST['sip_password'] ?? '') ?: null,
            'is_active' => 1,
        ]);

        if ($sendInvite) {
            // Enviar link de definição de senha (auto-login após definir)
            $this->userModel->sendFirstAccessInvite($userId);
            flash('success', 'Usuário criado! Um email foi enviado para definição de senha.');
        } else {
            // Enviar email com credenciais definidas manualmente
            $loginUrl = baseUrl('login');
            $htmlBody = Mailer::template(
                'Seu acesso foi criado!',
                "<p>Olá, <strong>" . htmlspecialchars($name) . "</strong>!</p>
                <p>Seu acesso ao sistema de Helpdesk foi criado. Use as credenciais abaixo:</p>
                <div style='background:#f5f7fa;border-radius:8px;padding:15px;margin:15px 0;'>
                    <p style='margin:5px 0;'><strong>Email:</strong> {$email}</p>
                    <p style='margin:5px 0;'><strong>Senha:</strong> {$rawPassword}</p>
                </div>
                <p style='text-align:center;margin:20px 0;'>
                    <a href='{$loginUrl}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                        Acessar o Sistema
                    </a>
                </p>
                <p style='font-size:0.82rem;color:#999;'>Recomendamos que altere sua senha no primeiro acesso.</p>"
            );
            Mailer::send($email, 'Seu acesso ao Helpdesk - ON Solutions', $htmlBody);
            flash('success', 'Usuário criado com sucesso! Email enviado com as credenciais.');
        }

        // Salvar acesso a empresas (para equipe interna)
        if (in_array($role, ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']) && isset($_POST['company_access'])) {
            PlanningCard::setUserCompanyAccess($userId, $_POST['company_access']);
        }

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
        $companyId = $_POST['company_id'] ?? null;
        $companyName = trim($_POST['company_name'] ?? '');
        $isOwner = isset($_POST['is_company_owner']) ? 1 : 0;

        if (empty($name) || empty($email)) {
            flash('error', 'Nome e email são obrigatórios.');
            $this->redirect('users/edit/' . $id);
        }

        $existing = $this->userModel->findByEmail($email);
        if ($existing && $existing['id'] != $id) {
            flash('error', 'Este email já está cadastrado.');
            $this->redirect('users/edit/' . $id);
        }

        // Resolver empresa
        $finalCompanyId = null;
        if ($role === 'client') {
            if (!empty($companyName) && empty($companyId)) {
                $companyModel = new Company();
                $finalCompanyId = $companyModel->create(['name' => $companyName, 'email' => $email, 'phone' => $phone]);
            } elseif (!empty($companyId)) {
                $finalCompanyId = (int)$companyId;
            }
        }

        $seeAll = isset($_POST['see_all_companies']) ? 1 : 0;
        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'company_id' => $finalCompanyId,
            'is_company_owner' => ($role === 'client') ? $isOwner : 0,
            'see_all_companies' => (in_array($role, ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']) && $seeAll) ? 1 : 0,
            'commission_percent' => ($role === 'comercial') ? floatval($_POST['commission_percent'] ?? 0) : 0,
            'commission_prospection_percent' => ($role === 'comercial') ? floatval($_POST['commission_prospection_percent'] ?? 0) : 0,
            'commission_closing_percent' => ($role === 'comercial') ? floatval($_POST['commission_closing_percent'] ?? 0) : 0,
            'sip_user' => trim($_POST['sip_user'] ?? '') ?: null,
        ];

        if (!empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // Senha SIP é secreta: só atualiza se um novo valor for informado (em branco mantém a atual)
        if (isset($_POST['sip_password']) && trim($_POST['sip_password']) !== '') {
            $data['sip_password'] = trim($_POST['sip_password']);
        }

        $db = Database::getInstance();
        $db->update('users', $data, 'id = ?', [$id]);

        // Salvar ramal SIP (Nvoip) — por usuário
        $sipUser = trim($_POST['sip_user'] ?? ($_POST['nvoip_sip_user'] ?? ''));
        $sipPassword = trim($_POST['sip_password'] ?? ($_POST['nvoip_sip_password'] ?? ''));
        $sipData = ['sip_user' => $sipUser ?: null];
        if ($sipPassword !== '') {
            $sipData['sip_password'] = $sipPassword;
        }
        $db->update('users', $sipData, 'id = ?', [$id]);

        // Salvar acesso a empresas (para equipe interna)
        $role = $data['role'] ?? $_POST['role'] ?? '';
        if (in_array($role, ['attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial'])) {
            $companyAccess = $_POST['company_access'] ?? [];
            PlanningCard::setUserCompanyAccess($id, $companyAccess);
        }

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
