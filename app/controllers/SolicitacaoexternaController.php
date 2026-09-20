<?php

/**
 * Acesso externo por PIN (demanda #239).
 *
 * Página pública em /solicitacaoexterna onde um cliente informa o PIN de 4
 * dígitos de um usuário da equipe para entrar num ambiente restrito cujo
 * ÚNICO recurso é criar novas demandas em nome daquele usuário.
 *
 * IMPORTANTE:
 * - Este fluxo NÃO usa $_SESSION['user_id']; usa $_SESSION['external_access'].
 *   Assim o login normal do sistema não é afetado e o usuário externo não
 *   "vira" um usuário logado do sistema.
 * - Qualquer outra rota do sistema exige requireLogin()/requireRole(), que
 *   checam $_SESSION['user_id']. Como a sessão externa não o define, tentar
 *   acessar outra área redireciona para /login (ou seja, sai do ambiente).
 * - Aqui dentro, requireExternal() garante que só quem passou pelo PIN acessa
 *   as telas de criação de demanda; caso contrário volta para a tela do PIN.
 */
class SolicitacaoexternaController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /** Garante que há uma sessão de acesso externo ativa; senão volta ao PIN. */
    private function requireExternal()
    {
        if (empty($_SESSION['external_access']['user_id'])) {
            $this->redirect('solicitacaoexterna');
        }
    }

    /** Dados do usuário da equipe dono do acesso externo atual. */
    private function externalOwner()
    {
        $id = $_SESSION['external_access']['user_id'] ?? null;
        if (!$id) return null;
        return $this->userModel->findById($id);
    }

    /** Tela de entrada: formulário do PIN. Se já autenticado, vai à nova demanda. */
    public function index()
    {
        if (!empty($_SESSION['external_access']['user_id'])) {
            $this->redirect('solicitacaoexterna/novaDemanda');
        }
        $this->renderExternal('external/login', []);
    }

    /** Valida o PIN e abre a sessão externa. */
    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('solicitacaoexterna');
        }

        $pin = trim($_POST['pin'] ?? '');

        if (!preg_match('/^\d{4}$/', $pin)) {
            flash('error', 'Informe um PIN válido de 4 dígitos.');
            $this->redirect('solicitacaoexterna');
        }

        $owner = $this->userModel->findByPin($pin);
        if (!$owner) {
            flash('error', 'PIN inválido.');
            $this->redirect('solicitacaoexterna');
        }

        // Higiene: se havia uma sessão de login normal neste navegador, encerra-a.
        // O acesso externo é um contexto separado e restrito (só criar demandas).
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'],
              $_SESSION['user_role'], $_SESSION['user_avatar'], $_SESSION['user_company_id'],
              $_SESSION['user_is_company_owner'], $_SESSION['active_company_id'], $_SESSION['impersonator']);

        // Sessão externa isolada (não interfere no login normal).
        $_SESSION['external_access'] = [
            'user_id' => (int)$owner['id'],
            'user_name' => $owner['name'],
            'started_at' => time(),
        ];

        $this->redirect('solicitacaoexterna/novaDemanda');
    }

    /** Formulário de nova demanda (único recurso do ambiente externo). */
    public function novaDemanda()
    {
        $this->requireExternal();
        $owner = $this->externalOwner();
        if (!$owner) {
            unset($_SESSION['external_access']);
            $this->redirect('solicitacaoexterna');
        }
        $this->renderExternal('external/nova_demanda', ['owner' => $owner]);
    }

    /** Cria a demanda a partir do ambiente externo. */
    public function store()
    {
        $this->requireExternal();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('solicitacaoexterna/novaDemanda');
        }

        $owner = $this->externalOwner();
        if (!$owner) {
            unset($_SESSION['external_access']);
            $this->redirect('solicitacaoexterna');
        }

        $requesterName = trim($_POST['requester_name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium';

        if ($title === '' || $description === '') {
            flash('error', 'Título e descrição são obrigatórios.');
            $this->redirect('solicitacaoexterna/novaDemanda');
        }

        // A demanda é registrada em nome do usuário da equipe dono do PIN.
        // O nome de quem solicitou (cliente externo) é anexado à descrição para
        // rastreabilidade, sem criar novos usuários/relações.
        $finalDescription = $description;
        if ($requesterName !== '') {
            $finalDescription = "Solicitado por (externo): {$requesterName}\n\n" . $description;
        }

        $ticketData = [
            'client_id' => (int)$owner['id'],
            'attendant_id' => (int)$owner['id'],
            'title' => $title,
            'description' => $finalDescription,
            'category' => $category ?: null,
            'priority' => $priority,
            'status' => 'open',
        ];

        // Número sequencial por "cliente" (aqui, o dono do PIN).
        $db = Database::getInstance();
        $lastNumber = $db->fetch(
            "SELECT MAX(client_ticket_number) as last_num FROM tickets WHERE client_id = ?",
            [(int)$owner['id']]
        );
        $ticketData['client_ticket_number'] = ($lastNumber['last_num'] ?? 0) + 1;

        $ticketModel = new Ticket();
        $ticketId = $ticketModel->create($ticketData);

        // Anexos (mesmo esquema da criação interna).
        if (!empty($_FILES['attachments']['name'][0])) {
            $attachmentModel = new TicketAttachment();
            $files = $_FILES['attachments'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size' => $files['size'][$i],
                    ];
                    $attachmentModel->upload($file, $ticketId, (int)$owner['id']);
                }
            }
        }

        // Card automático no Planejamento (mesmo comportamento da criação interna).
        try {
            $ticket = $ticketModel->findById($ticketId);
            (new PlanningCard())->createFromTicket($ticket);
        } catch (\Throwable $e) {
            // Não bloqueia a criação da demanda se o card falhar.
        }

        // Notifica o usuário da equipe que recebeu uma demanda externa.
        try {
            $db->insert('notifications', [
                'user_id' => (int)$owner['id'],
                'ticket_id' => $ticketId,
                'title' => 'Nova demanda (acesso externo)',
                'message' => 'Uma nova demanda foi criada via link externo: "' . $title . '".',
                'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* opcional */ }

        $this->renderExternal('external/sucesso', ['owner' => $owner, 'ticketTitle' => $title]);
    }

    /** Encerra a sessão externa (não toca na sessão de login normal). */
    public function logout()
    {
        unset($_SESSION['external_access']);
        $this->redirect('solicitacaoexterna');
    }

    /**
     * Renderiza uma view "externa" standalone (sem sidebar/layout interno).
     * As views externas são páginas completas próprias.
     */
    private function renderExternal($view, $data = [])
    {
        extract($data);
        require APP_PATH . '/views/' . $view . '.php';
    }
}
