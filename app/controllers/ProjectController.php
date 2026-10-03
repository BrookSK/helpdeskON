<?php

/**
 * Projetos & Garantia (Fase 9) + ponte lead->cliente. Módulo 'projects'
 * (comercial + full-access).
 *
 * Projetos: entrega, garantia de 90 dias (tipo 'zero'), aviso de 15 dias antes
 * do fim e bloqueio de chamados pós-garantia sem contrato de suporte.
 * Conversão: registra a ponte lead->empresa/usuário (LeadConversion), fechando a
 * rastreabilidade da esteira.
 */
class ProjectController extends Controller
{
    private $projects;

    public function __construct()
    {
        $this->projects = new Project();
    }

    public function index()
    {
        $this->requireModule('projects');
        $user = $this->currentUser();
        $items = $this->projects->getAll();
        // Anota aviso de garantia próxima do fim para a listagem.
        foreach ($items as &$p) {
            $p['warn_warranty'] = ProjectRules::shouldWarnWarrantyEnding($p['warranty_ends_at'] ?? null);
            $p['warranty_days_left'] = ProjectRules::warrantyDaysLeft($p['warranty_ends_at'] ?? null);
        }
        unset($p);
        $this->view('commercial/projects', ['user' => $user, 'items' => $items]);
    }

    public function edit($id = null)
    {
        $this->requireModule('projects');
        if (!$id) $this->redirect('project');
        $p = $this->projects->findById($id);
        if (!$p) $this->redirect('project');
        $user = $this->currentUser();
        $this->view('commercial/project_detail', [
            'user' => $user,
            'project' => $p,
            'events' => $this->projects->getEvents($id),
            'warrantyDaysLeft' => ProjectRules::warrantyDaysLeft($p['warranty_ends_at'] ?? null),
            'warnWarranty' => ProjectRules::shouldWarnWarrantyEnding($p['warranty_ends_at'] ?? null),
            'canOpenTicket' => ProjectRules::canOpenTicket($p),
            'blockReason' => ProjectRules::blockReason($p),
        ]);
    }

    public function store()
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do projeto.'], 400);
        $id = $this->projects->create([
            'company_id' => !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null,
            'onboarding_id' => !empty($_POST['onboarding_id']) ? (int)$_POST['onboarding_id'] : null,
            'name' => $name,
            'contract_type' => $_POST['contract_type'] ?? 'outro',
            'warranty_days' => isset($_POST['warranty_days']) && $_POST['warranty_days'] !== '' ? (int)$_POST['warranty_days'] : ProjectRules::DEFAULT_WARRANTY_DAYS,
            'created_by' => $user['id'],
        ]);
        $this->projects->addEvent($id, $user['id'], 'created', 'Projeto criado');
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Marca o projeto como entregue (calcula a garantia). */
    public function deliver($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->projects->markDelivered((int)$id, $user['id'])) {
            $this->json(['error' => 'Projeto não encontrado.'], 404);
        }
        $this->json(['success' => true]);
    }

    /** Ativa/desativa o contrato de suporte (libera chamados pós-garantia). */
    public function toggleSupport($id = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $active = !empty($_POST['active']);
        if (!$this->projects->setSupportContract((int)$id, $active, $user['id'])) {
            $this->json(['error' => 'Projeto não encontrado.'], 404);
        }
        $this->json(['success' => true]);
    }

    // ================= Ponte lead -> cliente =================

    /**
     * Converte um lead (whatsapp_contacts) em cliente: cria/vincula empresa e
     * usuário, registra a ponte e envia o convite de primeiro acesso.
     */
    public function convertLead()
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $contactId = (int)($_POST['contact_id'] ?? 0);
        $contact = $contactId ? Database::getInstance()->fetch("SELECT * FROM whatsapp_contacts WHERE id = ?", [$contactId]) : null;
        if (!$contact) $this->json(['error' => 'Lead não encontrado.'], 404);

        $lead = [
            'contact_id' => $contactId,
            'name' => $contact['name'] ?? null,
            'company_name' => trim($_POST['company_name'] ?? '') ?: ($contact['name'] ?? null),
            'email' => trim($_POST['email'] ?? '') ?: ($contact['email'] ?? ''),
            'phone' => $contact['phone'] ?? null,
            'proposal_id' => !empty($_POST['proposal_id']) ? (int)$_POST['proposal_id'] : null,
            'contract_id' => !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null,
            'onboarding_id' => !empty($_POST['onboarding_id']) ? (int)$_POST['onboarding_id'] : null,
        ];
        $result = (new LeadConversion())->convert($lead, $user['id']);

        // Convite de primeiro acesso (fora do model para manter o model sem rede).
        if (!empty($result['created_user']) && !empty($result['user_id'])) {
            try { (new User())->sendFirstAccessInvite($result['user_id']); } catch (\Throwable $e) {}
        }
        $this->json(['success' => true] + $result);
    }

    // ================= PIN do cliente (gestão interna) =================

    /** Gera/renova o PIN de login simplificado de um usuário CLIENTE. */
    public function generateClientPin($userId = null)
    {
        $this->requireModule('projects');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$userId) $this->json(['error' => 'Requisição inválida'], 400);
        $u = (new User())->findById($userId);
        if (!$u) $this->json(['error' => 'Usuário não encontrado.'], 404);
        if (!ClientPinRules::roleCanUseClientPin($u['role'] ?? null)) {
            $this->json(['error' => 'PIN de cliente só para usuários do tipo cliente.'], 409);
        }
        $pin = (new User())->setClientPin((int)$userId, null);
        if ($pin === null) $this->json(['error' => 'Não foi possível gerar o PIN.'], 500);
        $this->json(['success' => true, 'pin' => $pin]);
    }
}
