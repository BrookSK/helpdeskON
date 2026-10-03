<?php

/**
 * Login simplificado do CLIENTE por PIN (Fase 9).
 *
 * Página pública em /clientpin onde o PRÓPRIO cliente informa seu PIN de 6
 * dígitos (users.client_pin, role=client) e cai direto na criação de uma nova
 * demanda vinculada a ELE — sem ver dados de outro cliente.
 *
 * NÃO confundir com /solicitacaoexterna (PIN de EQUIPE, 4 dígitos, cria demanda
 * em nome do atendente). Aqui a sessão é 'client_pin_access', isolada da sessão
 * de login normal e da sessão externa de equipe.
 */
class ClientpinController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    private const MAX_TRIES = 5;
    private const WINDOW = 300;

    private function requireClient()
    {
        if (empty($_SESSION['client_pin_access']['user_id'])) {
            $this->redirect('clientpin');
        }
    }

    private function rateLimited(): bool
    {
        $data = $_SESSION['client_pin_attempts'] ?? null;
        if (!$data) return false;
        if ((time() - ($data['first'] ?? 0)) > self::WINDOW) {
            unset($_SESSION['client_pin_attempts']);
            return false;
        }
        return ($data['count'] ?? 0) >= self::MAX_TRIES;
    }

    private function registerAttempt(): void
    {
        $data = $_SESSION['client_pin_attempts'] ?? null;
        if (!$data || (time() - ($data['first'] ?? 0)) > self::WINDOW) {
            $_SESSION['client_pin_attempts'] = ['first' => time(), 'count' => 1];
        } else {
            $_SESSION['client_pin_attempts']['count'] = ($data['count'] ?? 0) + 1;
        }
    }

    private function clientOwner()
    {
        $id = $_SESSION['client_pin_access']['user_id'] ?? null;
        return $id ? $this->userModel->findById($id) : null;
    }

    public function index()
    {
        if (!empty($_SESSION['client_pin_access']['user_id'])) {
            $this->redirect('clientpin/novaDemanda');
        }
        $this->renderExternal('external/client_login', []);
    }

    public function authenticate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('clientpin');
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('clientpin');
        }
        if ($this->rateLimited()) {
            flash('error', 'Muitas tentativas. Aguarde alguns minutos e tente novamente.');
            $this->redirect('clientpin');
        }

        $pin = trim($_POST['pin'] ?? '');
        if (!ClientPinRules::isValidFormat($pin)) {
            $this->registerAttempt();
            flash('error', 'Informe um PIN válido de 6 dígitos.');
            $this->redirect('clientpin');
        }

        $owner = $this->userModel->findByClientPin($pin);
        if (!$owner || !ClientPinRules::canAuthenticate($owner)) {
            $this->registerAttempt();
            flash('error', 'PIN inválido.');
            $this->redirect('clientpin');
        }

        unset($_SESSION['client_pin_attempts']);
        // Higiene: encerra qualquer sessão de login normal ou de equipe neste navegador.
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'],
              $_SESSION['user_role'], $_SESSION['user_avatar'], $_SESSION['user_company_id'],
              $_SESSION['user_is_company_owner'], $_SESSION['active_company_id'], $_SESSION['impersonator'],
              $_SESSION['external_access']);

        $_SESSION['client_pin_access'] = [
            'user_id' => (int)$owner['id'],
            'user_name' => $owner['name'],
            'company_id' => $owner['company_id'] ?? null,
            'started_at' => time(),
        ];
        $this->redirect('clientpin/novaDemanda');
    }

    public function novaDemanda()
    {
        $this->requireClient();
        $owner = $this->clientOwner();
        if (!$owner) {
            unset($_SESSION['client_pin_access']);
            $this->redirect('clientpin');
        }
        $this->renderExternal('external/client_nova_demanda', ['owner' => $owner]);
    }

    public function store()
    {
        $this->requireClient();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('clientpin/novaDemanda');
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('clientpin/novaDemanda');
        }
        $owner = $this->clientOwner();
        if (!$owner) {
            unset($_SESSION['client_pin_access']);
            $this->redirect('clientpin');
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium';
        if ($title === '' || $description === '') {
            flash('error', 'Título e descrição são obrigatórios.');
            $this->redirect('clientpin/novaDemanda');
        }

        $db = Database::getInstance();
        // Demanda vinculada ao PRÓPRIO cliente (client_id = o usuário cliente).
        $lastNumber = $db->fetch("SELECT MAX(client_ticket_number) as last_num FROM tickets WHERE client_id = ?", [(int)$owner['id']]);
        $ticketNumber = ($lastNumber['last_num'] ?? 0) + 1;

        $ticketModel = new Ticket();
        $ticketId = $ticketModel->create([
            'client_id' => (int)$owner['id'],
            'title' => $title,
            'description' => $description,
            'category' => $category ?: null,
            'priority' => $priority,
            'status' => 'open',
            'client_ticket_number' => $ticketNumber,
        ]);

        // Card no planejamento vinculado à empresa do cliente.
        try {
            $ticket = $ticketModel->findById($ticketId);
            (new PlanningCard())->createFromTicket($ticket, $owner['company_id'] ?? null);
        } catch (\Throwable $e) {}

        $this->renderExternal('external/client_sucesso', [
            'owner' => $owner,
            'ticketTitle' => $title,
            'ticketNumber' => $ticketNumber,
        ]);
    }

    public function logout()
    {
        unset($_SESSION['client_pin_access']);
        $this->redirect('clientpin');
    }

    private function renderExternal($view, $data = [])
    {
        extract($data);
        require APP_PATH . '/views/' . $view . '.php';
    }
}
