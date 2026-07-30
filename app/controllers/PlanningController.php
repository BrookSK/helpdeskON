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
        $this->requireRole(['super_admin', 'attendant']);
        $user = $this->currentUser();

        $filters = [];
        if (!empty($_GET['company_id'])) $filters['company_id'] = $_GET['company_id'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];

        $grouped = $this->cardModel->getGroupedByStatus($filters);

        $companyModel = new Company();
        $companies = $companyModel->getAll();

        $userModel = new User();
        $team = $userModel->getAttendants();

        // Admins também aparecem na lista de responsáveis
        $db = Database::getInstance();
        $admins = $db->fetchAll("SELECT id, name FROM users WHERE role = 'super_admin' AND is_active = 1");
        $teamMembers = array_merge($admins, $team);

        $this->view('planning/index', [
            'user' => $user,
            'grouped' => $grouped,
            'companies' => $companies,
            'teamMembers' => $teamMembers,
            'filters' => $filters,
        ]);
    }

    // API: Retornar cards para o calendário (JSON)
    public function calendar()
    {
        $this->requireRole(['super_admin', 'attendant']);

        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t 23:59:59');

        $filters = [];
        if (!empty($_GET['company_id'])) $filters['company_id'] = $_GET['company_id'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['hide_completed'])) $filters['hide_completed'] = true;

        $cards = $this->cardModel->getForCalendar($start, $end, $filters);

        $events = [];
        foreach ($cards as $card) {
            $events[] = [
                'id' => $card['id'],
                'title' => $card['title'],
                'start' => $card['due_date'],
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
        $this->requireRole(['super_admin', 'attendant']);
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
            'created_by' => $user['id'],
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => $_POST['status'] ?? 'open',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'position' => 0,
        ];

        $cardId = $this->cardModel->create($data);

        // Notificar responsável se atribuído
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notifyAssignment($cardId, $data['assigned_to'], $user, $title);
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
        $this->requireRole(['super_admin', 'attendant']);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $comments = $this->cardModel->getComments($id);
        $attachments = $this->cardModel->getAttachments($id);

        $this->json([
            'card' => $card,
            'comments' => $comments,
            'attachments' => $attachments,
        ]);
    }

    // Atualizar card
    public function update($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $user = $this->currentUser();

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['company_id'])) $data['company_id'] = $_POST['company_id'] ?: null;
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['priority'])) $data['priority'] = $_POST['priority'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['due_date'])) $data['due_date'] = $_POST['due_date'] ?: null;

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
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $status = $_POST['status'] ?? '';
        $position = intval($_POST['position'] ?? 0);

        $validStatuses = ['open', 'in_progress', 'waiting_client', 'completed', 'denied', 'archived'];
        if (!in_array($status, $validStatuses)) {
            $this->json(['error' => 'Status inválido'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $this->cardModel->updatePosition($id, $position, $status);

        // Sincronizar ticket vinculado
        if ($card['ticket_id']) {
            $ticketModel = new Ticket();
            $ticketModel->updateStatus($card['ticket_id'], $status);
        }

        $this->json(['success' => true]);
    }

    // Deletar card
    public function delete($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->cardModel->findById($id);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $this->cardModel->delete($id);
        $this->json(['success' => true]);
    }

    // Adicionar comentário
    public function comment($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
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
        $this->requireRole(['super_admin', 'attendant']);
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
        $this->requireRole(['super_admin', 'attendant']);
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

    // Notificação de atribuição
    private function notifyAssignment($cardId, $assignedTo, $currentUser, $cardTitle)
    {
        $db = Database::getInstance();
        $db->insert('notifications', [
            'user_id' => $assignedTo,
            'title' => 'Card atribuído a você',
            'message' => "{$currentUser['name']} atribuiu o card \"{$cardTitle}\" para você.",
            'type' => 'system',
        ]);
    }
}
