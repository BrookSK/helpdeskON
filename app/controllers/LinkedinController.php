<?php

/**
 * CRM → Minhas Ações (LinkedIn).
 *
 * Fila de ações LinkedIn geradas pelas etapas das sequências. Toda ação é MANUAL:
 * o vendedor ABRE o perfil + COPIA a mensagem, cola e envia no LinkedIn à mão, e
 * confirma com "ENVIEI". A confirmação retoma a sequência.
 *
 * Este controller NÃO acessa o LinkedIn: não faz login, não manipula DOM, não usa
 * cookies/tokens, não automatiza nada. Apenas gerencia as tarefas no CRM.
 *
 * Rotas (convention: controller/metodo):
 *   linkedin ou linkedin/queue     -> tela "Minhas Ações"
 *   linkedin/run                   -> modo execução rápida
 *   linkedin/list                  -> JSON (fila filtrada + contadores)
 *   linkedin/open                  -> POST (registra abertura do perfil; NÃO conclui)
 *   linkedin/markSent              -> POST (ENVIEI: registra e retoma a sequência)
 *   linkedin/skip                  -> POST (PULAR: registra e retoma a sequência)
 *   linkedin/leadReplied           -> POST (LEAD RESPONDEU: pausa a sequência)
 *   linkedin/regenerate            -> POST (regenera a mensagem com IA)
 */
class LinkedinController extends Controller
{
    private $roles = ['super_admin', 'comercial', 'attendant'];

    private function model()
    {
        return new LinkedinTask();
    }

    /** Índice = fila. */
    public function index()
    {
        $this->queue();
    }

