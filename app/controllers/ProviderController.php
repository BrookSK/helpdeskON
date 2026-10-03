<?php

/**
 * Contratação de prestadores (Fase 8). Módulo 'providers', RESTRITO a
 * super_admin/developer (full-access) — é gestão interna/RH, sensível.
 *
 * Reaproveita a lógica da esteira: estados (prospect->proposal->contract->active
 * ->terminated), eventos e checklist. Regra de ouro no encerramento: só encerra
 * com TODOS os acessos revogados (ProviderRules::canTerminate).
 */
class ProviderController extends Controller
{
    private $providers;

    public function __construct()
    {
        $this->providers = new Provider();
    }

    public function index()
    {
        $this->requireModule('providers');
        $user = $this->currentUser();
        $this->view('commercial/providers', [
            'user' => $user,
            'items' => $this->providers->getAll(),
        ]);
    }

    public function edit($id = null)
    {
        $this->requireModule('providers');
        if (!$id) $this->redirect('provider');
        $p = $this->providers->findById($id);
        if (!$p) $this->redirect('provider');
        $user = $this->currentUser();
        $this->view('commercial/provider_detail', [
            'user' => $user,
            'provider' => $p,
            'accesses' => $this->providers->getAccesses($id),
            'documents' => $this->providers->getDocuments($id),
            'events' => $this->providers->getEvents($id),
            'pendingAccesses' => $this->providers->pendingAccesses($id),
            'defaultAccessLabels' => ProviderRules::defaultAccessLabels(),
        ]);
    }

    public function store()
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do prestador.'], 400);
        $id = $this->providers->create([
            'name' => $name,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'document' => trim($_POST['document'] ?? '') ?: null,
            'role_title' => trim($_POST['role_title'] ?? '') ?: null,
            'engagement_type' => $_POST['engagement_type'] ?? 'pj',
            'work_model' => $_POST['work_model'] ?? null,
            'pay_type' => $_POST['pay_type'] ?? null,
            'pay_amount' => isset($_POST['pay_amount']) && $_POST['pay_amount'] !== '' ? CrmRules::parseMoneyBR($_POST['pay_amount']) : null,
            'workload' => trim($_POST['workload'] ?? '') ?: null,
            'scope' => trim($_POST['scope'] ?? '') ?: null,
            'out_of_scope' => trim($_POST['out_of_scope'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'created_by' => $user['id'],
        ]);
        $this->providers->addEvent($id, $user['id'], 'created', 'Prestador cadastrado');
        $this->json(['success' => true, 'id' => $id]);
    }

    public function save($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->providers->findById($id)) $this->json(['error' => 'Prestador não encontrado.'], 404);
        $this->providers->update((int)$id, [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'document' => trim($_POST['document'] ?? '') ?: null,
            'role_title' => trim($_POST['role_title'] ?? '') ?: null,
            'engagement_type' => $_POST['engagement_type'] ?? 'pj',
            'work_model' => $_POST['work_model'] ?? null,
            'pay_type' => $_POST['pay_type'] ?? null,
            'pay_amount' => isset($_POST['pay_amount']) && $_POST['pay_amount'] !== '' ? CrmRules::parseMoneyBR($_POST['pay_amount']) : null,
            'workload' => trim($_POST['workload'] ?? '') ?: null,
            'scope' => trim($_POST['scope'] ?? '') ?: null,
            'out_of_scope' => trim($_POST['out_of_scope'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ]);
        $this->json(['success' => true]);
    }

    /** Avança/retrocede o status validando a transição. */
    public function status($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $to = trim($_POST['status'] ?? '');
        if (!$this->providers->changeStatus((int)$id, $to, $user['id'])) {
            $this->json(['error' => 'Transição de status inválida.'], 409);
        }
        $this->json(['success' => true]);
    }

    // ================= Acessos =================

    public function addAccess($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->providers->findById($id)) $this->json(['error' => 'Prestador não encontrado.'], 404);
        $label = trim($_POST['access_label'] ?? '');
        if ($label === '') $this->json(['error' => 'Informe o acesso.'], 400);
        $aid = $this->providers->addAccess((int)$id, $label, trim($_POST['details'] ?? '') ?: null, true);
        $this->providers->addEvent((int)$id, $user['id'], 'access_granted', 'Acesso concedido: ' . $label);
        $this->json(['success' => true, 'id' => $aid]);
    }

    public function revokeAccess($accessId = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$accessId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $this->providers->revokeAccess((int)$accessId, $user['id']);
        $this->json(['success' => true]);
    }

    public function revokeAll($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $n = $this->providers->revokeAllAccesses((int)$id, $user['id']);
        $this->json(['success' => true, 'revoked' => $n]);
    }

    // ================= Encerramento =================

    /**
     * Encerra o prestador. Exige motivo e que TODOS os acessos estejam revogados
     * (regra de ouro). Desativa o usuário vinculado.
     */
    public function terminate($id = null)
    {
        $this->requireModule('providers');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') $this->json(['error' => 'Informe o motivo do encerramento.'], 400);
        if (!$this->providers->terminate((int)$id, $reason, $user['id'])) {
            $pending = $this->providers->pendingAccesses((int)$id);
            $this->json([
                'error' => 'Para encerrar, revogue todos os acessos primeiro (mesmo dia).',
                'pending' => $pending,
            ], 409);
        }
        $this->json(['success' => true]);
    }
}
