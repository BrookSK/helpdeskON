<?php

/**
 * Login por PIN (/clientpin).
 *
 * Página pública onde o usuário informa seu PIN de 4 dígitos (users.client_pin).
 * Ao validar, abrimos uma SESSÃO DE LOGIN REAL (as mesmas chaves do login por
 * senha) e o levamos à área interna, começando na tela de Nova Demanda
 * (tickets/create) — com os mesmos acessos do login normal. Vale para qualquer
 * papel: o PIN é por usuário.
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
            flash('error', 'Informe um PIN válido de 4 dígitos.');
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

        // O acesso por PIN estabelece uma SESSÃO DE LOGIN REAL do usuário dono
        // do PIN (mesmas chaves do login por senha), com os mesmos acessos do
        // papel dele. Vale para qualquer papel (o PIN é por usuário), caindo na
        // área interna começando em "Nova Demanda". $owner é a linha completa
        // de users (findByClientPin).
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
     * Tela pública de definição de PIN via token (link enviado pelo admin).
     * O admin nunca vê nem escolhe o PIN: quem define é o próprio usuário aqui.
     */
    public function resetPin($token = null)
    {
        if (!$token) {
            flash('error', 'Link inválido.');
            $this->redirect('clientpin');
        }
        if (!$this->validatePinToken($token)) {
            flash('error', 'Link expirado ou inválido. Peça um novo ao administrador.');
            $this->redirect('clientpin');
        }
        $this->renderExternal('external/client_set_pin', ['token' => $token]);
    }

    /** Processa a definição do novo PIN a partir do token. */
    public function updatePin()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('clientpin');
        }

        $token = $_POST['token'] ?? '';
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            flash('error', 'Sessão expirada. Recarregue a página e tente novamente.');
            $this->redirect('clientpin/resetPin/' . $token);
        }

        $reset = $this->validatePinToken($token);
        if (!$reset) {
            flash('error', 'Link expirado ou inválido. Peça um novo ao administrador.');
            $this->redirect('clientpin');
        }

        $pin = trim($_POST['pin'] ?? '');
        if (!ClientPinRules::isValidFormat($pin)) {
            flash('error', 'O PIN deve ter exatamente 4 dígitos numéricos.');
            $this->redirect('clientpin/resetPin/' . $token);
        }
        if ($this->userModel->clientPinExists($pin, (int)$reset['user_id'])) {
            flash('error', 'Este PIN já está em uso. Escolha outro.');
            $this->redirect('clientpin/resetPin/' . $token);
        }

        $saved = $this->userModel->setClientPin((int)$reset['user_id'], $pin);
        if ($saved === null) {
            flash('error', 'Não foi possível salvar o PIN. Tente outro.');
            $this->redirect('clientpin/resetPin/' . $token);
        }

        // Consome o token (não pode ser reutilizado).
        Database::getInstance()->update(
            'password_resets',
            ['used_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$reset['id']]
        );

        flash('success', 'PIN definido com sucesso! Use-o na opção "Entrar com PIN".');
        $this->redirect('clientpin');
    }

    /** Valida um token de redefinição de PIN (kind = 'pin', não usado, não expirado). */
    private function validatePinToken($token)
    {
        return Database::getInstance()->fetch(
            "SELECT * FROM password_resets
             WHERE token = ? AND kind = 'pin' AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1",
            [$token]
        );
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
