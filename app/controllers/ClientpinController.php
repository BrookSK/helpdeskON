<?php

/**
 * Login simplificado do CLIENTE por PIN.
 *
 * Página pública em /clientpin onde o PRÓPRIO cliente informa seu PIN de 6
 * dígitos (users.client_pin, role=client). Ao validar, abrimos uma SESSÃO DE
 * LOGIN REAL do cliente (as mesmas chaves do login por senha) e o levamos à
 * área interna normal, começando na tela de Nova Demanda (tickets/create) —
 * com a sidebar e as mesmas opções que o cliente teria logando com senha.
 *
 * NÃO confundir com /solicitacaoexterna (PIN de EQUIPE, 4 dígitos, cria demanda
 * em nome do atendente, numa tela externa isolada). Aqui o PIN é do próprio
 * cliente e dá acesso à área dele.
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

    /** Tela de entrada: formulário do PIN. Se já logado, vai à Nova Demanda. */
    public function index()
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('tickets/create');
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
        // Higiene: encerra qualquer sessão residual de acesso externo/impersonação.
        unset($_SESSION['external_access'], $_SESSION['client_pin_access'],
              $_SESSION['impersonator'], $_SESSION['active_company_id']);

        // O acesso por PIN do cliente estabelece uma SESSÃO DE LOGIN REAL do
        // próprio cliente (mesmas chaves do login por senha), para que ele caia
        // na área interna normal (sidebar do cliente) e tenha acesso às mesmas
        // opções (Minhas Demandas, Cronograma, Documentos), começando em
        // "Nova Demanda". $owner é a linha completa de users (findByClientPin).
        $_SESSION['user_id'] = (int)$owner['id'];
        $_SESSION['user_name'] = $owner['name'];
        $_SESSION['user_email'] = $owner['email'] ?? '';
        $_SESSION['user_role'] = $owner['role'];
        $_SESSION['user_avatar'] = $owner['avatar'] ?? null;
        $_SESSION['user_company_id'] = $owner['company_id'] ?? null;
        $_SESSION['user_is_company_owner'] = $owner['is_company_owner'] ?? 0;

        // Auditoria: registra o acesso por PIN de cliente.
        if (class_exists('ActivityLogger')) {
            try { ActivityLogger::logLogin((int)$owner['id'], 'client_pin'); } catch (\Throwable $e) {}
        }

        // Cai direto na tela interna de Nova Demanda (com a sidebar do cliente).
        $this->redirect('tickets/create');
    }

    /**
     * Renderiza a view "externa" standalone da tela de PIN (sem sidebar).
     * A criação da demanda em si acontece na área interna (tickets/create),
     * após a sessão de login do cliente ter sido estabelecida.
     */
    private function renderExternal($view, $data = [])
    {
        extract($data);
        require APP_PATH . '/views/' . $view . '.php';
    }
}
