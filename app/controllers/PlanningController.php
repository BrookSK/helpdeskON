<?php

class PlanningController extends Controller
{
    private $cardModel;

    public function __construct()
    {
        $this->cardModel = new PlanningCard();
    }

    // Página principal — Kanban + Calendário
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();

        // Cliente é redirecionado para a view de demandas em lista
        if ($user['role'] === 'client') {
            $this->redirect('planning/clientDemands');
            return;
        }

        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);

        $filters = [];
        if (!empty($_GET['company_id'])) $filters['company_id'] = $_GET['company_id'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['order'])) $filters['order'] = $_GET['order'];

        // whatsapp_agent, developer e analyst só veem cards atribuídos a eles (forçar filtro)
        if (in_array($user['role'], ['whatsapp_agent', 'developer', 'analyst', 'comercial'])) {
            $filters['assigned_to'] = $user['id'];
        }

        // Para super_admin e attendant: pré-filtrar pelo usuário logado por padrão
        // a menos que o usuário explicitamente escolha "Todos" (via parâmetro show_all=1)
        if (in_array($user['role'], ['super_admin', 'attendant'])) {
            if (empty($_GET['show_all']) && empty($_GET['assigned_to'])) {
                $filters['assigned_to'] = $user['id'];
            }
        }

        // Controle de acesso por empresa
        $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
        if ($allowedCompanies !== null) {
            $filters['allowed_companies'] = $allowedCompanies;
        }

        $grouped = $this->cardModel->getGroupedByStatus($filters);

        $companyModel = new Company();
        // Atendente/agent só vê empresas que tem acesso
        if ($allowedCompanies !== null && !in_array(0, $allowedCompanies)) {
            $allCompanies = $companyModel->getAll();
            $companies = array_filter($allCompanies, fn($c) => in_array($c['id'], $allowedCompanies));
        } else {
            $companies = $companyModel->getAll();
        }

        $userModel = new User();
        $team = $userModel->getAttendants();

        // whatsapp_agent/developer/analyst só veem a si mesmos na lista de responsáveis
        if (in_array($user['role'], ['whatsapp_agent', 'developer', 'analyst', 'comercial'])) {
            $teamMembers = [['id' => $user['id'], 'name' => $user['name']]];
        } else {
            $db = Database::getInstance();
            $admins = $db->fetchAll("SELECT id, name FROM users WHERE role = 'super_admin' AND is_active = 1");
            $teamMembers = array_merge($admins, $team);
        }

        // Listas específicas por papel para os seletores do card
        $attendantsList = $userModel->getByRoles(['super_admin', 'attendant', 'whatsapp_agent', 'analyst']);
        $techniciansList = $userModel->getByRoles(['developer']);
        $analystsList = $userModel->getByRoles(['analyst']);

        $this->view('planning/index', [
            'user' => $user,
            'grouped' => $grouped,
            'companies' => $companies,
            'teamMembers' => $teamMembers,
            'attendantsList' => $attendantsList,
            'techniciansList' => $techniciansList,
            'analystsList' => $analystsList,
            'filters' => $filters,
        ]);
    }

    // API: Retornar cards para o calendário (JSON)
    public function calendar()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        $user = $this->currentUser();

        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t 23:59:59');

        $filters = [];
        if (!empty($_GET['company_id'])) $filters['company_id'] = $_GET['company_id'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['hide_completed'])) $filters['hide_completed'] = true;

        // Controle de acesso por empresa
        $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
        if ($allowedCompanies !== null) {
            $filters['allowed_companies'] = $allowedCompanies;
        }

        $cards = $this->cardModel->getForCalendar($start, $end, $filters);

        $events = [];
        foreach ($cards as $card) {
            $events[] = [
                'id' => $card['id'],
                'title' => $card['title'],
                'start_date' => $card['start_date'],
                'end_date' => $card['end_date'],
                'due_date' => $card['due_date'],
                'priority' => $card['priority'],
                'status' => $card['status'],
                'assigned_name' => $card['assigned_name'] ?? 'Não atribuído',
                'company_name' => $card['company_name'] ?? '',
            ];
        }

        $this->json($events);
    }

    // Criar card
    public function create()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('planning');
        }

        $user = $this->currentUser();

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Título obrigatório'], 400);
            }
            flash('error', 'Título obrigatório.');
            $this->redirect('planning');
        }

        $data = [
            'title' => $title,
            'description' => $_POST['description'] ?? '',
            'company_id' => !empty($_POST['company_id']) ? $_POST['company_id'] : null,
            'assigned_to' => !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'technical_responsible_id' => !empty($_POST['technical_responsible_id']) ? $_POST['technical_responsible_id'] : null,
            'analyst_id' => !empty($_POST['analyst_id']) ? $_POST['analyst_id'] : null,
            'created_by' => $user['id'],
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => $_POST['status'] ?? 'open',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'position' => 0,
        ];

        $cardId = $this->cardModel->create($data);

        // Notificar responsável se atribuído
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notifyAssignment($cardId, $data['assigned_to'], $user, $title);
        }

        // Webhook WhatsApp para qualquer card criado (mesmo sem atribuição)
        if (!($data['assigned_to'] && $data['assigned_to'] != $user['id'])) {
            $this->triggerCardWebhook($cardId, $user, $title);
        }

        if ($this->isAjax()) {
            $card = $this->cardModel->findById($cardId);
            $this->json(['success' => true, 'card' => $card]);
        }

        flash('success', 'Card criado com sucesso!');
        $this->redirect('planning');
    }

    // Obter card (JSON)
    public function get($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $comments = $this->cardModel->getComments($id);
        $attachments = $this->cardModel->getAttachments($id);

        $response = [
            'card' => $card,
            'comments' => $comments,
            'attachments' => $attachments,
            'tasks' => $this->cardModel->getTasks($id),
        ];

        // Se o card está vinculado a uma demanda, buscar dados da demanda
        if (!empty($card['ticket_id'])) {
            $ticketModel = new Ticket();
            $ticket = $ticketModel->findById($card['ticket_id']);
            $response['ticket'] = $ticket;

            // Mensagens da demanda (conversação com o cliente)
            $ticketMessageModel = new TicketMessage();
            $response['ticket_messages'] = $ticketMessageModel->getByTicket($card['ticket_id']);

            // Anexos da demanda (arquivos que o cliente/atendente enviou)
            $ticketAttachmentModel = new TicketAttachment();
            $response['ticket_attachments'] = $ticketAttachmentModel->getByTicket($card['ticket_id']);

            // Notas internas da demanda
            $db = Database::getInstance();
            $response['ticket_internal_notes'] = $db->fetchAll(
                "SELECT n.*, u.name as user_name
                 FROM ticket_internal_notes n
                 LEFT JOIN users u ON n.user_id = u.id
                 WHERE n.ticket_id = ?
                 ORDER BY n.created_at ASC",
                [$card['ticket_id']]
            );
        }

        $this->json($response);
    }

    // Atualizar card
    public function update($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $user = $this->currentUser();

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        // Descrição pode vir como arquivo (para contornar limite do ModSecurity)
        if (!empty($_FILES['description_file']['tmp_name']) && $_FILES['description_file']['error'] === UPLOAD_ERR_OK) {
            $data['description'] = file_get_contents($_FILES['description_file']['tmp_name']);
        } elseif (isset($_POST['description'])) {
            $data['description'] = $_POST['description'];
        }
        if (isset($_POST['company_id'])) $data['company_id'] = $_POST['company_id'] ?: null;
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['technical_responsible_id'])) $data['technical_responsible_id'] = $_POST['technical_responsible_id'] ?: null;
        if (isset($_POST['analyst_id'])) $data['analyst_id'] = $_POST['analyst_id'] ?: null;
        if (isset($_POST['priority'])) $data['priority'] = $_POST['priority'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['due_date'])) $data['due_date'] = $_POST['due_date'] ?: null;
        if (isset($_POST['start_date'])) $data['start_date'] = $_POST['start_date'] ?: null;
        if (isset($_POST['end_date'])) $data['end_date'] = $_POST['end_date'] ?: null;
        // Campos CX Hub
        if (isset($_POST['cx_hub_number'])) $data['cx_hub_number'] = trim($_POST['cx_hub_number']) ?: null;
        if (isset($_POST['cx_hub_name'])) $data['cx_hub_name'] = trim($_POST['cx_hub_name']) ?: null;
        if (isset($_POST['branch_name'])) $data['branch_name'] = trim($_POST['branch_name']) ?: null;
        if (isset($_POST['pr_number'])) $data['pr_number'] = trim($_POST['pr_number']) ?: null;

        if (empty($data)) {
            $this->json(['error' => 'Nenhum campo para atualizar'], 400);
        }

        $this->cardModel->update($id, $data);

        // Notificar responsável se mudou
        if (isset($data['assigned_to']) && $data['assigned_to'] != $card['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notifyAssignment($id, $data['assigned_to'], $user, $card['title']);
        }

        // Se mudou status e card está vinculado a ticket, sincronizar
        if (isset($data['status']) && $card['ticket_id']) {
            $ticketModel = new Ticket();
            $ticketModel->updateStatus($card['ticket_id'], $data['status']);
        }

        $updatedCard = $this->cardModel->findById($id);
        $this->json(['success' => true, 'card' => $updatedCard]);
    }

    // Atualizar status via drag-and-drop (Kanban)
    public function updateStatus($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $status = $_POST['status'] ?? '';
        $position = intval($_POST['position'] ?? 0);

        $validStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        if (!in_array($status, $validStatuses)) {
            $this->json(['error' => 'Status inválido'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $this->cardModel->updatePosition($id, $position, $status);

        // Sincronizar ticket vinculado e enviar notificação de status
        if ($card['ticket_id']) {
            $ticketModel = new Ticket();
            $previousStatus = $card['status'];
            $ticketModel->updateStatus($card['ticket_id'], $status);

            // Enviar notificação WhatsApp de mudança de status (mesmo formato do TicketsController)
            if ($previousStatus !== $status) {
                $ticket = $ticketModel->findById($card['ticket_id']);
                if ($ticket) {
                    $this->sendPlanningStatusNotification($ticket, $status, $previousStatus);
                }
            }
        }

        $this->json(['success' => true]);
    }

    // Reordenar cards de uma coluna (persiste a ordem completa)
    public function reorder()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $status = $_POST['status'] ?? '';
        $idsRaw = $_POST['card_ids'] ?? '';

        $validStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'aprovado_producao', 'completed', 'denied', 'archived'];
        if (!in_array($status, $validStatuses)) {
            $this->json(['error' => 'Status inválido'], 400);
        }

        // card_ids vem como string separada por vírgula: "12,5,8,3"
        $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
        if (empty($ids)) {
            $this->json(['error' => 'Lista de cards vazia'], 400);
        }

        $this->cardModel->reorderCards($ids, $status);
        $this->json(['success' => true]);
    }

    // Deletar card
    public function delete($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $this->cardModel->delete($id);
        $this->json(['success' => true]);
    }

    // Excluir permanentemente o card e a demanda vinculada (apenas super_admin)
    public function deletePermanent($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $ticketId = $card['ticket_id'] ?? null;

        // Remove o card (e seus comentários/anexos via cascade) e a demanda vinculada
        $this->cardModel->deletePermanent($id, $ticketId);

        $this->json(['success' => true]);
    }

    // Adicionar comentário
    public function comment($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            $this->json(['error' => 'Mensagem vazia'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $commentId = $this->cardModel->addComment($id, $user['id'], $message);

        // Notificar responsável do card (se não for quem comentou)
        if ($card['assigned_to'] && $card['assigned_to'] != $user['id']) {
            $db = Database::getInstance();
            $db->insert('notifications', [
                'user_id' => $card['assigned_to'],
                'title' => 'Novo comentário no card',
                'message' => "{$user['name']} comentou no card \"{$card['title']}\"",
                'type' => 'system',
            ]);
        }

        // Notificar criador do card (se diferente do responsável e de quem comentou)
        if ($card['created_by'] && $card['created_by'] != $user['id'] && $card['created_by'] != $card['assigned_to']) {
            $db = Database::getInstance();
            $db->insert('notifications', [
                'user_id' => $card['created_by'],
                'title' => 'Novo comentário no card',
                'message' => "{$user['name']} comentou no card \"{$card['title']}\"",
                'type' => 'system',
            ]);
        }

        $this->json([
            'success' => true,
            'comment' => [
                'id' => $commentId,
                'user_name' => $user['name'],
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    // Upload de anexo no card
    public function upload($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhum arquivo enviado'], 400);
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $this->json(['error' => 'Arquivo muito grande (máx 10MB)'], 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/uploads/planning';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = 'uploads/planning/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar arquivo'], 500);
        }

        $attachmentId = $this->cardModel->addAttachment([
            'card_id' => $id,
            'user_id' => $user['id'],
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
        ]);

        $this->json([
            'success' => true,
            'attachment' => [
                'id' => $attachmentId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
            ],
        ]);
    }

    // Deletar anexo
    public function deleteAttachment($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $attachment = $this->cardModel->findAttachment($id);
        if (!$attachment) $this->json(['error' => 'Anexo não encontrado'], 404);

        // Deletar arquivo físico
        $fullPath = PUBLIC_PATH . '/' . $attachment['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $this->cardModel->deleteAttachment($id);
        $this->json(['success' => true]);
    }

    // Upload de imagem colada no editor (Quill)
    public function uploadImage($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhuma imagem enviada'], 400);
        }

        $file = $_FILES['image'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $this->json(['error' => 'Imagem muito grande (máx 10MB)'], 400);
        }

        // Validar tipo
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->json(['error' => 'Tipo de imagem não permitido'], 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $fileName = 'img_' . uniqid() . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/uploads/planning';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = 'uploads/planning/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar imagem'], 500);
        }

        $this->json([
            'success' => true,
            'url' => baseUrl($filePath),
        ]);
    }

    // ========================
    // TASKS INTERNAS DO CARD
    // ========================

    // Listar tasks de um card (JSON)
    public function tasks($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if (!$cardId) $this->json(['error' => 'ID do card não informado'], 400);

        $card = $this->cardModel->findById($cardId);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $tasks = $this->cardModel->getTasks($cardId);
        $this->json(['success' => true, 'tasks' => $tasks]);
    }

    // Criar task no card
    public function createTask($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $card = $this->cardModel->findById($cardId);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            $this->json(['error' => 'Título da task obrigatório'], 400);
        }

        $taskId = $this->cardModel->createTask([
            'card_id' => $cardId,
            'title' => $title,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'created_by' => $user['id'],
            'position' => intval($_POST['position'] ?? 0),
        ]);

        // Notificar responsável do card sobre nova task (se diferente de quem criou)
        if ($card['assigned_to'] && $card['assigned_to'] != $user['id']) {
            $db = Database::getInstance();

            // Notificação no sistema
            $db->insert('notifications', [
                'user_id' => $card['assigned_to'],
                'title' => 'Nova task no card',
                'message' => "{$user['name']} criou a task \"{$title}\" no card \"{$card['title']}\"",
                'type' => 'system',
            ]);

            // Notificação via WhatsApp para o responsável
            $userModel = new User();
            $assignedUser = $userModel->findById($card['assigned_to']);
            if ($assignedUser && !empty($assignedUser['phone'])) {
                $whatsMessage = "📋 *Nova Task no Card*\n\n"
                    . "*Card:* #{$card['id']} — {$card['title']}\n"
                    . "*Task:* {$title}\n"
                    . ($task['description'] ?? '' ? "*Descrição:* " . trim($_POST['description'] ?? '') . "\n" : '')
                    . "*Criada por:* {$user['name']}\n\n"
                    . "Acesse o planejamento para ver os detalhes.";

                WhatsappNotifier::sendToPhone(
                    $assignedUser['phone'],
                    $whatsMessage,
                    $assignedUser['name']
                );
            }
        }

        // Notificar também o técnico, se diferente do assigned_to e de quem criou
        if (!empty($card['technical_responsible_id'])
            && $card['technical_responsible_id'] != $user['id']
            && $card['technical_responsible_id'] != ($card['assigned_to'] ?? null)
        ) {
            $db = Database::getInstance();

            $db->insert('notifications', [
                'user_id' => $card['technical_responsible_id'],
                'title' => 'Nova task no card',
                'message' => "{$user['name']} criou a task \"{$title}\" no card \"{$card['title']}\"",
                'type' => 'system',
            ]);

            if (!isset($userModel)) $userModel = new User();
            $techUser = $userModel->findById($card['technical_responsible_id']);
            if ($techUser && !empty($techUser['phone'])) {
                $whatsMessage = "📋 *Nova Task no Card*\n\n"
                    . "*Card:* #{$card['id']} — {$card['title']}\n"
                    . "*Task:* {$title}\n"
                    . ($task['description'] ?? '' ? "*Descrição:* " . trim($_POST['description'] ?? '') . "\n" : '')
                    . "*Criada por:* {$user['name']}\n\n"
                    . "Acesse o planejamento para ver os detalhes.";

                WhatsappNotifier::sendToPhone(
                    $techUser['phone'],
                    $whatsMessage,
                    $techUser['name']
                );
            }
        }

        $task = $this->cardModel->findTask($taskId);
        $task['images'] = [];
        $task['created_by_name'] = $user['name'];

        $this->json(['success' => true, 'task' => $task]);
    }

    // Atualizar task (título/descrição)
    public function updateTask($taskId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$taskId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $task = $this->cardModel->findTask($taskId);
        if (!$task) $this->json(['error' => 'Task não encontrada'], 404);

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = trim($_POST['description']) ?: null;
        if (isset($_POST['position'])) $data['position'] = intval($_POST['position']);

        if (!empty($data)) {
            $this->cardModel->updateTask($taskId, $data);
        }

        $this->json(['success' => true]);
    }

    // Toggle completar/descompletar task
    public function toggleTask($taskId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$taskId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $task = $this->cardModel->findTask($taskId);
        if (!$task) $this->json(['error' => 'Task não encontrada'], 404);

        $this->cardModel->toggleTaskComplete($taskId, $user['id']);

        // Recarregar task atualizada
        $updatedTask = $this->cardModel->findTask($taskId);

        $this->json([
            'success' => true,
            'is_completed' => (bool)$updatedTask['is_completed'],
            'completed_by' => $user['name'],
        ]);
    }

    // Deletar task
    public function deleteTask($taskId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$taskId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $task = $this->cardModel->findTask($taskId);
        if (!$task) $this->json(['error' => 'Task não encontrada'], 404);

        // Deletar imagens físicas da task
        $images = $this->cardModel->getTaskImages($taskId);
        foreach ($images as $img) {
            $fullPath = PUBLIC_PATH . '/' . $img['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $this->cardModel->deleteTask($taskId);
        $this->json(['success' => true]);
    }

    // Upload de imagem na task
    public function uploadTaskImage($taskId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$taskId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $task = $this->cardModel->findTask($taskId);
        if (!$task) $this->json(['error' => 'Task não encontrada'], 404);

        if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhuma imagem enviada'], 400);
        }

        $file = $_FILES['image'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $this->json(['error' => 'Imagem muito grande (máx 10MB)'], 400);
        }

        // Validar tipo (imagens)
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->json(['error' => 'Tipo de arquivo não permitido. Apenas imagens.'], 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $fileName = 'task_' . uniqid() . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/uploads/planning/tasks';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = 'uploads/planning/tasks/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar imagem'], 500);
        }

        $imageId = $this->cardModel->addTaskImage([
            'task_id' => $taskId,
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'user_id' => $user['id'],
        ]);

        $this->json([
            'success' => true,
            'image' => [
                'id' => $imageId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
                'user_name' => $user['name'],
            ],
        ]);
    }

    // Deletar imagem de task
    public function deleteTaskImage($imageId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$imageId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $image = $this->cardModel->findTaskImage($imageId);
        if (!$image) $this->json(['error' => 'Imagem não encontrada'], 404);

        // Deletar arquivo físico
        $fullPath = PUBLIC_PATH . '/' . $image['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $this->cardModel->deleteTaskImage($imageId);
        $this->json(['success' => true]);
    }

    // PR Feito: salva PR, move card para em_revisao_interna e notifica analista
    public function prDone($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $user = $this->currentUser();
        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $prNumber = trim($_POST['pr_number'] ?? '');
        if (empty($prNumber)) {
            $this->json(['error' => 'Número do PR obrigatório'], 400);
        }

        // Atualizar campos CX Hub e PR
        $data = [
            'pr_number' => $prNumber,
            'status' => 'em_revisao_interna',
        ];
        if (!empty($_POST['cx_hub_number'])) $data['cx_hub_number'] = trim($_POST['cx_hub_number']);
        if (!empty($_POST['cx_hub_name'])) $data['cx_hub_name'] = trim($_POST['cx_hub_name']);
        if (!empty($_POST['branch_name'])) $data['branch_name'] = trim($_POST['branch_name']);

        $previousStatus = $card['status'];
        $this->cardModel->update($id, $data);

        // Sincronizar ticket vinculado
        if ($card['ticket_id']) {
            $ticketModel = new Ticket();
            $ticketModel->updateStatus($card['ticket_id'], 'em_revisao_interna');
        }

        // Recarregar card com dados atualizados
        $card = $this->cardModel->findById($id);

        // Notificar analista (se atribuído ao card)
        $db = Database::getInstance();
        $notifiedUsers = [];

        if (!empty($card['analyst_id']) && $card['analyst_id'] != $user['id']) {
            $this->notifyPrDone($card, $user, $card['analyst_id'], 'analista');
            $notifiedUsers[] = $card['analyst_id'];
        }

        // Notificar também o técnico se diferente do analista e de quem fez o PR
        if (!empty($card['technical_responsible_id'])
            && $card['technical_responsible_id'] != $user['id']
            && !in_array($card['technical_responsible_id'], $notifiedUsers)
        ) {
            $this->notifyPrDone($card, $user, $card['technical_responsible_id'], 'técnico');
            $notifiedUsers[] = $card['technical_responsible_id'];
        }

        // Se não tem analista definido, notificar todos os analistas ativos
        if (empty($card['analyst_id'])) {
            $analysts = $db->fetchAll("SELECT id FROM users WHERE role = 'analyst' AND is_active = 1");
            foreach ($analysts as $analyst) {
                if ($analyst['id'] != $user['id'] && !in_array($analyst['id'], $notifiedUsers)) {
                    $this->notifyPrDone($card, $user, $analyst['id'], 'analista');
                    $notifiedUsers[] = $analyst['id'];
                }
            }
        }

        // Notificação WhatsApp de mudança de status (grupo)
        if ($card['ticket_id']) {
            $ticketModel = $ticketModel ?? new Ticket();
            $ticket = $ticketModel->findById($card['ticket_id']);
            if ($ticket) {
                $this->sendPlanningStatusNotification($ticket, 'em_revisao_interna', $previousStatus);
            }
        }

        $this->json(['success' => true]);
    }

    /**
     * Notifica um usuário sobre PR feito: sistema + WhatsApp com dados do CX Hub.
     */
    private function notifyPrDone($card, $fromUser, $toUserId, $roleLabel)
    {
        $db = Database::getInstance();
        $userModel = new User();
        $recipient = $userModel->findById($toUserId);
        if (!$recipient) return;

        $prNumber = $card['pr_number'] ?? '';
        $branchName = $card['branch_name'] ?? '';
        $cxNumber = $card['cx_hub_number'] ?? '';
        $cxName = $card['cx_hub_name'] ?? '';

        // Notificação no sistema
        $message = "{$fromUser['name']} finalizou o PR #{$prNumber} no card \"{$card['title']}\". Aguardando revisão.";
        $db->insert('notifications', [
            'user_id' => $toUserId,
            'title' => "PR #{$prNumber} pronto para revisão",
            'message' => $message,
            'type' => 'system',
        ]);

        // Notificação WhatsApp
        if (!empty($recipient['phone'])) {
            $priorityText = priorityLabel($card['priority'] ?? 'medium');
            $priorityEmoji = match($card['priority'] ?? 'medium') {
                'urgent' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                'low' => '🟢',
                default => '⚪',
            };

            $whatsMsg = "🚀 *PR Pronto para Revisão*\n\n"
                . "*Card:* #{$card['id']} — {$card['title']}\n"
                . "━━━━━━━━━━━━━━━━━━━\n"
                . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
                . "🏢 *Empresa:* " . ($card['company_name'] ?? 'N/A') . "\n"
                . "👨‍💻 *Desenvolvedor:* {$fromUser['name']}\n"
                . "━━━━━━━━━━━━━━━━━━━\n"
                . "🔀 *PR:* #{$prNumber}\n"
                . ($branchName ? "🌿 *Branch:* {$branchName}\n" : '')
                . ($cxNumber ? "📋 *CX Hub:* #{$cxNumber}" . ($cxName ? " — {$cxName}" : '') . "\n" : '')
                . "━━━━━━━━━━━━━━━━━━━\n"
                . "📌 *Status:* Em Revisão Interna\n"
                . "👤 *Para:* {$recipient['name']} ({$roleLabel})\n\n"
                . "Por favor, revise o PR e aprove para homologação.";

            WhatsappNotifier::sendToPhone($recipient['phone'], $whatsMsg, $recipient['name']);
        }
    }

    /**
     * Envia notificação WhatsApp de mudança de status quando card é movido no kanban do planejamento.
     */
    private function sendPlanningStatusNotification($ticket, $status, $previousStatus)
    {
        $label = statusLabel($status);
        $prevLabel = statusLabel($previousStatus);
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
        $company = null;
        if (!empty($clientUser['company_id'])) {
            $company = $db->fetch("SELECT name, whatsapp_group_jid FROM companies WHERE id = ?", [$clientUser['company_id']]);
            $companyName = $company['name'] ?? '';
        }

        // Buscar prazo do card de planejamento vinculado
        $dueDate = '';
        $planningCard = $db->fetch("SELECT due_date FROM planning_cards WHERE ticket_id = ?", [$ticket['id']]);
        if ($planningCard && !empty($planningCard['due_date'])) {
            $dueDate = date('d/m/Y H:i', strtotime($planningCard['due_date']));
        }

        $baseMsg = "🔔 *Atualização de Demanda*\n\n"
            . "*#{$ticket['id']}* — {$ticket['title']}\n"
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
            . "👤 *Cliente:* " . ($ticket['client_name'] ?? '-') . "\n"
            . ($companyName ? "🏢 *Empresa:* {$companyName}\n" : '')
            . "👨‍💻 *Atendente:* " . ($ticket['attendant_name'] ?? 'Não atribuído') . "\n"
            . ($ticket['technical_name'] ?? '' ? "🔧 *Técnico:* {$ticket['technical_name']}\n" : '')
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "📌 *Status anterior:* {$prevLabel}\n"
            . "📌 *Novo status:* {$label}\n"
            . ($dueDate ? "⏰ *Prazo:* {$dueDate}\n" : '');

        // Grupo padrão
        WhatsappNotifier::sendToDefaultGroup($baseMsg);

        // Grupo da empresa do cliente
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
                WhatsappNotifier::sendToGroup($company['whatsapp_group_jid'], $baseMsg);
            }
        }
    }

    // Notificação de atribuição (sistema + email + webhook)
    private function notifyAssignment($cardId, $assignedTo, $currentUser, $cardTitle)    {
        $db = Database::getInstance();
        $card = $this->cardModel->findById($cardId);

        // Notificação no sistema
        $db->insert('notifications', [
            'user_id' => $assignedTo,
            'title' => 'Card atribuído a você',
            'message' => "{$currentUser['name']} atribuiu o card \"{$cardTitle}\" para você.",
            'type' => 'system',
        ]);

        // Email para o responsável
        $userModel = new User();
        $assignedUser = $userModel->findById($assignedTo);

        // WhatsApp direto para o telefone do atendente atribuído (mesma conexão do chat).
        // Complementa o webhook: garante que o responsável selecionado seja avisado.
        if ($assignedUser && !empty($assignedUser['phone'])) {
            $startStr = $card['start_date'] ? date('d/m/Y', strtotime($card['start_date'])) : 'Não definido';
            $endStr = $card['end_date'] ? date('d/m/Y', strtotime($card['end_date'])) : 'Não definido';
            $dueStr = $card['due_date'] ? date('d/m/Y H:i', strtotime($card['due_date'])) : 'Não definido';
            $priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
            $priorityLabel = $priorityLabels[$card['priority']] ?? $card['priority'];

            $whatsMessage = "📋 *Novo Card Atribuído a Você*\n\n"
                . "*Card:* #{$card['id']} — {$cardTitle}\n"
                . "*Empresa:* " . ($card['company_name'] ?? 'N/A') . "\n"
                . "*Prioridade:* {$priorityLabel}\n"
                . "*Desenvolvimento:* {$startStr} até {$endStr}\n"
                . "*Entrega:* {$dueStr}\n"
                . "*Atribuído por:* {$currentUser['name']}\n\n"
                . "Acesse o planejamento para ver os detalhes.";

            try {
                WhatsappNotifier::sendToPhone($assignedUser['phone'], $whatsMessage, $assignedUser['name']);
            } catch (\Throwable $e) {
                // Silencioso — WhatsApp é canal complementar
            }
        }

        if ($assignedUser && $assignedUser['email']) {
            $startStr = $card['start_date'] ? date('d/m/Y', strtotime($card['start_date'])) : 'Não definido';
            $endStr = $card['end_date'] ? date('d/m/Y', strtotime($card['end_date'])) : 'Não definido';
            $dueStr = $card['due_date'] ? date('d/m/Y H:i', strtotime($card['due_date'])) : 'Não definido';
            $priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
            $priorityLabel = $priorityLabels[$card['priority']] ?? $card['priority'];

            $emailBody = "
                <p>Olá, <strong>{$assignedUser['name']}</strong>!</p>
                <p>Uma nova tarefa foi atribuída a você:</p>
                <div style='background:#f8fafc;border-radius:8px;padding:16px 20px;margin:16px 0;border-left:4px solid #00BFA6;'>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Card</td><td style='padding:6px 0;font-weight:600;color:#333;'>#{$card['id']} — {$cardTitle}</td></tr>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Empresa</td><td style='padding:6px 0;color:#333;'>" . ($card['company_name'] ?? 'Não definida') . "</td></tr>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Prioridade</td><td style='padding:6px 0;color:#333;'>{$priorityLabel}</td></tr>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Desenvolvimento</td><td style='padding:6px 0;color:#333;'>{$startStr} até {$endStr}</td></tr>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Prazo de Entrega</td><td style='padding:6px 0;font-weight:600;color:#e65100;'>{$dueStr}</td></tr>
                        <tr><td style='padding:6px 0;color:#666;font-size:0.85rem;'>Atribuído por</td><td style='padding:6px 0;color:#333;'>{$currentUser['name']}</td></tr>
                    </table>
                </div>
                <p style='margin-top:20px;'>
                    <a href='" . baseUrl('planning') . "' style='display:inline-block;background:#00BFA6;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.9rem;'>Ver no Planejamento</a>
                </p>
            ";
            $htmlBody = Mailer::template('Nova Tarefa Atribuída', $emailBody);
            Mailer::send($assignedUser['email'], "Nova tarefa: {$cardTitle}", $htmlBody);
        }

        // Webhook WhatsApp
        $webhookEnabled = Config::get('webhook_enabled');
        if ($webhookEnabled) {
            $webhookUrl = Config::get('webhook_url');
            if (!empty($webhookUrl)) {
                $startStr = $card['start_date'] ? date('d/m/Y', strtotime($card['start_date'])) : '?';
                $endStr = $card['end_date'] ? date('d/m/Y', strtotime($card['end_date'])) : '?';
                $dueStr = $card['due_date'] ? date('d/m/Y', strtotime($card['due_date'])) : '?';
                $priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];

                $webhookMessage = "📋 *Nova Tarefa Atribuída*\n\n"
                    . "*Card:* #{$card['id']} — {$cardTitle}\n"
                    . "*Empresa:* " . ($card['company_name'] ?? 'N/A') . "\n"
                    . "*Prioridade:* " . ($priorityLabels[$card['priority']] ?? '') . "\n"
                    . "*Desenvolvimento:* {$startStr} até {$endStr}\n"
                    . "*Entrega:* {$dueStr}\n"
                    . "*Atribuído por:* {$currentUser['name']}\n"
                    . "*Responsável:* " . ($assignedUser['name'] ?? '') . "\n\n"
                    . "Acesse o painel para ver os detalhes.";

                // Enviar diretamente via cURL para cada telefone configurado
                $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: '';
                $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';
                $phones = array_map('trim', explode(',', $phonesRaw));
                $names = array_map('trim', explode(',', $namesRaw));

                foreach ($phones as $index => $phone) {
                    $phone = preg_replace('/[^0-9]/', '', $phone);
                    if (empty($phone)) continue;
                    $recipientName = $names[$index] ?? ($names[0] ?? 'Admin');

                    $payload = json_encode([
                        'phone' => $phone,
                        'name' => $recipientName,
                        'message' => $webhookMessage,
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
    }

    // Webhook para criação de card (quando não há atribuição)
    private function triggerCardWebhook($cardId, $currentUser, $cardTitle)
    {
        $webhookEnabled = Config::get('webhook_enabled');
        if (!$webhookEnabled) return;

        $webhookUrl = Config::get('webhook_url');
        if (empty($webhookUrl)) return;

        $card = $this->cardModel->findById($cardId);
        $priorityLabels = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta', 'urgent' => 'Urgente'];
        $dueStr = $card['due_date'] ? date('d/m/Y', strtotime($card['due_date'])) : '?';

        $webhookMessage = "📋 *Novo Card Criado*\n\n"
            . "*Card:* #{$card['id']} — {$cardTitle}\n"
            . "*Empresa:* " . ($card['company_name'] ?? 'N/A') . "\n"
            . "*Prioridade:* " . ($priorityLabels[$card['priority']] ?? '') . "\n"
            . "*Entrega:* {$dueStr}\n"
            . "*Criado por:* {$currentUser['name']}\n\n"
            . "Acesse o painel para ver os detalhes.";

        $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: '';
        $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';
        $phones = array_map('trim', explode(',', $phonesRaw));
        $names = array_map('trim', explode(',', $namesRaw));

        foreach ($phones as $index => $phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (empty($phone)) continue;
            $recipientName = $names[$index] ?? ($names[0] ?? 'Admin');

            $payload = json_encode([
                'phone' => $phone,
                'name' => $recipientName,
                'message' => $webhookMessage,
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

    /**
     * View de demandas (planning cards) para o cliente.
     * Exibe em formato de lista, sem informações sensíveis (prazo, datas, atendente técnico).
     */
    public function clientDemands()
    {
        // Acesso ao Planejamento removido para clientes.
        // O cliente acompanha somente as demandas dele em "Minhas Demandas".
        $this->requireLogin();
        $this->redirect('dashboard');
    }
}


