<?php

class AgendaController extends Controller
{
    private $accessRoles = ['super_admin', 'comercial', 'marketing'];
    private $model;
    private $contactModel;

    public function __construct()
    {
        $this->model = new AgendaMeeting();
        $this->contactModel = new WhatsappContact();
    }

    // Página principal — Kanban + Calendário
    public function index()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $filters = [];
        // Comercial vê só suas reuniões; super_admin e marketing veem tudo
        if ($user['role'] === 'comercial' && empty($_GET['show_all'])) {
            $filters['assigned_to'] = $user['id'];
        }

        $grouped = $this->model->getGroupedByStatus($filters);

        $userModel = new User();
        $team = $userModel->getByRoles(['super_admin', 'comercial', 'marketing']);
        $leads = $this->contactModel->getLeadsForSelect();

        $this->view('agenda/index', [
            'user' => $user,
            'grouped' => $grouped,
            'team' => $team,
            'leads' => $leads,
            'isAdmin' => $user['role'] === 'super_admin',
        ]);
    }

    // API: reuniões para o calendário (JSON)
    public function calendar()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $start = ($_GET['start'] ?? date('Y-m-01')) . ' 00:00:00';
        $end = ($_GET['end'] ?? date('Y-m-t')) . ' 23:59:59';

        $filters = [];
        if ($user['role'] === 'comercial' && empty($_GET['show_all'])) {
            $filters['assigned_to'] = $user['id'];
        }

        $meetings = $this->model->getForCalendar($start, $end, $filters);
        $events = array_map(function ($m) {
            return [
                'id' => $m['id'],
                'title' => $m['title'],
                'meeting_at' => $m['meeting_at'],
                'status' => $m['status'],
                'urgency' => $m['urgency'],
                'temperature' => $m['temperature'],
                'assigned_name' => $m['assigned_name'] ?? 'Sem responsável',
                'client' => $m['crm_contact_name'] ?? $m['client_name'],
            ];
        }, $meetings);

        $this->json(['events' => $events]);
    }

    // API: obter uma reunião + briefing do cliente
    public function get($id = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        $briefing = null;
        if (!empty($meeting['contact_id'])) {
            $briefing = $this->contactModel->getBriefing($meeting['contact_id']);
        }
        $meeting['briefing'] = $briefing;
        $this->json(['meeting' => $meeting]);
    }

    // API: briefing de um lead (ao selecionar o cliente no formulário)
    public function briefing($contactId = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$contactId) $this->json(['error' => 'ID não informado'], 400);
        $contact = $this->contactModel->findById($contactId);
        $briefing = $this->contactModel->getBriefing($contactId);
        $this->json(['contact' => $contact, 'briefing' => $briefing]);
    }

    // API: criar reunião
    public function create()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $title = trim($_POST['title'] ?? '');
        if ($title === '') $this->json(['error' => 'Título obrigatório'], 400);

        $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;

        // Cliente novo (manual): cria o lead no CRM para ficar disponível depois
        if (!$contactId && !empty($_POST['new_client_name'])) {
            $contactId = $this->contactModel->createManualLead(
                trim($_POST['new_client_name']),
                trim($_POST['new_client_phone'] ?? ''),
                $user['id']
            );
        }

        $data = [
            'title' => $title,
            'contact_id' => $contactId,
            'client_name' => trim($_POST['client_name'] ?? '') ?: (trim($_POST['new_client_name'] ?? '') ?: null),
            'client_phone' => trim($_POST['client_phone'] ?? '') ?: (trim($_POST['new_client_phone'] ?? '') ?: null),
            'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $user['id'],
            'created_by' => $user['id'],
            'urgency' => in_array($_POST['urgency'] ?? '', ['baixa','media','alta','urgente']) ? $_POST['urgency'] : 'media',
            'temperature' => in_array($_POST['temperature'] ?? '', ['frio','morno','quente']) ? $_POST['temperature'] : null,
            'status' => in_array($_POST['status'] ?? '', AgendaMeeting::$statuses) ? $_POST['status'] : 'a_agendar',
            'meeting_at' => !empty($_POST['meeting_at']) ? str_replace('T', ' ', $_POST['meeting_at']) : null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ];

        $id = $this->model->create($data);

        // Salva/atualiza o briefing do cliente (se houver contato vinculado)
        if ($contactId) {
            $this->saveBriefingFromPost($contactId, $user['id']);
        }

        // Notifica o responsável se for outra pessoa
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notify($data['assigned_to'], 'Nova reunião na agenda', "{$user['name']} agendou \"{$title}\" com você.");
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    // API: atualizar reunião
    public function update($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        $user = $this->currentUser();
        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['urgency']) && in_array($_POST['urgency'], ['baixa','media','alta','urgente'])) $data['urgency'] = $_POST['urgency'];
        if (isset($_POST['temperature'])) $data['temperature'] = in_array($_POST['temperature'], ['frio','morno','quente']) ? $_POST['temperature'] : null;
        if (isset($_POST['status']) && in_array($_POST['status'], AgendaMeeting::$statuses)) $data['status'] = $_POST['status'];
        if (isset($_POST['meeting_at'])) $data['meeting_at'] = $_POST['meeting_at'] ? str_replace('T', ' ', $_POST['meeting_at']) : null;
        if (isset($_POST['notes'])) $data['notes'] = trim($_POST['notes']) ?: null;

        if (!empty($data)) $this->model->update($id, $data);

        // Atualiza o briefing do cliente vinculado
        if (!empty($meeting['contact_id'])) {
            $this->saveBriefingFromPost($meeting['contact_id'], $user['id']);
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    // API: mudar status (drag-and-drop no Kanban)
    public function updateStatus($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $status = $_POST['status'] ?? '';
        if (!in_array($status, AgendaMeeting::$statuses)) $this->json(['error' => 'Status inválido'], 400);

        $this->model->updateStatus($id, $status, intval($_POST['position'] ?? 0));
        $this->json(['success' => true]);
    }

    // API: excluir reunião
    public function delete($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        if (!$this->model->findById($id)) $this->json(['error' => 'Reunião não encontrada'], 404);
        $this->model->delete($id);
        $this->json(['success' => true]);
    }

    // ===== Helpers =====
    private function saveBriefingFromPost($contactId, $userId)
    {
        // Só grava se algum campo de briefing foi enviado
        $keys = ['need', 'main_pain', 'current_solution', 'expected_goal', 'urgency', 'investment_range',
                 'decision_level', 'lead_temperature', 'main_objection', 'next_step', 'notes'];
        $data = [];
        foreach ($keys as $k) {
            if (isset($_POST['bf_' . $k])) $data[$k] = trim($_POST['bf_' . $k]) ?: null;
        }
        if (empty($data)) return;
        // lead_temperature precisa ser válido ou null
        if (isset($data['lead_temperature']) && !in_array($data['lead_temperature'], ['frio','morno','quente'])) {
            $data['lead_temperature'] = null;
        }
        $this->contactModel->saveBriefing($contactId, $data, $userId);
    }

    private function notify($userId, $title, $message)
    {
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => $userId, 'title' => $title, 'message' => $message, 'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* ignora */ }
    }
}
