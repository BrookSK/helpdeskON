<?php

class PasswordController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Tela: Esqueci minha senha
     */
    public function forgot()
    {
        if ($this->isLoggedIn()) {
            $this->redirect('dashboard');
        }
        $this->view('auth/forgot_password');
    }

    /**
     * Processar: Enviar email com link de redefinição
     */
    public function sendReset()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('password/forgot');
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            flash('error', 'Informe seu email.');
            $this->redirect('password/forgot');
        }

        $user = $this->userModel->findByEmail($email);

        // Sempre mostra mensagem de sucesso (não revela se email existe)
        if (!$user) {
            flash('success', 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.');
            $this->redirect('password/forgot');
        }

        // Gerar token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = Database::getInstance();

        // Invalidar tokens anteriores
        $db->query(
            "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL",
            [$user['id']]
        );

        // Salvar novo token
        $db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        // Enviar email
        $resetLink = baseUrl('password/reset/' . $token);
        $htmlBody = Mailer::template(
            'Redefinição de Senha',
            "<p>Olá, <strong>" . htmlspecialchars($user['name']) . "</strong>!</p>
            <p>Recebemos uma solicitação para redefinir sua senha.</p>
            <p style='text-align:center;margin:25px 0;'>
                <a href='{$resetLink}' style='background:#00BFA6;color:#fff;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.9rem;display:inline-block;'>
                    Redefinir Minha Senha
                </a>
            </p>
            <p>Este link expira em <strong>1 hora</strong>.</p>
            <p style='font-size:0.82rem;color:#999;'>Se você não solicitou, ignore este email.</p>
            <p style='font-size:0.78rem;color:#bbb;word-break:break-all;'>Link direto: {$resetLink}</p>"
        );

        Mailer::send($user['email'], 'Redefinição de Senha - ON Solutions Helpdesk', $htmlBody);

        flash('success', 'Se o email estiver cadastrado, você receberá um link para redefinir sua senha.');
        $this->redirect('password/forgot');
    }

    /**
     * Tela: Redefinir senha (com token)
     */
    public function reset($token = null)
    {
        if (!$token) {
            flash('error', 'Link inválido.');
            $this->redirect('login');
        }

        $resetData = $this->validateToken($token);
        if (!$resetData) {
            flash('error', 'Link expirado ou inválido. Solicite um novo.');
            $this->redirect('password/forgot');
        }

        $this->view('auth/reset_password', ['token' => $token]);
    }

    /**
     * Processar: Salvar nova senha
     */
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('login');
        }

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($token) || empty($password) || empty($confirmPassword)) {
            flash('error', 'Preencha todos os campos.');
            $this->redirect('password/reset/' . $token);
        }

        if ($password !== $confirmPassword) {
            flash('error', 'As senhas não coincidem.');
            $this->redirect('password/reset/' . $token);
        }

        if (strlen($password) < 6) {
            flash('error', 'A senha deve ter no mínimo 6 caracteres.');
            $this->redirect('password/reset/' . $token);
        }

        $resetData = $this->validateToken($token);
        if (!$resetData) {
            flash('error', 'Link expirado ou inválido. Solicite um novo.');
            $this->redirect('password/forgot');
        }

        // Atualizar senha
        $this->userModel->update($resetData['user_id'], ['password' => $password]);

        // Marcar token como usado
        $db = Database::getInstance();
        $db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [$resetData['id']]);

        flash('success', 'Senha redefinida com sucesso! Faça login.');
        $this->redirect('login');
    }

    /**
     * Validar token
     */
    private function validateToken($token)
    {
        $db = Database::getInstance();
        return $db->fetch(
            "SELECT * FROM password_resets 
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );
    }
}
