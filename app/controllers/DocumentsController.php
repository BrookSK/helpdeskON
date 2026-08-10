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
            // Empresas que a equipe pode ver (super_admin vê todas; atendente só as autorizadas)
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
            $companyModel = new Company();
            $allCompanies = $companyModel->getAll();
            if ($allowedCompanies !== null) {
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                $companies = array_values(array_filter($allCompanies, fn($c) => in_array($c['id'], $realIds)));
            } else {
                $companies = $allCompanies;
            }

            // Empresa selecionada? Mostrar documentos dela. Caso contrário, listar empresas.
            $selectedCompany = isset($_GET['company']) ? $_GET['company'] : null;

            if ($selectedCompany !== null && $selectedCompany !== '') {
                // Validar acesso do atendente à empresa selecionada
                if ($allowedCompanies !== null && $selectedCompany !== '0') {
                    $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                    if (!in_array((int)$selectedCompany, $realIds)) {
                        flash('error', 'Sem acesso a esta empresa.');
                        $this->redirect('documents');
                    }
                }

                if ($selectedCompany === '0') {
                    // Documentos gerais (sem empresa vinculada)
                    $documents = $this->docModel->getForTeamFiltered([0]);
                    $currentCompany = ['id' => 0, 'name' => 'Geral (sem empresa)'];
                } else {
                    $documents = $this->docModel->getByCompanyOnly((int)$selectedCompany);
                    $currentCompany = $companyModel->findById((int)$selectedCompany);
                }

                $this->view('documents/index', [
                    'user' => $user,
                    'documents' => $documents,
                    'fullUser' => $fullUser,
                    'viewMode' => 'documents',
                    'currentCompany' => $currentCompany,
                ]);
                return;
            }

            // Listagem de empresas com contagem de documentos
            foreach ($companies as &$c) {
                $c['documents_count'] = $companyModel->countDocuments($c['id']);
            }
            unset($c);

            // Contagem de documentos gerais (sem empresa)
            $generalDocs = $this->docModel->getForTeamFiltered([0]);

            $this->view('documents/index', [
                'user' => $user,
                'fullUser' => $fullUser,
                'viewMode' => 'companies',
                'companies' => $companies,
                'generalCount' => count($generalDocs),
            ]);
            return;
        }

        // Clientes veem seus documentos diretamente
        $companyId = $fullUser['company_id'] ?? null;
        $documents = $this->docModel->getForClient($companyId, $user['id']);

        $this->view('documents/index', [
            'user' => $user,
            'documents' => $documents,
            'fullUser' => $fullUser,
            'viewMode' => 'documents',
            'currentCompany' => null,
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
