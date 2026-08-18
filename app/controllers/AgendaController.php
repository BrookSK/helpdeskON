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

        // Todos os usuários internos (exceto clientes) para o multi-select de participantes
        $allInternalRoles = ['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent'];
        $participants = $userModel->getGroupedByRole($allInternalRoles);

        $this->view('agenda/index', [
            'user' => $user,
            'grouped' => $grouped,
            'team' => $team,
            'leads' => $leads,
            'participants' => $participants,
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
        $meeting['participants'] = $this->model->getParticipants($id);
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

        // Contato é obrigatório (selecionar existente ou cadastrar novo)
        if (!$contactId) {
            $this->json(['error' => 'Selecione um cliente ou cadastre um novo.'], 400);
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

        // Salva participantes da equipe
        $participantIds = array_filter(array_map('intval', $_POST['participants'] ?? []));
        if (!empty($participantIds)) {
            $this->model->setParticipants($id, $participantIds);
        }

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

        // Emails dos participantes internos da reunião
        $participantEmails = $this->model->getParticipantEmails($meetingId);

        // 1) Cria o evento no Google (se configurado e ainda não criado)
        $google = new GoogleCalendarApi();
        if ($createEvent && empty($meeting['google_event_id']) && $google->isConfigured()) {
            $attendees = array_values(array_unique(array_filter(
                array_merge([$adminEmail, $clientEmail], $participantEmails)
            )));
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

        // Atualiza participantes da equipe
        if (isset($_POST['participants'])) {
            $participantIds = array_filter(array_map('intval', $_POST['participants']));
            $this->model->setParticipants($id, $participantIds);
        }

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

        // Se o status mudou para "cancelada" e há evento no Google, remove o evento se solicitado
        if (isset($data['status']) && $data['status'] === 'cancelada' && !empty($meeting['google_event_id'])) {
            if (!empty($_POST['delete_google_event'])) {
                $notify = !empty($_POST['notify_participants']);
                try {
                    $g = new GoogleCalendarApi();
                    if ($g->isConfigured()) {
                        $g->deleteEvent($meeting['google_event_id'], $notify);
                    }
                    $this->model->update($id, ['google_event_id' => null, 'meet_link' => null]);
                } catch (\Throwable $e) { /* ignora */ }
            }
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

    // Dashboard de Performance Comercial
    public function dashboard()
    {
        $this->requireRole(['super_admin', 'comercial', 'marketing']);
        $user = $this->currentUser();

        // Filtros de período (padrão: mês atual)
        $startDate = $_GET['start'] ?? date('Y-m-01');
        $endDate = $_GET['end'] ?? date('Y-m-t');

        // Se for comercial, vê apenas os próprios dados
        $filterUserId = null;
        if ($user['role'] === 'comercial') {
            $filterUserId = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $filterUserId = intval($_GET['user_id']);
        }

        // Métricas de reuniões por usuário
        $meetingStats = $this->model->getPerformanceStats($startDate, $endDate, $filterUserId);
        $uniqueContacts = $this->model->getUniqueContactsByUser($startDate, $endDate, $filterUserId);

        // Métricas de mensagens WhatsApp
        $msgModel = new WhatsappMessage();
        $messageStats = $msgModel->getMessageStatsByUser($startDate, $endDate, $filterUserId);
        $responseStats = $msgModel->getContactResponseStats($startDate, $endDate, $filterUserId);

        // Métricas de e-mail prospecção
        $emailModel = new EmailProspection();
        $emailStats = $emailModel->getStatsByUser($startDate, $endDate, $filterUserId);

        // Série mensal (gráfico)
        $trend = $this->model->getMonthlyTrend(6, $filterUserId);

        // Lista de usuários comerciais (para filtro no admin)
        $userModel = new User();
        $comerciais = $userModel->getByRoles(['super_admin', 'comercial', 'marketing']);

        // Monta dados consolidados por usuário para a tabela comparativa
        $tableData = [];
        $allUserIds = array_unique(array_merge(
            array_keys($meetingStats),
            array_keys($messageStats),
            array_keys($responseStats),
            array_keys($emailStats)
        ));
        foreach ($allUserIds as $uid) {
            $ms = $meetingStats[$uid] ?? [];
            $msg = $messageStats[$uid] ?? ['sent' => 0, 'received' => 0, 'contacts_messaged' => 0];
            $resp = $responseStats[$uid] ?? ['contacted' => 0, 'replied' => 0, 'no_reply' => 0];
            $em = $emailStats[$uid] ?? ['sent' => 0, 'failed' => 0, 'total' => 0, 'unique_contacts' => 0];
            $tableData[] = [
                'user_id' => $uid,
                'user_name' => $ms['user_name'] ?? 'Usuário #' . $uid,
                'total_meetings' => $ms['total'] ?? 0,
                'agendada' => ($ms['agendada'] ?? 0) + ($ms['a_agendar'] ?? 0),
                'confirmada' => $ms['confirmada'] ?? 0,
                'realizada' => $ms['realizada'] ?? 0,
                'convertida' => $ms['convertida'] ?? 0,
                'remarcada' => $ms['remarcada'] ?? 0,
                'cancelada' => $ms['cancelada'] ?? 0,
                'unique_contacts' => $uniqueContacts[$uid] ?? 0,
                'messages_sent' => $msg['sent'],
                'messages_received' => $msg['received'],
                'contacts_messaged' => $msg['contacts_messaged'],
                'contacts_contacted' => $resp['contacted'],
                'contacts_replied' => $resp['replied'],
                'contacts_no_reply' => $resp['no_reply'],
                'emails_sent' => $em['sent'],
                'emails_failed' => $em['failed'],
                'emails_total' => $em['total'],
                'emails_unique_contacts' => $em['unique_contacts'],
            ];
        }

        // Ordena por total de conversões (desc)
        usort($tableData, fn($a, $b) => $b['convertida'] <=> $a['convertida']);

        $this->view('agenda/dashboard', [
            'user' => $user,
            'tableData' => $tableData,
            'trend' => $trend,
            'comerciais' => $comerciais,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterUserId' => $filterUserId,
            'isAdmin' => in_array($user['role'], ['super_admin', 'marketing']),
        ]);
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

        // Emails dos participantes selecionados no modal
        $participantIds = array_filter(array_map('intval', $_POST['participants'] ?? []));
        if (!empty($participantIds)) {
            $userModel = new User();
            foreach ($participantIds as $pid) {
                $pu = $userModel->findById($pid);
                if (!empty($pu['email'])) $attendees[] = $pu['email'];
            }
        }
        $attendees = array_values(array_unique(array_filter($attendees)));

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

        $meeting = $this->model->findById($id);
        if (!$meeting) $this->json(['error' => 'Reunião não encontrada'], 404);

        // Se há evento no Google Calendar e o usuário pediu para remover
        if (!empty($meeting['google_event_id']) && !empty($_POST['delete_google_event'])) {
            $notify = !empty($_POST['notify_participants']);
            try {
                $google = new GoogleCalendarApi();
                if ($google->isConfigured()) {
                    $google->deleteEvent($meeting['google_event_id'], $notify);
                }
            } catch (\Throwable $e) { /* ignora erro na remoção do evento */ }
        }

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
