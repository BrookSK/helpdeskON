<?php

class SettingsController extends Controller
{
    public function index()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $settings = Config::getAll();
        $this->view('admin/settings', ['user' => $user, 'settings' => $settings]);
    }

    public function save()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings');
        }

        $fields = [
            'app_name', 'app_email',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'smtp_from_name', 'smtp_from_email',
            'openai_api_key',
            'webhook_url', 'webhook_phone', 'webhook_name', 'webhook_enabled',
            'webhook_message_template',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                Config::set($field, trim($_POST[$field]));
            }
        }

        // Checkbox
        if (!isset($_POST['webhook_enabled'])) {
            Config::set('webhook_enabled', '0');
        }

        Config::reload();
        flash('success', 'Configurações salvas com sucesso!');
        $this->redirect('settings');
    }

    // Configuração do banco via tela (primeira execução)
    public function database()
    {
        $user = $this->currentUser();
        if ($user && $user['role'] !== 'super_admin') {
            $this->redirect('dashboard');
        }
        $configFile = BASE_PATH . '/config/database.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $this->view('admin/database_config', ['config' => $config, 'user' => $user]);
    }

    public function saveDatabase()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings/database');
        }

        $config = [
            'host' => trim($_POST['host'] ?? 'localhost'),
            'port' => trim($_POST['port'] ?? '3306'),
            'database' => trim($_POST['database'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ];

        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        $configFile = BASE_PATH . '/config/database.php';
        file_put_contents($configFile, $content);

        flash('success', 'Configuração do banco de dados salva!');
        $this->redirect('settings');
    }

    // Testar envio de email SMTP
    public function testEmail()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método inválido'], 405);
        }

        $fromEmail = Config::get('smtp_from_email');
        $smtpHost = Config::get('smtp_host');
        $adminEmail = $_SESSION['user_email'] ?? '';

        if (empty($smtpHost) || empty($fromEmail)) {
            $this->json(['success' => false, 'message' => 'SMTP não configurado. Preencha os campos e salve antes de testar.']);
        }

        $toEmail = $adminEmail ?: $fromEmail;
        $subject = 'Teste de Email - ON Solutions Helpdesk';
        $body = Mailer::template(
            'Teste de Email',
            '<p>Este é um email de teste enviado pelo sistema ON Solutions Helpdesk.</p>
             <p>Se você está recebendo este email, a configuração SMTP está funcionando corretamente!</p>
             <p style="font-size:0.8rem;color:#999;">Enviado em: ' . date('d/m/Y H:i:s') . '</p>'
        );

        $sent = Mailer::send($toEmail, $subject, $body);

        if ($sent) {
            $this->json(['success' => true, 'message' => "Email de teste enviado para {$toEmail}!"]);
        } else {
            $this->json(['success' => false, 'message' => 'Falha no envio. Verifique as credenciais SMTP.']);
        }
    }
}
