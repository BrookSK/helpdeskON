<?php

class CrmController extends Controller
{
    private $boardModel;

    // Papéis com acesso à Captação de Leads (Apollo)
    private $captureRoles = ['super_admin', 'comercial'];

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
        $comerciais = $db->fetchAll("SELECT id, name FROM users WHERE role = 'comercial' AND is_active = 1");
        $teamMembers = array_merge($admins, $comerciais, $team);

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
     * Se a coluna de destino for "Fechado", exige closed_by e marca como convertido.
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
        $newCol = $this->boardModel->findColumn($columnId);

        // Detectar se a coluna de destino é "Fechado" (case-insensitive)
        $isClosedColumn = mb_strtolower(trim($newCol['name'])) === 'fechado';

        if ($isClosedColumn) {
            // Exigir informação de quem fechou
            $closedBy = !empty($_POST['closed_by']) ? intval($_POST['closed_by']) : null;
            if (!$closedBy) {
                $this->json(['error' => 'Informe quem fechou o negócio.', 'requires_closed_by' => true], 400);
            }

            // Buscar card para determinar quem prospectou
            $card = $this->boardModel->findCard($cardId);
            $prospectedBy = $card['assigned_to'] ?? $user['id'];

            // Mover o card
            $this->boardModel->moveCard($cardId, $columnId, $position);

            // Marcar como convertido com as informações de fechamento
            $this->boardModel->updateCard($cardId, [
                'lead_outcome' => 'converted',
                'outcome_at' => date('Y-m-d H:i:s'),
                'converted_by' => $closedBy,
                'prospected_by' => $prospectedBy,
            ]);

            // Registrar atividade
            $closedByName = '';
            if ($closedBy != $user['id']) {
                $closedUser = (new User())->findById($closedBy);
                $closedByName = $closedUser ? " por {$closedUser['name']}" : '';
            }
            $this->boardModel->addActivity($cardId, $user['id'], 'move', "Movido para \"{$newCol['name']}\"");
            $this->boardModel->addActivity($cardId, $user['id'], 'note', "✅ Lead convertido (fechamento){$closedByName}");
        } else {
            // Movimentação normal
            $this->boardModel->moveCard($cardId, $columnId, $position);
            $this->boardModel->addActivity($cardId, $user['id'], 'move', "Movido para \"{$newCol['name']}\"");
        }

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

        // Quem fechou: se informado no POST, usa; senão assume o próprio usuário
        $closedBy = !empty($_POST['closed_by']) ? intval($_POST['closed_by']) : $user['id'];

        // Quem prospectou: o assigned_to do card (quem estava responsável pelo lead)
        $card = $this->boardModel->findCard($cardId);
        $prospectedBy = $card['assigned_to'] ?? $user['id'];

        $this->boardModel->updateCard($cardId, [
            'lead_outcome' => 'converted',
            'outcome_at' => date('Y-m-d H:i:s'),
            'converted_by' => $closedBy,
            'prospected_by' => $prospectedBy,
        ]);

        $closedByName = '';
        if ($closedBy != $prospectedBy) {
            $closedUser = (new User())->findById($closedBy);
            $closedByName = $closedUser ? " por {$closedUser['name']}" : '';
        }
        $this->boardModel->addActivity($cardId, $user['id'], 'note', "✅ Lead convertido — fechado{$closedByName}");
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

        $showArchived = !empty($_GET['archived']);

