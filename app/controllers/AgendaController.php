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

        $data['client_email'] = trim($_POST['client_email'] ?? '') ?: null;
        // Se o link do Meet já foi gerado no modal, reaproveita (evita criar evento duplicado)
        $preEventId = trim($_POST['google_event_id'] ?? '') ?: null;
        $preMeetLink = trim($_POST['meet_link'] ?? '') ?: null;
        if ($preEventId) $data['google_event_id'] = $preEventId;
        if ($preMeetLink) $data['meet_link'] = $preMeetLink;

        $id = $this->model->create($data);

        // Salva/atualiza o briefing do cliente (se houver contato vinculado)
        if ($contactId) {
            $this->saveBriefingFromPost($contactId, $user['id']);
        }

        // Notifica o responsável se for outra pessoa
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notify($data['assigned_to'], 'Nova reunião na agenda', "{$user['name']} agendou \"{$title}\" com você.");
        }

        // Integração Google Agenda/Meet + convites (email + WhatsApp)
        if (!empty($data['meeting_at'])) {
            // Se o evento já foi criado no modal (link gerado), só envia os convites; senão cria agora
            $this->createGoogleEventAndInvites($id, !$preEventId);
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    /**
     * Cria o evento no Google Agenda (com Meet), salva o link e envia os convites
     * por e-mail (super admin + cliente) e WhatsApp (cliente).
     */
    private function createGoogleEventAndInvites($meetingId, $createEvent = true)
    {
        $meeting = $this->model->findById($meetingId);
        if (!$meeting) return;

        $clientName = $meeting['crm_contact_name'] ?? $meeting['client_name'] ?? 'Cliente';
        $clientEmail = $meeting['client_email'] ?? null;
        $clientPhone = $meeting['crm_contact_phone'] ?? $meeting['client_phone'] ?? null;
        $meetingAt = $meeting['meeting_at'];

        // E-mail do super admin (primeiro super_admin ativo)
        $db = Database::getInstance();
        $admin = $db->fetch("SELECT name, email FROM users WHERE role = 'super_admin' AND is_active = 1 AND email <> '' ORDER BY id ASC LIMIT 1");
        $adminEmail = $admin['email'] ?? null;

        // Reaproveita o link já gerado (no modal), se houver
        $meetLink = $meeting['meet_link'] ?? null;

        // 1) Cria o evento no Google (se configurado e ainda não criado)
        $google = new GoogleCalendarApi();
        if ($createEvent && empty($meeting['google_event_id']) && $google->isConfigured()) {
            $attendees = array_filter([$adminEmail, $clientEmail]);
            $res = $google->createEvent([
                'title' => 'Reunião: ' . $meeting['title'],
                'description' => "Reunião comercial com {$clientName}." . ($meeting['notes'] ? "\n\n" . $meeting['notes'] : ''),
                'start' => $meetingAt,
                'durationMin' => 60,
                'timezone' => 'America/Sao_Paulo',
                'attendees' => $attendees,
            ]);
            if (!empty($res['success'])) {
                $meetLink = $res['meet_link'];
                $this->model->update($meetingId, [
                    'google_event_id' => $res['event_id'],
                    'meet_link' => $meetLink,
                ]);
            }
        }

        $whenFmt = date('d/m/Y \à\s H:i', strtotime($meetingAt));

        // 2) E-mail personalizado (super admin + cliente)
        $emailBody = Mailer::template(
            'Convite de Reunião',
            "<p>Olá!</p>
             <p>Uma reunião foi agendada:</p>
             <p style='margin:6px 0;'><strong>Assunto:</strong> " . htmlspecialchars($meeting['title']) . "</p>
             <p style='margin:6px 0;'><strong>Cliente:</strong> " . htmlspecialchars($clientName) . "</p>
             <p style='margin:6px 0;'><strong>Data:</strong> {$whenFmt}</p>"
             . ($meetLink ? "<p style='text-align:center;margin:24px 0;'>
                    <a href='{$meetLink}' style='background:#00BFA6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;'>
                        Entrar na reunião (Google Meet)
                    </a></p>
                    <p style='font-size:0.8rem;color:#888;word-break:break-all;'>Link: {$meetLink}</p>" : "")
             . "<p>Nos vemos lá!</p>"
        );
        foreach (array_filter([$adminEmail, $clientEmail]) as $to) {
            try { Mailer::send($to, 'Convite de Reunião — ' . $meeting['title'], $emailBody); } catch (\Throwable $e) {}
        }

        // 3) WhatsApp para o cliente
        if (!empty($clientPhone)) {
            $waMsg = "📅 *Reunião agendada*\n\n"
                . "*Assunto:* {$meeting['title']}\n"
                . "*Data:* {$whenFmt}\n"
                . ($meetLink ? "*Link da call:* {$meetLink}\n" : "")
                . "\nAté breve!";
            try { WhatsappNotifier::sendToPhone($clientPhone, $waMsg, $clientName); } catch (\Throwable $e) {}
        }
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
        if (isset($_POST['client_email'])) $data['client_email'] = trim($_POST['client_email']) ?: null;

        if (!empty($data)) $this->model->update($id, $data);

        // Atualiza o briefing do cliente vinculado
        if (!empty($meeting['contact_id'])) {
            $this->saveBriefingFromPost($meeting['contact_id'], $user['id']);
        }

        // Se mudou a data e já existe evento no Google, atualiza o horário
        if (isset($data['meeting_at']) && !empty($meeting['google_event_id'])) {
            try {
                $g = new GoogleCalendarApi();
                if ($g->isConfigured()) $g->updateEventTime($meeting['google_event_id'], $data['meeting_at'], 60);
            } catch (\Throwable $e) { /* ignora */ }
        }
        // Se não havia evento e agora tem data, cria (e envia convites)
        if (empty($meeting['google_event_id']) && !empty($data['meeting_at'])) {
            $this->createGoogleEventAndInvites($id);
        }

        $this->json(['success' => true, 'meeting' => $this->model->findById($id)]);
    }

    // API: verifica se a integração Google está configurada/funcionando
    public function googleStatus()
    {
        $this->requireRole($this->accessRoles);
        $google = new GoogleCalendarApi();
        $this->json(['configured' => $google->isConfigured()]);
    }

    // API: gerar o link do Google Meet ANTES de salvar (garante o link)
    public function generateMeet()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $google = new GoogleCalendarApi();
        if (!$google->isConfigured()) {
            $this->json(['error' => 'Google Agenda não configurado. Adicione as credenciais em Configurações.'], 400);
        }

        $title = trim($_POST['title'] ?? '') ?: 'Reunião';
        $meetingAt = !empty($_POST['meeting_at']) ? str_replace('T', ' ', $_POST['meeting_at']) : null;
        if (!$meetingAt) $this->json(['error' => 'Informe a data e o horário da reunião.'], 400);

        $user = $this->currentUser();
        $db = Database::getInstance();
        $admin = $db->fetch("SELECT email FROM users WHERE role = 'super_admin' AND is_active = 1 AND email <> '' ORDER BY id ASC LIMIT 1");
        $attendees = array_filter([$admin['email'] ?? null, trim($_POST['client_email'] ?? '')]);

        $res = $google->createEvent([
            'title' => 'Reunião: ' . $title,
            'description' => trim($_POST['notes'] ?? ''),
            'start' => $meetingAt,
            'durationMin' => 60,
            'timezone' => 'America/Sao_Paulo',
            'attendees' => $attendees,
        ]);

        if (empty($res['success'])) {
            $this->json(['error' => $res['error'] ?? 'Falha ao gerar o link do Meet.'], 400);
        }

        // Se estiver editando uma reunião existente, já vincula o evento
        $meetingId = !empty($_POST['meeting_id']) ? intval($_POST['meeting_id']) : null;
        if ($meetingId && $this->model->findById($meetingId)) {
            $this->model->update($meetingId, ['google_event_id' => $res['event_id'], 'meet_link' => $res['meet_link']]);
        }

        $this->json([
            'success' => true,
            'event_id' => $res['event_id'],
            'meet_link' => $res['meet_link'],
            'html_link' => $res['html_link'] ?? null,
        ]);
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
                 'decision_level', 'lead_temperature', 'lead_source', 'main_objection', 'next_step', 'notes'];
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
