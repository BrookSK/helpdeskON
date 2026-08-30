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

        $boards = $this->boardModel->getAll($user['role'] ?? null);

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
     * API: salva o briefing comercial diretamente pelo card do board.
     * POST crm/saveCardBriefing/{cardId}  body: bf_* (campos do briefing)
     */
    public function saveCardBriefing($cardId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cardId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $card = $this->boardModel->findCard($cardId);
        if (!$card) $this->json(['error' => 'Card não encontrado'], 404);
        if (empty($card['contact_id'])) {
            $this->json(['error' => 'Este card não está vinculado a um contato do CRM.'], 400);
        }

        $user = $this->currentUser();
        $bfKeys = ['need', 'main_pain', 'current_solution', 'expected_goal', 'urgency', 'investment_range',
                   'decision_level', 'lead_temperature', 'lead_source', 'main_objection', 'next_step', 'next_contact_date', 'notes'];
        $bf = [];
        foreach ($bfKeys as $k) {
            if (isset($_POST['bf_' . $k])) $bf[$k] = trim($_POST['bf_' . $k]) ?: null;
        }
        if (isset($bf['lead_temperature']) && !in_array($bf['lead_temperature'], ['frio', 'morno', 'quente'])) {
            $bf['lead_temperature'] = null;
        }

        (new WhatsappContact())->saveBriefing($card['contact_id'], $bf, $user['id']);
        $this->json(['success' => true, 'briefing' => (new WhatsappContact())->getBriefing($card['contact_id'])]);
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
        $newPhone = null;
        if (isset($_POST['phone'])) {
            $newPhone = preg_replace('/\D/', '', $_POST['phone']) ?: null;
            $data['phone'] = $newPhone;
        }
        if (isset($_POST['lead_email'])) {
            $em = trim($_POST['lead_email']);
            $data['lead_email'] = ($em && filter_var($em, FILTER_VALIDATE_EMAIL)) ? $em : null;
        }

        // Ao cadastrar um telefone real num lead captado (JID sintético lead_/manual_),
        // regenera o remote_jid para o JID real — assim ele passa a aparecer no chat.
        $isSynthetic = preg_match('/^(lead_|manual_)/', (string) $contact['remote_jid']);
        if ($newPhone && $isSynthetic) {
            $realJid = $newPhone . '@s.whatsapp.net';
            // Evita colidir com um contato já existente com esse JID na mesma instância
            $dup = Database::getInstance()->fetch(
                "SELECT id FROM whatsapp_contacts WHERE instance_id = ? AND remote_jid = ? AND id <> ?",
                [$contact['instance_id'], $realJid, $contactId]
            );
            if (!$dup) $data['remote_jid'] = $realJid;
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

        // Métricas de e-mail (envios, aberturas, respostas, melhor e-mail).
        // Sempre exibe a seção; se o módulo ainda não foi migrado, mostra zeros.
        $emailStats = [
            'sent' => 0, 'opened' => 0, 'clicked' => 0, 'replied' => 0, 'bounced' => 0,
            'manual' => 0, 'sequence' => 0, 'open_rate' => 0, 'click_rate' => 0, 'reply_rate' => 0, 'top_email' => null,
        ];
        $emailTrend = [];

        // Detecta a existência da tabela de forma independente (não depende da query)
        $emailModuleReady = false;
        try {
            $chk = Database::getInstance()->fetch(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_messages'"
            );
            $emailModuleReady = (bool) $chk;
        } catch (\Throwable $e) { $emailModuleReady = false; }

        $abResults = [];
        if ($emailModuleReady) {
            try {
                $seqModel = new EmailSequence();
                $emailStats = $seqModel->emailDashboard();
                $emailTrend = $seqModel->emailMonthlyTrend(6);
                $abResults = $seqModel->abResults();
            } catch (\Throwable $e) {
                // Tabela existe mas a query falhou: loga e mantém zeros (não escondemos a seção)
                Logger::error('emailDashboard falhou', ['error' => $e->getMessage()]);
            }
        }

        $this->view('crm/dashboard', [
            'user' => $user, 'stats' => $stats, 'trend' => $trend,
            'emailStats' => $emailStats, 'emailTrend' => $emailTrend,
            'emailModuleReady' => $emailModuleReady, 'abResults' => $abResults,
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
        $user = $this->currentUser();
        $boards = $this->boardModel->getAll($user['role'] ?? null);

        // Incluir colunas de cada board
        foreach ($boards as &$board) {
            $board['columns'] = $this->boardModel->getColumns($board['id']);
        }

        $this->json($boards);
    }

    /**
     * API: lista de sequências ativas (para o seletor ao importar leads).
     * GET crm/sequencesList
     */
    public function sequencesList()
    {
        $this->requireRole($this->captureRoles);
        $rows = [];
        try {
            $rows = (new EmailSequence())->all();
        } catch (\Throwable $e) { $rows = []; }
        $out = array_map(fn($s) => [
            'id' => (int)$s['id'],
            'name' => $s['name'] ?? ('Sequência #' . $s['id']),
            'active' => (int)($s['active'] ?? $s['is_active'] ?? 1),
        ], $rows);
        $this->json(['success' => true, 'sequences' => $out]);
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
        // currentUser() vem da sessão e não traz apollo_daily_credits; busca o registro completo.
        $fullUser = (new User())->findById($user['id']) ?: $user;
        $credit = new ApolloCreditUsage();
        $chk = $credit->check($fullUser, 0);
        $this->view('crm/capture', [
            'user' => $user,
            'apolloConfigured' => $apollo->isConfigured(),
            'isAdmin' => $user['role'] === 'super_admin',
            'creditLimit' => $chk['limit'],
            'creditUsed' => $chk['used'],
            'creditRemaining' => $chk['remaining'],
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

        // Cada pesquisa consome 1 crédito do limite diário.
        $user = (new User())->findById($this->currentUser()['id']) ?: $this->currentUser();
        $credit = new ApolloCreditUsage();
        $chk = $credit->check($user, 1);
        if (!$chk['allowed']) {
            $this->json(['error' => "Você atingiu o limite diário de {$chk['limit']} crédito(s) Apollo. Tente novamente amanhã.", 'credits' => ['limit' => $chk['limit'], 'used' => $chk['used'], 'remaining' => $chk['remaining']]], 429);
        }

        $filters = $this->collectPeopleFilters();
        $res = $apollo->searchPeople($filters);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.'], 502);

        // Pesquisa bem-sucedida: debita 1 crédito.
        $credit->consume($user['id'], 1);

        $data = $res['data'] ?? [];
        // A Apollo separa resultados "fora da conta" (people) dos "salvos na conta" (contacts).
        $people = $this->mergeApolloLists($data, 'people', 'contacts');
        $pagination = $data['pagination'] ?? [];

        // Persiste em staging para consulta/importação posterior
        $leadModel = new ApolloLead();
        $out = [];
        foreach ($people as $p) {
            $localId = $leadModel->upsertFromApollo($p, $user['id']);
            $existing = $localId ? $leadModel->findById($localId) : null;
            $out[] = $this->formatApolloPerson($p, $existing);
        }

        $after = $credit->check($user, 0);
        $this->json([
            'success' => true,
            'people' => $out,
            'pagination' => [
                'page' => $pagination['page'] ?? ($filters['page'] ?? 1),
                'per_page' => $pagination['per_page'] ?? ($filters['per_page'] ?? 25),
                'total_entries' => $pagination['total_entries'] ?? count($out),
                'total_pages' => $pagination['total_pages'] ?? 1,
            ],
            'credits' => ['limit' => $after['limit'], 'used' => $after['used'], 'remaining' => $after['remaining']],
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

        // Cada pesquisa consome 1 crédito do limite diário.
        $user = (new User())->findById($this->currentUser()['id']) ?: $this->currentUser();
        $credit = new ApolloCreditUsage();
        $chk = $credit->check($user, 1);
        if (!$chk['allowed']) {
            $this->json(['error' => "Você atingiu o limite diário de {$chk['limit']} crédito(s) Apollo. Tente novamente amanhã.", 'credits' => ['limit' => $chk['limit'], 'used' => $chk['used'], 'remaining' => $chk['remaining']]], 429);
        }

        $filters = $this->collectOrganizationFilters();
        $res = $apollo->searchOrganizations($filters);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.'], 502);

        // Pesquisa bem-sucedida: debita 1 crédito.
        $credit->consume($user['id'], 1);

        $data = $res['data'] ?? [];
        // Empresas já salvas na conta Apollo voltam em "accounts" (não em "organizations").
        $orgs = $this->mergeApolloLists($data, 'organizations', 'accounts');
        $pagination = $data['pagination'] ?? [];

        $after = $credit->check($user, 0);
        $this->json([
            'success' => true,
            'organizations' => $orgs,
            'pagination' => [
                'page' => $pagination['page'] ?? ($filters['page'] ?? 1),
                'per_page' => $pagination['per_page'] ?? ($filters['per_page'] ?? 25),
                'total_entries' => $pagination['total_entries'] ?? count($orgs),
                'total_pages' => $pagination['total_pages'] ?? 1,
            ],
            'credits' => ['limit' => $after['limit'], 'used' => $after['used'], 'remaining' => $after['remaining']],
        ]);
    }

    /**
     * API: LIBERAR DADOS — revela e-mail (síncrono) e solicita telefone (assíncrono
     * via webhook) de um lead capturado. Consome créditos do Apollo.
     * POST crm/apolloReveal/{apolloLeadId}
     * Body opcional: reveal_phone (1 = também solicita o telefone)
     */
    public function apolloReveal($id = null)
    {
        $this->requireRole($this->captureRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $leadModel = new ApolloLead();
        $lead = $leadModel->findById($id);
        if (!$lead) $this->json(['error' => 'Lead não encontrado'], 404);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado.'], 400);

        // Controle de crédito diário. Liberar e-mail+telefone custa 8 créditos;
        // exige esse saldo disponível antes de chamar o Apollo.
        $user = (new User())->findById($this->currentUser()['id']) ?: $this->currentUser();
        $credit = new ApolloCreditUsage();
        $chk = $credit->check($user, ApolloCreditUsage::COST_MOBILE);
        if (!$chk['allowed']) {
            $this->json(['error' => "Créditos insuficientes: liberar contato custa " . ApolloCreditUsage::COST_MOBILE . " créditos e você tem {$chk['remaining']} restante(s) hoje. Tente novamente amanhã."], 429);
        }

        // O /people/match revela o e-mail com mais confiabilidade quando recebe
        // os identificadores da pessoa (não apenas o apollo_id).
        $params = [
            'id' => $lead['apollo_id'],
            'first_name' => $lead['first_name'] ?? null,
            'last_name' => $lead['last_name'] ?? null,
            'name' => $lead['full_name'] ?? null,
            'organization_name' => $lead['organization_name'] ?? null,
            'domain' => $lead['organization_domain'] ?? null,
            'linkedin_url' => $lead['linkedin_url'] ?? null,
            'reveal_personal_emails' => true,
        ];

        // Telefone: revelado de forma ASSÍNCRONA pela Apollo, exige webhook_url.
        $wantPhone = !empty($_POST['reveal_phone']);
        $webhookUrl = $this->apolloWebhookUrl();
        if ($wantPhone && $webhookUrl) {
            $params['reveal_phone_number'] = true;
            $params['webhook_url'] = $webhookUrl;
        }

        $res = $apollo->enrichPerson($params);
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha ao liberar os dados.'], 502);

        $person = $res['data']['person'] ?? ($res['data']['people'][0] ?? null);
        if (!$person) $this->json(['error' => 'Apollo não encontrou dados para este lead.'], 404);

        $localId = $leadModel->upsertFromApollo($person, $user['id']);
        $updated = $leadModel->findById($localId);
        $formatted = $this->formatApolloPerson($person, $updated);

        // Se o lead JÁ foi enviado para Meus Leads, propaga o e-mail/telefone revelado
        // para o contato do CRM. Sem isso, o lead importado ficaria sem e-mail e nunca
        // entraria na captação automática (que exige lead_email).
        if (!empty($updated['contact_id'])) {
            $this->propagateRevealToContact((int)$updated['contact_id'], $updated);
        }

        // Liberar dados (e-mail + telefone) consome 8 créditos do limite diário.
        $credit->consume($user['id'], ApolloCreditUsage::COST_MOBILE);

        // Se pedimos o telefone, guarda o request_id para casar com o webhook.
        $phonePending = false;
        if ($wantPhone && $webhookUrl) {
            $requestId = $person['id'] ?? ($res['data']['request_id'] ?? null);
            $leadModel->update($localId, [
                'phone_status' => 'pending',
                'phone_request_id' => $requestId,
                'phone_requested_by' => $user['id'],
            ]);
            $phonePending = true;
        }

        $formatted['phone_pending'] = $phonePending;

        $warnings = [];
        if (empty($formatted['email'])) {
            $warnings[] = 'E-mail não retornado (indisponível no plano atual ou contato sem e-mail verificado).';
        }
        if ($wantPhone && !$webhookUrl) {
            $warnings[] = 'Para revelar telefones, configure a URL de webhook do Apollo em Configurações.';
        } elseif ($phonePending) {
            $warnings[] = 'Telefone solicitado. O número chega em instantes via Apollo e a lista será atualizada automaticamente.';
        }

        $out = ['success' => true, 'lead' => $formatted];
        if ($warnings) $out['warning'] = implode("\n", $warnings);
        // Créditos restantes hoje (-1 = ilimitado)
        $after = $credit->check($user, 0);
        $out['credits'] = ['limit' => $after['limit'], 'used' => $after['used'], 'remaining' => $after['remaining']];
        $this->json($out);
    }

    /**
     * Propaga e-mail/telefone revelados no Apollo para o contato do CRM já
     * importado (whatsapp_contacts), sem sobrescrever dados já preenchidos.
     * Garante que o lead fique elegível à captação automática (que exige e-mail).
     */
    private function propagateRevealToContact($contactId, $lead)
    {
        $db = Database::getInstance();
        $contact = $db->fetch("SELECT * FROM whatsapp_contacts WHERE id = ? LIMIT 1", [$contactId]);
        if (!$contact) return;

        $update = [];

        // E-mail real (ignora placeholders de e-mail bloqueado)
        $email = $lead['email'] ?? null;
        $isRealEmail = $email && stripos($email, 'email_not_unlocked') === false
            && filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($isRealEmail && empty($contact['lead_email'])) {
            $update['lead_email'] = mb_strtolower($email);
        }

        // Telefone (só preenche se o contato ainda não tem)
        if (!empty($lead['phone']) && empty($contact['phone'])) {
            $digits = preg_replace('/\D/', '', (string) $lead['phone']) ?: null;
            if ($digits) {
                $update['phone'] = $digits;
                // Se o JID é sintético, regenera para o número real (aparece no chat)
                if (preg_match('/^(lead_|manual_)/', (string) $contact['remote_jid'])) {
                    $realJid = $digits . '@s.whatsapp.net';
                    $dup = $db->fetch(
                        "SELECT id FROM whatsapp_contacts WHERE instance_id = ? AND remote_jid = ? AND id <> ?",
                        [$contact['instance_id'], $realJid, $contactId]
                    );
                    if (!$dup) $update['remote_jid'] = $realJid;
                }
            }
        }

        if (!empty($update)) {
            $db->update('whatsapp_contacts', $update, 'id = ?', [$contactId]);
        }
    }

    /**
     * Monta a URL pública do webhook de telefone do Apollo, anexando o token
     * de segurança configurado (se houver). Retorna null se o app não expõe URL.
     */
    private function apolloWebhookUrl()
    {
        $configured = trim((string) Config::get('app_public_url'));
        $base = $configured !== '' ? rtrim($configured, '/') : rtrim(baseUrl(''), '/');
        if ($base === '' || stripos($base, 'http') !== 0) return null;
        $token = trim((string) Config::get('apollo_webhook_token'));
        $url = $base . '/crm/apolloPhoneWebhook';
        if ($token !== '') $url .= '?token=' . rawurlencode($token);
        return $url;
    }

    /**
     * Endpoint PÚBLICO (sem sessão): recebe o retorno assíncrono do Apollo com
     * o telefone revelado e grava no lead correspondente.
     * POST crm/apolloPhoneWebhook?token=...
     */
    public function apolloPhoneWebhook()
    {
        // Validação por token (se configurado)
        $expected = trim((string) Config::get('apollo_webhook_token'));
        if ($expected !== '' && ($_GET['token'] ?? '') !== $expected) {
            $this->json(['error' => 'unauthorized'], 401);
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) { http_response_code(200); echo 'ignored'; exit; }

        // O Apollo pode enviar people[] ou um person único, com request_id.
        $people = $payload['people'] ?? (isset($payload['person']) ? [$payload['person']] : []);
        if (empty($people) && isset($payload['id'])) $people = [$payload];
        $requestId = $payload['request_id'] ?? null;

        $leadModel = new ApolloLead();
        $resolved = 0;
        foreach ($people as $person) {
            if (!is_array($person)) continue;
            $phone = $this->extractApolloPhone($person);
            $apolloId = $person['id'] ?? null;

            // Localiza o lead por apollo_id ou por request_id salvo
            $lead = null;
            if ($apolloId) $lead = $leadModel->findByApolloId($apolloId);
            if (!$lead && $requestId) {
                $lead = Database::getInstance()->fetch(
                    "SELECT * FROM apollo_leads WHERE phone_request_id = ? LIMIT 1", [$requestId]
                );
            }
            if (!$lead) continue;

            $update = ['phone_status' => 'received'];
            if ($phone) { $update['phone'] = $phone; $update['is_enriched'] = 1; }
            $leadModel->update($lead['id'], $update);

            // Propaga para o contato do CRM já importado, se houver
            if ($phone && !empty($lead['contact_id'])) {
                try { Database::getInstance()->update('whatsapp_contacts', ['phone' => $phone], 'id = ?', [$lead['contact_id']]); } catch (\Throwable $e) {}
            }
            $resolved++;
        }

        http_response_code(200);
        echo json_encode(['ok' => true, 'resolved' => $resolved]);
        exit;
    }

    // =====================================================
    // Automação de Prospecção Apollo (campanhas)
    // =====================================================

    /**
     * Tela de configuração das campanhas de prospecção automática (Apollo).
     * Somente super_admin.
     */
    public function prospecting()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $db = Database::getInstance();

        $campaigns = [];
        try {
            $campaigns = $db->fetchAll(
                "SELECT c.*, s.name AS sequence_name, b.name AS board_name, col.name AS column_name, u.name AS assigned_name,
                        (SELECT COUNT(*) FROM apollo_prospecting_log l WHERE l.campaign_id = c.id AND l.action='enrolled' AND DATE(l.created_at)=CURDATE()) AS captured_today,
                        (SELECT COUNT(*) FROM apollo_prospecting_log l WHERE l.campaign_id = c.id AND l.action='enrolled') AS captured_total
                 FROM apollo_campaigns c
                 LEFT JOIN email_sequences s ON c.sequence_id = s.id
                 LEFT JOIN crm_boards b ON c.board_id = b.id
                 LEFT JOIN crm_columns col ON c.column_id = col.id
                 LEFT JOIN users u ON c.assigned_to = u.id
                 ORDER BY c.id ASC"
            );
        } catch (\Throwable $e) {
            // Tabela ainda não criada — orienta rodar a migration
            $campaigns = null;
        }

        $boards = $this->boardModel->getAll();
        foreach ($boards as &$b) $b['columns'] = $this->boardModel->getColumns($b['id']);
        unset($b);

        $sequences = [];
        try { $sequences = (new EmailSequence())->all(); } catch (\Throwable $e) {}
        $team = (new User())->getByRoles(['super_admin', 'comercial']);
        $apollo = new ApolloApi();

        $this->view('crm/prospecting', [
            'user' => $user,
            'campaigns' => $campaigns,
            'boards' => $boards,
            'sequences' => $sequences,
            'team' => $team,
            'apolloConfigured' => $apollo->isConfigured(),
            'cronToken' => (string) Config::get('cron_token'),
            'baseUrl' => rtrim(baseUrl(''), '/'),
        ]);
    }

    /** Salva (cria/atualiza) uma campanha de prospecção. POST crm/saveCampaign */
    public function saveCampaign()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $db = Database::getInstance();
        $id = !empty($_POST['id']) ? intval($_POST['id']) : 0;

        $name = trim($_POST['name'] ?? '');
        if ($name === '') $this->json(['error' => 'Informe o nome da campanha.'], 400);

        // Origem dos leads: apollo (busca/reveal) ou my_leads (CRM existente)
        $leadSource = ($_POST['lead_source'] ?? 'apollo') === 'my_leads' ? 'my_leads' : 'apollo';

        // Filtros de busca: aceita JSON direto ou campos simples
        $searchFilters = $this->buildCampaignFilters();
        $icpRules = $this->buildCampaignIcp();
        $myLeadsFilters = $this->buildMyLeadsFilters();

        $data = [
            'name' => $name,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'lead_source' => $leadSource,
            'global_dedupe' => !empty($_POST['global_dedupe']) ? 1 : 0,
            'my_leads_filters' => json_encode($myLeadsFilters, JSON_UNESCAPED_UNICODE),
            'my_leads_ids' => json_encode($this->buildMyLeadsIds(), JSON_UNESCAPED_UNICODE),
            'sequence_id' => !empty($_POST['sequence_id']) ? intval($_POST['sequence_id']) : null,
            'auto_route' => !empty($_POST['auto_route']) ? 1 : 0,
            'sequence_id_email' => !empty($_POST['sequence_id_email']) ? intval($_POST['sequence_id_email']) : null,
            'sequence_id_whatsapp' => !empty($_POST['sequence_id_whatsapp']) ? intval($_POST['sequence_id_whatsapp']) : null,
            'sequence_id_mixed' => !empty($_POST['sequence_id_mixed']) ? intval($_POST['sequence_id_mixed']) : null,
            'board_id' => !empty($_POST['board_id']) ? intval($_POST['board_id']) : null,
            'column_id' => !empty($_POST['column_id']) ? intval($_POST['column_id']) : null,
            'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
            'search_filters' => json_encode($searchFilters, JSON_UNESCAPED_UNICODE),
            'icp_rules' => json_encode($icpRules, JSON_UNESCAPED_UNICODE),
            'min_score' => max(0, intval($_POST['min_score'] ?? 70)),
            'daily_target' => max(1, intval($_POST['daily_target'] ?? 12)),
            'search_per_page' => min(100, max(10, intval($_POST['search_per_page'] ?? 50))),
            'days_of_week' => trim($_POST['days_of_week'] ?? '1,2,3,4,5'),
            'window_start' => $this->normalizeTime($_POST['window_start'] ?? '08:00', '08:00:00'),
            'window_end' => $this->normalizeTime($_POST['window_end'] ?? '18:00', '18:00:00'),
            'reveal_email' => !empty($_POST['reveal_email']) ? 1 : 0,
            'reveal_phone' => !empty($_POST['reveal_phone']) ? 1 : 0,
        ];

        if ($id) {
            $db->update('apollo_campaigns', $data, 'id = ?', [$id]);
        } else {
            $data['created_by'] = $user['id'];
            $data['search_page'] = 1;
            $id = $db->insert('apollo_campaigns', $data);
        }
        $this->json(['success' => true, 'id' => $id]);
    }

    /** Ativa/desativa uma campanha. POST crm/toggleCampaign/{id} */
    public function toggleCampaign($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        $db = Database::getInstance();
        $c = $db->fetch("SELECT is_active FROM apollo_campaigns WHERE id = ?", [$id]);
        if (!$c) $this->json(['error' => 'Campanha não encontrada'], 404);
        $db->update('apollo_campaigns', ['is_active' => $c['is_active'] ? 0 : 1], 'id = ?', [$id]);
        $this->json(['success' => true, 'is_active' => $c['is_active'] ? 0 : 1]);
    }

    /** Exclui uma campanha. POST crm/deleteCampaign/{id} */
    public function deleteCampaign($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        Database::getInstance()->delete('apollo_campaigns', 'id = ?', [$id]);
        $this->json(['success' => true]);
    }

    /** Executa uma campanha agora (manual). POST crm/runCampaign/{id} */
    public function runCampaign($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);
        @set_time_limit(300);

        $db = Database::getInstance();
        $camp = $db->fetch("SELECT * FROM apollo_campaigns WHERE id = ?", [$id]);
        if (!$camp) $this->json(['error' => 'Campanha não encontrada'], 404);

        // Apollo só é obrigatório quando a fonte é Apollo (Meus Leads não consome API).
        if (($camp['lead_source'] ?? 'apollo') !== 'my_leads') {
            $apollo = new ApolloApi();
            if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado.'], 400);
        }

        $already = 0;
        try {
            $r = $db->fetch("SELECT COUNT(*) t FROM apollo_prospecting_log WHERE campaign_id=? AND action='enrolled' AND DATE(created_at)=CURDATE()", [$id]);
            $already = (int)($r['t'] ?? 0);
        } catch (\Throwable $e) {}
        $target = max(1, (int)$camp['daily_target'] - $already);

        try {
            $service = new ApolloProspectingService();
            // Disparo MANUAL: força a (re)inscrição/reinício, mesmo para leads que já
            // passaram pela sequência (o operador está pedindo explicitamente).
            $result = $service->runCampaign($camp, $target, true);
        } catch (\Throwable $e) {
            Logger::error('runCampaign manual', ['campaign' => $id, 'error' => $e->getMessage()]);
            $this->json(['error' => 'Erro ao executar: ' . $e->getMessage()], 500);
        }
        $this->json(['success' => empty($result['error']), 'result' => $result]);
    }

    /**
     * Executa os passos pendentes das sequências agora (mesmo motor do cron
     * /cron/runSequences), sem depender do agendamento no servidor.
     * Protegido por login super_admin. POST crm/runSequencesNow
     */
    public function runSequencesNow()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);
        @set_time_limit(300);

        $stats = (new SequenceEngine())->processDue(200);
        $this->json(['success' => true, 'engine' => $stats, 'replies_detected' => 0]);
    }

    /**
     * Reexecuta uma etapa específica de um participante (para testar/forçar o erro
     * sem refazer o fluxo inteiro). POST crm/runSequenceNode
     * body: participant_id, node_id
     */
    public function runSequenceNode()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $participantId = intval($_POST['participant_id'] ?? 0);
        $nodeId = trim($_POST['node_id'] ?? '');
        if (!$participantId || $nodeId === '') $this->json(['error' => 'Participante e etapa são obrigatórios.'], 400);

        @set_time_limit(120);
        $res = (new SequenceEngine())->runSingleNode($participantId, $nodeId);
        if (empty($res['success'])) $this->json(['error' => $res['error'] ?? 'Falha ao reexecutar etapa.'], 400);
        $this->json($res);
    }

    /** Log recente de uma campanha (para acompanhar). GET crm/campaignLog/{id} */
    public function campaignLog($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->json(['error' => 'ID obrigatório'], 400);
        $rows = Database::getInstance()->fetchAll(
            "SELECT action, detail, credits, created_at FROM apollo_prospecting_log WHERE campaign_id = ? ORDER BY id DESC LIMIT 50",
            [$id]
        );
        $this->json(['success' => true, 'log' => $rows]);
    }

    /**
     * Diagnóstico: simula a abertura do último e-mail de sequência enviado,
     * registrando via o mesmo caminho do pixel. Confirma se o tracking grava.
     * POST crm/testEmailOpen
     */
    public function testEmailOpen()
    {
        $this->requireRole(['super_admin']);
        $db = Database::getInstance();
        // Prioriza uma mensagem COM variante A/B (para a taxa A/B refletir); senão, a última.
        $msg = $db->fetch("SELECT track_token, recipient_email FROM email_messages WHERE origin='sequence' AND track_token IS NOT NULL AND ab_variant IS NOT NULL ORDER BY id DESC LIMIT 1");
        if (!$msg) {
            $msg = $db->fetch("SELECT track_token, recipient_email FROM email_messages WHERE origin='sequence' AND track_token IS NOT NULL ORDER BY id DESC LIMIT 1");
        }
        if (!$msg) $this->json(['error' => 'Nenhum e-mail de sequência enviado ainda.'], 404);
        try {
            (new EmailMessageService())->registerOpen($msg['track_token'], '127.0.0.1', 'DiagnosticoAberturaManual');
        } catch (\Throwable $e) {
            $this->json(['error' => 'Falha ao registrar: ' . $e->getMessage()], 500);
        }
        $base = trim((string) Config::get('app_public_url')) ?: rtrim(baseUrl(''), '/');
        $this->json([
            'success' => true,
            'message' => 'Abertura simulada registrada para ' . $msg['recipient_email'] . '. Atualize os logs/dashboard.',
            'pixel_url' => rtrim($base, '/') . '/track/open/' . $msg['track_token'],
        ]);
    }

    /**
     * Logs de execução das sequências de prospecção (etapas concluídas) + erros.
     * GET crm/prospectingExecLog
     */
    public function prospectingExecLog()
    {
        $this->requireRole(['super_admin']);
        $db = Database::getInstance();

        // Etapas executadas por participante das sequências de prospecção (Apollo).
        $steps = [];
        try {
            $steps = $db->fetchAll(
                "SELECT e.executed_at, e.participant_id, e.node_id, e.node_type, e.result, e.detail,
                        s.name AS sequence_name, wc.contact_name, wc.lead_email, wc.phone,
                        sp.status AS participant_status, sp.stop_reason, sp.ab_variant
                 FROM sequence_executions e
                 JOIN sequence_participants sp ON e.participant_id = sp.id
                 JOIN email_sequences s ON sp.sequence_id = s.id
                 JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
                 WHERE (s.name LIKE '%Apollo%' OR s.name LIKE '%ON Solu%')
                 ORDER BY e.id DESC
                 LIMIT 200"
            );
        } catch (\Throwable $e) { $steps = []; }

        // Participantes ativos/recentes das sequências de prospecção
        $participants = [];
        try {
            $participants = $db->fetchAll(
                "SELECT sp.id, sp.status, sp.current_node, sp.next_run_at, sp.stop_reason, sp.ab_variant,
                        sp.started_at, sp.finished_at, s.name AS sequence_name,
                        wc.contact_name, wc.lead_email, wc.phone
                 FROM sequence_participants sp
                 JOIN email_sequences s ON sp.sequence_id = s.id
                 JOIN whatsapp_contacts wc ON sp.contact_id = wc.id
                 WHERE (s.name LIKE '%Apollo%' OR s.name LIKE '%ON Solu%')
                 ORDER BY sp.updated_at DESC
                 LIMIT 50"
            );
        } catch (\Throwable $e) { $participants = []; }

        // Erros recentes do arquivo de log da aplicação (só linhas de sequência/apollo)
        $errors = [];
        try {
            $file = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/app-error.log';
            if (is_file($file)) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $lines = array_slice($lines, -400);
                foreach (array_reverse($lines) as $ln) {
                    if (stripos($ln, 'Sequence') !== false || stripos($ln, 'Apollo') !== false
                        || stripos($ln, 'reveal') !== false || stripos($ln, 'runProspecting') !== false) {
                        $errors[] = $ln;
                        if (count($errors) >= 80) break;
                    }
                }
            }
        } catch (\Throwable $e) { $errors = []; }

        // Log geral de prospecção (buscas, reveals, enrolls, testes)
        $prospectLog = [];
        try {
            $prospectLog = $db->fetchAll(
                "SELECT l.action, l.detail, l.credits, l.created_at, wc.contact_name, wc.lead_email
                 FROM apollo_prospecting_log l
                 LEFT JOIN whatsapp_contacts wc ON l.contact_id = wc.id
                 ORDER BY l.id DESC LIMIT 100"
            );
        } catch (\Throwable $e) { $prospectLog = []; }

        // E-mails enviados pelas sequências: status de abertura/clique/resposta por mensagem
        $emails = [];
        try {
            $emails = $db->fetchAll(
                "SELECT m.subject, m.recipient_email, m.ab_variant, m.status,
                        m.open_count, m.first_open_at, m.click_count, m.first_click_at, m.replied_at, m.sent_at,
                        wc.contact_name
                 FROM email_messages m
                 LEFT JOIN whatsapp_contacts wc ON m.contact_id = wc.id
                 WHERE m.origin = 'sequence'
                 ORDER BY m.id DESC LIMIT 100"
            );
        } catch (\Throwable $e) { $emails = []; }

        $this->json(['success' => true, 'steps' => $steps, 'participants' => $participants, 'prospect_log' => $prospectLog, 'emails' => $emails, 'errors' => $errors]);
    }

    // Helpers de campanha
    private function normalizeTime($v, $default)
    {
        $v = trim((string)$v);
        if (preg_match('/^\d{1,2}:\d{2}$/', $v)) return $v . ':00';
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $v)) return $v;
        return $default;
    }

    private function buildCampaignFilters()
    {
        // Se veio JSON bruto (campo avançado), usa-o; senão monta dos campos simples.
        $raw = trim($_POST['search_filters_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        $toArr = fn($k) => array_values(array_filter(array_map('trim', explode(',', $_POST[$k] ?? ''))));
        $f = [];
        if (!empty($_POST['f_titles'])) $f['person_titles'] = $toArr('f_titles');
        if (!empty($_POST['f_seniorities'])) $f['person_seniorities'] = $toArr('f_seniorities');
        if (!empty($_POST['f_person_locations'])) $f['person_locations'] = $toArr('f_person_locations');
        if (!empty($_POST['f_org_locations'])) $f['organization_locations'] = $toArr('f_org_locations');
        if (!empty($_POST['f_domains'])) $f['q_organization_domains_list'] = $toArr('f_domains');
        if (!empty($_POST['f_keywords'])) $f['q_keywords'] = implode(' ', $toArr('f_keywords'));
        if (!empty($_POST['f_employee_ranges'])) $f['organization_num_employees_ranges'] = $toArr('f_employee_ranges');
        return $f;
    }

    private function buildCampaignIcp()
    {
        $raw = trim($_POST['icp_rules_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        $toArr = fn($k) => array_values(array_filter(array_map('trim', explode(',', $_POST[$k] ?? ''))));
        $icp = [
            'score' => [
                'decisor' => intval($_POST['w_decisor'] ?? 30),
                'title' => intval($_POST['w_title'] ?? 20),
                'size' => intval($_POST['w_size'] ?? 15),
                'region' => intval($_POST['w_region'] ?? 10),
                'website' => intval($_POST['w_website'] ?? 5),
                'technology' => intval($_POST['w_technology'] ?? 10),
            ],
        ];
        if (!empty($_POST['icp_seniorities'])) $icp['seniorities'] = $toArr('icp_seniorities');
        if (!empty($_POST['icp_titles'])) $icp['titles_any'] = $toArr('icp_titles');
        if (!empty($_POST['icp_employee_min'])) $icp['employee_min'] = intval($_POST['icp_employee_min']);
        if (!empty($_POST['icp_employee_max'])) $icp['employee_max'] = intval($_POST['icp_employee_max']);
        if (!empty($_POST['icp_require_website'])) $icp['require_website'] = true;
        return $icp;
    }

    /** Filtros aplicados quando a origem da campanha é "Meus Leads". */
    private function buildMyLeadsFilters()
    {
        $f = [];
        if (!empty($_POST['ml_temperature'])) $f['temperature'] = trim($_POST['ml_temperature']);
        if (!empty($_POST['ml_source']))      $f['source'] = trim($_POST['ml_source']);
        if (!empty($_POST['ml_assigned_to'])) $f['assigned_to'] = intval($_POST['ml_assigned_to']);
        return $f;
    }

    /** IDs de leads selecionados manualmente para a campanha "Meus Leads". */
    private function buildMyLeadsIds()
    {
        $raw = $_POST['my_leads_ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', (string)$raw)), fn($v) => $v > 0);
        return array_values(array_unique($ids));
    }

    /**
     * API: lista leads do CRM (com e-mail) para o modal de multiseleção da campanha
     * "Meus Leads". Aceita filtros opcionais (search, temperature, source, assigned_to).
     * GET crm/leadsForCampaign
     */
    public function leadsForCampaign()
    {
        $this->requireRole(['super_admin']);
        $db = Database::getInstance();

        // Canal de elegibilidade (email/whatsapp/mixed) — combina com o canal da sequência.
        $channel = in_array($_GET['channel'] ?? '', ['email', 'whatsapp', 'mixed'], true) ? $_GET['channel'] : 'email';
        if ($channel === 'whatsapp') {
            $channelSql = "(c.phone IS NOT NULL AND c.phone <> '')";
        } elseif ($channel === 'mixed') {
            $channelSql = "((c.lead_email IS NOT NULL AND c.lead_email <> '') OR (c.phone IS NOT NULL AND c.phone <> ''))";
        } else {
            $channelSql = "(c.lead_email IS NOT NULL AND c.lead_email <> '')";
        }

        $sql = "SELECT c.id, c.contact_name, c.lead_email, c.phone, u.name AS assigned_name,
                       b.lead_temperature, b.lead_source
                FROM whatsapp_contacts c
                LEFT JOIN users u ON c.assigned_to = u.id
                LEFT JOIN commercial_briefings b ON b.contact_id = c.id
                WHERE COALESCE(c.is_group,0)=0
                  AND $channelSql
                  AND COALESCE(c.unsubscribed,0)=0
                  AND COALESCE(c.email_bounced,0)=0
                  AND COALESCE(c.crm_archived,0)=0";
        $params = [];

        if (!empty($_GET['search'])) {
            $sql .= " AND (c.contact_name LIKE ? OR c.lead_email LIKE ? OR c.phone LIKE ?)";
            $s = '%' . trim($_GET['search']) . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($_GET['temperature'])) { $sql .= " AND b.lead_temperature = ?"; $params[] = $_GET['temperature']; }
        if (!empty($_GET['source']))      { $sql .= " AND b.lead_source = ?";      $params[] = $_GET['source']; }
        if (!empty($_GET['assigned_to'])) { $sql .= " AND c.assigned_to = ?";       $params[] = intval($_GET['assigned_to']); }

        $sql .= " ORDER BY c.contact_name IS NULL, c.contact_name ASC LIMIT 500";
        $rows = $db->fetchAll($sql, $params);
        $this->json(['success' => true, 'leads' => $rows]);
    }

    /**
     * API: lista usuários que podem ser responsáveis por leads (comercial + super_admin).
     * GET crm/leadOwners
     */
    public function leadOwners()
    {
        $this->requireRole($this->captureRoles);
        $rows = (new User())->getByRoles(['super_admin', 'comercial']);
        $out = array_map(fn($u) => ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']], $rows);
        $this->json(['success' => true, 'users' => $out]);
    }

    /**
     * API: reatribui um lead capturado (já importado) a outro responsável.
     * Somente super_admin. POST crm/apolloReassign/{apolloLeadId}  body: user_id
     */
    public function apolloReassign($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $newUserId = !empty($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$newUserId) $this->json(['error' => 'Selecione o novo responsável.'], 400);

        $leadModel = new ApolloLead();
        $lead = $leadModel->findById($id);
        if (!$lead) $this->json(['error' => 'Lead não encontrado'], 404);
        if (empty($lead['contact_id'])) $this->json(['error' => 'Este lead ainda não foi enviado para Meus Leads.'], 400);

        $newOwner = (new User())->findById($newUserId);
        if (!$newOwner) $this->json(['error' => 'Usuário inválido.'], 400);

        // Atualiza o dono do contato no CRM (whatsapp_contacts.assigned_to)
        Database::getInstance()->update('whatsapp_contacts', ['assigned_to' => $newUserId], 'id = ?', [$lead['contact_id']]);

        // Atualiza também os cards vinculados a esse contato (assigned_to)
        try {
            Database::getInstance()->query(
                "UPDATE crm_cards SET assigned_to = ? WHERE contact_id = ?",
                [$newUserId, $lead['contact_id']]
            );
        } catch (\Throwable $e) { /* ignora se schema difere */ }

        $this->json(['success' => true, 'owner_id' => $newUserId, 'owner_name' => $newOwner['name']]);
    }

    /**
     * API: exclui um lead capturado (staging Apollo). Somente super_admin.
     * POST crm/apolloDeleteLead/{id}
     */
    public function apolloDeleteLead($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->json(['error' => 'Requisição inválida'], 400);

        $leadModel = new ApolloLead();
        $lead = $leadModel->findById($id);
        if (!$lead) $this->json(['error' => 'Lead não encontrado'], 404);

        Database::getInstance()->delete('apollo_leads', 'id = ?', [$id]);
        $this->json(['success' => true]);
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

        // Board + coluna são OBRIGATÓRIOS: todo lead puxado deve gerar um card no CRM.
        $columnId = !empty($_POST['column_id']) ? intval($_POST['column_id']) : null;
        $sequenceId = !empty($_POST['sequence_id']) ? intval($_POST['sequence_id']) : null;

        if (!$columnId) {
            $this->json(['error' => 'Selecione um board e uma coluna do CRM para atribuir o(s) lead(s).'], 400);
        }
        // Valida que a coluna existe (e obtém o board para retorno/consistência)
        $column = $this->boardModel->findColumn($columnId);
        if (!$column) {
            $this->json(['error' => 'Coluna do CRM inválida. Atualize a página e selecione novamente.'], 400);
        }

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
     * API (diagnóstico): rastreia o PIPELINE de uma busca de Pessoas, mostrando
     * quantos itens existem em cada etapa entre a resposta bruta do Apollo e o que
     * é efetivamente exibido na tela. Serve para identificar onde os resultados
     * "somem" (filtragem/formatação/máscara), sem depender do que a UI mostra.
     *
     * POST crm/apolloSearchTrace  (restrito a super_admin)
     * Body: scope=people|orgs, q=<termo livre>, per_page=<int>
     */
    public function apolloSearchTrace()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $apollo = new ApolloApi();
        if (!$apollo->isConfigured()) $this->json(['error' => 'Apollo não configurado. Informe a API key em Configurações.'], 400);

        $scope = ($_POST['scope'] ?? 'people') === 'orgs' ? 'orgs' : 'people';
        $q = trim($_POST['q'] ?? '');
        $perPage = min(25, max(1, intval($_POST['per_page'] ?? 10)));

        $stages = [];   // etapas do pipeline, na ordem
        $notes = [];    // observações/alertas encontrados

        if ($scope === 'orgs') {
            $filters = ['page' => 1, 'per_page' => $perPage];
            if ($q !== '') $filters['q_organization_name'] = $q;
            $res = $apollo->searchOrganizations($filters);

            $stages[] = ['stage' => 'Payload enviado ao Apollo', 'payload' => $filters];
            if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.', 'stages' => $stages], 502);

            $data = $res['data'] ?? [];
            $rawOrgs = is_array($data['organizations'] ?? null) ? $data['organizations'] : [];
            $rawAccounts = is_array($data['accounts'] ?? null) ? $data['accounts'] : [];
            $orgs = $this->mergeApolloLists($data, 'organizations', 'accounts');
            $pagination = $data['pagination'] ?? [];

            $stages[] = ['stage' => 'Resposta bruta da API — organizations[]', 'count' => count($rawOrgs)];
            $stages[] = ['stage' => 'Resposta bruta da API — accounts[] (já na sua conta Apollo)', 'count' => count($rawAccounts)];
            $stages[] = ['stage' => 'Após mesclar organizations + accounts', 'count' => count($orgs)];
            $stages[] = ['stage' => 'total_entries informado pela API', 'count' => $pagination['total_entries'] ?? null];
            $stages[] = ['stage' => 'Exibido na tela (sem formatação/filtro no backend)', 'count' => count($orgs)];

            if (count($rawOrgs) === 0 && count($rawAccounts) > 0) {
                $notes[] = 'A empresa voltou em "accounts[]" (já está salva na sua conta Apollo) e não em "organizations[]". Antes da correção, o código usava "??" e ignorava a lista "accounts", por isso a empresa "sumia". Agora as duas listas são mescladas e ela aparece.';
            } elseif (count($orgs) === 0) {
                $notes[] = 'A API retornou 0 empresas para este termo, tanto em "organizations[]" quanto em "accounts[]". O filtro está na própria consulta ao Apollo, não no código de exibição.';
            } else {
                $notes[] = 'Empresas não passam por máscara no backend: o que a API retorna (organizations + accounts) é exibido integralmente.';
            }

            $sample = array_map(fn($o) => [
                'name' => $o['name'] ?? null,
                'domain' => $o['primary_domain'] ?? null,
                'id' => $o['id'] ?? null,
            ], array_slice($orgs, 0, 10));

            $this->json(['success' => true, 'scope' => $scope, 'q' => $q, 'stages' => $stages, 'notes' => $notes, 'sample' => $sample]);
        }

        // scope = people
        $filters = ['page' => 1, 'per_page' => $perPage];
        if ($q !== '') $filters['q_keywords'] = $q;
        $res = $apollo->searchPeople($filters);

        $stages[] = ['stage' => 'Payload enviado ao Apollo', 'payload' => $filters];
        if (!$res['success']) $this->json(['error' => $res['error'] ?? 'Falha na busca.', 'stages' => $stages], 502);

        $data = $res['data'] ?? [];
        $rawPeople = is_array($data['people'] ?? null) ? $data['people'] : [];
        $rawContacts = is_array($data['contacts'] ?? null) ? $data['contacts'] : [];
        $people = $this->mergeApolloLists($data, 'people', 'contacts');
        $pagination = $data['pagination'] ?? [];

        $stages[] = ['stage' => 'Resposta bruta da API — people[]', 'count' => count($rawPeople)];
        $stages[] = ['stage' => 'Resposta bruta da API — contacts[] (já na sua conta Apollo)', 'count' => count($rawContacts)];
        $stages[] = ['stage' => 'Após mesclar people + contacts', 'count' => count($people)];
        $stages[] = ['stage' => 'total_entries informado pela API', 'count' => $pagination['total_entries'] ?? null];

        // Reproduz exatamente o que apolloSearchPeople faz: upsert + format
        $leadModel = new ApolloLead();
        $out = [];
        $upsertNulls = 0;
        foreach ($people as $p) {
            $localId = $leadModel->upsertFromApollo($p, $this->currentUser()['id']);
            if (!$localId) { $upsertNulls++; }
            $existing = $localId ? $leadModel->findById($localId) : null;
            $out[] = $this->formatApolloPerson($p, $existing);
        }

        $stages[] = ['stage' => 'Após upsert em apollo_leads (staging)', 'count' => count($out)];
        if ($upsertNulls > 0) {
            $notes[] = "{$upsertNulls} pessoa(s) sem apollo_id foram ignoradas no upsert (upsertFromApollo retorna null quando falta 'id'). Elas ainda aparecem na tela, mas sem local_id não podem ser liberadas/importadas.";
        }

        // Contagens de estados que afetam a exibição
        $masked = 0; $ownedByOther = 0; $imported = 0; $noLocalId = 0;
        foreach ($out as $row) {
            if (!empty($row['contact_masked'])) $masked++;
            if (!empty($row['imported'])) $imported++;
            if (empty($row['local_id'])) $noLocalId++;
            if (!empty($row['imported']) && !empty($row['owner_id'])
                && (int)$row['owner_id'] !== (int)$this->currentUser()['id']) $ownedByOther++;
        }

        $stages[] = ['stage' => 'Enviado ao navegador (people[])', 'count' => count($out)];
        $stages[] = ['stage' => '↳ marcados como sigilosos (contact_masked)', 'count' => $masked];
        $stages[] = ['stage' => '↳ já importados (imported)', 'count' => $imported];
        $stages[] = ['stage' => '↳ de outro responsável (bloqueados p/ importar)', 'count' => $ownedByOther];
        $stages[] = ['stage' => '↳ sem local_id (não puderam ser gravados)', 'count' => $noLocalId];

        if (count($rawPeople) === 0 && count($rawContacts) > 0) {
            $notes[] = 'A API trouxe resultados apenas em "contacts[]" (pessoas já salvas na sua conta Apollo) e nada em "people[]". Antes da correção, o código usava "??" e descartava esses contatos, fazendo o resultado sumir. Agora as duas listas são mescladas.';
        }
        if (count($people) > 0 && count($out) === count($people)) {
            $notes[] = 'O backend NÃO descarta nenhuma pessoa: todas as ' . count($people) . ' retornadas pela API são enviadas ao navegador. Se você vê menos na tela, a redução é (a) no filtro enviado ao Apollo, (b) na paginação (per_page), ou (c) na renderização do front-end.';
        }
        if ($masked > 0) {
            $notes[] = "{$masked} contato(s) aparecem, mas com e-mail/telefone ocultos por pertencerem a outro responsável (regra de sigilo em formatApolloPerson). Isso oculta DADOS, não a linha inteira.";
        }
        if (count($people) === 0) {
            $notes[] = 'A API retornou 0 pessoas. Como a busca da UI usa filtros específicos (cargos, localização, tecnologias etc.), verifique se algum filtro está restringindo demais — o "sumiço" ocorre na consulta ao Apollo, não na exibição.';
        }

        $sample = array_map(fn($r) => [
            'name' => $r['name'] ?? null,
            'title' => $r['title'] ?? null,
            'organization_name' => $r['organization_name'] ?? null,
            'imported' => (bool)($r['imported'] ?? false),
            'masked' => (bool)($r['contact_masked'] ?? false),
            'local_id' => $r['local_id'] ?? null,
        ], array_slice($out, 0, 10));

        $this->json(['success' => true, 'scope' => $scope, 'q' => $q, 'stages' => $stages, 'notes' => $notes, 'sample' => $sample]);
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
     * Extrai o melhor e-mail revelado de um person do Apollo, checando os
     * vários campos onde o e-mail pode vir (email, personal_emails, contact_emails).
     */
    private function extractApolloEmail($person)
    {
        $isReal = function ($e) {
            return !empty($e)
                && stripos($e, 'email_not_unlocked') === false
                && stripos($e, 'domain.com') === false
                && filter_var($e, FILTER_VALIDATE_EMAIL);
        };

        // 1) Campo direto
        if ($isReal($person['email'] ?? null)) return $person['email'];

        // 2) contact.email (quando vem aninhado)
        if (!empty($person['contact']) && $isReal($person['contact']['email'] ?? null)) {
            return $person['contact']['email'];
        }

        // 3) Listas de e-mails pessoais/profissionais
        foreach (['personal_emails', 'contact_emails'] as $key) {
            if (!empty($person[$key]) && is_array($person[$key])) {
                foreach ($person[$key] as $item) {
                    $val = is_array($item) ? ($item['email'] ?? null) : $item;
                    if ($isReal($val)) return $val;
                }
            }
        }
        return null;
    }

    /**
     * Extrai o primeiro telefone disponível de um person do Apollo.
     */
    private function extractApolloPhone($person)
    {
        if (!empty($person['phone_numbers']) && is_array($person['phone_numbers'])) {
            foreach ($person['phone_numbers'] as $ph) {
                if (!empty($ph['sanitized_number'])) return $ph['sanitized_number'];
                if (!empty($ph['raw_number'])) return $ph['raw_number'];
            }
        }
        if (!empty($person['contact']['sanitized_phone'])) return $person['contact']['sanitized_phone'];
        return $person['sanitized_phone'] ?? ($person['phone'] ?? null);
    }

    /**
     * Formata um person do Apollo + registro local para exibição no painel.
     * $stored é a linha em apollo_leads (pode conter o e-mail já revelado).
     */
    /**
     * Mescla as duas listas que a Apollo pode retornar para o mesmo tipo de
     * resultado. Ex.: pessoas vêm em "people" (fora da sua conta) E/OU "contacts"
     * (já salvas na sua conta); empresas vêm em "organizations" E/OU "accounts".
     * Usar "??" é errado porque a Apollo devolve um array VAZIO (não nulo) para a
     * lista sem itens, escondendo os resultados que estão na outra lista.
     */
    private function mergeApolloLists($data, $primaryKey, $secondaryKey)
    {
        $primary = is_array($data[$primaryKey] ?? null) ? $data[$primaryKey] : [];
        $secondary = is_array($data[$secondaryKey] ?? null) ? $data[$secondaryKey] : [];
        if (empty($secondary)) return $primary;
        if (empty($primary)) return $secondary;

        // Evita duplicar quando a mesma entidade vier nas duas listas (mesmo id).
        $byId = [];
        foreach (array_merge($primary, $secondary) as $item) {
            $id = $item['id'] ?? spl_object_hash((object)$item);
            if (!isset($byId[$id])) $byId[$id] = $item;
        }
        return array_values($byId);
    }

    private function formatApolloPerson($person, $stored = null)
    {
        $org = $person['organization'] ?? ($person['account'] ?? []);
        $email = $this->extractApolloEmail($person) ?: ($stored['email'] ?? null);
        $hasRealEmail = !empty($email) && stripos($email, 'email_not_unlocked') === false && stripos($email, 'domain.com') === false;
        $phone = $this->extractApolloPhone($person) ?: ($stored['phone'] ?? null);

        // Sigilo dos dados quando o lead pertence a outro responsável (não-admin).
        $ownerId = $stored['owner_id'] ?? null;
        $isImported = !empty($stored['contact_id']);
        $curUser = $this->currentUser();
        $isAdmin = in_array($curUser['role'] ?? '', ['super_admin', 'admin'], true);
        $mask = $isImported && $ownerId && (int)$ownerId !== (int)($curUser['id'] ?? 0) && !$isAdmin;

        return [
            'local_id' => $stored['id'] ?? null,
            'apollo_id' => $person['id'] ?? ($stored['apollo_id'] ?? null),
            'name' => $person['name'] ?? ($stored['full_name'] ?? null),
            'first_name' => $person['first_name'] ?? null,
            'last_name' => $person['last_name'] ?? null,
            'title' => $person['title'] ?? ($stored['title'] ?? null),
            'seniority' => $person['seniority'] ?? null,
            'email' => $mask ? null : ($hasRealEmail ? $email : null),
            'email_locked' => !$hasRealEmail,
            'email_status' => $person['email_status'] ?? ($stored['email_status'] ?? null),
            'phone' => $mask ? null : $phone,
            'phone_status' => $stored['phone_status'] ?? null,
            'is_full_enriched' => (int)($stored['is_full_enriched'] ?? 0),
            'linkedin_url' => $person['linkedin_url'] ?? ($stored['linkedin_url'] ?? null),
            'organization_name' => $org['name'] ?? ($stored['organization_name'] ?? null),
            'organization_domain' => $org['primary_domain'] ?? ($stored['organization_domain'] ?? null),
            'organization_industry' => $org['industry'] ?? ($stored['organization_industry'] ?? null),
            'city' => $person['city'] ?? ($stored['city'] ?? null),
            'state' => $person['state'] ?? ($stored['state'] ?? null),
            'country' => $person['country'] ?? ($stored['country'] ?? null),
            'is_enriched' => (int)($stored['is_enriched'] ?? ($hasRealEmail ? 1 : 0)),
            'imported' => $isImported,
            'contact_id' => $stored['contact_id'] ?? null,
            'owner_id' => $ownerId,
            'owner_name' => $stored['owner_name'] ?? null,
            'contact_masked' => $mask,
            'can_reassign' => ($curUser['role'] ?? '') === 'super_admin' && $isImported,
        ];
    }

    /** Formata uma linha de apollo_leads (staging) para exibição. */
    private function formatStoredLead($l)
    {
        $ownerId = $l['owner_id'] ?? null;
        $ownerName = $l['owner_name'] ?? ($l['imported_by_name'] ?? null);

        // Sigilo dos dados de contato: se o lead está atribuído a OUTRO usuário e
        // o usuário atual não é admin/super_admin, oculta e-mail e telefone.
        $user = $this->currentUser();
        $isAdmin = in_array($user['role'] ?? '', ['super_admin', 'admin'], true);
        $isImported = !empty($l['contact_id']);
        $ownedByOther = $isImported && $ownerId && (int)$ownerId !== (int)($user['id'] ?? 0);
        $mask = $ownedByOther && !$isAdmin;

        return [
            'local_id' => $l['id'],
            'apollo_id' => $l['apollo_id'],
            'name' => $l['full_name'],
            'title' => $l['title'],
            'seniority' => $l['seniority'],
            'email' => $mask ? null : $l['email'],
            'email_locked' => empty($l['email']),
            'email_status' => $l['email_status'],
            'phone' => $mask ? null : $l['phone'],
            'phone_status' => $l['phone_status'] ?? null,
            'is_full_enriched' => (int)($l['is_full_enriched'] ?? 0),
            'linkedin_url' => $l['linkedin_url'],
            'organization_name' => $l['organization_name'],
            'organization_domain' => $l['organization_domain'],
            'organization_industry' => $l['organization_industry'],
            'city' => $l['city'],
            'state' => $l['state'],
            'country' => $l['country'],
            'is_enriched' => (int)$l['is_enriched'],
            'imported' => $isImported,
            'contact_id' => $l['contact_id'],
            'imported_at' => $l['imported_at'],
            // Responsável: dono do lead no CRM (assigned_to) ou quem importou
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            // Contato sigiloso (para a UI indicar bloqueio)
            'contact_masked' => $mask,
            // Permite reatribuição (só super_admin)
            'can_reassign' => ($user['role'] ?? '') === 'super_admin' && $isImported,
        ];
    }
}

