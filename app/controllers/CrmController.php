<?php

class CrmController extends Controller
{
    private $boardModel;

    public function __construct()
    {
        $this->boardModel = new CrmBoard();
    }

    /**
     * Lista de boards
     */
    public function index()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $user = $this->currentUser();

        $boards = $this->boardModel->getAll();

        $this->view('crm/index', [
            'user' => $user,
            'boards' => $boards,
        ]);
    }

    /**
     * Visualizar board (Kanban)
     */
    public function board($boardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$boardId) $this->redirect('crm');

        $user = $this->currentUser();
        $board = $this->boardModel->findById($boardId);
        if (!$board) {
            flash('error', 'Board não encontrado.');
            $this->redirect('crm');
        }

        // Mover para a primeira coluna os cards com retomada de contato vencida
        $this->boardModel->processFollowUps();

        $columns = $this->boardModel->getColumns($boardId);
        foreach ($columns as &$col) {
            $col['cards'] = $this->boardModel->getCards($col['id']);
        }

        $db = Database::getInstance();
        $userModel = new User();
        $team = $userModel->getAttendants();
        $admins = $db->fetchAll("SELECT id, name FROM users WHERE role = 'super_admin' AND is_active = 1");
        $teamMembers = array_merge($admins, $team);

        // Etiquetas disponíveis para vincular a colunas/cards
        $labels = $db->fetchAll("SELECT * FROM whatsapp_labels ORDER BY name ASC");

        $this->view('crm/board', [
            'user' => $user,
            'board' => $board,
            'columns' => $columns,
            'teamMembers' => $teamMembers,
            'labels' => $labels,
        ]);
    }

    /**
     * Criar board
     */
    public function createBoard()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('crm');
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            if ($this->isAjax()) $this->json(['error' => 'Nome obrigatório'], 400);
            flash('error', 'Nome do board é obrigatório.');
            $this->redirect('crm');
        }

        $user = $this->currentUser();
        $boardId = $this->boardModel->create([
            'name' => $name,
            'description' => $description,
            'created_by' => $user['id'],
        ]);

        // Criar colunas padrão
        $defaultColumns = [
            ['name' => 'Novo Lead', 'color' => '#1565c0'],
            ['name' => 'Contato Feito', 'color' => '#e65100'],
            ['name' => 'Em Negociação', 'color' => '#7b1fa2'],
            ['name' => 'Fechado', 'color' => '#2e7d32'],
            ['name' => 'Perdido', 'color' => '#c62828'],
        ];

        foreach ($defaultColumns as $col) {
            $this->boardModel->createColumn([
                'board_id' => $boardId,
                'name' => $col['name'],
                'color' => $col['color'],
            ]);
        }

        if ($this->isAjax()) {
            $this->json(['success' => true, 'board_id' => $boardId]);
        }

        flash('success', 'Board criado com sucesso!');
        $this->redirect('crm/board/' . $boardId);
    }

    /**
     * Deletar board
     */
    public function deleteBoard($boardId = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$boardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $this->boardModel->delete($boardId);
        $this->json(['success' => true]);
    }

    /**
     * API: Criar coluna
     */
    public function createColumn()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $boardId = $_POST['board_id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#6c757d';
        $validStatus = ['novo','em_atendimento','aguardando','concluido','perdido'];
        $status = in_array($_POST['status'] ?? '', $validStatus) ? $_POST['status'] : null;
        $labelId = !empty($_POST['label_id']) ? intval($_POST['label_id']) : null;

        if (!$boardId || empty($name)) {
            $this->json(['error' => 'Board e nome obrigatórios'], 400);
        }

        $id = $this->boardModel->createColumn([
            'board_id' => $boardId,
            'name' => $name,
            'color' => $color,
            'label_id' => $labelId,
            'status' => $status,
        ]);

        $this->json(['success' => true, 'column' => ['id' => $id, 'name' => $name, 'color' => $color]]);
    }

    /**
     * API: Atualizar coluna
     */
    public function updateColumn($columnId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$columnId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['color'])) $data['color'] = $_POST['color'];

        if (empty($data)) $this->json(['error' => 'Nada para atualizar'], 400);

        $this->boardModel->updateColumn($columnId, $data);
        $this->json(['success' => true]);
    }

    /**
     * API: Deletar coluna
     */
    public function deleteColumn($columnId = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$columnId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $this->boardModel->deleteColumn($columnId);
        $this->json(['success' => true]);
    }

    /**
     * API: Criar card
     */
    public function createCard()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $columnId = $_POST['column_id'] ?? null;
        $title = trim($_POST['title'] ?? '');

        if (!$columnId || empty($title)) {
            $this->json(['error' => 'Coluna e título obrigatórios'], 400);
        }

        $validStatus = ['novo','em_atendimento','aguardando','concluido','perdido'];
        $status = in_array($_POST['status'] ?? '', $validStatus) ? $_POST['status'] : null;
        $labelId = !empty($_POST['label_id']) ? intval($_POST['label_id']) : null;

        $user = $this->currentUser();
        $cardId = $this->boardModel->createCard([
            'column_id' => $columnId,
            'title' => $title,
            'description' => $_POST['description'] ?? '',
            'phone' => $_POST['phone'] ?? null,
            'value' => !empty($_POST['value']) ? floatval($_POST['value']) : null,
            'label_id' => $labelId,
            'status' => $status,
            'contact_id' => !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null,
            'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
            'created_by' => $user['id'],
        ]);

        // Sincronizar valor com o briefing comercial do contato (mesmo campo de valor)
        $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
        if ($contactId && !empty($_POST['value'])) {
            $this->syncValueToBriefing($contactId, $_POST['value'], $user['id']);
        }

        $card = $this->boardModel->findCard($cardId);
        $this->json(['success' => true, 'card' => $card]);
    }

    /**
     * Grava o valor informado como faixa de investimento no briefing do contato,
     * mantendo o campo de valor do card e do briefing sincronizados.
     */
    private function syncValueToBriefing($contactId, $value, $userId)
    {
        $num = is_numeric($value) ? floatval($value) : floatval(preg_replace('/[^\d.]/', '', $value));
        if ($num <= 0) return;
        $formatted = 'R$ ' . number_format($num, 2, ',', '.');
        $contactModel = new WhatsappContact();
        $contactModel->saveBriefing($contactId, ['investment_range' => $formatted], $userId);
    }

    /**
     * API: Atualizar card
     */
    public function updateCard($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = trim($_POST['description']);
        if (isset($_POST['phone'])) $data['phone'] = $_POST['phone'];
        if (isset($_POST['value'])) $data['value'] = floatval($_POST['value']) ?: null;
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;

        if (empty($data)) $this->json(['error' => 'Nada para atualizar'], 400);

        $this->boardModel->updateCard($cardId, $data);

        // Sincronizar valor com o briefing do contato vinculado
        if (isset($data['value']) && $data['value']) {
            $existingCard = $this->boardModel->findCard($cardId);
            if ($existingCard && !empty($existingCard['contact_id'])) {
                $user = $this->currentUser();
                $this->syncValueToBriefing($existingCard['contact_id'], $data['value'], $user['id']);
            }
        }

        $card = $this->boardModel->findCard($cardId);
        $this->json(['success' => true, 'card' => $card]);
    }

    /**
     * API: Mover card (drag-and-drop)
     */
    public function moveCard()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $cardId = $_POST['card_id'] ?? null;
        $columnId = $_POST['column_id'] ?? null;
        $position = intval($_POST['position'] ?? 0);

        if (!$cardId || !$columnId) {
            $this->json(['error' => 'Card e coluna obrigatórios'], 400);
        }

        $user = $this->currentUser();
        $this->boardModel->moveCard($cardId, $columnId, $position);

        // Registrar atividade
        $newCol = $this->boardModel->findColumn($columnId);
        $this->boardModel->addActivity($cardId, $user['id'], 'move', "Movido para \"{$newCol['name']}\"");

        $this->json(['success' => true]);
    }

    /**
     * API: Deletar card
     */
    public function deleteCard($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $this->boardModel->deleteCard($cardId);
        $this->json(['success' => true]);
    }

    /**
     * API: Marcar lead como convertido
     */
    public function convertLead($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $user = $this->currentUser();
        $this->boardModel->updateCard($cardId, [
            'lead_outcome' => 'converted',
            'outcome_at' => date('Y-m-d H:i:s'),
            'converted_by' => $user['id'],
        ]);
        $this->boardModel->addActivity($cardId, $user['id'], 'note', '✅ Lead convertido');
        $this->json(['success' => true]);
    }

    /**
     * API: Marcar lead como perdido
     */
    public function lostLead($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $this->boardModel->updateCard($cardId, [
            'lead_outcome' => 'lost',
            'outcome_at' => date('Y-m-d H:i:s'),
        ]);
        $user = $this->currentUser();
        $this->boardModel->addActivity($cardId, $user['id'], 'note', '❌ Lead perdido');
        $this->json(['success' => true]);
    }

    /**
     * API: Definir retomada de contato em X dias
     */
    public function setFollowUp($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $amount = intval($_POST['amount'] ?? 0);
        $unit = $_POST['unit'] ?? 'days';
        $targetColumnId = !empty($_POST['target_column_id']) ? intval($_POST['target_column_id']) : null;

        if ($amount <= 0) {
            $this->json(['error' => 'Informe um valor válido.'], 400);
        }

        $unitMap = [
            'minutes' => 'minutes',
            'hours' => 'hours',
            'days' => 'days',
        ];
        $strUnit = $unitMap[$unit] ?? 'days';
        $datetime = date('Y-m-d H:i:s', strtotime("+{$amount} {$strUnit}"));

        $this->boardModel->updateCard($cardId, [
            'follow_up_at' => $datetime,
            'follow_up_column_id' => $targetColumnId,
        ]);

        $unitLabels = ['minutes' => 'minuto(s)', 'hours' => 'hora(s)', 'days' => 'dia(s)'];
        $user = $this->currentUser();
        $this->boardModel->addActivity($cardId, $user['id'], 'note', "⏰ Retomar contato em {$amount} {$unitLabels[$strUnit]} — " . date('d/m/Y H:i', strtotime($datetime)));

        $this->json(['success' => true, 'follow_up_at' => $datetime]);
    }

    /**
     * Processa retomadas de contato vencidas (mover cards para a primeira coluna).
     * Pode ser chamado por cron ou ao abrir o board.
     */
    public function runFollowUps()
    {
        $moved = $this->boardModel->processFollowUps();
        $this->json(['success' => true, 'moved' => $moved]);
    }

    /**
     * Meus Leads — lista de leads gerenciáveis.
     * super_admin vê todos; comercial só vê os atribuídos a ele.
     */
    public function leads()
    {
        $this->requireRole(['super_admin', 'comercial']);
        $user = $this->currentUser();

        $isComercial = ($user['role'] === 'comercial');

        $filters = [
            'search' => trim($_GET['q'] ?? ''),
            'temperature' => $_GET['temperature'] ?? '',
            'source' => $_GET['source'] ?? '',
        ];
        // Comercial: escopo travado nos próprios leads
        if ($isComercial) {
            $filters['assigned_to'] = $user['id'];
        }

        $contactModel = new WhatsappContact();
        $leads = $contactModel->getManagedLeads($filters);

        $this->view('crm/leads', [
            'user' => $user,
            'leads' => $leads,
            'isComercial' => $isComercial,
            'filters' => $filters,
        ]);
    }

    /**
     * API: dados de um lead (para o modal de gerenciamento).
     */
    public function leadDetail($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $contactModel = new WhatsappContact();
        $contact = $contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Lead não encontrado'], 404);

        // Comercial só acessa os próprios leads
        $user = $this->currentUser();
        if ($user['role'] === 'comercial' && (int)$contact['assigned_to'] !== (int)$user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        $briefing = $contactModel->getBriefing($contactId);
        $this->json(['contact' => $contact, 'briefing' => $briefing ?: null]);
    }

    /**
     * API: atualizar dados de um lead (nome, telefone e briefing).
     */
    public function updateLead($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contactModel = new WhatsappContact();
        $contact = $contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Lead não encontrado'], 404);

        $user = $this->currentUser();
        if ($user['role'] === 'comercial' && (int)$contact['assigned_to'] !== (int)$user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        // Dados do contato
        $data = [];
        if (isset($_POST['contact_name'])) $data['contact_name'] = trim($_POST['contact_name']) ?: null;
        if (isset($_POST['phone'])) $data['phone'] = preg_replace('/\D/', '', $_POST['phone']) ?: null;
        if (!empty($data)) {
            Database::getInstance()->update('whatsapp_contacts', $data, 'id = ?', [$contactId]);
        }

        // Briefing
        $bfKeys = ['need', 'main_pain', 'current_solution', 'expected_goal', 'urgency', 'investment_range',
                   'decision_level', 'lead_temperature', 'lead_source', 'main_objection', 'next_step', 'notes'];
        $bf = [];
        foreach ($bfKeys as $k) {
            if (isset($_POST['bf_' . $k])) $bf[$k] = trim($_POST['bf_' . $k]) ?: null;
        }
        if (isset($bf['lead_temperature']) && !in_array($bf['lead_temperature'], ['frio', 'morno', 'quente'])) {
            $bf['lead_temperature'] = null;
        }
        if (!empty($bf)) {
            $contactModel->saveBriefing($contactId, $bf, $user['id']);
        }

        $this->json(['success' => true]);
    }

    /**
     * Dashboard do CRM
     */
    public function dashboard()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $user = $this->currentUser();

        // Processa retomadas pendentes ao abrir o dashboard
        $this->boardModel->processFollowUps();

        $stats = $this->boardModel->getDashboardStats();
        $trend = $this->boardModel->getMonthlyTrend(6);
        $this->view('crm/dashboard', ['user' => $user, 'stats' => $stats, 'trend' => $trend]);
    }

    /**
     * Aba de Comissões (apenas super_admin)
     */
    public function commissions()
    {
        $this->requireRole(['super_admin', 'comercial']);
        $user = $this->currentUser();

        $month = $_GET['month'] ?? date('Y-m');
        $isComercial = ($user['role'] === 'comercial');

        // Comercial só vê as próprias comissões; super_admin pode filtrar por usuário
        $filterUserId = $isComercial ? $user['id'] : (!empty($_GET['user_id']) ? intval($_GET['user_id']) : null);

        $commissions = $this->boardModel->getCommissions($month, $filterUserId);

        // Lista de comerciais para o filtro (apenas para super_admin)
        $allComerciais = (new User())->getByRoles(['comercial']);
        $comerciais = $isComercial ? [] : $allComerciais;

        $this->view('crm/commissions', [
            'user' => $user,
            'commissions' => $commissions,
            'comerciais' => $comerciais,
            'comerciaisCount' => count($allComerciais),
            'month' => $month,
            'filterUserId' => $filterUserId,
            'isComercial' => $isComercial,
        ]);
    }

    /**
     * API: Leads convertidos por um comercial em um período (para o dropdown)
     */
    public function commissionLeads($userId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if (!$userId) $this->json(['error' => 'ID obrigatório'], 400);

        $current = $this->currentUser();
        // Comercial só pode ver os próprios leads
        if ($current['role'] === 'comercial' && (int)$userId !== (int)$current['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        $month = $_GET['month'] ?? null;
        $leads = $this->boardModel->getConvertedLeadsByUser($userId, $month);
        $this->json(['leads' => $leads]);
    }

    /**
     * API: Detalhes do card + atividades
     */
    public function cardDetail($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$cardId) $this->json(['error' => 'ID obrigatório'], 400);

        $card = $this->boardModel->findCard($cardId);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);

        $activities = $this->boardModel->getActivities($cardId);

        // Briefing comercial do contato vinculado (se houver)
        $briefing = null;
        if (!empty($card['contact_id'])) {
            $briefing = (new WhatsappContact())->getBriefing($card['contact_id']);
        }

        $this->json(['card' => $card, 'activities' => $activities, 'briefing' => $briefing ?: null]);
    }

    /**
     * API: Adicionar nota/atividade ao card
     */
    public function addNote($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $description = trim($_POST['description'] ?? '');
        if (empty($description)) $this->json(['error' => 'Descrição obrigatória'], 400);

        $user = $this->currentUser();
        $id = $this->boardModel->addActivity($cardId, $user['id'], 'note', $description);

        $this->json([
            'success' => true,
            'activity' => [
                'id' => $id,
                'user_name' => $user['name'],
                'activity_type' => 'note',
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * API: Listar boards (para selects)
     */
    public function listBoards()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $boards = $this->boardModel->getAll();

        // Incluir colunas de cada board
        foreach ($boards as &$board) {
            $board['columns'] = $this->boardModel->getColumns($board['id']);
        }

        $this->json($boards);
    }
}

