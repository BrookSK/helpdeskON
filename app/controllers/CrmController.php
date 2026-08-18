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
            'caller' => $user['sip_user'] ?: Config::get('nvoip_sip_user'),
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

        $params = [];
        $sql = "SELECT nc.*, c.contact_name, c.push_name, u.name AS user_name
                FROM nvoip_calls nc
                LEFT JOIN whatsapp_contacts c ON c.id = nc.contact_id
                LEFT JOIN users u ON u.id = nc.user_id
                WHERE 1=1";
        if ($user['role'] === 'comercial') {
            $sql .= " AND nc.user_id = ?";
            $params[] = $user['id'];
        }
        $sql .= " ORDER BY nc.created_at DESC LIMIT 300";

        $calls = Database::getInstance()->fetchAll($sql, $params);
        $this->view('crm/calls', ['user' => $user, 'calls' => $calls]);
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

