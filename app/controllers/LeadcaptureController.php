<?php

/**
 * Módulo Captação de Leads — Oportunidades (fonte: 99Freelas).
 *
 * Rotas (via Router: controller/method):
 *   leadcapture                      -> redirect para opportunities
 *   leadcapture/opportunities        -> aba principal
 *   leadcapture/configuracoes        -> configurações de busca
 *   leadcapture/saude                -> saúde da integração
 *   leadcapture/collect (POST)       -> dispara coleta (mesmo runner do scheduler)
 *   leadcapture/runStatus/{id}       -> status de uma run (polling)
 *   leadcapture/list                 -> lista JSON paginada (recarga sem F5)
 *   leadcapture/setStatus/{id} POST  -> vista/ignorada/nova
 *   leadcapture/markAllSeen POST     -> marca todas as novas como vistas
 *   leadcapture/convert/{id} POST    -> promove a Meus Leads
 *   leadcapture/saveTerm / deleteTerm / saveSettings (POST) -> config
 */
class LeadcaptureController extends Controller
{
    private $roles = ['super_admin', 'comercial'];
    private $model;

    public function __construct()
    {
        $this->model = new Opportunity();
    }

    public function index()
    {
        $this->requireRole($this->roles);
        $this->redirect('leadcapture/opportunities');
    }

    // ---- Telas ----

