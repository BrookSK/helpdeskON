<?php

class TicketsController extends Controller
{
    private $ticketModel;
    private $attachmentModel;
    private $messageModel;

    public function __construct()
    {
        $this->ticketModel = new Ticket();
        $this->attachmentModel = new TicketAttachment();
        $this->messageModel = new TicketMessage();
    }

    // Listagem de tickets
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();

        if ($user['role'] === 'client') {
            $tickets = $this->ticketModel->getByClient($user['id']);
            $this->view('client/tickets', ['user' => $user, 'tickets' => $tickets]);
        } else {
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
            $tickets = $this->ticketModel->getAll($filters);
            $this->view('attendant/tickets', ['user' => $user, 'tickets' => $tickets]);
        }
    }

    // Kanban view para atendentes/admin
    public function kanban()
    {
        $this->requireRole(['super_admin', 'attendant']);
        $user = $this->currentUser();
        $attendantId = ($user['role'] === 'attendant') ? $user['id'] : null;
        $grouped = $this->ticketModel->getGroupedByStatus($attendantId);
        $this->view('attendant/kanban', ['user' => $user, 'grouped' => $grouped]);
    }

    // Formulário para criar nova demanda (cliente)
    public function create()
    {
        $this->requireRole(['client']);
        $user = $this->currentUser();
        $this->view('client/ticket_create', ['user' => $user]);
    }

    // Salvar nova demanda
    public function store()
    {
        $this->requireRole(['client']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('tickets');
        }

        $user = $this->currentUser();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';

        if (empty($title) || empty($description)) {
            flash('error', 'Título e descrição são obrigatórios.');
            $this->redirect('tickets/create');
        }

        $ticketId = $this->ticketModel->create([
            'client_id' => $user['id'],
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
        ]);

        // Upload de arquivos
        if (!empty($_FILES['attachments']['name'][0])) {
            $files = $_FILES['attachments'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $this->attachmentModel->upload($file, $ticketId, $user['id']);
                }
            }
        }

        // Enviar notificação
        $this->sendNewTicketNotification($ticketId);

        flash('success', 'Demanda criada com sucesso!');
        $this->redirect('tickets/show/' . $ticketId);
    }

    // Visualizar ticket
    public function show($id = null)
    {
        $this->requireLogin();
        if (!$id) $this->redirect('tickets');

        $user = $this->currentUser();
        $ticket = $this->ticketModel->findById($id);

        if (!$ticket) {
            flash('error', 'Demanda não encontrada.');
            $this->redirect('tickets');
        }

        // Verificar permissão
        if ($user['role'] === 'client' && $ticket['client_id'] != $user['id']) {
            $this->redirect('tickets');
        }

        $messages = $this->messageModel->getByTicket($id);
        $attachments = $this->attachmentModel->getByTicket($id);

        // Marcar mensagens como lidas
        $this->messageModel->markAsRead($id, $user['id']);

        $userModel = new User();
        $attendants = $userModel->getAttendants();

        $this->view('tickets/view', [
            'user' => $user,
            'ticket' => $ticket,
            'messages' => $messages,
            'attachments' => $attachments,
            'attendants' => $attendants,
        ]);
    }

    // Atualizar status do ticket
    public function updateStatus($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Requisição inválida'], 400);
            }
            $this->redirect('tickets');
        }

        $status = $_POST['status'] ?? '';
        $validStatuses = ['open', 'in_progress', 'waiting_client', 'completed', 'denied', 'archived'];
        if (!in_array($status, $validStatuses)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Status inválido'], 400);
            }
            $this->redirect('tickets/show/' . $id);
        }

        $this->ticketModel->updateStatus($id, $status);

        // Notificar cliente sobre mudança de status
        $ticket = $this->ticketModel->findById($id);
        $this->sendStatusChangeNotification($ticket, $status);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'status' => $status]);
        }

        flash('success', 'Status atualizado com sucesso!');
        $this->redirect('tickets/show/' . $id);
    }

    // Atribuir atendente
    public function assign($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $attendantId = $_POST['attendant_id'] ?? null;
        if ($attendantId) {
            $this->ticketModel->assignAttendant($id, $attendantId);
            flash('success', 'Atendente atribuído com sucesso!');
        }

        $this->redirect('tickets/show/' . $id);
    }

    // Enviar mensagem no chat
    public function sendMessage($id = null)
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            $this->json(['error' => 'Mensagem vazia'], 400);
        }

        $messageId = $this->messageModel->create([
            'ticket_id' => $id,
            'user_id' => $user['id'],
            'message' => $message,
        ]);

        // Enviar notificação
        $ticket = $this->ticketModel->findById($id);
        $this->sendMessageNotification($ticket, $user, $message);

        $this->json([
            'success' => true,
            'message' => [
                'id' => $messageId,
                'user_name' => $user['name'],
                'user_role' => $user['role'],
                'message' => escape($message),
                'created_at' => date('d/m/Y H:i'),
            ]
        ]);
    }

    // Buscar novas mensagens (polling)
    public function getMessages($id = null)
    {
        $this->requireLogin();
        if (!$id) $this->json(['error' => 'ID inválido'], 400);

        $lastId = $_GET['last_id'] ?? 0;
        $messages = Database::getInstance()->fetchAll(
            "SELECT m.*, u.name as user_name, u.role as user_role
             FROM ticket_messages m
             LEFT JOIN users u ON m.user_id = u.id
             WHERE m.ticket_id = ? AND m.id > ?
             ORDER BY m.created_at ASC",
            [$id, $lastId]
        );

        // Marcar como lidas
        $user = $this->currentUser();
        $this->messageModel->markAsRead($id, $user['id']);

        $this->json(['messages' => $messages]);
    }

    // Upload de anexo via AJAX
    public function uploadAttachment($id = null)
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        if (!empty($_FILES['file'])) {
            $result = $this->attachmentModel->upload($_FILES['file'], $id, $user['id']);
            $this->json($result);
        }

        $this->json(['error' => 'Nenhum arquivo enviado'], 400);
    }

    // Notificações
    private function sendNewTicketNotification($ticketId)
    {
        $ticket = $this->ticketModel->findById($ticketId);
        $notificationTitle = "Nova demanda: {$ticket['title']}";
        $notificationMessage = "O cliente {$ticket['client_name']} abriu uma nova demanda.";

        // Notificar todos atendentes via sistema
        $userModel = new User();
        $attendants = $userModel->getAttendants();
        $db = Database::getInstance();

        foreach ($attendants as $att) {
            $db->insert('notifications', [
                'user_id' => $att['id'],
                'ticket_id' => $ticketId,
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'type' => 'system',
            ]);
        }

        // Webhook
        $this->triggerWebhook($notificationMessage, $ticket['client_phone'] ?? '');
    }

    private function sendStatusChangeNotification($ticket, $newStatus)
    {
        $statusLabels = [
            'open' => 'Aberto',
            'in_progress' => 'Em andamento',
            'waiting_client' => 'Aguardando cliente',
            'completed' => 'Concluído',
            'denied' => 'Negado',
            'archived' => 'Arquivado',
        ];

        $label = $statusLabels[$newStatus] ?? $newStatus;
        $db = Database::getInstance();
        $userModel = new User();
        $currentUser = $this->currentUser();

        // Mensagem para o cliente
        $clientMessage = "Sua demanda \"{$ticket['title']}\" teve o status alterado para: {$label}";
        // Mensagem para atendentes/admins
        $internalMessage = "A demanda #{$ticket['id']} \"{$ticket['title']}\" foi alterada para: {$label}";

        // Lista de quem será notificado (evitar duplicatas)
        $notifiedIds = [];

        // 1. Notificar o cliente
        if ($ticket['client_id'] && $ticket['client_id'] != $currentUser['id']) {
            $db->insert('notifications', [
                'user_id' => $ticket['client_id'],
                'ticket_id' => $ticket['id'],
                'title' => 'Status atualizado',
                'message' => $clientMessage,
                'type' => 'system',
            ]);
            $notifiedIds[] = $ticket['client_id'];

            // Enviar email ao cliente
            $client = $userModel->findById($ticket['client_id']);
            if ($client && $client['email']) {
                $htmlBody = Mailer::template('Status Atualizado', "
                    <p>Olá, <strong>" . htmlspecialchars($client['name']) . "</strong>!</p>
                    <p>{$clientMessage}</p>
                    <p><a href='" . baseUrl('tickets/show/' . $ticket['id']) . "' style='color:#00BFA6;font-weight:600;'>Ver demanda</a></p>
                ");
                Mailer::send($client['email'], "Status atualizado - #{$ticket['id']}", $htmlBody);
            }
        }

        // 2. Notificar o atendente atribuído (se não foi ele quem fez a ação)
        if ($ticket['attendant_id'] && $ticket['attendant_id'] != $currentUser['id'] && !in_array($ticket['attendant_id'], $notifiedIds)) {
            $db->insert('notifications', [
                'user_id' => $ticket['attendant_id'],
                'ticket_id' => $ticket['id'],
                'title' => 'Status atualizado',
                'message' => $internalMessage,
                'type' => 'system',
            ]);
            $notifiedIds[] = $ticket['attendant_id'];

            // Enviar email ao atendente
            $attendant = $userModel->findById($ticket['attendant_id']);
            if ($attendant && $attendant['email']) {
                $htmlBody = Mailer::template('Status Atualizado', "
                    <p>Olá, <strong>" . htmlspecialchars($attendant['name']) . "</strong>!</p>
                    <p>{$internalMessage}</p>
                    <p><a href='" . baseUrl('tickets/show/' . $ticket['id']) . "' style='color:#00BFA6;font-weight:600;'>Ver demanda</a></p>
                ");
                Mailer::send($attendant['email'], "Status atualizado - #{$ticket['id']}", $htmlBody);
            }
        }

        // 3. Notificar todos os super admins (que não sejam quem fez a ação)
        $admins = $db->fetchAll("SELECT id, email, name FROM users WHERE role = 'super_admin' AND id != ? AND is_active = 1", [$currentUser['id']]);
        foreach ($admins as $admin) {
            if (!in_array($admin['id'], $notifiedIds)) {
                $db->insert('notifications', [
                    'user_id' => $admin['id'],
                    'ticket_id' => $ticket['id'],
                    'title' => 'Status atualizado',
                    'message' => $internalMessage,
                    'type' => 'system',
                ]);
                $notifiedIds[] = $admin['id'];
            }
        }

        // 4. Webhook
        $this->triggerWebhook($clientMessage, $ticket['client_phone'] ?? '');
    }

    private function sendMessageNotification($ticket, $sender, $messageText)
    {
        $db = Database::getInstance();
        $recipientId = ($sender['id'] == $ticket['client_id']) ? $ticket['attendant_id'] : $ticket['client_id'];

        if ($recipientId) {
            $db->insert('notifications', [
                'user_id' => $recipientId,
                'ticket_id' => $ticket['id'],
                'title' => "Nova mensagem de {$sender['name']}",
                'message' => mb_substr($messageText, 0, 200),
                'type' => 'system',
            ]);

            $userModel = new User();
            $recipient = $userModel->findById($recipientId);
            $this->triggerWebhook(
                "Nova mensagem de {$sender['name']} no ticket #{$ticket['id']}: " . mb_substr($messageText, 0, 100),
                $recipient['phone'] ?? ''
            );
        }
    }

    private function triggerWebhook($message, $phone = '')
    {
        $webhookUrl = Config::get('webhook_url');
        $webhookEnabled = Config::get('webhook_enabled');
        $webhookPhone = Config::get('webhook_phone') ?: $phone;
        $webhookName = Config::get('webhook_name', 'ON Solutions Helpdesk');

        if ($webhookEnabled && $webhookUrl) {
            $payload = json_encode([
                'message' => $message,
                'phone' => $webhookPhone,
                'name' => $webhookName,
            ]);

            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