        $filters = [
            'search' => trim($_GET['q'] ?? ''),
            'temperature' => $_GET['temperature'] ?? '',
            'source' => $_GET['source'] ?? '',
            'archived' => $showArchived ? 1 : 0,
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
            'showArchived' => $showArchived,
        ]);
    }

    /**
     * API: arquivar/desarquivar um lead (remove/retorna da lista principal).
     * POST crm/toggleArchiveLead/{contactId}
     */
    public function toggleArchiveLead($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contactModel = new WhatsappContact();
        $contact = $contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Lead não encontrado'], 404);

        // Comercial só pode arquivar os próprios leads
        $user = $this->currentUser();
        if ($user['role'] === 'comercial' && (int)$contact['assigned_to'] !== (int)$user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        $contactModel->toggleCrmArchive($contactId);
        $archived = empty($contact['crm_archived']) ? 1 : 0;
        $this->json(['success' => true, 'archived' => $archived]);
    }

    /**
     * API: timeline unificada + score de um lead.
     * GET crm/leadTimeline/{contactId}
     */
    public function leadTimeline($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);
        $this->json([
            'timeline' => (new LeadTimelineService())->forContact($contactId, 100),
            'score' => (new LeadScoreService())->get($contactId),
        ]);
    }

    /**
     * API: sequências disponíveis (para inscrever um lead a partir do modal).
     * GET crm/sequencesForSelect
     */
    public function sequencesForSelect()
    {
        $this->requireRole(['super_admin', 'comercial']);
        $rows = Database::getInstance()->fetchAll(
            "SELECT id, name FROM email_sequences WHERE is_active = 1 ORDER BY name ASC"
        );
        $this->json(['sequences' => $rows]);
    }

    /**
     * API: inscreve um lead numa sequência a partir do CRM.
     * POST crm/enrollLead/{contactId}  body: sequence_id
     */
    public function enrollLead($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) $this->json(['error' => 'Requisição inválida'], 400);
        $sequenceId = intval($_POST['sequence_id'] ?? 0);
        if (!$sequenceId) $this->json(['error' => 'Selecione a sequência.'], 400);
        $user = $this->currentUser();
        $r = (new SequenceEngine())->enroll($sequenceId, $contactId, $user['id']);
        if (empty($r['success'])) $this->json(['error' => $r['error'] ?? 'Falha ao inscrever.'], 400);
        $this->json(['success' => true]);
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
        if (isset($_POST['lead_email'])) {
            $em = trim($_POST['lead_email']);
            $data['lead_email'] = ($em && filter_var($em, FILTER_VALIDATE_EMAIL)) ? $em : null;
        }
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
     * API: originar uma ligação para o Lead via Nvoip.
     * O frontend envia apenas o contactId; o backend resolve o telefone e o usuário.
     * POST crm/callLead/{contactId}
     */
    public function callLead($contactId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contactModel = new WhatsappContact();
        $contact = $contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Lead não encontrado'], 404);

        $user = $this->currentUser();
        // Comercial só liga para os próprios leads
        if ($user['role'] === 'comercial' && (int)$contact['assigned_to'] !== (int)$user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        // Envia o número COM 55 (um só) e checkDDI:FALSE — assim a Nvoip NÃO mexe no DDI
        // e disca exatamente o número enviado (evita a duplicação do 55).
        $called = preg_replace('/\D/', '', (string)($contact['phone'] ?? ''));
        while (strlen($called) > 11 && strpos($called, '55') === 0) {
            $called = substr($called, 2);
        }
        $called = '55' . $called; // garante exatamente um 55
        if ($called === '55') $this->json(['error' => 'Este lead não possui telefone cadastrado.'], 400);

        $api = new NvoipApi();
        if (!$api->isConfigured()) $this->json(['error' => 'Telefonia Nvoip não configurada.'], 400);

        $caller = $api->caller();
        if ($caller === '') $this->json(['error' => 'Originador (caller) não configurado em Configurações.'], 400);

        // checkDDI:false -> Nvoip não completa/duplica o DDI; disca o número como enviado.
        Logger::info('Nvoip createCall (checkDDI=false)', ['caller' => $caller, 'called' => $called]);
        $res = $api->createCall($caller, $called, false, false);

        // Registra a resposta completa (sucesso ou não) para inspecionar a estrutura real.
        Logger::info('Nvoip clickToCall resposta', [
            'http' => $res['status'] ?? null,
            'called' => $called,
            'data' => $this->safeResponseJson($res['data'] ?? null),
        ]);

        // Localiza o callId na resposta efetiva (sem inventar estrutura).
        // A Nvoip pode retornar o identificador com nomes diferentes; tentamos os mais prováveis.
        $callId = null;
        $status = null;
        if (is_array($res['data'] ?? null)) {
            $d = $res['data'];
            $callId = $d['callId'] ?? $d['call_id'] ?? $d['id'] ?? $d['uuid'] ?? null;
            // A Nvoip usa o campo "state" para a situação da chamada.
            $status = $d['state'] ?? $d['status'] ?? null;
        }

        // Persiste o registro mínimo, mesmo em caso de falha (para auditoria), sem dados de auth
        $this->recordCall([
            'contact_id' => $contactId,
            'user_id' => $user['id'],
            'call_id' => $callId,
            'caller' => $caller,
            'called' => $called,
            'status' => $status,
            'response_json' => $this->safeResponseJson($res['data'] ?? null),
        ]);

        if (empty($res['success'])) {
            // Repassa a mensagem da Nvoip quando disponível (ex.: originador/usuário SIP inválido)
            $apiMsg = is_array($res['data'] ?? null) ? ($res['data']['message'] ?? null) : null;
            $msg = 'Não foi possível iniciar a ligação (HTTP ' . ($res['status'] ?? '—') . ').';
            if ($apiMsg) $msg .= ' Nvoip: ' . $apiMsg;
            $this->json(['error' => $msg], 502);
        }

        // Não consultamos a situação imediatamente: o click-to-call é assíncrono e
        // consultar em <1s retornaria um estado transitório. A situação final é obtida
        // depois via crm/callStatus/{callId} (ex.: quando o usuário quiser conferir).
        $this->json([
            'success' => true,
            'call_id' => $callId,
            'status' => $status, // estado inicial retornado na criação (ex.: calling_origin)
            'raw' => $res['data'] ?? null,
        ]);
    }

    /**
     * API: prepara a discagem do lead pelo webphone nativo (WebRTC).
     * O backend resolve/normaliza o telefone (não confia no frontend) e registra a ligação.
     * Retorna o número a ser discado pelo ramal SIP. POST crm/dialLead/{contactId}
     */
    public function dialLead($contactId = null)
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

        // Normaliza o número conforme o formato configurado (garante um único 55 quando 'ddi').
        $called = $this->normalizeCalled($contact['phone'] ?? '');
        $fmt = Config::get('nvoip_dial_format') ?: 'local';
        if ($called === '') $this->json(['error' => 'Este lead não possui telefone cadastrado.'], 400);

        // Verifica se o ramal está em uso por outro usuário
        $db = Database::getInstance();
        $dbUserSip = $db->fetch("SELECT sip_user FROM users WHERE id = ?", [$user['id']]);
        $sipUser = !empty($dbUserSip['sip_user']) ? $dbUserSip['sip_user'] : Config::get('nvoip_sip_user');
        $lockCheck = $this->checkRamalLock($sipUser, $user['id']);
        if ($lockCheck) {
            $this->json(['error' => 'O ramal está em uso por ' . $lockCheck . '. Aguarde a ligação encerrar para tentar novamente.'], 409);
        }

        // Marca ramal como em uso
        $this->lockRamal($sipUser, $user['id'], $user['name']);

        // Log de diagnóstico: telefone bruto do lead x número final discado
        Logger::info('Nvoip dial normalize', [
            'raw' => $contact['phone'] ?? null,
            'formato' => $fmt,
            'called_final' => $called,
        ]);

        // Registra a ligação (origem WebRTC — sem callId da API REST)
        $recordId = $this->recordCall([
            'contact_id' => $contactId,
            'user_id' => $user['id'],
            'direction' => 'outbound',
            'call_id' => null,
            'caller' => $sipUser,
            'called' => $called,
            'status' => 'dialing',
            'response_json' => null,
        ]);

        // Dados do cliente para exibir no modal da ligação
        $briefing = $contactModel->getBriefing($contactId);
        $lead = [
            'id' => $contact['id'],
            'name' => $contact['contact_name'] ?: ($contact['push_name'] ?? 'Cliente'),
            'phone' => $contact['phone'] ?? null,
        ];

        $this->json([
            'success' => true,
            'called' => $called,
            'call_record_id' => $recordId,
            'lead' => $lead,
            'briefing' => $briefing ?: null,
        ]);
    }

    /**
     * API: salva a nota/observação da ligação no registro nvoip_calls e (opcional) no briefing.
     * POST crm/saveCallNote/{recordId}  body: note, contact_id
     */
    public function saveCallNote($recordId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$recordId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $note = trim($_POST['note'] ?? '');
        $db = Database::getInstance();
        // Grava a nota no registro da ligação (coluna response_json reaproveitada como observação)
        $db->query("UPDATE nvoip_calls SET response_json = ? WHERE id = ?", [
            json_encode(['note' => $note], JSON_UNESCAPED_UNICODE), $recordId
        ]);

        // Se veio contato, acrescenta a nota ao briefing (campo notes)
        $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
        if ($contactId && $note !== '') {
            $user = $this->currentUser();
            $cm = new WhatsappContact();
            $bf = $cm->getBriefing($contactId);
            $prev = $bf['notes'] ?? '';
            $stamp = date('d/m/Y H:i');
            $merged = trim($prev . "\n[" . $stamp . " • ligação] " . $note);
            $cm->saveBriefing($contactId, ['notes' => $merged], $user['id']);
        }
        $this->json(['success' => true]);
    }

    /**
     * API: registra eventos do ciclo de vida da chamada do webphone.
     * POST crm/callEvent/{recordId}  body: event=ringing|answered|ended, cause=?, duration=?
     * Mantém todos os registros (início, atendida, encerrada, duração).
     */
    public function callEvent($recordId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$recordId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $db = Database::getInstance();
        $rec = $db->fetch("SELECT * FROM nvoip_calls WHERE id = ?", [$recordId]);
        if (!$rec) $this->json(['error' => 'Registro não encontrado'], 404);

        $user = $this->currentUser();
        if ($user['role'] === 'comercial' && (int)$rec['user_id'] !== (int)$user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        $event = $_POST['event'] ?? '';
        $data = [];
        switch ($event) {
            case 'ringing':
                $data['status'] = 'ringing';
                break;
            case 'answered':
                $data['status'] = 'answered';
                $data['answered_at'] = date('Y-m-d H:i:s');
                break;
            case 'ended':
                $data['status'] = 'ended';
                $data['ended_at'] = date('Y-m-d H:i:s');
                $data['duration_seconds'] = max(0, intval($_POST['duration'] ?? 0));
                if (!empty($_POST['cause'])) $data['hangup_cause'] = substr(trim($_POST['cause']), 0, 50);
                break;
            default:
                $this->json(['error' => 'Evento inválido'], 400);
        }

        $db->update('nvoip_calls', $data, 'id = ?', [$recordId]);

        // Libera o ramal quando a ligação encerra
        if ($event === 'ended') {
            $this->unlockRamal($user['id']);
        }

        $this->json(['success' => true]);
    }

    /**
     * API: registra uma ligação recebida no webphone (histórico de entrada).
     * POST crm/registerInbound  body: from=numero
     */
    public function registerInbound()
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $from = preg_replace('/\D/', '', (string)($_POST['from'] ?? ''));

        // Tenta associar a um contato existente pelo telefone (últimos 8 dígitos)
        $contactId = null;
        if ($from !== '') {
            $last8 = substr($from, -8);
            $c = Database::getInstance()->fetch(
                "SELECT id FROM whatsapp_contacts WHERE COALESCE(is_group,0)=0
                 AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ? LIMIT 1",
                ['%' . $last8]
            );
            $contactId = $c['id'] ?? null;
        }

        $recordId = $this->recordCall([
            'contact_id' => $contactId,
            'user_id' => $user['id'],
            'direction' => 'inbound',
            'call_id' => null,
            'caller' => $from ?: null,
            'called' => Config::get('nvoip_sip_user'),
            'status' => 'ringing',
            'response_json' => null,
        ]);

        $this->json(['success' => true, 'call_record_id' => $recordId]);
    }

    /**
     * API: consultar a situação de uma chamada pelo callId armazenado.
     * GET crm/callStatus/{callId}
     */
    public function callStatus($callId = null)
    {
        $this->requireRole(['super_admin', 'comercial']);
        if (!$callId) $this->json(['error' => 'callId obrigatório'], 400);

        $api = new NvoipApi();
        if (!$api->isConfigured()) $this->json(['error' => 'Telefonia Nvoip não configurada.'], 400);

        $res = $api->getCall($callId);
        if (empty($res['success'])) {
            $this->json(['error' => 'Falha ao consultar a chamada (HTTP ' . ($res['status'] ?? '—') . ').'], 502);
        }

        // Atualiza o status local se a resposta trouxer a situação (campo "state" na Nvoip)
        $status = is_array($res['data'] ?? null) ? ($res['data']['state'] ?? $res['data']['status'] ?? null) : null;
        if ($status !== null) {
            Database::getInstance()->query(
                "UPDATE nvoip_calls SET status = ? WHERE call_id = ?",
                [$status, $callId]
            );
        }

        $this->json(['success' => true, 'data' => $res['data']]);
    }

    /**
     * API: histórico de ligações em uma data.
     * GET crm/callHistory?date=YYYY-MM-DD&type=all
     */
    public function callHistory()
    {
        $this->requireRole(['super_admin', 'comercial']);

        $date = $_GET['date'] ?? date('Y-m-d');
        $type = $_GET['type'] ?? 'all';

        $api = new NvoipApi();
        if (!$api->isConfigured()) $this->json(['error' => 'Telefonia Nvoip não configurada.'], 400);

        $res = $api->getHistory($date, $type);
        if (empty($res['success'])) {
            $this->json(['error' => 'Falha ao consultar o histórico (HTTP ' . ($res['status'] ?? '—') . ').'], 502);
        }
        $this->json(['success' => true, 'data' => $res['data']]);
    }

    /**
     * API: entrega as credenciais SIP do webphone ao usuário autenticado (sob demanda).
     * A senha SIP não fica no HTML — é buscada por este endpoint apenas por usuário logado.
     * GET crm/sipCredentials
     */
    public function sipCredentials()
    {
        $this->requireRole(['super_admin', 'comercial']);

        $user = $this->currentUser();
        $wsServer = Config::get('nvoip_ws_server') ?: 'wss://app.nvoip.com.br:7443';
        $domain = Config::get('nvoip_sip_domain') ?: 'app.nvoip.com.br';

        // Busca ramal SIP do usuário diretamente no banco (currentUser() não traz esses campos)
        $db = Database::getInstance();
        $dbUser = $db->fetch("SELECT sip_user, sip_password FROM users WHERE id = ?", [$user['id']]);
        $userSip = $dbUser['sip_user'] ?? null;
        $userSipPass = $dbUser['sip_password'] ?? null;

        // Se o usuário tem ramal E senha próprios, usa os dele.
        // Caso contrário, usa o ramal/senha padrão (global = Super Admin).
        if (!empty($userSip) && !empty($userSipPass)) {
            $sipUser = $userSip;
            $sipPassword = $userSipPass;
        } else {
            $sipUser = Config::get('nvoip_sip_user');
            $sipPassword = Config::get('nvoip_sip_password');
        }

        if (empty($sipUser) || empty($sipPassword)) {
            // Usuário sem ramal SIP configurado: webphone não é habilitado para ele.
            Logger::info('Webphone: usuário sem credenciais SIP', [
                'user_id' => $user['id'],
                'has_sip_user' => !empty($sipUser),
                'has_sip_password' => !empty($sipPassword),
            ]);
            $this->json(['configured' => false, 'reason' => 'no_extension']);
        }

        // Servidores ICE (STUN/TURN) — configuráveis via settings 'nvoip_ice_servers' (JSON).
        // Ex.: [{"urls":"stun:stun.l.google.com:19302"},{"urls":"turn:host:3478","username":"u","credential":"p"}]
        $iceRaw = Config::get('nvoip_ice_servers');
        $iceServers = [];
        if (!empty($iceRaw)) {
            $decoded = json_decode($iceRaw, true);
            if (is_array($decoded)) $iceServers = $decoded;
        }

        $this->json([
            'configured' => true,
            'ws_server' => $wsServer,
            'domain' => $domain,
            'sip_user' => $sipUser,
            'sip_password' => $sipPassword,
            'uri' => 'sip:' . $sipUser . '@' . $domain,
            'ice_servers' => $iceServers,
        ]);
    }

    /**
     * API: recebe logs do webphone (frontend) e grava no log do servidor via Logger.
     * Permite depurar as chamadas WebRTC pelo painel de logs. POST crm/webphoneLog
     */
    public function webphoneLog()
    {
        $this->requireRole(['super_admin', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $level = $_POST['level'] ?? 'info';
        $message = substr(trim($_POST['message'] ?? ''), 0, 500);
        // Loga o ramal REAL do usuário (não o global, que foi removido)
        $uRamal = null;
        try { $r = Database::getInstance()->fetch("SELECT sip_user FROM users WHERE id = ?", [$user['id']]); $uRamal = $r['sip_user'] ?? null; } catch (\Throwable $e) {}
        $context = [
            'user_id' => $user['id'],
            'ramal' => $uRamal,
        ];
        if (isset($_POST['detail'])) $context['detail'] = substr((string)$_POST['detail'], 0, 1000);

        if ($message !== '') {
            if (strtolower($level) === 'error') Logger::error('Webphone: ' . $message, $context);
            else Logger::info('Webphone: ' . $message, $context);
        }
        $this->json(['success' => true]);
    }

    /**
     * Normaliza o telefone do lead para discagem na Nvoip, GARANTINDO um único DDI 55.
     * 1) Mantém só dígitos. 2) Remove TODOS os prefixos 55 repetidos até o número nacional.
     * 3) Aplica o formato: 'ddi' => 55+nacional (um só); 'local' => nacional (sem 55).
     */
    private function normalizeCalled($phone)
    {
        $orig = (string)$phone;
        $n = preg_replace('/\D/', '', $orig);
        $apenasDigitos = $n;
        // Remove prefixos 55 repetidos enquanto sobrar mais que um número nacional (>11 díg.)
        while (strlen($n) > 11 && strpos($n, '55') === 0) {
            $n = substr($n, 2);
        }
        $semDDI = $n;
        if ($n === '') return '';
        $fmt = Config::get('nvoip_dial_format') ?: 'local';
        // $n aqui é o número nacional (DDD+numero, sem 55). Aplica o prefixo conforme o formato.
        switch ($fmt) {
            case 'ddi':      $n = '55' . $n; break;       // 5517991253062
            case 'zero':     $n = '0' . $n; break;        // 017991253062
            case 'zero_ddi': $n = '055' . $n; break;      // 05517991253062
            case 'e164':     $n = '+55' . $n; break;      // +5517991253062 (E.164 com +)
            case 'local':    default: /* mantém nacional */ break; // 17991253062
        }
        Logger::info('normalizeCalled etapas', [
            'orig' => $orig, 'digitos' => $apenasDigitos, 'sem_ddi' => $semDDI, 'formato' => $fmt, 'final' => $n,
        ]);
        return $n;
    }

    /** Grava o registro mínimo da ligação. Retorna o id do registro (ou null em falha). */
    private function recordCall($data)
    {
        try {
            return Database::getInstance()->insert('nvoip_calls', $data);
        } catch (\Throwable $e) { /* não bloqueia a ligação por falha de log */ }
        return null;
    }

    /** Serializa a resposta para armazenamento, removendo eventuais campos sensíveis de auth. */
    private function safeResponseJson($data)
    {
        if ($data === null) return null;
        if (is_array($data)) {
            foreach (['access_token', 'refresh_token', 'authorization', 'client_credential'] as $k) {
                unset($data[$k]);
            }
        }
        return is_string($data) ? $data : json_encode($data);
    }

    /**
     * DEBUG: Lista ramais/usuários da conta Nvoip (para descobrir dados SIP).
     * GET crm/nvoipUsers — apenas super_admin
     */
    public function nvoipUsers()
    {
        $this->requireRole(['super_admin']);
        $api = new NvoipApi();
        if (!$api->isConfigured()) $this->json(['error' => 'Nvoip não configurado'], 400);
        $res = $api->getUsers(0, 100);
        $this->json($res);
    }

    /**
     * Histórico de ligações registradas pelo CRM (webphone).
     * super_admin vê todas; comercial vê só as suas.
     */
    public function calls()
    {
        $this->requireRole(['super_admin', 'comercial']);
        $user = $this->currentUser();

        $perPage = 15;
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        // Filtro base (comercial só vê as próprias ligações)
        $where = " WHERE 1=1";
        $params = [];
        if ($user['role'] === 'comercial') {
            $where .= " AND nc.user_id = ?";
            $params[] = $user['id'];
        }

        $db = Database::getInstance();

        // Total de registros para calcular as páginas
        $total = (int)($db->fetch(
            "SELECT COUNT(*) AS t FROM nvoip_calls nc" . $where,
            $params
        )['t'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $sql = "SELECT nc.*, c.contact_name, c.push_name, u.name AS user_name
                FROM nvoip_calls nc
                LEFT JOIN whatsapp_contacts c ON c.id = nc.contact_id
                LEFT JOIN users u ON u.id = nc.user_id"
                . $where
                . " ORDER BY nc.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

        $calls = $db->fetchAll($sql, $params);

        $this->view('crm/calls', [
            'user' => $user,
            'calls' => $calls,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
        ]);
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

        // Métricas de e-mail (envios, aberturas, respostas, melhor e-mail)
        $emailStats = null; $emailTrend = [];
        try {
            $seqModel = new EmailSequence();
            $emailStats = $seqModel->emailDashboard();
            $emailTrend = $seqModel->emailMonthlyTrend(6);
        } catch (\Throwable $e) { /* módulo de e-mail pode não estar migrado ainda */ }

        $this->view('crm/dashboard', [
            'user' => $user, 'stats' => $stats, 'trend' => $trend,
            'emailStats' => $emailStats, 'emailTrend' => $emailTrend,
        ]);
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

    // ===== Lock de ramal (evita dois usuários no mesmo ramal) =====

    /**
     * Verifica se o ramal está em uso por outro usuário.
     * Retorna o nome do usuário que está usando, ou null se livre.
     * Lock expira automaticamente após 5 minutos (segurança contra locks órfãos).
     */
    private function checkRamalLock($sipUser, $currentUserId)
    {
        $db = Database::getInstance();
        $lock = $db->fetch(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            ['ramal_lock_' . $sipUser]
        );
        if (!$lock) return null;

        $lockData = json_decode($lock['setting_value'] ?? '{}', true);
        if (!$lockData) return null;

        // Lock expirou (mais de 5 minutos) — libera
        $lockedAt = strtotime($lockData['locked_at'] ?? '');
        if ($lockedAt && (time() - $lockedAt) > 300) {
            $db->query("DELETE FROM settings WHERE setting_key = ?", ['ramal_lock_' . $sipUser]);
            return null;
        }

        // É o próprio usuário — não bloqueia
        if (($lockData['user_id'] ?? null) == $currentUserId) return null;

        return $lockData['user_name'] ?? 'outro usuário';
    }

    /** Marca o ramal como em uso pelo usuário. */
    private function lockRamal($sipUser, $userId, $userName)
    {
        $db = Database::getInstance();
        $key = 'ramal_lock_' . $sipUser;
        $value = json_encode(['user_id' => $userId, 'user_name' => $userName, 'locked_at' => date('Y-m-d H:i:s')]);
        $existing = $db->fetch("SELECT id FROM settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            $db->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
        } else {
            $db->insert('settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    /** Libera o lock do ramal para o usuário. */
    private function unlockRamal($userId)
    {
        $db = Database::getInstance();
        $locks = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ramal_lock_%'");
        foreach ($locks as $lock) {
            $data = json_decode($lock['setting_value'] ?? '{}', true);
            if (($data['user_id'] ?? null) == $userId) {
                $db->query("DELETE FROM settings WHERE setting_key = ?", [$lock['setting_key']]);
            }
        }
    }

    // =====================================================
    // CAPTAÇÃO DE LEADS (Apollo.io)
    // =====================================================

    /**
     * Página "Captação de Leads": painel de busca + resultados capturados.
     */
    public function capture()
    {
        $this->requireRole($this->captureRoles);
        $user = $this->currentUser();

        $apollo = new ApolloApi();
        $this->view('crm/capture', [
            'user' => $user,
            'apolloConfigured' => $apollo->isConfigured(),
        ]);
    }

    /**
     * API: pesquisa de pessoas (prospects) no Apollo.
     * POST crm/apolloSearchPeople
     * Aceita todos os filtros documentados via $_POST.
     */
    public function apolloSearchPeople()
    {
        $this->requireRole($this->captureRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado. Informe a API key em Configurações.'], 400);

        $filters = $this->collectPeopleFilters();
        $res = $apollo->searchPeople($filters);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.'], 502);

        $data = $res['data'] ?? [];
        $people = $data['people'] ?? ($data['contacts'] ?? []);
        $pagination = $data['pagination'] ?? [];

        // Persiste em staging para consulta/importação posterior
        $leadModel = new ApolloLead();
        $user = $this->currentUser();
        $out = [];
        foreach ($people as $p) {
            $localId = $leadModel->upsertFromApollo($p, $user['id']);
            $existing = $localId ? $leadModel->findById($localId) : null;
            $out[] = $this->formatApolloPerson($p, $existing);
        }

        $this->json([
            'success' => true,
            'people' => $out,
            'pagination' => [
                'page' => $pagination['page'] ?? ($filters['page'] ?? 1),
                'per_page' => $pagination['per_page'] ?? ($filters['per_page'] ?? 25),
                'total_entries' => $pagination['total_entries'] ?? count($out),
                'total_pages' => $pagination['total_pages'] ?? 1,
            ],
        ]);
    }

    /**
     * API: pesquisa de organizações (empresas) no Apollo.
     * POST crm/apolloSearchOrganizations
     */
    public function apolloSearchOrganizations()
    {
        $this->requireRole($this->captureRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado.'], 400);

        $filters = $this->collectOrganizationFilters();
        $res = $apollo->searchOrganizations($filters);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.'], 502);

        $data = $res['data'] ?? [];
        $orgs = $data['organizations'] ?? ($data['accounts'] ?? []);
        $pagination = $data['pagination'] ?? [];

        $this->json([
            'success' => true,
            'organizations' => $orgs,
            'pagination' => [
                'page' => $pagination['page'] ?? ($filters['page'] ?? 1),
                'per_page' => $pagination['per_page'] ?? ($filters['per_page'] ?? 25),
                'total_entries' => $pagination['total_entries'] ?? count($orgs),
                'total_pages' => $pagination['total_pages'] ?? 1,
            ],
        ]);
    }

    /**
     * API: enriquece (revela e-mail/telefone) um lead capturado.
     * POST crm/apolloEnrich/{apolloLeadId}
     * Body opcional: reveal_personal_emails, reveal_phone_number, webhook_url
     */
    public function apolloEnrich($id = null)
    {
        $this->requireRole($this->captureRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $leadModel = new ApolloLead();
        $lead = $leadModel->findById($id);
        if (!$lead) $this->json(['error' => 'Lead não encontrado'], 404);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado.'], 400);

        $params = ['id' => $lead['apollo_id']];
        $params['reveal_personal_emails'] = !empty($_POST['reveal_personal_emails']);
        // Telefone exige webhook_url configurado; só habilita se veio uma URL válida
        $webhook = trim($_POST['webhook_url'] ?? '');
        if (!empty($_POST['reveal_phone_number']) && $webhook !== '') {
            $params['reveal_phone_number'] = true;
            $params['webhook_url'] = $webhook;
        }

        $res = $apollo->enrichPerson($params);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha ao enriquecer.'], 502);

        $person = $res['data']['person'] ?? ($res['data']['people'][0] ?? null);
        if (!$person) $this->json(['error' => 'Apollo não encontrou dados para este lead.'], 404);

        $user = $this->currentUser();
        $localId = $leadModel->upsertFromApollo($person, $user['id']);
        $updated = $leadModel->findById($localId);

        $this->json(['success' => true, 'lead' => $this->formatApolloPerson($person, $updated)]);
    }

    /**
     * API: importa leads capturados para "Meus Leads" (whatsapp_contacts).
     * POST crm/apolloImport  Body: ids[] (ids locais em apollo_leads)
     */
    public function apolloImport()
    {
        $this->requireRole($this->captureRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $ids = $_POST['ids'] ?? [];
        if (is_string($ids)) $ids = array_filter(explode(',', $ids));
        if (empty($ids)) $this->json(['error' => 'Selecione ao menos um lead.'], 400);

        $user = $this->currentUser();
        $leadModel = new ApolloLead();
        $contactModel = new WhatsappContact();

        // Opções extras: adicionar ao board (coluna) e/ou iniciar sequência
        $columnId = !empty($_POST['column_id']) ? intval($_POST['column_id']) : null;
        $sequenceId = !empty($_POST['sequence_id']) ? intval($_POST['sequence_id']) : null;

        $resolver = new LeadResolver();
        $imported = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            $lead = $leadModel->findById(intval($id));
            if (!$lead) { $skipped++; continue; }
            if (!empty($lead['contact_id'])) { $skipped++; continue; } // já importado

            $name = $lead['full_name'] ?: trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
            $name = $name ?: ($lead['organization_name'] ?? 'Lead Apollo');

            $notesParts = [];
            if (!empty($lead['title'])) $notesParts[] = 'Cargo: ' . $lead['title'];
            if (!empty($lead['organization_name'])) $notesParts[] = 'Empresa: ' . $lead['organization_name'];
            if (!empty($lead['linkedin_url'])) $notesParts[] = 'LinkedIn: ' . $lead['linkedin_url'];

            // Identidade única via LeadResolver (dedup por e-mail/telefone)
            $contactId = $resolver->resolve([
                'name' => $name,
                'email' => $lead['email'] ?? null,
                'phone' => $lead['phone'] ?? null,
                'company' => $lead['organization_name'] ?? null,
                'source' => 'apollo',
                'assigned_to' => $user['id'],
                'briefing' => [
                    'need' => $lead['organization_industry'] ?? null,
                    'notes' => implode(' | ', $notesParts) ?: null,
                ],
            ], $user['id']);

            if (!$contactId) {
                $this->json(['error' => 'Não há uma instância de WhatsApp cadastrada para vincular os leads. Conecte o WhatsApp antes de importar.'], 400);
            }

            // Board opcional: cria card na coluna escolhida
            if ($columnId) {
                $this->boardModel->createCard([
                    'column_id' => $columnId,
                    'title' => $name,
                    'contact_id' => $contactId,
                    'created_by' => $user['id'],
                    'assigned_to' => $user['id'],
                ]);
            }

            // Sequência opcional
            if ($sequenceId) {
                (new SequenceEngine())->enroll($sequenceId, $contactId, $user['id']);
            }

            $leadModel->markImported($lead['id'], $contactId, $user['id']);
            $imported++;
        }

        $this->json(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
    }

    /**
     * API: lista os leads capturados (staging) com filtros/paginação.
     * GET crm/apolloLeads?search=&status=&page=
     */
    public function apolloLeads()
    {
        $this->requireRole($this->captureRoles);

        $perPage = 25;
        $page = max(1, intval($_GET['page'] ?? 1));
        $filters = [
            'search' => trim($_GET['search'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $leadModel = new ApolloLead();
        $leads = $leadModel->getList($filters);
        $total = $leadModel->countList($filters);

        $out = array_map(fn($l) => $this->formatStoredLead($l), $leads);
        $this->json([
            'success' => true,
            'leads' => $out,
            'page' => $page,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    /**
     * API: verifica a configuração/saúde da integração Apollo.
     * GET crm/apolloStatus
     */
    public function apolloStatus()
    {
        $this->requireRole($this->captureRoles);
        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) {
            $this->json(['configured' => false, 'healthy' => false, 'error' => 'API key não configurada.']);
        }
        $res = $apollo->health();
        $healthy = $res['success'] && !empty($res['data']['is_logged_in'] ?? $res['success']);
        $this->json(['configured' => true, 'healthy' => (bool)$healthy, 'status' => $res['status'] ?? null]);
    }

    /**
     * API: executa o diagnóstico de todos os endpoints Apollo e retorna
     * request/response/erro de cada um para depuração.
     * POST crm/apolloDiagnostics  (restrito a super_admin)
     */
    public function apolloDiagnostics()
    {
        $this->requireRole(['super_admin']);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) {
            $this->json(['error' => 'Apollo não configurado. Informe a API key em Configurações.'], 400);
        }

        $results = $apollo->runDiagnostics();

        $total = count($results);
        $ok = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($results as $r) {
            if (!empty($r['skipped'])) { $skipped++; continue; }
            if ($r['success']) $ok++; else $failed++;
        }

        $this->json([
            'success' => true,
            'summary' => [
                'total' => $total,
                'ok' => $ok,
                'failed' => $failed,
                'skipped' => $skipped,
                'ran_at' => date('Y-m-d H:i:s'),
            ],
            'results' => $results,
        ]);
    }

    /**
     * API: lista de estados (UF) do Brasil via IBGE (proxy, evita CORS).
     * GET crm/ibgeStates
     */
    public function ibgeStates()
    {
        $this->requireRole($this->captureRoles);

        // 1) Fonte primária: arquivo local completo (app/data/br_states_cities.json)
        $local = $this->loadBrLocalities();
        if (!empty($local)) {
            $states = [];
            foreach ($local as $uf => $info) {
                $states[] = ['id' => $uf, 'uf' => $uf, 'name' => $info['name'] ?? $uf];
            }
            $this->json(['success' => true, 'states' => $states, 'source' => 'local']);
        }

        // 2) Fallback: API do IBGE
        $data = $this->ibgeGet('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome');
        if (is_array($data) && !empty($data)) {
            $states = array_map(fn($s) => [
                'id' => $s['id'],
                'uf' => $s['sigla'],
                'name' => $s['nome'],
            ], $data);
            $this->json(['success' => true, 'states' => $states, 'source' => 'ibge']);
        }

        $this->json(['error' => 'Não foi possível carregar os estados.'], 502);
    }

    /**
     * API: municípios de uma UF. Usa o arquivo local; se ausente, consulta o IBGE.
     * GET crm/ibgeCities/{uf}
     */
    public function ibgeCities($uf = null)
    {
        $this->requireRole($this->captureRoles);
        $uf = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$uf));
        if (strlen($uf) !== 2) $this->json(['error' => 'UF inválida'], 400);

        // 1) Arquivo local
        $local = $this->loadBrLocalities();
        if (!empty($local[$uf]['cities'])) {
            $this->json(['success' => true, 'uf' => $uf, 'cities' => $local[$uf]['cities'], 'source' => 'local']);
        }

        // 2) Fallback IBGE
        $data = $this->ibgeGet("https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios?orderBy=nome");
        if (is_array($data)) {
            $cities = array_map(fn($c) => $c['nome'], $data);
            $this->json(['success' => true, 'uf' => $uf, 'cities' => $cities, 'source' => 'ibge']);
        }

        $this->json(['error' => 'Não foi possível carregar as cidades.'], 502);
    }

    /**
     * Carrega o dataset local de estados/cidades (cacheado em memória por request).
     * @return array UF => ['name'=>..., 'cities'=>[...]]
     */
    private function loadBrLocalities()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $file = APP_PATH . '/data/br_states_cities.json';
        if (is_file($file)) {
            $decoded = json_decode(file_get_contents($file), true);
            if (is_array($decoded)) { $cache = $decoded; return $cache; }
        }
        $cache = [];
        return $cache;
    }

    /**
     * GET simples ao IBGE com cache em arquivo (24h) para reduzir chamadas externas.
     * Tenta cURL e, se indisponível/falho, faz fallback para file_get_contents.
     * @return array|null  Array decodificado ou null em falha.
     */
    private function ibgeGet($url)
    {
        $cacheDir = PUBLIC_PATH . '/uploads/ibge_cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        $cacheFile = $cacheDir . '/' . md5($url) . '.json';

        // Cache válido por 24h
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) return $cached;
        }

        $resp = false;
        $code = 0;
        $curlErr = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, // alguns servidores não têm bundle de CA atualizado
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'HelpdeskON/1.0',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
        }

        // Fallback: file_get_contents (quando cURL não existe ou falhou)
        if ($resp === false || $code >= 400 || $resp === '') {
            Logger::error('IBGE via cURL falhou, tentando fallback', ['url' => $url, 'http' => $code, 'curl_error' => $curlErr]);
            $ctx = stream_context_create([
                'http' => ['timeout' => 20, 'header' => "Accept: application/json\r\nUser-Agent: HelpdeskON/1.0\r\n"],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $alt = @file_get_contents($url, false, $ctx);
            if ($alt !== false && $alt !== '') {
                $resp = $alt;
            }
        }

        if ($resp === false || $resp === '') {
            // Último recurso: cache antigo
            if (is_file($cacheFile)) {
                $old = json_decode(file_get_contents($cacheFile), true);
                if (is_array($old)) return $old;
            }
            Logger::error('IBGE indisponível', ['url' => $url, 'http' => $code, 'curl_error' => $curlErr]);
            return null;
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            Logger::error('IBGE resposta não-JSON', ['url' => $url, 'sample' => substr((string)$resp, 0, 200)]);
            return null;
        }

        @file_put_contents($cacheFile, $resp);
        return $data;
    }

    // ===== Helpers Apollo =====

    /** Coleta filtros de pessoas do $_POST no formato esperado pelo ApolloApi. */
    private function collectPeopleFilters()
    {
        $f = [];
        // Strings
        foreach (['q_keywords', 'include_similar_titles'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') $f[$k] = $_POST[$k];
        }
        // Arrays (aceitam string separada por vírgula OU array do form)
        $arrayKeys = [
            'person_titles', 'person_seniorities', 'person_locations', 'organization_locations',
            'q_organization_domains_list', 'contact_email_status', 'organization_ids',
            'organization_num_employees_ranges',
            'currently_using_all_of_technology_uids', 'currently_using_any_of_technology_uids',
            'currently_not_using_any_of_technology_uids',
            'q_organization_job_titles', 'organization_job_locations',
        ];
        foreach ($arrayKeys as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '' && $_POST[$k] !== []) $f[$k] = $_POST[$k];
        }
        // Faixas min/max
        $f['revenue_range'] = ['min' => $_POST['revenue_min'] ?? '', 'max' => $_POST['revenue_max'] ?? ''];
        $f['organization_num_jobs_range'] = ['min' => $_POST['num_jobs_min'] ?? '', 'max' => $_POST['num_jobs_max'] ?? ''];
        $f['organization_job_posted_at_range'] = ['min' => $_POST['job_posted_min'] ?? '', 'max' => $_POST['job_posted_max'] ?? ''];
        // Paginação
        $f['page'] = intval($_POST['page'] ?? 1);
        $f['per_page'] = intval($_POST['per_page'] ?? 25);
        return $f;
    }

    /** Coleta filtros de organizações do $_POST. */
    private function collectOrganizationFilters()
    {
        $f = [];
        if (isset($_POST['q_organization_name']) && $_POST['q_organization_name'] !== '') {
            $f['q_organization_name'] = $_POST['q_organization_name'];
        }
        $arrayKeys = [
            'q_organization_keyword_tags', 'q_organization_domains_list',
            'organization_locations', 'organization_not_locations',
            'organization_num_employees_ranges', 'currently_using_any_of_technology_uids',
            'organization_ids', 'q_organization_job_titles', 'organization_job_locations',
        ];
        foreach ($arrayKeys as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '' && $_POST[$k] !== []) $f[$k] = $_POST[$k];
        }
        $f['revenue_range'] = ['min' => $_POST['revenue_min'] ?? '', 'max' => $_POST['revenue_max'] ?? ''];
        $f['latest_funding_amount_range'] = ['min' => $_POST['latest_funding_min'] ?? '', 'max' => $_POST['latest_funding_max'] ?? ''];
        $f['total_funding_range'] = ['min' => $_POST['total_funding_min'] ?? '', 'max' => $_POST['total_funding_max'] ?? ''];
        $f['organization_num_jobs_range'] = ['min' => $_POST['num_jobs_min'] ?? '', 'max' => $_POST['num_jobs_max'] ?? ''];
        $f['page'] = intval($_POST['page'] ?? 1);
        $f['per_page'] = intval($_POST['per_page'] ?? 25);
        return $f;
    }

    /**
     * Formata um person do Apollo + registro local para exibição no painel.
     * $stored é a linha em apollo_leads (pode conter o e-mail já revelado).
     */
    private function formatApolloPerson($person, $stored = null)
    {
        $org = $person['organization'] ?? ($person['account'] ?? []);
        $email = $person['email'] ?? ($stored['email'] ?? null);
        $hasRealEmail = !empty($email) && stripos($email, 'email_not_unlocked') === false;

        return [
            'local_id' => $stored['id'] ?? null,
            'apollo_id' => $person['id'] ?? ($stored['apollo_id'] ?? null),
            'name' => $person['name'] ?? ($stored['full_name'] ?? null),
            'first_name' => $person['first_name'] ?? null,
            'last_name' => $person['last_name'] ?? null,
            'title' => $person['title'] ?? ($stored['title'] ?? null),
            'seniority' => $person['seniority'] ?? null,
            'email' => $hasRealEmail ? $email : null,
            'email_locked' => !$hasRealEmail,
            'email_status' => $person['email_status'] ?? ($stored['email_status'] ?? null),
            'phone' => $stored['phone'] ?? null,
            'linkedin_url' => $person['linkedin_url'] ?? ($stored['linkedin_url'] ?? null),
            'organization_name' => $org['name'] ?? ($stored['organization_name'] ?? null),
            'organization_domain' => $org['primary_domain'] ?? ($stored['organization_domain'] ?? null),
            'organization_industry' => $org['industry'] ?? ($stored['organization_industry'] ?? null),
            'city' => $person['city'] ?? ($stored['city'] ?? null),
            'state' => $person['state'] ?? ($stored['state'] ?? null),
            'country' => $person['country'] ?? ($stored['country'] ?? null),
            'is_enriched' => (int)($stored['is_enriched'] ?? ($hasRealEmail ? 1 : 0)),
            'imported' => !empty($stored['contact_id']),
            'contact_id' => $stored['contact_id'] ?? null,
        ];
    }

    /** Formata uma linha de apollo_leads (staging) para exibição. */
    private function formatStoredLead($l)
    {
        return [
            'local_id' => $l['id'],
            'apollo_id' => $l['apollo_id'],
            'name' => $l['full_name'],
            'title' => $l['title'],
            'seniority' => $l['seniority'],
            'email' => $l['email'],
            'email_locked' => empty($l['email']),
            'email_status' => $l['email_status'],
            'phone' => $l['phone'],
            'linkedin_url' => $l['linkedin_url'],
            'organization_name' => $l['organization_name'],
            'organization_domain' => $l['organization_domain'],
            'organization_industry' => $l['organization_industry'],
            'city' => $l['city'],
            'state' => $l['state'],
            'country' => $l['country'],
            'is_enriched' => (int)$l['is_enriched'],
            'imported' => !empty($l['contact_id']),
            'contact_id' => $l['contact_id'],
            'imported_at' => $l['imported_at'],
        ];
    }
}

