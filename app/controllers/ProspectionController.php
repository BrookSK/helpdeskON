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

        $this->view('prospection/index', [
            'user' => $user,
            'accounts' => $accounts,
            'leads' => $leads,
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

        // Enviar
        $result = $this->prospectionModel->sendEmail($account, $recipientEmail, $subject, $body, $cc, $bcc, $attachments);

        // Registrar no histórico
        $data = [
            'user_id' => $user['id'],
            'email_account_id' => $accountId,
            'contact_id' => $contactId,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'body' => $body,
            'attachments_json' => !empty($attachmentsJson) ? json_encode($attachmentsJson) : null,
            'status' => ($result === true) ? 'sent' : 'failed',
            'error_message' => ($result !== true) ? $result : null,
            'sent_at' => ($result === true) ? date('Y-m-d H:i:s') : null,
        ];

        $this->prospectionModel->create($data);

        if ($result === true) {
            $this->json(['success' => true, 'message' => 'E-mail enviado com sucesso!']);
        } else {
            $this->json(['error' => $result], 500);
        }
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

        $result = $this->prospectionModel->sendEmail($account, $to, $subject, $body, $cc, null, []);

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
