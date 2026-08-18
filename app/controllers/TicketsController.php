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
            $fullUser = (new User())->findById($user['id']);
            // Dono da empresa vê todos os tickets da empresa
            if ($fullUser['is_company_owner'] && $fullUser['company_id']) {
                $tickets = $this->ticketModel->getByCompany($fullUser['company_id']);
                $this->view('client/tickets', ['user' => $user, 'tickets' => $tickets, 'isOwner' => true]);
            } else {
                $tickets = $this->ticketModel->getByClient($user['id']);
                $this->view('client/tickets', ['user' => $user, 'tickets' => $tickets, 'isOwner' => false]);
            }
        } elseif ($user['role'] === 'comercial') {
            // Comercial vê apenas os tickets atribuídos a ele ou criados por ele
            $tickets = $this->ticketModel->getByAttendantOrCreator($user['id']);
            $this->view('attendant/tickets', ['user' => $user, 'tickets' => $tickets, 'companies' => []]);
        } else {
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
            if (!empty($_GET['company'])) $filters['company_id'] = $_GET['company'];
            // Filtro por atendente: padrão = o próprio usuário logado (a menos que selecione "Todos")
            if (isset($_GET['attendant'])) {
                if ($_GET['attendant'] !== '') $filters['attendant_id'] = $_GET['attendant'];
            } else {
                $filters['attendant_id'] = $user['id'];
            }
            if (!empty($_GET['hide_completed'])) $filters['hide_completed'] = true;

            // "Ocultar arquivados" vem marcado por padrão. Só desativa se o form foi
            // enviado (filtered=1) e o checkbox veio desmarcado.
            $isSubmitted = isset($_GET['filtered']);
            $hideArchived = $isSubmitted ? !empty($_GET['hide_archived']) : true;
            if ($hideArchived) $filters['hide_archived'] = true;

            // Controle de acesso por empresa para atendentes
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
            if ($allowedCompanies !== null) {
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                $filters['allowed_companies'] = !empty($realIds) ? $realIds : [0];
            }

            $tickets = $this->ticketModel->getAll($filters);
            $companyModel = new Company();

            // Atendente só vê empresas que tem acesso no filtro
            if ($allowedCompanies !== null) {
                $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
                if (!empty($realIds)) {
                    $allCompanies = $companyModel->getAll();
                    $companies = array_filter($allCompanies, fn($c) => in_array($c['id'], $realIds));
                } else {
                    $companies = [];
                }
            } else {
                $companies = $companyModel->getAll();
            }

            $this->view('attendant/tickets', ['user' => $user, 'tickets' => $tickets, 'companies' => $companies, 'attendants' => (new User())->getByRoles(['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent'])]);
        }
    }

    // Kanban view para atendentes/admin
    public function kanban()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst']);
        $user = $this->currentUser();

        // Desenvolvedores e analistas veem apenas as atividades atribuídas a eles
        if (in_array($user['role'], ['developer', 'analyst'])) {
            $grouped = $this->ticketModel->getGroupedByAssignee($user['id']);
            $this->view('attendant/kanban', ['user' => $user, 'grouped' => $grouped, 'myTasksOnly' => true]);
            return;
        }

        $attendantId = (in_array($user['role'], ['attendant', 'whatsapp_agent'])) ? $user['id'] : null;

        // Controle de acesso por empresa
        $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
        $filterCompanies = null;
        if ($allowedCompanies !== null) {
            $realIds = array_filter($allowedCompanies, fn($id) => $id > 0);
            $filterCompanies = !empty($realIds) ? $realIds : [0];
        }
        $grouped = $this->ticketModel->getGroupedByStatus($attendantId, $filterCompanies);

        $this->view('attendant/kanban', ['user' => $user, 'grouped' => $grouped, 'myTasksOnly' => false]);
    }

    // Formulário para criar nova demanda (cliente e super_admin)
    public function create()
    {
        $this->requireRole(['client', 'super_admin']);
        $user = $this->currentUser();

        $data = ['user' => $user];

        // Se for super_admin, carregar lista de clientes + equipe para atribuição
        if ($user['role'] === 'super_admin') {
            $userModel = new User();
            // Clientes com a empresa vinculada, para seleção hierárquica Empresa > Usuário
            $clients = Database::getInstance()->fetchAll(
                "SELECT u.id, u.name, u.email, u.company_id, comp.name as company_name
                 FROM users u
                 LEFT JOIN companies comp ON u.company_id = comp.id
                 WHERE u.role = 'client' AND u.is_active = 1
                 ORDER BY comp.name IS NULL, comp.name, u.name ASC"
            );
            $data['clients'] = $clients;
            // Empresas para o primeiro nível da seleção
            $data['companies'] = (new Company())->getAll();
            // Atendentes (quem comunica no ticket) — inclui super_admin/admin
            $data['attendants'] = $userModel->getByRoles(['super_admin', 'attendant', 'whatsapp_agent']);
            $data['technicalGrouped'] = $userModel->getGroupedByRole(['developer', 'analyst', 'attendant', 'super_admin']);
        }

        $this->view('client/ticket_create', $data);
    }

    // Salvar nova demanda
    public function store()
    {
        $this->requireRole(['client', 'super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('tickets');
        }

        $user = $this->currentUser();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $transcription = trim($_POST['transcription'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';

        if (empty($title) || empty($description)) {
            flash('error', 'Título e descrição são obrigatórios.');
            $this->redirect('tickets/create');
        }

        // Determinar o client_id: se super_admin pode selecionar um cliente
        $clientId = $user['id'];
        $attendantId = null;
        $technicalId = null;
        if ($user['role'] === 'super_admin') {
            $selectedClient = $_POST['client_id'] ?? '';
            if (!empty($selectedClient)) {
                $clientId = (int)$selectedClient;
            }
            // Se não selecionou, o ticket fica vinculado ao próprio admin

            // Atribuições opcionais na criação
            $attendantId = !empty($_POST['attendant_id']) ? (int)$_POST['attendant_id'] : null;
            $technicalId = !empty($_POST['technical_responsible_id']) ? (int)$_POST['technical_responsible_id'] : null;
        }

        $ticketData = [
            'client_id' => $clientId,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
        ];

        if ($attendantId) {
            $ticketData['attendant_id'] = $attendantId;
        }
        if ($technicalId) {
            $ticketData['technical_responsible_id'] = $technicalId;
        }

        if (!empty($transcription)) {
            $ticketData['transcription'] = $transcription;
        }

        // Calcular número sequencial do cliente
        $db = Database::getInstance();
        $lastNumber = $db->fetch(
            "SELECT MAX(client_ticket_number) as last_num FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        $ticketData['client_ticket_number'] = ($lastNumber['last_num'] ?? 0) + 1;

        $ticketId = $this->ticketModel->create($ticketData);

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

        // Na criação, notificar apenas o atendente atribuído.
        // O responsável técnico só é notificado quando a demanda entra em Revisão Interna.
        if ($attendantId) {
            $this->notifyAssignment($ticketId, $attendantId, 'atendente');
        }

        // Criar card automático no Planejamento
        $ticket = $this->ticketModel->findById($ticketId);
        $planningCard = new PlanningCard();
        $planningCard->createFromTicket($ticket);

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
            // Dono da empresa pode ver tickets de membros da empresa
            $fullUser = (new User())->findById($user['id']);
            if ($fullUser['is_company_owner'] && $fullUser['company_id']) {
                $ticketOwner = (new User())->findById($ticket['client_id']);
                if (!$ticketOwner || $ticketOwner['company_id'] != $fullUser['company_id']) {
                    $this->redirect('tickets');
                }
            } else {
                $this->redirect('tickets');
            }
        }

        $messages = $this->messageModel->getByTicket($id);
        $attachments = $this->attachmentModel->getByTicket($id);

        // Marcar mensagens como lidas
        $this->messageModel->markAsRead($id, $user['id']);

        $userModel = new User();
        // Atendentes incluem super_admin/admin para atribuição
        $attendants = $userModel->getByRoles(['super_admin', 'attendant', 'whatsapp_agent']);
        // Candidatos a responsável técnico, agrupados por papel (Papel > Usuários)
        $technicalGrouped = $userModel->getGroupedByRole(['developer', 'analyst', 'attendant', 'super_admin']);

        // Buscar observações internas (apenas para equipe)
        $internalNotes = [];
        if (in_array($user['role'], ['super_admin', 'attendant'])) {
            $internalNotes = Database::getInstance()->fetchAll(
                "SELECT n.*, u.name as user_name
                 FROM ticket_internal_notes n
                 LEFT JOIN users u ON n.user_id = u.id
                 WHERE n.ticket_id = ?
                 ORDER BY n.created_at ASC",
                [$id]
            );
        }

        $this->view('tickets/view', [
            'user' => $user,
            'ticket' => $ticket,
            'messages' => $messages,
            'attachments' => $attachments,
            'attendants' => $attendants,
            'technicalGrouped' => $technicalGrouped,
            'internalNotes' => $internalNotes,
        ]);
    }

    // Atualizar status do ticket
    public function updateStatus($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Requisição inválida'], 400);
            }
            $this->redirect('tickets');
        }

        $status = $_POST['status'] ?? '';
        $validStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        if (!in_array($status, $validStatuses)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Status inválido'], 400);
            }
            $this->redirect('tickets/show/' . $id);
        }

        // Capturar status anterior para detectar transições
        $previousTicket = $this->ticketModel->findById($id);
        $previousStatus = $previousTicket['status'] ?? null;

        $this->ticketModel->updateStatus($id, $status);

        // Sincronizar card do planejamento
        $planningCard = new PlanningCard();
        $planningCard->syncFromTicket($id, $status);

        // Notificar cliente sobre mudança de status
        $ticket = $this->ticketModel->findById($id);
        $this->sendStatusChangeNotification($ticket, $status);

        // Ao entrar em "Em Revisão Interna" (vindo de outro status), notificar o responsável técnico e o atendente via WhatsApp/email
        if ($status === 'em_revisao_interna' && $previousStatus !== 'em_revisao_interna') {
            if (!empty($ticket['technical_responsible_id'])) {
                $this->notifyTechnicalReview($ticket);
            }
            if (!empty($ticket['attendant_id']) && $ticket['attendant_id'] != ($ticket['technical_responsible_id'] ?? null)) {
                $this->notifyReviewToUser($ticket, $ticket['attendant_id'], 'atendente');
            }
        }

        // Notificações via grupo de WhatsApp (usa a conexão do chat existente)
        $this->sendGroupStatusNotification($ticket, $status, $previousStatus);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'status' => $status]);
        }

        flash('success', 'Status atualizado com sucesso!');
        $this->redirect('tickets/show/' . $id);
    }

    // Atualizar prioridade do ticket
    public function updatePriority($id = null)
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $user = $this->currentUser();
        $fullUser = (new User())->findById($user['id']);

        // Permitir super_admin e donos de empresa
        $isCompanyOwner = ($user['role'] === 'client' && $fullUser && $fullUser['is_company_owner']);
        if ($user['role'] !== 'super_admin' && !$isCompanyOwner) {
            flash('error', 'Sem permissão para alterar prioridade.');
            $this->redirect('tickets/show/' . $id);
        }

        // Se é dono de empresa, verificar se o ticket pertence à empresa dele
        if ($isCompanyOwner) {
            $ticket = $this->ticketModel->findById($id);
            if (!$ticket) {
                flash('error', 'Demanda não encontrada.');
                $this->redirect('tickets');
            }
            $ticketOwner = (new User())->findById($ticket['client_id']);
            if (!$ticketOwner || $ticketOwner['company_id'] != $fullUser['company_id']) {
                flash('error', 'Sem permissão para alterar esta demanda.');
                $this->redirect('tickets');
            }
        }

        $priority = $_POST['priority'] ?? '';
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $validPriorities)) {
            flash('error', 'Prioridade inválida.');
            $this->redirect('tickets/show/' . $id);
        }

        $this->ticketModel->update($id, ['priority' => $priority]);

        flash('success', 'Prioridade atualizada com sucesso!');
        $this->redirect('tickets/show/' . $id);
    }

    // Atribuir atendente
    public function assign($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $attendantId = $_POST['attendant_id'] ?? null;
        if ($attendantId) {
            $this->ticketModel->assignAttendant($id, $attendantId);

            // Sincronizar card do planejamento
            $planningCard = new PlanningCard();
            $card = Database::getInstance()->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$id]);
            if ($card) {
                $planningCard->update($card['id'], ['assigned_to' => $attendantId]);
            }

            // Notificar atendente atribuído
            $this->notifyAssignment($id, (int)$attendantId, 'atendente');

            flash('success', 'Atendente atribuído com sucesso!');
        }

        $this->redirect('tickets/show/' . $id);
    }

    // Atribuir responsável técnico (segue hierarquia Papel > Usuários)
    public function assignTechnical($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $technicalId = $_POST['technical_responsible_id'] ?? null;
        $this->ticketModel->assignTechnical($id, $technicalId ? (int)$technicalId : null);

        // Sincronizar card do planejamento
        $card = Database::getInstance()->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$id]);
        if ($card) {
            (new PlanningCard())->update($card['id'], ['technical_responsible_id' => $technicalId ? (int)$technicalId : null]);
        }

        if ($technicalId) {
            $this->notifyAssignment($id, (int)$technicalId, 'responsável técnico');
            flash('success', 'Responsável técnico atribuído com sucesso!');
        } else {
            flash('success', 'Responsável técnico removido.');
        }

        $this->redirect('tickets/show/' . $id);
    }

    // Excluir permanentemente a demanda (apenas super_admin)
    public function deletePermanent($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            flash('error', 'Demanda não encontrada.');
            $this->redirect('tickets');
        }

        $db = Database::getInstance();

        // Remover card de planejamento vinculado (e seus comentários/anexos via cascade)
        $card = $db->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$id]);
        if ($card) {
            $db->delete('planning_cards', 'id = ?', [$card['id']]);
        }

        // Remover dependências do ticket e o próprio ticket
        $db->delete('ticket_messages', 'ticket_id = ?', [$id]);
        $db->delete('ticket_attachments', 'ticket_id = ?', [$id]);
        $db->query("DELETE FROM ticket_internal_notes WHERE ticket_id = ?", [$id]);
        $db->query("DELETE FROM notifications WHERE ticket_id = ?", [$id]);
        $db->delete('tickets', 'id = ?', [$id]);

        flash('success', 'Demanda excluída permanentemente.');
        $this->redirect('tickets');
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

        // Descobrir empresa do cliente
        $db = Database::getInstance();
        $clientUser = $db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
        $ticketCompanyId = $clientUser['company_id'] ?? null;

        // Notificar atendentes que têm acesso a essa empresa (ou super admins)
        $userModel = new User();
        $attendants = $userModel->getAttendants();

        foreach ($attendants as $att) {
            // Verificar se o atendente tem acesso à empresa do ticket
            $allowedCompanies = PlanningCard::getUserAllowedCompanies($att['id'], 'attendant');
            if ($allowedCompanies !== null && $ticketCompanyId) {
                // Tem restrição — checar se a empresa do ticket está na lista
                if (!in_array($ticketCompanyId, $allowedCompanies)) {
                    continue; // Pula este atendente
                }
            }

            $db->insert('notifications', [
                'user_id' => $att['id'],
                'ticket_id' => $ticketId,
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'type' => 'system',
            ]);
        }

        // Notificar super admins (sempre veem tudo)
        $admins = $db->fetchAll("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1");
        foreach ($admins as $admin) {
            $db->insert('notifications', [
                'user_id' => $admin['id'],
                'ticket_id' => $ticketId,
                'title' => $notificationTitle,
                'message' => $notificationMessage,
                'type' => 'system',
            ]);
        }

        // Webhook - dispara para cada telefone configurado
        $this->triggerWebhook($notificationMessage, '', $ticket);
    }

    private function sendStatusChangeNotification($ticket, $newStatus)
    {
        $statusLabels = [
            'open' => 'Aberto',
            'in_progress' => 'Em andamento',
            'em_revisao_interna' => 'Em Revisão Interna',
            'waiting_client' => 'Aguardando cliente',
            'em_homologacao' => 'Em Homologação',
            'aprovado_producao' => 'Aprovado para Produção',
            'completed' => 'Concluído',
            'denied' => 'Negado',
            'archived' => 'Arquivado',
        ];

        $statusColors = [
            'open' => '#3b82f6',
            'in_progress' => '#f59e0b',
            'em_revisao_interna' => '#5c6bc0',
            'waiting_client' => '#8b5cf6',
            'em_homologacao' => '#0097a7',
            'aprovado_producao' => '#8bc34a',
            'completed' => '#10b981',
            'denied' => '#ef4444',
            'archived' => '#6b7280',
        ];

        $label = $statusLabels[$newStatus] ?? $newStatus;
        $statusColor = $statusColors[$newStatus] ?? '#6b7280';
        $db = Database::getInstance();
        $userModel = new User();
        $currentUser = $this->currentUser();

        // Mensagem para o cliente
        $clientMessage = "Sua demanda \"{$ticket['title']}\" teve o status alterado para: {$label}";
        // Mensagem para atendentes/admins
        $internalMessage = "A demanda #{$ticket['id']} \"{$ticket['title']}\" foi alterada para: {$label}";

        // Lista de quem será notificado (evitar duplicatas)
        $notifiedIds = [];

        // Template de email bonito para mudança de status
        $ticketUrl = baseUrl('tickets/show/' . $ticket['id']);

        // 1. Notificar o cliente (criador da demanda)
        if ($ticket['client_id'] && $ticket['client_id'] != $currentUser['id']) {
            $db->insert('notifications', [
                'user_id' => $ticket['client_id'],
                'ticket_id' => $ticket['id'],
                'title' => 'Status atualizado',
                'message' => $clientMessage,
                'type' => 'system',
            ]);
            $notifiedIds[] = $ticket['client_id'];

            // Enviar email ao cliente/criador
            $client = $userModel->findById($ticket['client_id']);
            if ($client && $client['email']) {
                $emailBody = $this->buildStatusChangeEmailBody([
                    'recipient_name' => $client['name'],
                    'ticket_id' => $ticket['id'],
                    'ticket_title' => $ticket['title'],
                    'new_status' => $label,
                    'status_color' => $statusColor,
                    'changed_by' => $currentUser['name'],
                    'ticket_url' => $ticketUrl,
                ]);
                $htmlBody = Mailer::template('Status da Demanda Atualizado', $emailBody);
                Mailer::send($client['email'], "Demanda #{$ticket['id']} - Status atualizado para: {$label}", $htmlBody);
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
                $emailBody = $this->buildStatusChangeEmailBody([
                    'recipient_name' => $attendant['name'],
                    'ticket_id' => $ticket['id'],
                    'ticket_title' => $ticket['title'],
                    'new_status' => $label,
                    'status_color' => $statusColor,
                    'changed_by' => $currentUser['name'],
                    'ticket_url' => $ticketUrl,
                ]);
                $htmlBody = Mailer::template('Status da Demanda Atualizado', $emailBody);
                Mailer::send($attendant['email'], "Demanda #{$ticket['id']} - Status atualizado para: {$label}", $htmlBody);
            }
        }

        // 2b. Notificar o responsável técnico (se houver e não for quem fez a ação)
        if (!empty($ticket['technical_responsible_id']) && $ticket['technical_responsible_id'] != $currentUser['id'] && !in_array($ticket['technical_responsible_id'], $notifiedIds)) {
            $db->insert('notifications', [
                'user_id' => $ticket['technical_responsible_id'],
                'ticket_id' => $ticket['id'],
                'title' => 'Status atualizado',
                'message' => $internalMessage,
                'type' => 'system',
            ]);
            $notifiedIds[] = $ticket['technical_responsible_id'];

            $technical = $userModel->findById($ticket['technical_responsible_id']);
            if ($technical && $technical['email']) {
                $emailBody = $this->buildStatusChangeEmailBody([
                    'recipient_name' => $technical['name'],
                    'ticket_id' => $ticket['id'],
                    'ticket_title' => $ticket['title'],
                    'new_status' => $label,
                    'status_color' => $statusColor,
                    'changed_by' => $currentUser['name'],
                    'ticket_url' => $ticketUrl,
                ]);
                $htmlBody = Mailer::template('Status da Demanda Atualizado', $emailBody);
                Mailer::send($technical['email'], "Demanda #{$ticket['id']} - Status atualizado para: {$label}", $htmlBody);
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

                // Enviar email ao admin
                if ($admin['email']) {
                    $emailBody = $this->buildStatusChangeEmailBody([
                        'recipient_name' => $admin['name'],
                        'ticket_id' => $ticket['id'],
                        'ticket_title' => $ticket['title'],
                        'new_status' => $label,
                        'status_color' => $statusColor,
                        'changed_by' => $currentUser['name'],
                        'ticket_url' => $ticketUrl,
                    ]);
                    $htmlBody = Mailer::template('Status da Demanda Atualizado', $emailBody);
                    Mailer::send($admin['email'], "Demanda #{$ticket['id']} - Status atualizado para: {$label}", $htmlBody);
                }
            }
        }

        // 4. Webhook
        $this->triggerWebhook($clientMessage, '', $ticket);
    }

    /**
     * Monta o corpo HTML bonito do email de mudança de status
     */
    private function buildStatusChangeEmailBody($data)
    {
        $name = htmlspecialchars($data['recipient_name']);
        $ticketId = $data['ticket_id'];
        $title = htmlspecialchars($data['ticket_title']);
        $status = htmlspecialchars($data['new_status']);
        $color = $data['status_color'];
        $changedBy = htmlspecialchars($data['changed_by']);
        $url = $data['ticket_url'];

        return "
            <p>Olá, <strong>{$name}</strong>!</p>
            <p>O status da sua demanda foi atualizado:</p>

            <div style='background:#f8fafc;border-radius:8px;padding:16px 20px;margin:16px 0;border-left:4px solid {$color};'>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr>
                        <td style='padding:6px 0;color:#666;font-size:0.85rem;'>Demanda</td>
                        <td style='padding:6px 0;font-weight:600;color:#333;'>#{$ticketId} — {$title}</td>
                    </tr>
                    <tr>
                        <td style='padding:6px 0;color:#666;font-size:0.85rem;'>Novo Status</td>
                        <td style='padding:6px 0;'>
                            <span style='display:inline-block;background:{$color};color:#fff;padding:3px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;'>{$status}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:6px 0;color:#666;font-size:0.85rem;'>Alterado por</td>
                        <td style='padding:6px 0;color:#333;'>{$changedBy}</td>
                    </tr>
                    <tr>
                        <td style='padding:6px 0;color:#666;font-size:0.85rem;'>Data</td>
                        <td style='padding:6px 0;color:#333;'>" . date('d/m/Y H:i') . "</td>
                    </tr>
                </table>
            </div>

            <p style='margin-top:20px;'>
                <a href='{$url}' style='display:inline-block;background:#00BFA6;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.9rem;'>
                    Ver Demanda
                </a>
            </p>

            <p style='color:#888;font-size:0.8rem;margin-top:20px;'>
                Você recebeu este email porque está vinculado à demanda #{$ticketId} no sistema de helpdesk.
            </p>
        ";
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
        }

        // Disparar webhook/WhatsApp para a equipe quando o CLIENTE enviar mensagem
        // Usa os telefones configurados no sistema (webhook_phones)
        if ($sender['role'] === 'client') {
            $this->triggerWebhook(
                "📩 Nova mensagem de {$sender['name']} no ticket #{$ticket['id']} ({$ticket['title']}): " . mb_substr($messageText, 0, 100),
                '',
                $ticket
            );
        }
    }

    private function triggerWebhook($message, $phone = '', $ticketData = [])
    {
        $webhookEnabled = Config::get('webhook_enabled');

        if (!$webhookEnabled) {
            return;
        }

        $webhookUrl = Config::get('webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        // Buscar telefones e nomes configurados
        $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: $phone;
        $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';
        $template = Config::get('webhook_message_template') ?: '';

        $phones = array_map('trim', explode(',', $phonesRaw));
        $names = array_map('trim', explode(',', $namesRaw));

        // Montar a mensagem pré-formatada
        $formattedMessage = $message;
        if (!empty($template) && !empty($ticketData)) {
            $formattedMessage = str_replace(
                ['{ticket_id}', '{ticket_title}', '{client_name}', '{priority}', '{category}', '{date}', '{message}'],
                [
                    $ticketData['id'] ?? '',
                    $ticketData['title'] ?? '',
                    $ticketData['client_name'] ?? '',
                    $this->priorityLabelText($ticketData['priority'] ?? 'medium'),
                    $ticketData['category'] ?? 'Não definida',
                    date('d/m/Y H:i'),
                    $message,
                ],
                $template
            );
        }

        // Enviar diretamente via cURL para cada telefone (sem depender do cron)
        foreach ($phones as $index => $phoneNumber) {
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (empty($phoneNumber)) continue;

            $recipientName = $names[$index] ?? ($names[0] ?? 'Admin');
            $finalMessage = str_replace('{name}', $recipientName, $formattedMessage);

            $payload = json_encode([
                'phone' => $phoneNumber,
                'name' => $recipientName,
                'message' => $finalMessage,
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

    private function priorityLabelText($priority)
    {
        $labels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
        return $labels[$priority] ?? $priority;
    }

    /**
     * Notifica um usuário atribuído a uma demanda: notificação no sistema, email e WhatsApp.
     */
    private function notifyAssignment($ticketId, $userId, $roleLabel)
    {
        if (!$userId) return;

        $db = Database::getInstance();
        $ticket = $this->ticketModel->findById($ticketId);
        $assignee = (new User())->findById($userId);
        if (!$assignee) return;

        $title = "Você foi atribuído como {$roleLabel}";
        $message = "Demanda #{$ticket['id']} \"{$ticket['title']}\" foi atribuída a você como {$roleLabel}.";

        // Notificação no sistema
        $db->insert('notifications', [
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'title' => $title,
            'message' => $message,
            'type' => 'system',
        ]);

        // Email
        if (!empty($assignee['email'])) {
            $ticketUrl = baseUrl('tickets/show/' . $ticket['id']);
            $body = "
                <p>Olá, <strong>" . htmlspecialchars($assignee['name']) . "</strong>!</p>
                <p>Você foi atribuído como <strong>{$roleLabel}</strong> na demanda abaixo:</p>
                <div style='background:#f8fafc;border-radius:8px;padding:16px 20px;margin:16px 0;border-left:4px solid #00BFA6;'>
                    <p style='margin:4px 0;'><strong>#{$ticket['id']}</strong> — " . htmlspecialchars($ticket['title']) . "</p>
                    <p style='margin:4px 0;color:#666;font-size:0.85rem;'>Cliente: " . htmlspecialchars($ticket['client_name'] ?? '-') . "</p>
                </div>
                <p style='margin-top:20px;'>
                    <a href='{$ticketUrl}' style='display:inline-block;background:#00BFA6;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.9rem;'>Ver Demanda</a>
                </p>";
            $htmlBody = Mailer::template('Nova atribuição de demanda', $body);
            Mailer::send($assignee['email'], "Demanda #{$ticket['id']} atribuída a você", $htmlBody);
        }

        // WhatsApp (via Evolution API, se o usuário tiver telefone)
        $priorityText = priorityLabel($ticket['priority'] ?? 'medium');
        $priorityEmoji = match($ticket['priority'] ?? 'medium') {
            'urgent' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };

        // Buscar empresa do cliente
        $clientUser = $db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
        $companyName = '';
        if (!empty($clientUser['company_id'])) {
            $comp = $db->fetch("SELECT name FROM companies WHERE id = ?", [$clientUser['company_id']]);
            $companyName = $comp['name'] ?? '';
        }

        $whatsMsg = "📋 *Nova Atribuição de Demanda*\n\n"
            . "Você foi atribuído como *{$roleLabel}*:\n"
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "*#{$ticket['id']}* — {$ticket['title']}\n"
            . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
            . "👤 *Cliente:* " . ($ticket['client_name'] ?? '-') . "\n"
            . ($companyName ? "🏢 *Empresa:* {$companyName}\n" : '')
            . "👨‍💻 *Atendente:* " . ($ticket['attendant_name'] ?? 'Não atribuído') . "\n"
            . ($ticket['technical_name'] ?? '' ? "🔧 *Técnico:* {$ticket['technical_name']}\n" : '')
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "Acesse o sistema para ver os detalhes.";

        $this->sendWhatsappToUser($assignee, $whatsMsg);
    }

    /**
     * Envia notificações de mudança de status para grupos de WhatsApp:
     * - Sempre para o grupo padrão (empresa dona do helpdesk), se habilitado nas configurações.
     * - Quando a demanda vai para Homologação, também para o grupo da empresa do cliente.
     * Usa a conexão de chat WhatsApp existente (WhatsappNotifier), sem alterar a Evolution API.
     */
    private function sendGroupStatusNotification($ticket, $status, $previousStatus = null)
    {
        $label = statusLabel($status);
        $prevLabel = $previousStatus ? statusLabel($previousStatus) : null;
        $priorityText = priorityLabel($ticket['priority'] ?? 'medium');
        $priorityEmoji = match($ticket['priority'] ?? 'medium') {
            'urgent' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };

        // Buscar dados adicionais
        $db = Database::getInstance();
        $clientUser = $db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
        $companyId = $clientUser['company_id'] ?? null;
        $companyName = '';
        $company = null;
        if ($companyId) {
            $company = $db->fetch("SELECT name, whatsapp_group_jid FROM companies WHERE id = ?", [$companyId]);
            $companyName = $company['name'] ?? '';
        }

        // Buscar prazo do card de planejamento vinculado (se houver)
        $dueDate = '';
        $planningCard = $db->fetch("SELECT due_date FROM planning_cards WHERE ticket_id = ?", [$ticket['id']]);
        if ($planningCard && !empty($planningCard['due_date'])) {
            $dueDate = date('d/m/Y H:i', strtotime($planningCard['due_date']));
        }

        // Montar mensagem completa
        $baseMsg = "🔔 *Atualização de Demanda*\n\n"
            . "*#{$ticket['id']}* — {$ticket['title']}\n"
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
            . "👤 *Cliente:* " . ($ticket['client_name'] ?? '-') . "\n"
            . ($companyName ? "🏢 *Empresa:* {$companyName}\n" : '')
            . "👨‍💻 *Atendente:* " . ($ticket['attendant_name'] ?? 'Não atribuído') . "\n"
            . ($ticket['technical_name'] ?? '' ? "🔧 *Técnico:* {$ticket['technical_name']}\n" : '')
            . "━━━━━━━━━━━━━━━━━━━\n"
            . ($prevLabel ? "📌 *Status anterior:* {$prevLabel}\n" : '')
            . "📌 *Novo status:* {$label}\n"
            . ($dueDate ? "⏰ *Prazo:* {$dueDate}\n" : '');

        // 1. Grupo padrão — todas as atualizações de status
        WhatsappNotifier::sendToDefaultGroup($baseMsg);

        // 2. Grupo da empresa do cliente — destaque quando vai para Homologação
        if ($company && !empty($company['whatsapp_group_jid'])) {
            if ($status === 'em_homologacao') {
                $msg = "✅ *Demanda em Homologação*\n\n"
                    . "*#{$ticket['id']}* — {$ticket['title']}\n"
                    . "━━━━━━━━━━━━━━━━━━━\n"
                    . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
                    . "👤 *Cliente:* " . ($ticket['client_name'] ?? '-') . "\n"
                    . "🏢 *Empresa:* {$companyName}\n"
                    . "👨‍💻 *Atendente:* " . ($ticket['attendant_name'] ?? 'Não atribuído') . "\n"
                    . "━━━━━━━━━━━━━━━━━━━\n"
                    . "A demanda está pronta para homologação.\n"
                    . "Por favor, validem e retornem com o parecer. 🙏";
                WhatsappNotifier::sendToGroup($company['whatsapp_group_jid'], $msg);
            } else {
                // Demais atualizações também no grupo da empresa
                WhatsappNotifier::sendToGroup($company['whatsapp_group_jid'], $baseMsg);
            }
        }
    }

    /**
     * Notifica o responsável técnico quando a demanda entra em Revisão Interna.
     */
    private function notifyTechnicalReview($ticket)
    {
        $this->notifyReviewToUser($ticket, $ticket['technical_responsible_id'], 'responsável técnico');
    }

    /**
     * Notifica um usuário (responsável técnico ou atendente) quando a demanda entra em Revisão Interna.
     * Envia notificação no sistema, email e WhatsApp.
     */
    private function notifyReviewToUser($ticket, $userId, $roleLabel)
    {
        if (empty($userId)) return;

        $db = Database::getInstance();
        $recipient = (new User())->findById($userId);
        if (!$recipient) return;

        $title = 'Demanda em Revisão Interna';
        $message = "A demanda #{$ticket['id']} \"{$ticket['title']}\" passou para Revisão Interna e requer sua atenção como {$roleLabel}.";

        $db->insert('notifications', [
            'user_id' => $recipient['id'],
            'ticket_id' => $ticket['id'],
            'title' => $title,
            'message' => $message,
            'type' => 'system',
        ]);

        if (!empty($recipient['email'])) {
            $ticketUrl = baseUrl('tickets/show/' . $ticket['id']);
            $body = "
                <p>Olá, <strong>" . htmlspecialchars($recipient['name']) . "</strong>!</p>
                <p>A demanda abaixo entrou em <strong>Revisão Interna</strong> e precisa da sua atenção como {$roleLabel}:</p>
                <div style='background:#f8fafc;border-radius:8px;padding:16px 20px;margin:16px 0;border-left:4px solid #5c6bc0;'>
                    <p style='margin:4px 0;'><strong>#{$ticket['id']}</strong> — " . htmlspecialchars($ticket['title']) . "</p>
                </div>
                <p style='margin-top:20px;'>
                    <a href='{$ticketUrl}' style='display:inline-block;background:#5c6bc0;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.9rem;'>Ver Demanda</a>
                </p>";
            $htmlBody = Mailer::template('Demanda em Revisão Interna', $body);
            Mailer::send($recipient['email'], "Demanda #{$ticket['id']} em Revisão Interna", $htmlBody);
        }

        $this->sendWhatsappToUser($recipient, $this->buildReviewWhatsappMsg($ticket, $roleLabel));
    }

    /**
     * Monta mensagem WhatsApp enriquecida para notificação de Revisão Interna.
     */
    private function buildReviewWhatsappMsg($ticket, $roleLabel)
    {
        $priorityText = priorityLabel($ticket['priority'] ?? 'medium');
        $priorityEmoji = match($ticket['priority'] ?? 'medium') {
            'urgent' => '🔴',
            'high' => '🟠',
            'medium' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };

        $db = Database::getInstance();
        $clientUser = $db->fetch("SELECT company_id FROM users WHERE id = ?", [$ticket['client_id']]);
        $companyName = '';
        if (!empty($clientUser['company_id'])) {
            $comp = $db->fetch("SELECT name FROM companies WHERE id = ?", [$clientUser['company_id']]);
            $companyName = $comp['name'] ?? '';
        }

        return "🔎 *Demanda em Revisão Interna*\n\n"
            . "Requer sua atenção como *{$roleLabel}*:\n"
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "*#{$ticket['id']}* — {$ticket['title']}\n"
            . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
            . "👤 *Cliente:* " . ($ticket['client_name'] ?? '-') . "\n"
            . ($companyName ? "🏢 *Empresa:* {$companyName}\n" : '')
            . "👨‍💻 *Atendente:* " . ($ticket['attendant_name'] ?? 'Não atribuído') . "\n"
            . ($ticket['technical_name'] ?? '' ? "🔧 *Técnico:* {$ticket['technical_name']}\n" : '')
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "📌 *Status:* Em Revisão Interna\n"
            . "Acesse o sistema para revisar.";
    }

    /**
     * Envia mensagem WhatsApp para um usuário via Evolution API (se configurado e com telefone).
     */
    private function sendWhatsappToUser($user, $message)
    {
        if (empty($user['phone'])) return;

        try {
            // Envia e registra no histórico do chat (aparece na janela do chat)
            WhatsappNotifier::sendToPhone($user['phone'], $message, $user['name'] ?? null);
        } catch (Exception $e) {
            // Silencioso — WhatsApp é canal complementar
        }
    }

    // Observações internas (apenas equipe)
    public function addNote($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $note = trim($_POST['note'] ?? '');

        if (empty($note)) {
            $this->json(['error' => 'Observação vazia'], 400);
        }

        $db = Database::getInstance();
        $noteId = $db->insert('ticket_internal_notes', [
            'ticket_id' => $id,
            'user_id' => $user['id'],
            'note' => $note,
        ]);

        $this->json([
            'success' => true,
            'note' => [
                'id' => $noteId,
                'user_name' => $user['name'],
                'note' => escape($note),
                'created_at' => date('d/m/Y H:i'),
            ]
        ]);
    }

    // Buscar observações internas
    public function getNotes($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if (!$id) $this->json(['error' => 'ID inválido'], 400);

        $db = Database::getInstance();
        $notes = $db->fetchAll(
            "SELECT n.*, u.name as user_name
             FROM ticket_internal_notes n
             LEFT JOIN users u ON n.user_id = u.id
             WHERE n.ticket_id = ?
             ORDER BY n.created_at ASC",
            [$id]
        );

        $this->json(['notes' => $notes]);
    }
}
