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

        // Controle de acesso por empresa
        $allowedCompanies = PlanningCard::getUserAllowedCompanies($user['id'], $user['role']);
        if ($allowedCompanies !== null) {
            $filters['allowed_companies'] = $allowedCompanies;
        }

        $grouped = $this->cardModel->getGroupedByStatus($filters);

        $companyModel = new Company();
        // Atendente só vê empresas que tem acesso
        if ($allowedCompanies !== null && !in_array(0, $allowedCompanies)) {
            $allCompanies = $companyModel->getAll();
            $companies = array_filter($allCompanies, fn($c) => in_array($c['id'], $allowedCompanies));
        } else {
            $companies = $companyModel->getAll();
        }

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
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
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
        // Descrição pode vir como arquivo (para contornar limite do ModSecurity)
        if (!empty($_FILES['description_file']['tmp_name']) && $_FILES['description_file']['error'] === UPLOAD_ERR_OK) {
            $data['description'] = file_get_contents($_FILES['description_file']['tmp_name']);
        } elseif (isset($_POST['description'])) {
            $data['description'] = $_POST['description'];
        }
        if (isset($_POST['company_id'])) $data['company_id'] = $_POST['company_id'] ?: null;
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['priority'])) $data['priority'] = $_POST['priority'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['due_date'])) $data['due_date'] = $_POST['due_date'] ?: null;
        if (isset($_POST['start_date'])) $data['start_date'] = $_POST['start_date'] ?: null;
        if (isset($_POST['end_date'])) $data['end_date'] = $_POST['end_date'] ?: null;

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

        $validStatuses = ['open', 'in_progress', 'em_revisao_interna', 'waiting_client', 'em_homologacao', 'completed', 'denied', 'archived'];
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

    // Upload de imagem colada no editor (Quill)
    public function uploadImage($id = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
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

    // Notificação de atribuição (sistema + email + webhook)
    private function notifyAssignment($cardId, $assignedTo, $currentUser, $cardTitle)
    {
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

            // Inserir na fila de webhook para cada telefone configurado
            $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: '';
            $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';
            $phones = array_map('trim', explode(',', $phonesRaw));
            $names = array_map('trim', explode(',', $namesRaw));

            foreach ($phones as $index => $phone) {
                $phone = preg_replace('/[^0-9]/', '', $phone);
                if (empty($phone)) continue;
                $recipientName = $names[$index] ?? ($names[0] ?? 'Admin');

                $db->insert('webhook_queue', [
                    'phone' => $phone,
                    'name' => $recipientName,
                    'message' => $webhookMessage,
                    'status' => 'pending',
                ]);
            }
        }
    }
}
