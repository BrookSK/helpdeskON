<?php

/**
 * Cofre de credenciais do cliente (Fase 6). Módulo 'credentials', RESTRITO a
 * super_admin/developer (full-access) — não liberado ao comercial, pois é
 * sensível.
 *
 * O segredo nunca aparece em claro na listagem (máscara). Só a ação explícita
 * de 'reveal' descriptografa, e cada revelação é registrada no log de auditoria.
 */
class CredentialController extends Controller
{
    private $creds;

    public function __construct()
    {
        $this->creds = new ClientCredential();
    }

    public function index()
    {
        $this->requireModule('credentials');
        $user = $this->currentUser();
        $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
        $this->view('commercial/credentials', [
            'user' => $user,
            'credentials' => $this->creds->getAll($companyId),
            'companies' => (new Company())->getAll(),
            'filterCompany' => $companyId,
        ]);
    }

    public function store()
    {
        $this->requireModule('credentials');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $label = trim($_POST['service_label'] ?? '');
        if ($label === '') $this->json(['error' => 'Informe o rótulo do serviço.'], 400);

        $id = $this->creds->create([
            'company_id'    => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            'service_label' => $label,
            'username'      => trim($_POST['username'] ?? '') ?: null,
            'secret'        => (string)($_POST['secret'] ?? ''),
            'url'           => trim($_POST['url'] ?? '') ?: null,
            'notes'         => trim($_POST['notes'] ?? '') ?: null,
            'created_by'    => $user['id'],
        ]);
        ActivityLogger::logAction($user['id'], 'credential', 'store', [$id]);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($id = null)
    {
        $this->requireModule('credentials');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->creds->findById($id)) $this->json(['error' => 'Credencial não encontrada.'], 404);

        $this->creds->update((int)$id, [
            'company_id'    => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            'service_label' => trim($_POST['service_label'] ?? ''),
            'username'      => trim($_POST['username'] ?? '') ?: null,
            'secret'        => (string)($_POST['secret'] ?? ''), // em branco mantém o atual
            'url'           => trim($_POST['url'] ?? '') ?: null,
            'notes'         => trim($_POST['notes'] ?? '') ?: null,
        ]);
        ActivityLogger::logAction($user['id'], 'credential', 'update', [$id]);
        $this->json(['success' => true]);
    }

    public function delete($id = null)
    {
        $this->requireModule('credentials');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $this->creds->delete((int)$id);
        ActivityLogger::logAction($user['id'], 'credential', 'delete', [$id]);
        $this->json(['success' => true]);
    }

    /**
     * Revela o segredo em claro. Restrito ao módulo (super_admin/developer) e
     * registrado no log de auditoria a cada acesso.
     */
    public function reveal($id = null)
    {
        $this->requireModule('credentials');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $secret = $this->creds->revealSecret((int)$id);
        // Auditoria: quem revelou qual credencial e quando.
        ActivityLogger::logAction($user['id'], 'credential', 'reveal', [$id]);
        if ($secret === null) $this->json(['error' => 'Credencial sem segredo ou não decifrável.'], 404);
        $this->json(['success' => true, 'secret' => $secret]);
    }
}