    /** Tela "Minhas Ações". */
    public function queue()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('linkedin/queue', [
            'user' => $user,
            'counters' => $this->model()->counters($this->scopeUserId($user)),
            'team' => (new User())->getByRoles(['super_admin', 'comercial', 'attendant']),
        ]);
    }

    /** Modo execução rápida (AÇÃO X DE Y). */
    public function run()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('linkedin/run', [
            'user' => $user,
            'counters' => $this->model()->counters($this->scopeUserId($user)),
        ]);
    }

    /** JSON: lista filtrada + contadores. GET linkedin/list?scope=&status=&assigned_to=&action_type= */
    public function list()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();

        $filters = [
            'scope' => in_array($_GET['scope'] ?? '', ['today', 'overdue', 'all']) ? $_GET['scope'] : '',
            'status' => $_GET['status'] ?? 'pending',
            'action_type' => in_array($_GET['action_type'] ?? '', ['connect', 'message', 'followup', 'final']) ? $_GET['action_type'] : '',
            'limit' => 100,
        ];

        // Escopo de responsável: comercial/attendant só vê as próprias; super_admin
        // pode filtrar por qualquer responsável (ou ver todas).
        $assigned = $this->resolveAssignedFilter($user);
        if ($assigned !== null) $filters['assigned_to'] = $assigned;

        $tasks = $this->model()->getList($filters);
        $this->json([
            'success' => true,
            'tasks' => array_map([$this, 'formatTask'], $tasks),
            'counters' => $this->model()->counters($this->scopeUserId($user)),
        ]);
    }

    /**
     * POST linkedin/open — registra que o perfil foi aberto (ABRIR + COPIAR).
     * NÃO conclui a tarefa; apenas muda o estado para 'opened' e registra a abertura.
     */
    public function open()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $task = $this->requireOwnedTask();

        $this->model()->markOpened($task['id']);
        (new LeadTimelineService())->add(
            $task['contact_id'],
            'linkedin_open',
            'Perfil LinkedIn aberto (mensagem copiada) — aguardando envio manual.',
            ['task_id' => $task['id']],
            $this->currentUser()['id']
        );
        $this->json(['success' => true, 'status' => LinkedinTask::S_OPENED]);
    }

    /**
     * POST linkedin/markSent — ENVIEI. Registra a ação e retoma a sequência.
     * Body: id, final_message (opcional, se o vendedor editou).
     */
    public function markSent()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $task = $this->requireOwnedTask();
        $user = $this->currentUser();

        $finalMessage = isset($_POST['final_message']) ? trim((string) $_POST['final_message']) : null;
        $this->model()->markSent($task['id'], $user['id'], $finalMessage);

        (new LeadTimelineService())->add(
            $task['contact_id'],
            'linkedin_sent',
            'Ação LinkedIn enviada (' . $task['action_type'] . ') pelo vendedor.',
            [
                'task_id' => $task['id'],
                'action_type' => $task['action_type'],
                'sequence_id' => $task['sequence_id'],
                'node_id' => $task['node_id'],
                'message' => $finalMessage ?: $task['generated_message'],
            ],
            $user['id']
        );

        $resumed = $this->resumeSequence($task);
        $this->json(['success' => true, 'status' => LinkedinTask::S_SENT, 'sequence_resumed' => $resumed]);
    }

    /** POST linkedin/skip — PULAR. Registra e retoma a sequência (avança a etapa). */
    public function skip()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $task = $this->requireOwnedTask();
        $user = $this->currentUser();

        $this->model()->markSkipped($task['id']);
        (new LeadTimelineService())->add(
            $task['contact_id'],
            'linkedin_skip',
            'Ação LinkedIn pulada pelo vendedor.',
            ['task_id' => $task['id'], 'node_id' => $task['node_id']],
            $user['id']
        );

        $resumed = $this->resumeSequence($task);
        $this->json(['success' => true, 'status' => LinkedinTask::S_SKIPPED, 'sequence_resumed' => $resumed]);
    }

    /**
     * POST linkedin/leadReplied — LEAD RESPONDEU (no LinkedIn, registrado à mão).
     * Registra a resposta e PAUSA/interrompe as sequências ativas do lead. Nunca
     * continua follow-ups para quem respondeu.
     */
    public function leadReplied()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $task = $this->requireOwnedTask();
        $user = $this->currentUser();

        $this->model()->markReplied($task['id']);
        (new LeadTimelineService())->add(
            $task['contact_id'],
            'linkedin_reply',
            'Lead respondeu no LinkedIn (registrado manualmente).',
            ['task_id' => $task['id']],
            $user['id']
        );
        (new LeadScoreService())->add($task['contact_id'], LeadScoreService::W_REPLY, 'resposta LinkedIn');
        // Interrompe follow-ups automáticos do lead.
        (new SequenceEngine())->stopForContact($task['contact_id'], 'replied');

        $this->json(['success' => true, 'status' => LinkedinTask::S_REPLIED]);
    }

    /** POST linkedin/regenerate — regenera a mensagem com IA (só dados reais). */
    public function regenerate()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $task = $this->requireOwnedTask();

        $node = $this->rebuildNodeFromTask($task);
        $gen = (new LinkedinMessageService())->generate($task['contact_id'], $node);
        $this->model()->update($task['id'], ['generated_message' => $gen['message'] ?? '']);

        $this->json([
            'success' => true,
            'message' => $gen['message'] ?? '',
            'source' => $gen['source'] ?? null,
            'warning' => $gen['warning'] ?? null,
        ]);
    }

    // ================= helpers =================

    /**
     * Reconstrói o "nó" a partir da tarefa e do grafo da sequência (quando houver),
     * para regenerar a mensagem com os mesmos parâmetros (objetivo/tom/CTA/limite).
     */
    private function rebuildNodeFromTask($task)
    {
        $data = [
            'action_type' => $task['action_type'],
            'objective' => $task['objective'],
            'template_id' => $task['template_id'],
        ];
        if (!empty($task['sequence_id']) && !empty($task['node_id'])) {
            $seq = Database::getInstance()->fetch("SELECT graph FROM email_sequences WHERE id = ?", [$task['sequence_id']]);
            $graph = json_decode($seq['graph'] ?? '{}', true);
            foreach (($graph['nodes'] ?? []) as $n) {
                if (($n['id'] ?? null) === $task['node_id']) {
                    $data = array_merge($data, $n['data'] ?? []);
                    break;
                }
            }
        }
        return ['data' => $data];
    }

    /** Retoma a sequência do lead após a ação (se a tarefa veio de uma sequência). */
    private function resumeSequence($task)
    {
        if (empty($task['participant_id']) || empty($task['node_id'])) return false;
        return (new SequenceEngine())->resumeAfterLinkedinTask((int) $task['participant_id'], $task['node_id']);
    }

    /** Carrega a tarefa do POST e valida que o usuário pode agir sobre ela. */
    private function requireOwnedTask()
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) $this->json(['error' => 'ID da tarefa obrigatório.'], 400);
        $task = $this->model()->findById($id);
        if (!$task) $this->json(['error' => 'Tarefa não encontrada.'], 404);

        $user = $this->currentUser();
        // super_admin age sobre qualquer tarefa; demais só sobre as próprias
        // (ou não atribuídas).
        if ($user['role'] !== 'super_admin'
            && !empty($task['assigned_to'])
            && (int) $task['assigned_to'] !== (int) $user['id']) {
            $this->json(['error' => 'Esta ação está atribuída a outro responsável.'], 403);
        }
        return $task;
    }

    /** super_admin pode filtrar por responsável via query; demais são travados nas próprias. */
    private function resolveAssignedFilter($user)
    {
        if ($user['role'] === 'super_admin') {
            $q = $_GET['assigned_to'] ?? '';
            if ($q === '' || $q === 'all') return null; // todas
            return (int) $q;
        }
        return (int) $user['id'];
    }

    /** Escopo de contadores: super_admin vê tudo, demais só as próprias. */
    private function scopeUserId($user)
    {
        return $user['role'] === 'super_admin' ? null : (int) $user['id'];
    }

    /** Formata a tarefa para o front (extrai cargo/empresa do briefing). */
    private function formatTask($t)
    {
        $notes = (string) ($t['lead_notes'] ?? '');
        $cargo = '';
        $empresa = '';
        if (preg_match('/Cargo:\s*([^|]+)/i', $notes, $m)) $cargo = trim($m[1]);
        if (preg_match('/Empresa:\s*([^|]+)/i', $notes, $m)) $empresa = trim($m[1]);

        return [
            'id' => (int) $t['id'],
            'contact_id' => (int) $t['contact_id'],
            'lead_name' => $t['lead_name'] ?? 'Lead',
            'company' => $empresa,
            'title' => $cargo,
            'sector' => $t['lead_need'] ?? '',
            'action_type' => $t['action_type'],
            'objective' => $t['objective'] ?? '',
            'linkedin_url' => $t['linkedin_url'] ?? '',
            'has_linkedin' => !empty($t['linkedin_url']),
            'message' => $t['generated_message'] ?? '',
            'status' => $t['status'],
            'sequence_name' => $t['sequence_name'] ?? '',
            'assigned_name' => $t['assigned_name'] ?? '',
        ];
    }
}
