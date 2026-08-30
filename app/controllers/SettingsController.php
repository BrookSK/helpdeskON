<?php

class SettingsController extends Controller
{
    public function index()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $settings = Config::getAll();
        $whatsappGroups = (new WhatsappContact())->getAllGroups();
        $dbInfo = $this->detectDatabaseInfo();

        // Assinaturas de e-mail por domínio (tabela pode não existir ainda)
        $emailSignatures = [];
        try {
            $emailSignatures = Database::getInstance()->fetchAll("SELECT * FROM email_signatures ORDER BY domain ASC");
        } catch (\Throwable $e) { $emailSignatures = []; }

        $this->view('admin/settings', ['user' => $user, 'settings' => $settings, 'whatsappGroups' => $whatsappGroups, 'dbInfo' => $dbInfo, 'emailSignatures' => $emailSignatures]);
    }

    /**
     * Detecta qual banco está em uso e o ambiente (produção/beta).
     * Reflete a mesma lógica de detecção do config/database.php.
     */
    private function detectDatabaseInfo(): array
    {
        $configFile = BASE_PATH . '/config/database.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $dbName = $config['database'] ?? '';

        // Descobrir a branch detectada (mesmas duas camadas do config)
        $branch = 'main';
        $source = 'default';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if ($host !== '' && (str_contains($host, 'plesk.page') || str_contains($host, 'beta'))) {
            $branch = 'beta';
            $source = 'host';
        } else {
            $headFile = BASE_PATH . '/.git/HEAD';
            if (file_exists($headFile)) {
                $head = trim(file_get_contents($headFile));
                if (str_starts_with($head, 'ref: refs/heads/')) {
                    $branch = substr($head, strlen('ref: refs/heads/'));
                    $source = 'git';
                }
            }
        }

        $isProduction = ($branch === 'main');

        return [
            'database'    => $dbName,
            'host'        => $config['host'] ?? '',
            'username'    => $config['username'] ?? '',
            'environment' => $isProduction ? 'production' : 'beta',
            'branch'      => $branch,
            'source'      => $source, // host | git | default
            'http_host'   => $host,
        ];
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
            'buffer_api_key',
            'apollo_api_key', 'apollo_base_url', 'apollo_webhook_token',
            'app_public_url',
            'google_client_id', 'google_client_secret', 'google_refresh_token', 'google_calendar_id',
            // Agendamento público (bloco "Agendamento" das sequências)
            'booking_min_advance_days', 'booking_work_start', 'booking_work_end',
            'booking_slot_minutes', 'booking_days_of_week', 'booking_duration_min',
            'booking_notify_hours_before', 'booking_link_expiry_days',
            'webhook_url', 'webhook_phones', 'webhook_names', 'webhook_enabled',
            'webhook_message_template',
            'whatsapp_number', 'whatsapp_message', 'whatsapp_enabled',
            'whatsapp_default_group_jid', 'whatsapp_group_notify_enabled',
            'cron_token',
            'linkedin_client_id', 'linkedin_client_secret', 'linkedin_scopes',
            // Nvoip (telefonia) — campos não-secretos
            'nvoip_auth_base_url', 'nvoip_base_url', 'nvoip_oauth_client_id',
            'nvoip_oauth_scopes', 'nvoip_caller',
            // Nvoip webphone (WSS) — config global (ramal/senha SIP são por usuário)
            'nvoip_ws_server', 'nvoip_sip_domain', 'nvoip_ice_servers', 'nvoip_dial_format',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                Config::set($field, trim($_POST[$field]));
            }
        }

        // Nvoip client credential é SECRETO: só atualiza quando um novo valor for informado.
        // Deixar o campo em branco preserva a credencial já salva (nunca é reexibida no frontend).
        if (isset($_POST['nvoip_oauth_client_credential']) && trim($_POST['nvoip_oauth_client_credential']) !== '') {
            Config::set('nvoip_oauth_client_credential', trim($_POST['nvoip_oauth_client_credential']));
        }



        // === Tokens dinâmicos: Meta (array meta_tokens[]) ===
        if (isset($_POST['meta_tokens']) && is_array($_POST['meta_tokens'])) {
            // Limpa todos os tokens Meta antigos (até 20)
            Config::set('meta_access_token', '');
            for ($i = 2; $i <= 20; $i++) {
                Config::set('meta_access_token_' . $i, '');
            }
            // Salva os novos (filtra vazios)
            $metaTokens = array_values(array_filter(array_map('trim', $_POST['meta_tokens'])));
            foreach ($metaTokens as $idx => $token) {
                $key = $idx === 0 ? 'meta_access_token' : 'meta_access_token_' . ($idx + 1);
                Config::set($key, $token);
            }
            Config::set('meta_token_status', '');
            Config::set('meta_token_checked_at', '');
        }

        // === Tokens dinâmicos: LinkedIn (array linkedin_tokens[]) ===
        if (isset($_POST['linkedin_tokens']) && is_array($_POST['linkedin_tokens'])) {
            Config::set('linkedin_access_token', '');
            for ($i = 2; $i <= 20; $i++) {
                Config::set('linkedin_access_token_' . $i, '');
            }
            $linkedinTokens = array_values(array_filter(array_map('trim', $_POST['linkedin_tokens'])));
            foreach ($linkedinTokens as $idx => $token) {
                $key = $idx === 0 ? 'linkedin_access_token' : 'linkedin_access_token_' . ($idx + 1);
                Config::set($key, $token);
            }
            Config::set('linkedin_token_status', '');
            Config::set('linkedin_token_checked_at', '');
        }

        // Checkboxes
        if (!isset($_POST['webhook_enabled'])) {
            Config::set('webhook_enabled', '0');
        }
        if (!isset($_POST['whatsapp_enabled'])) {
            Config::set('whatsapp_enabled', '0');
        }
        if (!isset($_POST['whatsapp_group_notify_enabled'])) {
            Config::set('whatsapp_group_notify_enabled', '0');
        }

        // Upload de Logo
        if (!empty($_FILES['app_logo']['name']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = $this->uploadBrandFile($_FILES['app_logo'], 'logo');
            if ($logoPath) {
                Config::set('app_logo', $logoPath);
            }
        }

        // Upload de Favicon
        if (!empty($_FILES['app_favicon']['name']) && $_FILES['app_favicon']['error'] === UPLOAD_ERR_OK) {
            $faviconPath = $this->uploadBrandFile($_FILES['app_favicon'], 'favicon');
            if ($faviconPath) {
                Config::set('app_favicon', $faviconPath);
            }
        }

        Config::reload();
        flash('success', 'Configurações salvas com sucesso!');
        $this->redirect('settings');
    }

    private function uploadBrandFile($file, $prefix)
    {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB max
            return null;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $prefix . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/uploads/brand';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = 'uploads/brand/' . $fileName;
        if (move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            return $filePath;
        }
        return null;
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

    // Testar webhook WhatsApp
    public function testWebhook()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método inválido'], 405);
        }

        $webhookUrl = Config::get('webhook_url');
        $phonesRaw = Config::get('webhook_phones') ?: Config::get('webhook_phone') ?: '';
        $namesRaw = Config::get('webhook_names') ?: Config::get('webhook_name') ?: 'Admin';

        if (empty($webhookUrl)) {
            $this->json(['success' => false, 'message' => 'URL do webhook não configurada. Salve as configurações antes de testar.']);
        }

        if (empty($phonesRaw)) {
            $this->json(['success' => false, 'message' => 'Nenhum telefone configurado. Preencha os telefones e salve antes de testar.']);
        }

        $phones = array_map('trim', explode(',', $phonesRaw));
        $names = array_map('trim', explode(',', $namesRaw));

        // Pegar o primeiro telefone/nome para o teste
        $phone = preg_replace('/[^0-9]/', '', $phones[0] ?? '');
        $name = $names[0] ?? 'Admin';

        if (empty($phone)) {
            $this->json(['success' => false, 'message' => 'Telefone inválido.']);
        }

        $message = "🔔 *Teste de Webhook*\n\nOlá, {$name}! Este é um teste do webhook do helpdesk.\n\nSe você recebeu esta mensagem, a configuração está funcionando!\n\nData: " . date('d/m/Y H:i:s');

        $payload = json_encode([
            'phone' => $phone,
            'name' => $name,
            'message' => $message,
        ]);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            $this->json(['success' => false, 'message' => "Erro de conexão: {$error}"]);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->json(['success' => true, 'message' => "Webhook enviado com sucesso para {$phone} (HTTP {$httpCode})!"]);
        } else {
            $this->json(['success' => false, 'message' => "Falha no webhook. HTTP {$httpCode}. Resposta: " . substr($response, 0, 200)]);
        }
    }

    // Testar conexão Nvoip (fluxo client_credentials)
    public function testNvoip()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método inválido'], 405);
        }

        $api = new NvoipApi();
        if (!$api->isConfigured()) {
            $this->json(['success' => false, 'message' => 'Preencha as credenciais da Nvoip e salve antes de testar.']);
        }

        // Autenticação servidor a servidor (client_credentials).
        // Emitir o token é o critério de sucesso: confirma credenciais, URLs e escopos válidos.
        // Não testamos /users aqui porque a credencial de telefonia (call:make/call:query)
        // normalmente não tem escopo para listar usuários (retornaria 403 esperado).
        $auth = $api->authenticate();
        if (empty($auth['success'])) {
            // Mensagem genérica — não expõe token/segredo/headers.
            $this->json(['success' => false, 'message' => $auth['error'] ?? 'Falha na autenticação com a Nvoip.']);
        }

        $this->json(['success' => true, 'message' => 'Conexão com a Nvoip estabelecida com sucesso (autenticação OK).']);
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

    // ===== Contas de E-mail para Prospecção =====

    public function emailAccounts()
    {
        $this->requireRole(['super_admin']);
        $user = $this->currentUser();
        $model = new EmailAccount();
        $accounts = $model->getAll();

        // Usuários internos para vinculação
        $userModel = new User();
        $allUsers = $userModel->getByRoles(['super_admin', 'attendant', 'developer', 'analyst', 'comercial', 'marketing', 'whatsapp_agent']);

        // Defaults do servidor
        $defaults = [
            'prospection_smtp_host' => Config::get('prospection_smtp_host', ''),
            'prospection_smtp_port' => Config::get('prospection_smtp_port', '587'),
            'prospection_smtp_encryption' => Config::get('prospection_smtp_encryption', 'tls'),
            'prospection_imap_host' => Config::get('prospection_imap_host', ''),
            'prospection_imap_port' => Config::get('prospection_imap_port', '993'),
            'prospection_imap_encryption' => Config::get('prospection_imap_encryption', 'ssl'),
        ];

        $this->view('admin/email_accounts', [
            'user' => $user,
            'accounts' => $accounts,
            'allUsers' => $allUsers,
            'defaults' => $defaults,
        ]);
    }

    public function saveEmailDefaults()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('settings/emailAccounts');

        Config::set('prospection_smtp_host', trim($_POST['prospection_smtp_host'] ?? ''));
        Config::set('prospection_smtp_port', trim($_POST['prospection_smtp_port'] ?? '587'));
        Config::set('prospection_smtp_encryption', trim($_POST['prospection_smtp_encryption'] ?? 'tls'));
        Config::set('prospection_imap_host', trim($_POST['prospection_imap_host'] ?? ''));
        Config::set('prospection_imap_port', trim($_POST['prospection_imap_port'] ?? '993'));
        Config::set('prospection_imap_encryption', trim($_POST['prospection_imap_encryption'] ?? 'ssl'));

        flash('success', 'Servidor padrão salvo!');
        $this->redirect('settings/emailAccounts');
    }

    public function saveEmailAccount()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('settings/emailAccounts');

        $user = $this->currentUser();
        $model = new EmailAccount();
        $id = !empty($_POST['id']) ? intval($_POST['id']) : null;

        $data = [
            'email' => trim($_POST['email'] ?? ''),
            'display_name' => trim($_POST['display_name'] ?? '') ?: null,
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => intval($_POST['smtp_port'] ?? 587),
            'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none']) ? $_POST['smtp_encryption'] : 'tls',
            'smtp_username' => trim($_POST['smtp_username'] ?? ''),
            'imap_host' => trim($_POST['imap_host'] ?? '') ?: null,
            'imap_port' => intval($_POST['imap_port'] ?? 993),
            'imap_encryption' => in_array($_POST['imap_encryption'] ?? '', ['ssl', 'tls', 'none']) ? $_POST['imap_encryption'] : 'ssl',
        ];

        if ($id) {
            // Atualiza — só manda senha se foi preenchida
            if (!empty($_POST['smtp_password'])) {
                $data['smtp_password'] = $_POST['smtp_password'];
            }
            $model->update($id, $data);
        } else {
            $data['smtp_password'] = $_POST['smtp_password'] ?? '';
            $data['created_by'] = $user['id'];
            $id = $model->create($data);
        }

        // Vincula usuários
        $userIds = array_filter(array_map('intval', $_POST['users'] ?? []));
        $model->setLinkedUsers($id, $userIds);

        flash('success', 'Conta de e-mail salva com sucesso!');
        $this->redirect('settings/emailAccounts');
    }

    /**
     * Salva (cria/atualiza) uma assinatura de e-mail por domínio.
     * POST settings/saveEmailSignature  (multipart: logo opcional)
     */
    public function saveEmailSignature()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect('settings');

        $db = Database::getInstance();
        $id = !empty($_POST['sig_id']) ? intval($_POST['sig_id']) : null;
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        // normaliza: se colaram um e-mail, extrai o domínio
        if (strpos($domain, '@') !== false) $domain = substr(strrchr($domain, '@'), 1);
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = trim($domain, '/ ');

        if ($domain === '') { flash('error', 'Informe o domínio da assinatura.'); $this->redirect('settings'); }

        $data = [
            'domain' => $domain,
            'company' => trim($_POST['company'] ?? '') ?: null,
            'specialties' => trim($_POST['specialties'] ?? '') ?: null,
            'contact_email' => trim($_POST['contact_email'] ?? '') ?: null,
            'site' => trim($_POST['site'] ?? '') ?: null,
            'tagline' => trim($_POST['tagline'] ?? '') ?: null,
            'color' => trim($_POST['color'] ?? '') ?: '#00997D',
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];

        // Upload da logo (opcional). Vazio = mantém a atual / usa a logo do sistema.
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoPath = $this->uploadBrandFile($_FILES['logo'], 'sig');
            if ($logoPath) $data['logo'] = $logoPath;
        }

        try {
            if ($id) {
                $db->update('email_signatures', $data, 'id = ?', [$id]);
            } else {
                // upsert por domínio
                $exists = $db->fetch("SELECT id FROM email_signatures WHERE domain = ?", [$domain]);
                if ($exists) $db->update('email_signatures', $data, 'id = ?', [$exists['id']]);
                else $db->insert('email_signatures', $data);
            }
            flash('success', 'Assinatura do domínio salva!');
        } catch (\Throwable $e) {
            flash('error', 'Erro ao salvar assinatura: ' . $e->getMessage());
        }
        $this->redirect('settings');
    }

    /** Exclui uma assinatura de domínio. POST settings/deleteEmailSignature/{id} */
    public function deleteEmailSignature($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->redirect('settings');
        try { Database::getInstance()->delete('email_signatures', 'id = ?', [$id]); flash('success', 'Assinatura removida.'); }
        catch (\Throwable $e) { flash('error', 'Erro ao remover.'); }
        $this->redirect('settings');
    }

    public function deleteEmailAccount($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->redirect('settings/emailAccounts');

        $model = new EmailAccount();
        $model->delete($id);

        flash('success', 'Conta de e-mail excluída.');
        $this->redirect('settings/emailAccounts');
    }

    public function getEmailAccount($id = null)
    {
        $this->requireRole(['super_admin']);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $model = new EmailAccount();
        $account = $model->findById($id);
        if (!$account) $this->json(['error' => 'Conta não encontrada'], 404);

        $account['linked_users'] = $model->getLinkedUserIds($id);
        // Não envia a senha pro frontend
        unset($account['smtp_password']);

        $this->json(['account' => $account]);
    }
}
