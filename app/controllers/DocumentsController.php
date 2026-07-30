<?php

class DocumentsController extends Controller
{
    private $docModel;

    public function __construct()
    {
        $this->docModel = new SharedDocument();
    }

    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();
        $fullUser = (new User())->findById($user['id']);

        if (in_array($user['role'], ['super_admin', 'attendant'])) {
            // Controle de acesso por empresa para atendentes
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
            if ($allowedCompanies !== null) {
                // Atendente com restrição — filtrar por empresas autorizadas
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                if (!empty($realIds)) {
                    $documents = $this->docModel->getForTeamFiltered($realIds);
                } else {
                    // Nenhuma empresa autorizada — só docs sem empresa
                    $documents = $this->docModel->getForTeamFiltered([0]);
                }
            } else {
                // Super admin — vê tudo
                $documents = $this->docModel->getForTeam();
            }
        } else {
            $companyId = $fullUser['company_id'] ?? null;
            $documents = $this->docModel->getForClient($companyId, $user['id']);
        }

        $this->view('documents/index', [
            'user' => $user,
            'documents' => $documents,
            'fullUser' => $fullUser,
        ]);
    }

    public function upload()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('documents');
        }

        $user = $this->currentUser();
        $fullUser = (new User())->findById($user['id']);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visibility = $_POST['visibility'] ?? 'all';

        if (empty($title)) {
            flash('error', 'Título é obrigatório.');
            $this->redirect('documents');
        }

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Selecione um arquivo.');
            $this->redirect('documents');
        }

        $companyId = $fullUser['company_id'] ?? null;

        // Se for atendente/admin e enviar para empresa específica
        if (in_array($user['role'], ['super_admin', 'attendant']) && !empty($_POST['company_id'])) {
            $companyId = (int)$_POST['company_id'];
        }

        $result = $this->docModel->upload(
            $_FILES['document'],
            $user['id'],
            $companyId,
            $title,
            $description,
            $visibility
        );

        if (isset($result['success'])) {
            flash('success', 'Documento enviado com sucesso!');
        } else {
            flash('error', $result['error'] ?? 'Erro no upload.');
        }

        $this->redirect('documents');
    }

    public function delete($id = null)
    {
        $this->requireLogin();
        if (!$id) $this->redirect('documents');

        $user = $this->currentUser();
        $doc = $this->docModel->findById($id);

        if (!$doc) {
            flash('error', 'Documento não encontrado.');
            $this->redirect('documents');
        }

        // Verificar permissão: quem fez upload ou admin
        if ($doc['user_id'] != $user['id'] && $user['role'] !== 'super_admin') {
            flash('error', 'Sem permissão para excluir este documento.');
            $this->redirect('documents');
        }

        $this->docModel->delete($id);
        flash('success', 'Documento excluído.');
        $this->redirect('documents');
    }
}
