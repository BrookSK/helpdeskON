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

    /**
     * O usuário logado pode ver/agir sobre esta demanda? Fecha os IDOR das ações
     * de ticket (comentar/ler/anexar): equipe vê tudo; cliente só a própria
     * demanda ou, se dono da empresa, as da mesma empresa. Fonte: TicketAccess.
     */
    private function canAccessTicket($ticket): bool
    {
        if (!$ticket) return false;
        $user = $this->currentUser();
        if (!$user) return false;

        $viewerIsOwner = false;
        $viewerCompanyId = null;
        $ticketOwnerCompanyId = null;
        if (($user['role'] ?? '') === 'client') {
            $fullUser = (new User())->findById($user['id']);
            $viewerIsOwner = !empty($fullUser['is_company_owner']);
            $viewerCompanyId = isset($fullUser['company_id']) ? (int) $fullUser['company_id'] : null;
            // Empresa do dono da demanda (para o caso do dono da empresa).
            if (!empty($ticket['client_id'])) {
                $ticketOwner = (new User())->findById($ticket['client_id']);
                $ticketOwnerCompanyId = isset($ticketOwner['company_id']) ? (int) $ticketOwner['company_id'] : null;
            }
        }

        return TicketAccess::canAccess(
            $user['role'] ?? null,
            (int) $user['id'],
            (int) ($ticket['client_id'] ?? 0),
            $viewerIsOwner,
            $viewerCompanyId,
            $ticketOwnerCompanyId
        );
    }

    // Listagem de tickets
    public function index()
    {
        $this->requireLogin();
        $user = $this->currentUser();

        if ($user['role'] === 'client') {
            $fullUser = (new User())->findById($user['id']);
            // Contexto Multi-Empresas: usa a empresa ativa (Ver como) quando definida
            // e o usuário está de fato vinculado a ela.
            $activeCompanyId = $this->activeCompanyId();
            $companyContext = $fullUser['company_id'] ?? null;
            if ($activeCompanyId && (new User())->isLinkedToCompany($user['id'], $activeCompanyId)) {
                $companyContext = $activeCompanyId;
            }
            // Dono da empresa vê todos os tickets da empresa
            if ($fullUser['is_company_owner'] && $companyContext) {
                $tickets = $this->ticketModel->getByCompany($companyContext);
                $this->view('client/tickets', ['user' => $user, 'tickets' => $tickets, 'isOwner' => true]);
            } else {
                $tickets = $this->ticketModel->getByClient($user['id']);
                $this->view('client/tickets', ['user' => $user, 'tickets' => $tickets, 'isOwner' => false]);
            }
        } elseif ($user['role'] === 'comercial') {
            // Comercial vê os cards de planejamento atribuídos a ele
            $cardModel = new PlanningCard();
            $planningFilters = ['assigned_to' => $user['id']];
            if (!empty($_GET['status'])) $planningFilters['status'] = $_GET['status'];
            if (!empty($_GET['priority'])) $planningFilters['priority'] = $_GET['priority'];
            if (!empty($_GET['company'])) $planningFilters['company_id'] = $_GET['company'];
            if (!empty($_GET['hide_completed'])) $planningFilters['hide_completed'] = true;

            $cards = $cardModel->getAll($planningFilters);

            // Ocultar arquivados por padrão
            $isSubmitted = isset($_GET['filtered']);
            $hideArchived = $isSubmitted ? !empty($_GET['hide_archived']) : true;
            if ($hideArchived) {
                $cards = array_filter($cards, fn($c) => $c['status'] !== 'archived');
                $cards = array_values($cards);
            }

            $this->view('attendant/tickets', ['user' => $user, 'cards' => $cards, 'companies' => [], 'attendants' => [], 'isAdmin' => false]);
        } else {
            $filters = [];
            if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
            if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];
            if (!empty($_GET['company'])) $filters['company_id'] = $_GET['company'];

            // Filtro por atendente:
            // Admin/marketing podem ver de todos; atendentes só veem o próprio
            $isAdmin = in_array($user['role'], ['super_admin', 'marketing']);
            if ($isAdmin) {
                if (isset($_GET['attendant'])) {
                    if ($_GET['attendant'] !== '') $filters['attendant_id'] = $_GET['attendant'];
                } else {
                    $filters['attendant_id'] = $user['id'];
                }
            } else {
                // Atendente sempre vê só o dele
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

            // Buscar planning cards (mesmos dados do kanban do planejamento)
            $cardModel = new PlanningCard();
            $planningFilters = [];
            if (!empty($filters['status'])) $planningFilters['status'] = $filters['status'];
            if (!empty($filters['priority'])) $planningFilters['priority'] = $filters['priority'];
            if (!empty($_GET['company'])) $planningFilters['company_id'] = $_GET['company'];
            if (!empty($filters['attendant_id'])) $planningFilters['assigned_to'] = $filters['attendant_id'];
            if (!empty($filters['hide_completed'])) $planningFilters['hide_completed'] = true;
            if (!empty($filters['allowed_companies'])) $planningFilters['allowed_companies'] = $filters['allowed_companies'];

            $cards = $cardModel->getAll($planningFilters);

            // Filtrar arquivados se necessário
            if ($hideArchived) {
                $cards = array_filter($cards, fn($c) => $c['status'] !== 'archived');
                $cards = array_values($cards);
            }

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

            $this->view('attendant/tickets', ['user' => $user, 'cards' => $cards, 'companies' => $companies, 'attendants' => (new User())->getByRoles(['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent']), 'isAdmin' => $isAdmin]);
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
                "SELECT u.id, u.name, u.email, u.phone, u.company_id, comp.name as company_name
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
        $attendantIds = [];
        $technicalId = null;
        if ($user['role'] === 'super_admin') {
            $selectedClient = $_POST['client_id'] ?? '';
            if (!empty($selectedClient)) {
                $clientId = (int)$selectedClient;
            }
            // Se não selecionou, o ticket fica vinculado ao próprio admin

            // Atribuições opcionais na criação — múltiplos atendentes via checkbox
            $attendantIds = array_values(array_unique(array_filter(array_map(
                'intval',
                (array)($_POST['attendant_ids'] ?? [])
            ))));
            // O primeiro marcado é o atendente principal (compatibilidade)
            $attendantId = $attendantIds[0] ?? null;
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

        // Registrar todos os atendentes selecionados na tabela de junção
        if (!empty($attendantIds)) {
            foreach ($attendantIds as $aId) {
                $db->query(
                    "INSERT IGNORE INTO ticket_attendants (ticket_id, user_id) VALUES (?, ?)",
                    [$ticketId, $aId]
                );
            }
        }

        // Enviar notificação (lógica compartilhada com a criação via API).
        (new TicketNotificationService())->notifyNewTicket($ticketId);

        // Na criação, notificar todos os atendentes atribuídos.
        // O responsável técnico só é notificado quando a demanda entra em Revisão Interna.
        foreach ($attendantIds as $aId) {
            $this->notifyAssignment($ticketId, $aId, 'atendente');
        }

        // Criar card automático no Planejamento
        $ticket = $this->ticketModel->findById($ticketId);
        $planningCard = new PlanningCard();
        $planningCard->createFromTicket($ticket);

        flash('success', 'Demanda criada com sucesso!');
        $this->redirect('tickets/show/' . $ticketId);
    }

    /**
     * Visualizar detalhes de um planning card.
     * Busca o ticket vinculado ao card. Se não existir, cria um a partir do card.
     */
    public function showCard($cardId = null)
    {
        $this->requireLogin();
        if (!$cardId) $this->redirect('tickets');

        $db = Database::getInstance();
        $card = $db->fetch("SELECT * FROM planning_cards WHERE id = ?", [$cardId]);

        if (!$card) {
            flash('error', 'Demanda não encontrada.');
            $this->redirect('tickets');
        }

        // Se o card tem ticket vinculado, verificar se bate (mesmo título)
        if (!empty($card['ticket_id'])) {
            $ticket = $this->ticketModel->findById($card['ticket_id']);
            if ($ticket) {
                $this->redirect('tickets/show/' . $ticket['id']);
                return;
            }
        }

        // Não tem ticket vinculado ou o ticket não existe — criar um novo a partir do card
        $ticketData = [
            'title' => $card['title'],
            'description' => strip_tags($card['description'] ?? ''),
            'client_id' => $card['created_by'] ?? $this->currentUser()['id'],
            'attendant_id' => $card['assigned_to'],
            'technical_responsible_id' => $card['technical_responsible_id'] ?? null,
            'priority' => $card['priority'] ?? 'medium',
            'status' => $card['status'] ?? 'open',
            'category' => 'desenvolvimento',
        ];
        $newTicketId = $this->ticketModel->create($ticketData);

        // Vincular o card ao ticket recém-criado
        $db->update('planning_cards', ['ticket_id' => $newTicketId], 'id = ?', [$card['id']]);

        $this->redirect('tickets/show/' . $newTicketId);
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

        // Verificar permissão (equipe vê tudo; cliente só a própria demanda ou
        // as da sua empresa quando for dono). Centralizado em TicketAccess.
        if (!$this->canAccessTicket($ticket)) {
            $this->redirect('tickets');
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

        // Demandas relacionadas (Suporte -> Incidente -> Correção).
        $relations = $this->ticketModel->getRelations($id);

        // Atendentes atualmente vinculados a esta demanda (para marcar os checkboxes)
        $assignedAttendants = $this->ticketModel->getAttendants($id);
        $assignedAttendantIds = array_map(function ($a) { return (int)$a['id']; }, $assignedAttendants);
        // Fallback para o campo legado, caso a junção ainda não tenha registros
        if (empty($assignedAttendantIds) && !empty($ticket['attendant_id'])) {
            $assignedAttendantIds = [(int)$ticket['attendant_id']];
        }

        $this->view('tickets/view', [
            'user' => $user,
            'ticket' => $ticket,
            'messages' => $messages,
            'attachments' => $attachments,
            'attendants' => $attendants,
            'assignedAttendants' => $assignedAttendants,
            'assignedAttendantIds' => $assignedAttendantIds,
            'technicalGrouped' => $technicalGrouped,
            'internalNotes' => $internalNotes,
            'relations' => $relations,
        ]);
    }

    // Atualizar status do ticket
    public function updateStatus($id = null)
    {
        $this->requireLogin();
        $user = $this->currentUser();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Requisição inválida'], 400);
            }
            $this->redirect('tickets');
        }

        $status = $_POST['status'] ?? '';
        $validStatuses = TicketAccess::STATUSES;
        if (!in_array($status, $validStatuses)) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Status inválido'], 400);
            }
            $this->redirect('tickets/show/' . $id);
        }

        // Cliente só pode mudar de em_homologacao para aprovado_producao ou denied
        // E somente em demanda que ele pode acessar (própria ou da sua empresa).
        if ($user['role'] === 'client') {
            $currentTicket = $this->ticketModel->findById($id);
            if (!$currentTicket
                || !$this->canAccessTicket($currentTicket)
                || !TicketAccess::clientCanChangeStatus($currentTicket['status'] ?? null, $status)) {
                if ($this->isAjax()) {
                    $this->json(['error' => 'Sem permissão'], 403);
                }
                $this->redirect('tickets/show/' . $id);
                return;
            }
        } elseif (!in_array($user['role'], ['super_admin', 'attendant', 'whatsapp_agent'])) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Sem permissão'], 403);
            }
            $this->redirect('tickets');
            return;
        }

        // Capturar status anterior para detectar transições
        $previousTicket = $this->ticketModel->findById($id);
        $previousStatus = $previousTicket['status'] ?? null;

        // Recusa (escopo/homologação) EXIGE motivo. Registrado antes da mudança
        // de status para preservar a justificativa junto da demanda.
        $reason = trim($_POST['reason'] ?? '');
        $sideEffects = [];

        // Recusa na HOMOLOGAÇÃO (em_homologacao -> denied): guarda o motivo.
        if ($previousStatus === 'em_homologacao' && $status === 'denied') {
            if ($reason === '') {
                if ($this->isAjax()) {
                    $this->json(['error' => 'Informe o motivo da recusa.'], 400);
                }
                flash('error', 'Informe o motivo da recusa.');
                $this->redirect('tickets/show/' . $id);
                return;
            }
            $sideEffects['homolog_denied_reason'] = $reason;
        }

        // Decisão de ESCOPO (aguardando_aprovacao_escopo -> in_progress):
        // o cliente aprova ou recusa. A recusa é sinalizada por reject=1 + motivo.
        // approveScopeKeepCardOpen controla o Bug 2: na APROVAÇÃO, o ticket vai
        // para in_progress (visão do cliente = "Em andamento"), mas o card do
        // Planejamento deve ficar em "Aberto" (open) para a equipe pegar a tarefa.
        $approveScopeKeepCardOpen = false;
        if ($previousStatus === ScopeRules::STATUS_AGUARDANDO && $status === 'in_progress') {
            $isReject = !empty($_POST['reject']);
            if ($isReject) {
                $clean = ScopeRules::sanitizeRejectionReason($reason);
                if ($clean === null) {
                    if ($this->isAjax()) {
                        $this->json(['error' => 'Informe o motivo da recusa do escopo.'], 400);
                    }
                    flash('error', 'Informe o motivo da recusa do escopo.');
                    $this->redirect('tickets/show/' . $id);
                    return;
                }
                $sideEffects['scope_rejected_reason'] = $clean;
            } else {
                $sideEffects['scope_approved_at'] = date('Y-m-d H:i:s');
                // Escopo aprovado: a demanda avança, então nenhum banner de recusa
                // pendente deve permanecer (nem de escopo nem de homologação).
                $sideEffects['scope_rejected_reason'] = null;
                $sideEffects['homolog_denied_reason'] = null;
                $approveScopeKeepCardOpen = true;
            }
        }

        // Ao chegar em "Aprovado p/ Produção", nenhuma recusa pendente faz sentido.
        if ($status === 'aprovado_producao') {
            $sideEffects['scope_rejected_reason'] = null;
            $sideEffects['homolog_denied_reason'] = null;
        }

        // Entrada em HOMOLOGAÇÃO: inicia a janela de 48h e zera os contatos da
        // régua (idempotente — só reinicia ao (re)entrar em homologação).
        // Também limpa a recusa de homologação anterior: ao reenviar para
        // homologação, o banner "Homologação recusada" não deve ficar preso.
        if ($status === 'em_homologacao' && $previousStatus !== 'em_homologacao') {
            $sideEffects['homolog_started_at'] = date('Y-m-d H:i:s');
            $sideEffects['homolog_contact1_at'] = null;
            $sideEffects['homolog_contact2_at'] = null;
            $sideEffects['homolog_contact3_at'] = null;
            $sideEffects['homolog_auto_released_at'] = null;
            $sideEffects['homolog_denied_reason'] = null;
        }

        // (Re)entrada em APROVAÇÃO DE ESCOPO: carimba o envio do escopo ao cliente
        // e começa uma nova rodada de aprovação, então limpa a recusa de escopo
        // anterior (o banner "Escopo recusado" some).
        if ($status === ScopeRules::STATUS_AGUARDANDO && $previousStatus !== ScopeRules::STATUS_AGUARDANDO) {
            $sideEffects['scope_submitted_at'] = date('Y-m-d H:i:s');
            $sideEffects['scope_rejected_reason'] = null;
        }

        $this->ticketModel->updateStatus($id, $status);
        if (!empty($sideEffects)) {
            $this->ticketModel->update($id, $sideEffects);
        }

        // Sincronizar card do planejamento.
        // Bug 2: ao APROVAR o escopo, o ticket vai para "Em andamento" (visão do
        // cliente), mas o card da equipe deve ficar em "Aberto" (open) para ser
        // pego no Kanban. Nos demais casos, o card espelha o status do ticket.
        $planningCard = new PlanningCard();
        $planningCard->syncFromTicket($id, $approveScopeKeepCardOpen ? 'open' : $status);

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

    /**
     * Define/edita o ESCOPO TÉCNICO de uma demanda e, opcionalmente, envia ao
     * cliente para aprovação (muda o status para aguardando_aprovacao_escopo).
     * Apenas equipe (super_admin/attendant/whatsapp_agent).
     */
    public function saveScope($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            flash('error', 'Demanda não encontrada.');
            $this->redirect('tickets');
        }

        $scope = [
            'escopo_incluido' => trim($_POST['escopo_incluido'] ?? ''),
            'escopo_excluido' => trim($_POST['escopo_excluido'] ?? ''),
            'escopo_execucao' => trim($_POST['escopo_execucao'] ?? ''),
        ];
        $estimativa = $_POST['estimativa_dias'] ?? '';
        $data = [
            'escopo_incluido' => $scope['escopo_incluido'] ?: null,
            'escopo_excluido' => $scope['escopo_excluido'] ?: null,
            'escopo_execucao' => $scope['escopo_execucao'] ?: null,
            'estimativa_dias' => ($estimativa !== '' && ctype_digit((string)$estimativa)) ? (int)$estimativa : null,
        ];

        // "Enviar ao cliente" exige escopo mínimo (o que será feito).
        $sendToClient = !empty($_POST['send_to_client']);
        if ($sendToClient && !ScopeRules::isScopeComplete($scope)) {
            $msg = 'Preencha ao menos "o que será desenvolvido" antes de enviar o escopo ao cliente.';
            if ($this->isAjax()) {
                $this->json(['error' => $msg], 400);
            }
            flash('error', $msg);
            $this->redirect('tickets/show/' . $id);
        }

        $this->ticketModel->update($id, $data);

        if ($sendToClient) {
            // Reaproveita o fluxo central de mudança de status (carimba
            // scope_submitted_at, sincroniza card e notifica) via updateStatus.
            // Começa uma nova rodada de aprovação: limpa a recusa de escopo
            // anterior para o banner "Escopo recusado" não ficar preso.
            $this->ticketModel->updateStatus($id, ScopeRules::STATUS_AGUARDANDO);
            $this->ticketModel->update($id, [
                'scope_submitted_at' => date('Y-m-d H:i:s'),
                'scope_rejected_reason' => null,
            ]);
            (new PlanningCard())->syncFromTicket($id, ScopeRules::STATUS_AGUARDANDO);
            $fresh = $this->ticketModel->findById($id);
            $this->sendStatusChangeNotification($fresh, ScopeRules::STATUS_AGUARDANDO);
            $successMsg = 'Escopo enviado ao cliente para aprovação.';
        } else {
            $successMsg = 'Escopo salvo.';
        }
        // Chamada via AJAX (pop-up do Planejamento) responde JSON; o form
        // tradicional da tela da demanda mantém flash + redirect.
        if ($this->isAjax()) {
            $this->json(['success' => true]);
        }
        flash('success', $successMsg);
        $this->redirect('tickets/show/' . $id);
    }

    /**
     * Define/edita os campos do fluxo de SUPORTE: gravidade, prazos (análise e
     * resolução), solução temporária e identificação de problema de terceiros.
     * Apenas equipe. A gravidade determina o prazo interno de análise (SupportRules).
     */
    public function saveSupport($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            flash('error', 'Demanda não encontrada.');
            $this->redirect('tickets');
        }

        $severity = SupportRules::normalizeSeverity($_POST['support_severity'] ?? '');
        $data = ['support_severity' => $severity];

        // Prazo de análise derivado da gravidade (a partir de agora, se definida).
        if ($severity !== null) {
            $data['support_analysis_due_at'] = SupportRules::analysisDueAt(date('Y-m-d H:i:s'), $severity);
        } else {
            $data['support_analysis_due_at'] = null;
        }

        // Prazo previsto de resolução (minutos, faixa 30min–48h).
        $resMin = $_POST['support_resolution_minutes'] ?? '';
        if ($resMin !== '' && SupportRules::isValidResolutionMinutes($resMin)) {
            $data['support_resolution_due_at'] = date('Y-m-d H:i:s', time() + ((int)$resMin) * 60);
        } elseif (($_POST['clear_resolution'] ?? '') === '1') {
            $data['support_resolution_due_at'] = null;
        }

        // Solução temporária e problema de terceiros.
        $data['support_workaround'] = trim($_POST['support_workaround'] ?? '') ?: null;
        $data['is_third_party'] = !empty($_POST['is_third_party']) ? 1 : 0;
        $data['third_party_name'] = trim($_POST['third_party_name'] ?? '') ?: null;
        $data['third_party_notes'] = trim($_POST['third_party_notes'] ?? '') ?: null;

        $this->ticketModel->update($id, $data);
        if ($this->isAjax()) {
            $this->json(['success' => true]);
        }
        flash('success', 'Dados de suporte atualizados.');
        $this->redirect('tickets/show/' . $id);
    }

    /**
     * Define a previsão de publicação em produção (equipe).
     */
    public function savePrevisao($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }
        $previsao = trim($_POST['previsao_publicacao'] ?? '');
        $valid = $previsao !== '' && \DateTime::createFromFormat('Y-m-d', $previsao) !== false;
        $this->ticketModel->update($id, ['previsao_publicacao' => $valid ? $previsao : null]);
        if ($this->isAjax()) {
            $this->json(['success' => true]);
        }
        flash('success', 'Previsão de publicação atualizada.');
        $this->redirect('tickets/show/' . $id);
    }

    /**
     * Relaciona esta demanda a outra (Suporte -> Incidente -> Correção).
     * Cria a aresta direcionada em ticket_relations. Apenas equipe.
     */
    public function relate($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->redirect('tickets');
        }

        $target = (int)($_POST['target_ticket_id'] ?? 0);
        $type = $_POST['relation_type'] ?? 'relacionado';
        $validTypes = ['suporte', 'incidente', 'correcao', 'relacionado'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'relacionado';
        }

        if ($target <= 0 || $target == (int)$id) {
            flash('error', 'Selecione uma demanda válida para relacionar.');
            $this->redirect('tickets/show/' . $id);
        }

        $targetTicket = $this->ticketModel->findById($target);
        if (!$targetTicket) {
            flash('error', 'Demanda de destino não encontrada.');
            $this->redirect('tickets/show/' . $id);
        }

        $user = $this->currentUser();
        $db = Database::getInstance();
        try {
            $db->query(
                "INSERT IGNORE INTO ticket_relations (source_ticket_id, target_ticket_id, relation_type, created_by)
                 VALUES (?, ?, ?, ?)",
                [(int)$id, $target, $type, (int)$user['id']]
            );
            flash('success', 'Demanda relacionada com sucesso.');
        } catch (\Throwable $e) {
            flash('error', 'Não foi possível relacionar a demanda.');
        }
        $this->redirect('tickets/show/' . $id);
    }

    /**
     * Remove um relacionamento entre demandas. Apenas equipe.
     */
    public function unrelate($relationId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'developer', 'analyst']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$relationId) {
            $this->redirect('tickets');
        }
        $db = Database::getInstance();
        $rel = $db->fetch("SELECT source_ticket_id FROM ticket_relations WHERE id = ?", [(int)$relationId]);
        $db->query("DELETE FROM ticket_relations WHERE id = ?", [(int)$relationId]);
        flash('success', 'Relacionamento removido.');
        $this->redirect('tickets/show/' . ($rel['source_ticket_id'] ?? ''));
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

        // Suporte a múltiplos atendentes (checkbox) com fallback ao campo único
        if (isset($_POST['attendant_ids'])) {
            $attendantIds = array_values(array_unique(array_filter(array_map(
                'intval',
                (array)$_POST['attendant_ids']
            ))));
        } elseif (!empty($_POST['attendant_id'])) {
            $attendantIds = [(int)$_POST['attendant_id']];
        } else {
            $attendantIds = [];
        }

        // Atendentes que já estavam atribuídos (para notificar apenas os novos)
        $previousIds = array_map(function ($a) { return (int)$a['id']; }, $this->ticketModel->getAttendants($id));

        // Persiste o conjunto de atendentes e sincroniza o principal
        $this->ticketModel->setAttendants($id, $attendantIds);

        // Ao atribuir, mover para "em andamento" (mantém comportamento anterior).
        // A admissão (demanda #251, Opção A) NÃO vem da atribuição em si, mas da
        // entrada em trabalho: por isso usamos updateStatus('in_progress'), que
        // carimba admitted_at de forma idempotente apenas quando entra em trabalho.
        if (!empty($attendantIds)) {
            $this->ticketModel->updateStatus($id, 'in_progress');
        }

        $primaryId = $attendantIds[0] ?? null;

        // Sincronizar card do planejamento com o atendente principal
        $planningCard = new PlanningCard();
        $card = Database::getInstance()->fetch("SELECT id FROM planning_cards WHERE ticket_id = ?", [$id]);
        if ($card) {
            $planningCard->update($card['id'], ['assigned_to' => $primaryId]);
        }

        // Notificar apenas os atendentes recém-adicionados
        foreach ($attendantIds as $aId) {
            if (!in_array($aId, $previousIds, true)) {
                $this->notifyAssignment($id, $aId, 'atendente');
            }
        }

        flash('success', 'Atendentes atualizados com sucesso!');
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

        // Escopo: só pode comentar em demanda que pode acessar (evita IDOR).
        $ticketForAccess = $this->ticketModel->findById($id);
        if (!$this->canAccessTicket($ticketForAccess)) {
            $this->json(['error' => 'Sem permissão'], 403);
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

        // Escopo: só lê mensagens de demanda que pode acessar (evita IDOR).
        if (!$this->canAccessTicket($this->ticketModel->findById($id))) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

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

        // Escopo: só anexa em demanda que pode acessar (evita IDOR).
        if (!$this->canAccessTicket($this->ticketModel->findById($id))) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        if (!empty($_FILES['file'])) {
            $result = $this->attachmentModel->upload($_FILES['file'], $id, $user['id']);
            $this->json($result);
        }

        $this->json(['error' => 'Nenhum arquivo enviado'], 400);
    }

    // Notificações
    private function sendNewTicketNotification($ticketId)
    {
        // Lógica extraída para um serviço compartilhado com a API de criação de
        // chamados. Mantido como fino wrapper para preservar a assinatura e
        // qualquer chamada futura dentro do controller.
        (new TicketNotificationService())->notifyNewTicket((int)$ticketId);
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

    /**
     * Dashboard de Performance Operacional
     * Mostra métricas de tempo de resolução, quantidade de tickets, etc.
     */
    public function performance()
    {
        $this->requireRole(['super_admin', 'attendant', 'comercial']);
        $user = $this->currentUser();

        // Filtros de período (padrão: mês atual)
        $startDate = $_GET['start'] ?? date('Y-m-01');
        $endDate = $_GET['end'] ?? date('Y-m-t');

        // Filtro por atendente
        $filterUserId = null;
        if ($user['role'] !== 'super_admin') {
            $filterUserId = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $filterUserId = intval($_GET['user_id']);
        }

        // Métricas gerais
        $metrics = $this->ticketModel->getOperationalMetrics($startDate, $endDate, $filterUserId);

        // Tabela por atendente
        $byAttendant = $this->ticketModel->getOperationalMetricsByAttendant($startDate, $endDate);

        // Distribuição por status
        $statusDist = $this->ticketModel->getStatusDistribution($startDate, $endDate, $filterUserId);

        // Lista de atendentes para filtro
        $userModel = new User();
        $attendants = $userModel->getByRoles(['super_admin', 'attendant', 'developer', 'analyst', 'whatsapp_agent']);

        $this->view('tickets/performance', [
            'user' => $user,
            'metrics' => $metrics,
            'byAttendant' => $byAttendant,
            'statusDist' => $statusDist,
            'attendants' => $attendants,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filterUserId' => $filterUserId,
            'isAdmin' => $user['role'] === 'super_admin',
        ]);
    }
}
