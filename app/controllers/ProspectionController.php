<?php

class ProspectionController extends Controller
{
    private $accessRoles = ['super_admin', 'comercial', 'marketing', 'attendant'];
    private $accountModel;
    private $prospectionModel;
    private $contactModel;

    public function __construct()
    {
        $this->accountModel = new EmailAccount();
        $this->prospectionModel = new EmailProspection();
        $this->contactModel = new WhatsappContact();
    }

    /**
     * Tela principal: formulário de envio de e-mail.
     */
    public function index()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        // Contas vinculadas ao usuário
        $accounts = $this->accountModel->getByUser($user['id']);

        // Leads para seleção (contatos do CRM)
        $leads = $this->contactModel->getLeadsForSelect();

        // Sequências de follow-up ativas (para inscrever o lead)
        $sequences = Database::getInstance()->fetchAll(
            "SELECT id, name FROM email_sequences WHERE is_active = 1 ORDER BY name ASC"
        );

        $this->view('prospection/index', [
            'user' => $user,
            'accounts' => $accounts,
            'leads' => $leads,
            'sequences' => $sequences,
        ]);
    }

    /**
     * API: Enviar e-mail de prospecção.
     */
    public function send()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();

        // Validações
        $accountId = intval($_POST['email_account_id'] ?? 0);
        $recipientEmail = trim($_POST['recipient_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';

        if (!$accountId) $this->json(['error' => 'Selecione uma conta de e-mail.'], 400);
        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) $this->json(['error' => 'E-mail do destinatário inválido.'], 400);
        if (!$subject) $this->json(['error' => 'Informe o assunto.'], 400);
        if (!$body) $this->json(['error' => 'Escreva o corpo do e-mail.'], 400);

        // Verifica se o usuário tem acesso a essa conta
        $account = $this->accountModel->findById($accountId);
        if (!$account || !$account['is_active']) $this->json(['error' => 'Conta de e-mail inválida ou inativa.'], 400);

        $linkedUsers = $this->accountModel->getLinkedUserIds($accountId);
        if (!in_array($user['id'], $linkedUsers)) $this->json(['error' => 'Você não tem permissão para usar esta conta.'], 403);

        $cc = trim($_POST['cc'] ?? '') ?: null;
        $bcc = trim($_POST['bcc'] ?? '') ?: null;
        $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
        $recipientName = trim($_POST['recipient_name'] ?? '') ?: null;

        // Upload de anexos
        $attachments = [];
        $attachmentsJson = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = BASE_PATH . '/uploads/prospection/' . date('Y-m') . '/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                $dest = $uploadDir . $safeName;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $dest)) {
                    $attachments[] = ['path' => $dest, 'name' => $name];
                    $attachmentsJson[] = ['path' => 'uploads/prospection/' . date('Y-m') . '/' . $safeName, 'name' => $name];
                }
            }
        }

        // Identidade única: se não veio contact_id, resolve/cria o lead pelo e-mail (origem manual_email)
        if (!$contactId) {
            $resolver = new LeadResolver();
            $contactId = $resolver->findByEmail($recipientEmail);
            if (!$contactId) {
                $contactId = $resolver->resolve([
                    'name' => $recipientName ?: $recipientEmail,
                    'email' => $recipientEmail,
                    'source' => 'manual_email',
                    'assigned_to' => $user['id'],
                ], $user['id']);
            }
        }

        // Anexa a assinatura padrão da empresa ao corpo
        $bodyWithSignature = $body . $this->buildSignature($user);

        // Envio + registro unificado (email_messages) com tracking, quando há lead resolvido.
        if ($contactId) {
            $emailSvc = new EmailMessageService();
            $res = $emailSvc->send([
                'contact_id' => $contactId,
                'account' => $account,
                'to' => $recipientEmail,
                'subject' => $subject,
                'body_html' => $bodyWithSignature,
                'origin' => 'manual',
                'sent_by' => $user['id'],
                'cc' => $cc,
                'bcc' => $bcc,
            ]);
            // Mantém compatibilidade com o histórico antigo (email_prospections + métricas)
            $this->prospectionModel->create([
                'user_id' => $user['id'], 'email_account_id' => $accountId, 'contact_id' => $contactId,
                'recipient_email' => $recipientEmail, 'recipient_name' => $recipientName,
                'cc' => $cc, 'bcc' => $bcc, 'subject' => $subject, 'body' => $body,
                'attachments_json' => !empty($attachmentsJson) ? json_encode($attachmentsJson) : null,
                'status' => !empty($res['success']) ? 'sent' : 'failed',
                'error_message' => empty($res['success']) ? ($res['error'] ?? null) : null,
                'sent_at' => !empty($res['success']) ? date('Y-m-d H:i:s') : null,
            ]);
            if (!empty($res['success'])) {
                $this->json(['success' => true, 'message' => 'E-mail enviado com sucesso!', 'contact_id' => $contactId, 'email_message_id' => $res['message_id']]);
            }
            $this->json(['error' => $res['error'] ?? 'Falha no envio'], 500);
        }

        // Sem lead (sem instância WhatsApp): fallback ao envio antigo, sem tracking
        $result = $this->prospectionModel->sendEmail($account, $recipientEmail, $subject, $bodyWithSignature, $cc, $bcc, $attachments);
        $this->prospectionModel->create([
            'user_id' => $user['id'], 'email_account_id' => $accountId, 'contact_id' => null,
            'recipient_email' => $recipientEmail, 'recipient_name' => $recipientName,
            'cc' => $cc, 'bcc' => $bcc, 'subject' => $subject, 'body' => $body,
            'attachments_json' => !empty($attachmentsJson) ? json_encode($attachmentsJson) : null,
            'status' => ($result === true) ? 'sent' : 'failed',
            'error_message' => ($result !== true) ? $result : null,
            'sent_at' => ($result === true) ? date('Y-m-d H:i:s') : null,
        ]);
        if ($result === true) {
            $this->json(['success' => true, 'message' => 'E-mail enviado com sucesso!']);
        }
        $this->json(['error' => $result], 500);
    }

    /**
     * API: cria um follow-up automático após um envio manual.
     * POST prospection/followUp  body: contact_id, wait_amount, wait_unit, subject, body
     *   OU: contact_id, sequence_id (inscreve numa sequência existente)
     */
    public function followUp()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $contactId = intval($_POST['contact_id'] ?? 0);
        if (!$contactId) $this->json(['error' => 'Lead não informado.'], 400);

        $engine = new SequenceEngine();

        // Opção A: inscrever em sequência existente
        $sequenceId = !empty($_POST['sequence_id']) ? intval($_POST['sequence_id']) : null;
        if ($sequenceId) {
            $r = $engine->enroll($sequenceId, $contactId, $user['id']);
            if (empty($r['success'])) $this->json(['error' => $r['error'] ?? 'Falha ao inscrever.'], 400);
            $this->json(['success' => true, 'mode' => 'sequence']);
        }

        // Opção B: follow-up simples (mesma engine)
        $amount = max(1, intval($_POST['wait_amount'] ?? 2));
        $unit = in_array($_POST['wait_unit'] ?? '', ['minutes','hours','days']) ? $_POST['wait_unit'] : 'days';
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        if ($subject === '' || $body === '') $this->json(['error' => 'Informe assunto e mensagem do follow-up.'], 400);

        $r = $engine->createSimpleFollowUp($contactId, $amount, $unit, $subject, $body, $user['id']);
        if (empty($r['success'])) $this->json(['error' => $r['error'] ?? 'Falha ao criar follow-up.'], 400);
        $this->json(['success' => true, 'mode' => 'simple']);
    }

    /**
     * Histórico de envios.
     */
    public function history()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $filters = [];
        $isAdmin = ($user['role'] === 'super_admin');

        // Comercial vê só os próprios; admin vê todos ou filtra
        if (!$isAdmin) {
            $filters['user_id'] = $user['id'];
        } elseif (!empty($_GET['user_id'])) {
            $filters['user_id'] = intval($_GET['user_id']);
        }

        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
        if (!empty($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];

        $prospections = $this->prospectionModel->getAll($filters);

        // Usuários comerciais (para filtro admin)
        $userModel = new User();
        $comerciais = $userModel->getByRoles(['super_admin', 'comercial', 'marketing']);

        $this->view('prospection/history', [
            'user' => $user,
            'prospections' => $prospections,
            'comerciais' => $comerciais,
            'filters' => $filters,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * API: Buscar briefing de um lead (ao vincular no formulário).
     */
    public function leadInfo($contactId = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$contactId) $this->json(['error' => 'ID não informado'], 400);

        $contact = $this->contactModel->findById($contactId);
        $briefing = $this->contactModel->getBriefing($contactId);

        // E-mail do lead: campo dedicado lead_email (preenchido ao cadastrar/importar o lead)
        $email = $contact['lead_email'] ?? null;

        $this->json([
            'contact' => $contact ? [
                'id' => $contact['id'],
                'name' => $contact['contact_name'] ?? $contact['push_name'] ?? 'Contato',
                'phone' => $contact['phone'] ?? null,
                'email' => $email,
            ] : null,
            'briefing' => $briefing,
        ]);
    }

    /**
     * API: Visualizar detalhes de um envio.
     */
    public function view_detail($id = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $prospection = $this->prospectionModel->findById($id);
        if (!$prospection) $this->json(['error' => 'Não encontrado'], 404);

        // Comercial só vê os próprios
        $user = $this->currentUser();
        if ($user['role'] !== 'super_admin' && $prospection['user_id'] != $user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }

        $this->json(['prospection' => $prospection]);
    }

    /**
     * Caixa de entrada: lista e-mails recebidos das contas do usuário.
     */
    public function inbox()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        // Contas vinculadas ao usuário (com IMAP configurado)
        $accounts = $this->accountModel->getByUser($user['id']);
        $imapAccounts = array_filter($accounts, fn($a) => !empty($a['imap_host']));

        // Conta selecionada (filtro)
        $selectedAccountId = !empty($_GET['account_id']) ? intval($_GET['account_id']) : null;
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 30;
        $search = trim($_GET['search'] ?? '') ?: null;

        $messages = [];
        $total = 0;
        $error = null;
        $activeAccount = null;

        if (!empty($imapAccounts)) {
            // Se não selecionou, usa a primeira
            if (!$selectedAccountId) {
                $activeAccount = reset($imapAccounts);
                $selectedAccountId = $activeAccount['id'];
            } else {
                foreach ($imapAccounts as $acc) {
                    if ($acc['id'] == $selectedAccountId) { $activeAccount = $acc; break; }
                }
            }

            if ($activeAccount) {
                $reader = new ImapReader($activeAccount);
                $result = $reader->connect();

                if ($result === true) {
                    $imapSearch = null;
                    if ($search) {
                        $imapSearch = 'OR SUBJECT "' . addslashes($search) . '" FROM "' . addslashes($search) . '"';
                    }

                    $total = $reader->getTotal($imapSearch);
                    $offset = ($page - 1) * $perPage;
                    $messages = $reader->fetchMessages($perPage, $offset, $imapSearch);
                    $reader->disconnect();
                } else {
                    $error = $result;
                }
            }
        }

        $totalPages = ceil($total / $perPage);

        $this->view('prospection/inbox', [
            'user' => $user,
            'accounts' => $imapAccounts,
            'selectedAccountId' => $selectedAccountId,
            'activeAccount' => $activeAccount,
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'error' => $error,
        ]);
    }

    /**
     * API: Lê o conteúdo completo de um e-mail (AJAX).
     */
    public function readEmail()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $accountId = intval($_POST['account_id'] ?? 0);
        $uid = intval($_POST['uid'] ?? 0);

        if (!$accountId || !$uid) $this->json(['error' => 'Parâmetros inválidos'], 400);

        // Verifica se o usuário tem acesso a essa conta
        $account = $this->accountModel->findById($accountId);
        if (!$account || empty($account['imap_host'])) $this->json(['error' => 'Conta inválida ou IMAP não configurado'], 400);

        $linkedUsers = $this->accountModel->getLinkedUserIds($accountId);
        if (!in_array($user['id'], $linkedUsers)) $this->json(['error' => 'Sem permissão'], 403);

        $reader = new ImapReader($account);
        $result = $reader->connect();

        if ($result !== true) {
            $this->json(['error' => $result], 500);
        }

        $message = $reader->readMessage($uid);
        $reader->disconnect();

        if (!$message) {
            $this->json(['error' => 'E-mail não encontrado'], 404);
        }

        $this->json(['message' => $message]);
    }

    /**
     * API: histórico de conversa (enviados + recebidos) com um endereço de e-mail.
     * POST prospection/emailThread  body: account_id, email
     */
    public function emailThread()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $accountId = intval($_POST['account_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        if (!$accountId || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'Parâmetros inválidos'], 400);
        }

        $account = $this->requireImapAccount($accountId);
        $items = [];

        // 1) Enviados (registro local de prospecção) para/deste endereço
        $db = Database::getInstance();
        $sent = $db->fetchAll(
            "SELECT id, subject, body, recipient_email, recipient_name, status, sent_at, created_at
             FROM email_prospections
             WHERE email_account_id = ? AND recipient_email = ?
             ORDER BY COALESCE(sent_at, created_at) DESC
             LIMIT 50",
            [$accountId, $email]
        );
        foreach ($sent as $s) {
            $items[] = [
                'direction' => 'sent',
                'subject' => $s['subject'],
                'snippet' => mb_substr(trim(strip_tags($s['body'] ?? '')), 0, 160),
                'party' => $s['recipient_email'],
                'date' => $s['sent_at'] ?: $s['created_at'],
                'status' => $s['status'],
            ];
        }

        // 2) Recebidos deste endereço (via IMAP, busca por FROM)
        $reader = new ImapReader($account);
        if ($reader->connect() === true) {
            $received = $reader->searchFrom($email, 50);
            $reader->disconnect();
            foreach ($received as $m) {
                $items[] = [
                    'direction' => 'received',
                    'subject' => $m['subject'],
                    'snippet' => '',
                    'party' => $m['from_email'],
                    'date' => $m['date'],
                    'uid' => $m['uid'],
                    'status' => 'received',
                ];
            }
        }

        // Ordena do mais recente para o mais antigo
        usort($items, fn($a, $b) => strtotime($b['date'] ?? '0') <=> strtotime($a['date'] ?? '0'));

        $this->json(['success' => true, 'thread' => $items]);
    }

    /**
     * Monta a assinatura HTML padrão anexada a todos os e-mails enviados.
     * Inclui a logo do sistema (se configurada) e o nome do usuário remetente.
     */
    private function buildSignature($user)
    {
        $userName = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');

        $logoHtml = '';
        $logoPath = Config::get('app_logo');
        if (!empty($logoPath)) {
            $logoUrl = baseUrl($logoPath);
            $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="ON Solutions Brasil" style="max-height:56px;margin-bottom:8px;">';
        }

        return '
<div style="margin-top:28px;padding-top:16px;border-top:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#333;line-height:1.5;">
    ' . $logoHtml . '
    <div style="font-weight:600;color:#111;">' . $userName . '</div>
    <div style="margin-top:6px;">Atenciosamente,<br><strong>Equipe ON Solutions Brasil</strong></div>
    <div style="color:#666;margin-top:2px;">Tecnologia • Desenvolvimento • Automação</div>
    <div style="margin-top:8px;">
        📧 <a href="mailto:contato@onsolutionsbrasil.com.br" style="color:#00997D;text-decoration:none;">contato@onsolutionsbrasil.com.br</a><br>
        🌐 <a href="https://www.onsolutionsbrasil.com.br" style="color:#00997D;text-decoration:none;">www.onsolutionsbrasil.com.br</a>
    </div>
    <div style="margin-top:8px;color:#888;font-size:12px;">
        <strong>ON Solutions Brasil</strong><br>
        Soluções inteligentes para transformar processos e negócios.
    </div>
</div>';
    }

    /**
     * Valida o acesso do usuário atual a uma conta IMAP e retorna a conta.
     * Em caso de falha, responde JSON de erro e encerra.
     */
    private function requireImapAccount($accountId)
    {
        $account = $this->accountModel->findById($accountId);
        if (!$account || empty($account['imap_host'])) {
            $this->json(['error' => 'Conta inválida ou IMAP não configurado'], 400);
        }
        $user = $this->currentUser();
        $linkedUsers = $this->accountModel->getLinkedUserIds($accountId);
        if (!in_array($user['id'], $linkedUsers)) {
            $this->json(['error' => 'Sem permissão'], 403);
        }
        return $account;
    }

    /**
     * API: Excluir um e-mail da caixa de entrada.
     * POST prospection/deleteEmail  body: account_id, uid
     */
    public function deleteEmail()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $accountId = intval($_POST['account_id'] ?? 0);
        $uid = intval($_POST['uid'] ?? 0);
        if (!$accountId || !$uid) $this->json(['error' => 'Parâmetros inválidos'], 400);

        $account = $this->requireImapAccount($accountId);

        $reader = new ImapReader($account);
        $conn = $reader->connect();
        if ($conn !== true) $this->json(['error' => $conn], 500);

        $ok = $reader->deleteMessage($uid);
        $reader->disconnect();

        if ($ok) $this->json(['success' => true]);
        $this->json(['error' => 'Não foi possível excluir o e-mail.'], 500);
    }

    /**
     * API: Arquivar um e-mail.
     * POST prospection/archiveEmail  body: account_id, uid
     */
    public function archiveEmail()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $accountId = intval($_POST['account_id'] ?? 0);
        $uid = intval($_POST['uid'] ?? 0);
        if (!$accountId || !$uid) $this->json(['error' => 'Parâmetros inválidos'], 400);

        $account = $this->requireImapAccount($accountId);

        $reader = new ImapReader($account);
        $conn = $reader->connect();
        if ($conn !== true) $this->json(['error' => $conn], 500);

        $result = $reader->archiveMessage($uid);
        $reader->disconnect();

        if ($result === true) $this->json(['success' => true]);
        $this->json(['error' => is_string($result) ? $result : 'Não foi possível arquivar o e-mail.'], 500);
    }

    /**
     * API: Responder um e-mail recebido.
     * POST prospection/replyEmail  body: account_id, to, subject, body, cc, contact_id
     */
    public function replyEmail()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['error' => 'Método inválido'], 405);

        $user = $this->currentUser();
        $accountId = intval($_POST['account_id'] ?? 0);
        $to = trim($_POST['to'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        $cc = trim($_POST['cc'] ?? '') ?: null;

        if (!$accountId) $this->json(['error' => 'Conta inválida.'], 400);
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) $this->json(['error' => 'Destinatário inválido.'], 400);
        if (!$subject) $this->json(['error' => 'Informe o assunto.'], 400);
        if (!$body) $this->json(['error' => 'Escreva a resposta.'], 400);

        // A resposta usa a conta SMTP (mesma conta da caixa). Reaproveita a validação SMTP.
        $account = $this->accountModel->findById($accountId);
        if (!$account || !$account['is_active']) $this->json(['error' => 'Conta inválida ou inativa.'], 400);
        $linkedUsers = $this->accountModel->getLinkedUserIds($accountId);
        if (!in_array($user['id'], $linkedUsers)) $this->json(['error' => 'Sem permissão.'], 403);

        $bodyWithSignature = $body . $this->buildSignature($user);
        $result = $this->prospectionModel->sendEmail($account, $to, $subject, $bodyWithSignature, $cc, null, []);

        // Registra a resposta manual no histórico unificado do lead (se houver lead)
        if (!empty($_POST['contact_id'])) {
            try {
                (new LeadTimelineService())->add(intval($_POST['contact_id']), 'email_sent', 'Resposta enviada: ' . $subject, ['to' => $to], $user['id']);
            } catch (\Throwable $e) { /* silencioso */ }
        }

        // Registra no histórico de prospecção como envio
        $this->prospectionModel->create([
            'user_id' => $user['id'],
            'email_account_id' => $accountId,
            'contact_id' => !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null,
            'recipient_email' => $to,
            'recipient_name' => trim($_POST['recipient_name'] ?? '') ?: null,
            'cc' => $cc,
            'bcc' => null,
            'subject' => $subject,
            'body' => $body,
            'status' => ($result === true) ? 'sent' : 'failed',
            'error_message' => ($result !== true) ? $result : null,
            'sent_at' => ($result === true) ? date('Y-m-d H:i:s') : null,
        ]);

        if ($result === true) $this->json(['success' => true, 'message' => 'Resposta enviada!']);
        $this->json(['error' => $result], 500);
    }
}
