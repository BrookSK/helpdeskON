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
        $requesterCompany = trim($_POST['requester_company'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium';

        // Nome é obrigatório (precisamos saber quem abriu a demanda externa).
        if ($requesterName === '') {
            flash('error', 'Informe o seu nome.');
            $this->redirect('solicitacaoexterna/novaDemanda');
        }
        if ($title === '' || $description === '') {
            flash('error', 'Título e descrição são obrigatórios.');
            $this->redirect('solicitacaoexterna/novaDemanda');
        }

        // A demanda é registrada em nome do usuário da equipe dono do PIN.
        // Os dados de quem solicitou (nome e, se informada, a empresa) são anexados
        // ao topo da descrição para rastreabilidade — sem criar usuários/relações.
        $header = "Solicitado por (externo): {$requesterName}";
        if ($requesterCompany !== '') {
            $header .= "\nEmpresa vinculada: {$requesterCompany}";
        }
        $finalDescription = $header . "\n\n" . $description;

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
        $ticketNumber = ($lastNumber['last_num'] ?? 0) + 1;
        $ticketData['client_ticket_number'] = $ticketNumber;

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

        // Notificação por WhatsApp (canal complementar, nunca bloqueia a criação):
        //  - privado para o atendente dono do PIN (se tiver telefone cadastrado);
        //  - grupo padrão da equipe (se a notificação por grupo estiver habilitada).
        $this->notifyWhatsapp($owner, [
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
            'title' => $title,
            'priority' => $priority,
            'requester_name' => $requesterName,
            'requester_company' => $requesterCompany,
        ]);

        // Link interno do card (para quem TEM acesso ao sistema, ex.: o atendente).
        // Na tela externa exibimos apenas o número; o link segue pelo WhatsApp.
        $this->renderExternal('external/sucesso', [
            'owner' => $owner,
            'ticketTitle' => $title,
            'ticketNumber' => $ticketNumber,
        ]);
    }

    /**
     * Monta o texto da notificação de WhatsApp para uma demanda externa.
     *
     * Mantido como método público e "puro" (só recebe dados e devolve string)
     * para permitir teste unitário sem banco/rede. Os "dados pertinentes"
     * pedidos na demanda: número, título, prioridade, quem solicitou (nome e
     * empresa, quando informada) e o link do card.
     *
     * @param array $data ticket_number, title, priority, requester_name,
     *                     requester_company, card_url
     */
    public function buildWhatsappMessage(array $data)
    {
        $priorityLabels = [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];
        $priorityEmojis = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'urgent' => '🔴',
        ];
        $priority = $data['priority'] ?? 'medium';
        $priorityText = $priorityLabels[$priority] ?? 'Média';
        $priorityEmoji = $priorityEmojis[$priority] ?? '⚪';

        $requester = trim((string)($data['requester_name'] ?? '')) ?: 'Não informado';
        if (!empty($data['requester_company'])) {
            $requester .= ' (' . $data['requester_company'] . ')';
        }

        $msg = "🆕 *Nova demanda (acesso externo)*\n\n"
            . "*#" . ($data['ticket_number'] ?? '?') . "* — " . ($data['title'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━━\n"
            . "{$priorityEmoji} *Prioridade:* {$priorityText}\n"
            . "🙋 *Solicitado por:* {$requester}\n";

        if (!empty($data['card_url'])) {
            $msg .= "🔗 *Abrir card:* " . $data['card_url'] . "\n";
        }

        return $msg;
    }

    /**
     * Dispara a notificação de WhatsApp da nova demanda externa.
     * Nunca lança/bloqueia: WhatsApp é canal complementar.
     */
    private function notifyWhatsapp($owner, array $data)
    {
        try {
            $cardUrl = baseUrl('tickets/show/' . $data['ticket_id']);
            $message = $this->buildWhatsappMessage(array_merge($data, ['card_url' => $cardUrl]));

            // 1. Privado para o atendente dono do PIN (se tiver telefone).
            if (!empty($owner['phone'])) {
                WhatsappNotifier::sendToPhone($owner['phone'], $message, $owner['name'] ?? null);
            }

            // 2. Grupo padrão da equipe (só envia se habilitado nas Settings).
            WhatsappNotifier::sendToDefaultGroup($message);
        } catch (\Throwable $e) {
            // Silencioso — a demanda já foi criada com sucesso.
        }
    }

    /** Encerra a sessão externa (não toca na sessão de login normal). */
    public function logout()
    {
        unset($_SESSION['external_access']);
        $this->redirect('solicitacaoexterna');
    }

    /**
     * Transcreve áudio (Whisper) e organiza em campos (GPT), igual à Nova Demanda
     * interna, porém protegido por requireExternal() em vez de requireLogin() —
     * pois o ambiente externo não usa sessão de login normal.
     * Só acessível para quem já entrou com o PIN.
     */
    public function transcribe()
    {
        $this->requireExternal();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método não permitido'], 405);
        }

        $apiKey = Config::get('openai_api_key');
        if (empty($apiKey)) {
            $this->json(['error' => 'Chave da API OpenAI não configurada.'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $audioData = $input['audio'] ?? '';
        if (empty($audioData)) {
            $this->json(['error' => 'Áudio não recebido.'], 400);
        }

        $audioContent = base64_decode($audioData);
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.webm';
        file_put_contents($tempFile, $audioContent);

        $transcription = $this->whisperTranscribe($apiKey, $tempFile);
        @unlink($tempFile);

        if (!$transcription) {
            $this->json(['error' => 'Erro na transcrição do áudio.'], 500);
        }

        $organized = $this->organizeWithGPT($apiKey, $transcription);

        $this->json([
            'success' => true,
            'transcription' => $transcription,
            'organized' => $organized,
        ]);
    }

    private function whisperTranscribe($apiKey, $filePath)
    {
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        $cfile = new CURLFile($filePath, 'audio/webm', 'audio.webm');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => $cfile,
                'model' => 'whisper-1',
                'language' => 'pt',
            ],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['text'] ?? null;
    }

    private function organizeWithGPT($apiKey, $text)
    {
        $prompt = "Você é um assistente que organiza demandas de suporte. "
            . "Com base no texto abaixo (transcrição de áudio de um cliente), extraia e organize as informações em formato estruturado. "
            . "Retorne um JSON com os campos: title (título resumido da demanda), description (descrição detalhada e organizada), "
            . "category (categoria sugerida: design, desenvolvimento, marketing, suporte, outro), "
            . "priority (prioridade sugerida: low, medium, high, urgent).\n\n"
            . "Texto do cliente: \"{$text}\"";

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Responda apenas com JSON válido, sem markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        return json_decode($content, true) ?? ['title' => '', 'description' => $text, 'category' => 'outro', 'priority' => 'medium'];
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
