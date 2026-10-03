<?php

/**
 * Onboarding por etapas (Fase 6 — esteira comercial). Módulo 'onboarding'
 * (comercial + full-access).
 *
 * Fluxo: entrada paga no financeiro (FinanceProject::canStartOnboarding) ->
 * cria o onboarding com as etapas padrão -> inicia -> conclui etapas (as
 * obrigatórias exigem requisito cumprido) -> conclui o onboarding quando todas
 * as obrigatórias estão 'done'. Bloqueios espelhados de OnboardingRules.
 */
class OnboardingController extends Controller
{
    private $onboardings;
    private $projects;

    public function __construct()
    {
        $this->onboardings = new Onboarding();
        $this->projects = new FinanceProject();
    }

    public function index()
    {
        $this->requireModule('onboarding');
        $user = $this->currentUser();
        $this->view('commercial/onboarding', [
            'user' => $user,
            'items' => $this->onboardings->getAll(),
        ]);
    }

    public function edit($id = null)
    {
        $this->requireModule('onboarding');
        if (!$id) $this->redirect('onboarding');
        $onb = $this->onboardings->findById($id);
        if (!$onb) $this->redirect('onboarding');
        $user = $this->currentUser();
        $this->view('commercial/onboarding_detail', [
            'user' => $user,
            'onboarding' => $onb,
            'steps' => $this->onboardings->getSteps($id),
            'contacts' => $this->onboardings->getContacts($id, $onb['company_id'] ?? null),
            'events' => $this->onboardings->getEvents($id),
            'pendingRequired' => $this->onboardings->pendingRequired($id),
        ]);
    }

    /**
     * Cria o onboarding a partir de um projeto financeiro. Exige entrada paga
     * (canStartOnboarding). Status inicial 'blocked' — libera no start().
     */
    public function fromProject()
    {
        $this->requireModule('onboarding');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $user = $this->currentUser();

        $projectId = (int)($_POST['project_id'] ?? 0);
        $project = $projectId ? $this->projects->findById($projectId) : null;
        if (!$project) $this->json(['error' => 'Projeto financeiro não encontrado.'], 404);

        if (!$this->projects->canStartOnboarding($projectId)) {
            $this->json(['error' => 'O onboarding só inicia após a entrada ser paga.'], 409);
        }

        $contract = null;
        if (!empty($project['contract_id'])) {
            $contract = (new Contract())->findById((int)$project['contract_id']);
        }
        $id = $this->onboardings->createFromProject($project, $contract, $user['id']);
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Inicia o onboarding (destrava se a entrada estiver paga). */
    public function start($id = null)
    {
        $this->requireModule('onboarding');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $onb = $this->onboardings->findById($id);
        if (!$onb) $this->json(['error' => 'Onboarding não encontrado.'], 404);

        $entryPaid = !empty($onb['project_id']) ? $this->projects->canStartOnboarding((int)$onb['project_id']) : false;
        if (!$this->onboardings->start($id, $entryPaid, $user['id'])) {
            $this->json(['error' => 'Não foi possível iniciar: entrada ainda não paga.'], 409);
        }
        $this->json(['success' => true]);
    }

    /**
     * Marca uma etapa como concluída. Etapas obrigatórias exigem requisito
     * cumprido (requirement_met=1 no POST).
     */
    public function completeStep($stepId = null)
    {
        $this->requireModule('onboarding');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$stepId) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $requirementMet = !empty($_POST['requirement_met']);
        $ok = $this->onboardings->completeStep((int)$stepId, $requirementMet, $user['id']);
        if (!$ok) {
            $this->json(['error' => 'Etapa obrigatória: cumpra o requisito antes de concluir.'], 409);
        }
        $this->json(['success' => true]);
    }

    /** Cadastra um ponto focal do cliente. */
    public function addContact($id = null)
    {
        $this->requireModule('onboarding');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        $onb = $this->onboardings->findById($id);
        if (!$onb) $this->json(['error' => 'Onboarding não encontrado.'], 404);

        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome do contato.'], 400);
        $contactId = $this->onboardings->addContact([
            'company_id' => $onb['company_id'] ?? null,
            'onboarding_id' => (int)$id,
            'name' => $name,
            'role' => trim($_POST['role'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'responsibility' => trim($_POST['responsibility'] ?? '') ?: null,
            'is_primary' => !empty($_POST['is_primary']) ? 1 : 0,
        ]);
        $this->onboardings->addEvent((int)$id, $user['id'], 'contact_added', 'Ponto focal: ' . $name);
        $this->json(['success' => true, 'id' => $contactId]);
    }

    /** Conclui o onboarding (todas as etapas obrigatórias precisam estar done). */
    public function finish($id = null)
    {
        $this->requireModule('onboarding');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $user = $this->currentUser();
        if (!$this->onboardings->finish($id, $user['id'])) {
            $pending = $this->onboardings->pendingRequired($id);
            $this->json(['error' => 'Etapas obrigatórias pendentes.', 'pending' => $pending], 409);
        }
        $this->json(['success' => true]);
    }
}