    public function opportunities()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('leadcapture/opportunities', [
            'user' => $user,
            'settings' => $this->model->getSettings('freelas99'),
            'health' => $this->model->getHealth('freelas99'),
            'categories' => $this->model->getActiveCategoryNames(),
            'terms' => $this->model->getTerms(true),
            'counts' => $this->model->counts(),
        ]);
    }

    public function configuracoes()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('leadcapture/configuracoes', [
            'user' => $user,
            'settings' => $this->model->getSettings('freelas99'),
            'terms' => $this->model->getTerms(false),
            'categories' => $this->model->getCategories(false),
        ]);
    }

    public function saude()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('leadcapture/saude', [
            'user' => $user,
            'health' => $this->model->getHealth('freelas99'),
            'runs' => $this->model->recentRuns(20),
        ]);
    }

    // ---- Coleta ----

    /**
     * Dispara a coleta. Como o CRM não tem fila/worker, executa de forma síncrona
     * (o rate limit + timeout global mantêm o tempo controlado) e responde o resumo.
     */
    public function collect()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $runner = new CollectionRunner();

        // Verificação prévia de lock (resposta 409 amigável)
        if ($runner->currentLock()) {
            $lock = $runner->currentLock();
            $this->json(['error' => 'Coleta em andamento' . (!empty($lock['user_name']) ? ' por ' . $lock['user_name'] : '') . '.', 'busy' => true], 409);
        }

        $result = $runner->run('manual', $user['id'], $user['name']);

        if (!empty($result['busy'])) {
            $this->json(['error' => $result['error'], 'busy' => true], 409);
        }
        if (!empty($result['error'])) {
            $this->json(['error' => $result['error']], 400);
        }

        $this->json(['success' => true] + $result);
    }

    /** Status de uma run (polling). */
    public function runStatus($id = null)
    {
        $this->requireRole($this->roles);
        if (!$id) $this->json(['error' => 'ID obrigatório'], 400);
        $run = $this->model->getRun($id);
        if (!$run) $this->json(['error' => 'Run não encontrada'], 404);
        $this->json(['run' => $run]);
    }

    // ---- Lista (recarga sem F5) ----

    public function list()
    {
        $this->requireRole($this->roles);
        $filters = [
            'status' => $_GET['status'] ?? '',
            'term' => $_GET['term'] ?? '',
            'category' => $_GET['category'] ?? '',
            'search' => trim($_GET['search'] ?? ''),
            'budget_min' => $_GET['budget_min'] ?? '',
            'sort' => $_GET['sort'] ?? 'first_seen',
        ];
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = intval($_GET['per_page'] ?? 50);

        $res = $this->model->getList($filters, $page, $perPage);
        $items = array_map([$this, 'formatOpportunity'], $res['items']);

        $this->json([
            'success' => true,
            'items' => $items,
            'total' => $res['total'],
            'page' => $page,
            'per_page' => $res['per_page'],
            'total_pages' => max(1, (int) ceil($res['total'] / $res['per_page'])),
            'counts' => $this->model->counts(),
        ]);
    }

    private function formatOpportunity($o)
    {
        return [
            'id' => (int) $o['id'],
            'external_id' => $o['external_id'],
            'canonical_url' => $o['canonical_url'],
            'title' => $o['title'],
            'description' => mb_substr((string) $o['description'], 0, 400),
            'category' => $o['category'],
            'experience_level' => $o['experience_level'],
            'skills' => json_decode($o['skills'] ?? '[]', true) ?: [],
            'budget_min' => $o['budget_min'] !== null ? (float) $o['budget_min'] : null,
            'budget_max' => $o['budget_max'] !== null ? (float) $o['budget_max'] : null,
            'currency' => $o['currency'],
            'published_at' => $o['published_at'],
            'first_seen_at' => $o['first_seen_at'],
            'proposal_count' => $o['proposal_count'] !== null ? (int) $o['proposal_count'] : null,
            'interested_count' => $o['interested_count'] !== null ? (int) $o['interested_count'] : null,
            'client_name' => $o['client_name'],
            'client_rating' => $o['client_rating'],
            'score' => $o['score'] !== null ? (int) $o['score'] : null,
            'status' => $o['status'],
            'matched_terms' => json_decode($o['matched_terms'] ?? '[]', true) ?: [],
            'lead_id' => $o['lead_id'] ? (int) $o['lead_id'] : null,
        ];
    }

    // ---- Ações sobre oportunidade ----

    public function setStatus($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $status = $_POST['status'] ?? '';
        if (!$this->model->setStatus($id, $status)) $this->json(['error' => 'Status inválido'], 400);
        $this->json(['success' => true]);
    }

    public function markAllSeen()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        Database::getInstance()->query("UPDATE opportunities SET status = 'vista' WHERE status = 'nova'");
        $this->json(['success' => true]);
    }

    /**
     * Promove a oportunidade a lead em Meus Leads (whatsapp_contacts + briefing).
     */
    public function convert($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $opp = $this->model->findById($id);
        if (!$opp) $this->json(['error' => 'Oportunidade não encontrada'], 404);
        if (!empty($opp['lead_id'])) $this->json(['error' => 'Esta oportunidade já foi convertida.'], 400);

        $user = $this->currentUser();
        $contactModel = new WhatsappContact();

        // Cria o lead (sem telefone — origem 99Freelas)
        $contactId = $contactModel->createManualLead($opp['title'], '', $user['id']);
        if (!$contactId) {
            $this->json(['error' => 'Não há instância de WhatsApp cadastrada para vincular o lead. Conecte o WhatsApp antes.'], 400);
        }

        // Briefing com referência ao projeto original
        $notes = "Origem: 99Freelas\nProjeto: {$opp['canonical_url']}";
        if (!empty($opp['proposal_count'])) $notes .= "\nPropostas: {$opp['proposal_count']}";
        if (!empty($opp['client_name'])) $notes .= "\nCliente: {$opp['client_name']}";
        $contactModel->saveBriefing($contactId, [
            'lead_source' => 'freelas99',
            'need' => mb_substr((string) $opp['description'], 0, 500) ?: null,
            'notes' => $notes,
        ], $user['id']);

        $this->model->markConverted($id, $contactId);
        $this->json(['success' => true, 'lead_id' => $contactId, 'lead_url' => baseUrl('crm/leads')]);
    }

    /**
     * API: diagnóstico completo do módulo (parser contra fixtures + coleta ao vivo).
     * POST leadcapture/diagnostics
     */
    public function diagnostics()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $diag = new Freelas99Diagnostics();
        $result = $diag->run();
        $this->json(['success' => true] + $result);
    }

    // ---- Configurações de busca ----

    public function saveTerm()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $data = [];
            if (isset($_POST['term'])) $data['term'] = trim($_POST['term']);
            if (isset($_POST['active'])) $data['active'] = $_POST['active'] ? 1 : 0;
            if (empty($data)) $this->json(['error' => 'Nada para atualizar'], 400);
            $this->model->updateTerm($id, $data);
            $this->json(['success' => true]);
        }
        $term = trim($_POST['term'] ?? '');
        if ($term === '') $this->json(['error' => 'Informe o termo.'], 400);
        $newId = $this->model->addTerm($term);
        if (!$newId) $this->json(['error' => 'Termo já existe ou inválido.'], 400);
        $this->json(['success' => true, 'id' => $newId]);
    }

    public function deleteTerm($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->model->deleteTerm($id);
        $this->json(['success' => true]);
    }

    /** Ativa/desativa uma categoria. POST leadcapture/toggleCategory/{id} body: active */
    public function toggleCategory($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->model->updateCategory($id, ['active' => !empty($_POST['active']) ? 1 : 0]);
        $this->json(['success' => true]);
    }

    /** Ativa/desativa todas as categorias de uma vez. POST leadcapture/setAllCategories body: active */
    public function setAllCategories()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $this->model->setAllCategories(!empty($_POST['active']));
        $this->json(['success' => true]);
    }

    public function saveSettings()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $data = [
            'enabled' => !empty($_POST['enabled']) ? 1 : 0,
            'max_pages' => max(1, min(10, intval($_POST['max_pages'] ?? 2))),
            'collect_general' => !empty($_POST['collect_general']) ? 1 : 0,
            'schedule_minutes' => max(15, intval($_POST['schedule_minutes'] ?? 60)),
            // Filtros de exibição (0 = sem limite)
            'max_proposals' => max(0, intval($_POST['max_proposals'] ?? 0)),
            'min_budget' => max(0, floatval($_POST['min_budget'] ?? 0)),
            'max_age_days' => max(0, intval($_POST['max_age_days'] ?? 0)),
        ];
        $this->model->saveSettings('freelas99', $data);
        $this->json(['success' => true]);
    }
}
