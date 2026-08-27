<?php

/**
 * CRM → Sequências de E-mail.
 * CRUD + editor visual + inscrição de leads + follow-up engine.
 *
 * Rotas (controller/metodo):
 *   sequences                    -> lista
 *   sequences/edit/{id}          -> editor visual (novo se id ausente)
 *   sequences/save               -> POST (cria/atualiza definição + grafo)
 *   sequences/delete/{id}        -> POST
 *   sequences/detail/{id}        -> JSON (participantes/stats)
 *   sequences/addLeads           -> POST (inscreve leads por contact_ids[])
 *   sequences/removeLead         -> POST (participant_id)
 *   sequences/leadsForSelect      -> JSON (leads com e-mail, para o seletor)
 */
class SequencesController extends Controller
{
    private $roles = ['super_admin', 'comercial'];
    private $model;

    public function __construct()
    {
        $this->model = new EmailSequence();
    }

    public function index()
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $this->view('sequences/index', [
            'user' => $user,
            'sequences' => $this->model->all(),
        ]);
    }

    public function edit($id = null)
    {
        $this->requireRole($this->roles);
        $user = $this->currentUser();
        $seq = $id ? $this->model->findById($id) : null;

        $db = Database::getInstance();
        $accounts = $db->fetchAll("SELECT id, email, display_name FROM email_accounts WHERE is_active = 1 ORDER BY email");
        // Colunas de todos os boards (para o nó "mover card"), com board para agrupar
        $columns = $db->fetchAll(
            "SELECT col.id, col.name, col.board_id, b.name AS board_name
             FROM crm_columns col JOIN crm_boards b ON col.board_id = b.id
             WHERE b.is_active = 1 ORDER BY b.name, col.position"
        );
        // Etiquetas existentes no CRM (para o dropdown do bloco "tag")
        $labels = $db->fetchAll("SELECT id, name, color FROM whatsapp_labels ORDER BY name");

        $this->view('sequences/edit', [
            'user' => $user,
            'sequence' => $seq,
            'accounts' => $accounts,
            'columns' => $columns,
            'labels' => $labels,
        ]);
    }

    public function save()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome da sequência.'], 400);

        // Valida o grafo JSON
        $graphRaw = $_POST['graph'] ?? '';
        $graph = json_decode($graphRaw, true);
        if ($graphRaw !== '' && $graph === null) $this->json(['error' => 'Grafo inválido.'], 400);

        $data = [
            'name' => $name,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'email_account_id' => !empty($_POST['email_account_id']) ? intval($_POST['email_account_id']) : null,
            'graph' => $graphRaw !== '' ? json_encode($graph, JSON_UNESCAPED_UNICODE) : null,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'daily_limit' => max(1, intval($_POST['daily_limit'] ?? 100)),
            'window_start' => $_POST['window_start'] ?? '08:00:00',
            'window_end' => $_POST['window_end'] ?? '18:00:00',
            'send_weekends' => !empty($_POST['send_weekends']) ? 1 : 0,
        ];

        if ($id) {
            $this->model->update($id, $data);
        } else {
            $data['created_by'] = $user['id'];
            $id = $this->model->create($data);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    public function delete($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    public function detail($id = null)
    {
        $this->requireRole($this->roles);
        if (!$id) $this->json(['error' => 'ID obrigatório'], 400);
        $seq = $this->model->findById($id);
        if (!$seq) $this->json(['error' => 'Sequência não encontrada'], 404);
        $seq['graph'] = json_decode($seq['graph'] ?? '{}', true);
        $this->json([
            'sequence' => $seq,
            'participants' => $this->model->participants($id),
            'stats' => $this->model->stats($id),
        ]);
    }

    public function addLeads()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $sequenceId = intval($_POST['sequence_id'] ?? 0);
        $ids = $_POST['contact_ids'] ?? [];
        if (is_string($ids)) $ids = array_filter(explode(',', $ids));
        if (!$sequenceId || empty($ids)) $this->json(['error' => 'Selecione a sequência e ao menos um lead.'], 400);

        $engine = new SequenceEngine();
        $user = $this->currentUser();
        $ok = 0; $errors = [];
        foreach ($ids as $cid) {
            $r = $engine->enroll($sequenceId, intval($cid), $user['id']);
            if (!empty($r['success'])) $ok++;
            elseif (!empty($r['error'])) $errors[] = $r['error'];
        }
        $this->json(['success' => true, 'enrolled' => $ok, 'errors' => array_values(array_unique($errors))]);
    }

    public function removeLead()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        $participantId = intval($_POST['participant_id'] ?? 0);
        if (!$participantId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $p = $db->fetch("SELECT contact_id FROM sequence_participants WHERE id = ?", [$participantId]);
        $db->update('sequence_participants', [
            'status' => 'stopped', 'stop_reason' => 'manual', 'finished_at' => date('Y-m-d H:i:s'), 'next_run_at' => null,
        ], 'id = ?', [$participantId]);
        $this->json(['success' => true]);
    }

    /** Leads com e-mail cadastrado (para o seletor de inscrição). */
    public function leadsForSelect()
    {
        $this->requireRole($this->roles);
        $rows = Database::getInstance()->fetchAll(
            "SELECT id, COALESCE(contact_name, push_name) AS name, lead_email
             FROM whatsapp_contacts
             WHERE lead_email IS NOT NULL AND lead_email <> '' AND COALESCE(is_group,0)=0
               AND COALESCE(unsubscribed,0)=0 AND COALESCE(email_bounced,0)=0
             ORDER BY name ASC LIMIT 500"
        );
        $this->json(['leads' => $rows]);
    }

    // ============ Templates ============

    /** Lista de templates (JSON). GET sequences/templates?channel= */
    public function templates()
    {
        $this->requireRole($this->roles);
        $channel = in_array($_GET['channel'] ?? '', ['email', 'whatsapp']) ? $_GET['channel'] : null;
        $this->json(['templates' => (new MessageTemplate())->all($channel)]);
    }

    /** Cria/atualiza um template. POST sequences/saveTemplate */
    public function saveTemplate()
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $id = intval($_POST['id'] ?? 0);
        $channel = in_array($_POST['channel'] ?? '', ['email', 'whatsapp']) ? $_POST['channel'] : 'email';
        $name = trim($_POST['name'] ?? '');
        $body = $_POST['body'] ?? '';
        if ($name === '' || trim($body) === '') $this->json(['error' => 'Informe nome e conteúdo do template.'], 400);

        $data = [
            'channel' => $channel,
            'name' => $name,
            'subject' => ($channel === 'email') ? (trim($_POST['subject'] ?? '') ?: null) : null,
            'body' => $body,
        ];
        $model = new MessageTemplate();
        if ($id) {
            $model->update($id, $data);
        } else {
            $data['created_by'] = $user['id'];
            $id = $model->create($data);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Exclui um template. POST sequences/deleteTemplate/{id} */
    public function deleteTemplate($id = null)
    {
        $this->requireRole($this->roles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        (new MessageTemplate())->delete($id);
        $this->json(['success' => true]);
    }
}
