<?php

class ApiController extends Controller
{
    /** Prioridades aceitas na criação de chamados via API. */
    private const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Dispatcher da API versionada. O Router mapeia /api/v1/tickets para
     * ApiController::v1('tickets'). Aqui roteamos manualmente por recurso,
     * já que o Router do projeto é raso (2 níveis).
     */
    public function v1($resource = null)
    {
        switch ($resource) {
            case 'tickets':
                $this->createTicket();
                return;
            default:
                $this->jsonError('not_found', 'Recurso de API não encontrado.', 404);
        }
    }

    /**
     * POST /api/v1/tickets — cria um chamado em nome de uma empresa externa.
     *
     * Autenticação: X-Api-Key (requireApiKey). O client_id do ticket é o usuário
     * de integração da empresa dona da chave; a empresa é derivada por
     * users.company_id, preservando todo o fluxo interno (PlanningCard,
     * notificações, filtros por empresa).
     */
    public function createTicket()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonError('method_not_allowed', 'Use POST para criar um chamado.', 405);
        }

        // Autenticação por chave (encerra a requisição em caso de falha).
        $apiKey = $this->requireApiKey();

        // Corpo JSON.
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $this->jsonError('bad_request', 'Corpo da requisição deve ser um JSON válido.', 400);
        }

        // Validação/normalização dos campos.
        $payload = self::normalizePayload($input);
        if (!empty($payload['errors'])) {
            $first = $payload['errors'][0];
            $this->jsonError($first['code'], $first['message'], 422, ['fields' => array_column($payload['errors'], 'field')]);
        }

        $clientId = (int)$apiKey['integration_user_id'];
        $externalRef = $payload['external_ref'];

        $db = Database::getInstance();

        // Idempotência: se veio external_ref e já existe um ticket com o mesmo
        // (client_id, external_ref), retorna o existente sem recriar/redisparar.
        if ($externalRef !== null) {
            $existing = $db->fetch(
                "SELECT id, client_ticket_number, status FROM tickets WHERE client_id = ? AND external_ref = ? LIMIT 1",
                [$clientId, $externalRef]
            );
            if ($existing) {
                $this->json([
                    'success' => true,
                    'idempotent' => true,
                    'data' => [
                        'id' => (int)$existing['id'],
                        'client_ticket_number' => $existing['client_ticket_number'] !== null ? (int)$existing['client_ticket_number'] : null,
                        'status' => $existing['status'],
                    ],
                ], 200);
            }
        }

        // Descrição final: anexa dados do solicitante ao topo (mesmo padrão do
        // fluxo de solicitação externa), pois requester_name/company não são
        // colunas de tickets.
        $finalDescription = self::buildDescription(
            $payload['description'],
            $payload['requester_name'],
            $payload['requester_company']
        );

        // Número sequencial por cliente (mesma lógica de TicketsController::store).
        $lastNumber = $db->fetch(
            "SELECT MAX(client_ticket_number) as last_num FROM tickets WHERE client_id = ?",
            [$clientId]
        );
        $ticketNumber = ($lastNumber['last_num'] ?? 0) + 1;

        $ticketData = [
            'client_id' => $clientId,
            'title' => $payload['title'],
            'description' => $finalDescription,
            'category' => $payload['category'],
            'priority' => $payload['priority'],
            'status' => 'open',
            'client_ticket_number' => $ticketNumber,
        ];
        if ($externalRef !== null) {
            $ticketData['external_ref'] = $externalRef;
        }

        try {
            $ticketModel = new Ticket();
            $ticketId = (int)$ticketModel->create($ticketData);

            // PlanningCard automático (mesmo comportamento do fluxo interno).
            // A empresa é derivada de users.company_id do usuário de integração.
            try {
                $ticket = $ticketModel->findById($ticketId);
                (new PlanningCard())->createFromTicket($ticket);
            } catch (\Throwable $e) {
                // Não bloqueia a criação da demanda se o card falhar.
                Logger::warning('Falha ao criar PlanningCard via API', ['error' => $e->getMessage()]);
            }

            // Notificações (serviço compartilhado com a criação interna).
            try {
                (new TicketNotificationService())->notifyNewTicket($ticketId);
            } catch (\Throwable $e) {
                Logger::warning('Falha ao notificar novo ticket via API', ['error' => $e->getMessage()]);
            }

            // Auditoria específica da API.
            Logger::info('Ticket criado via API', [
                'ticket_id' => $ticketId,
                'api_key_id' => (int)$apiKey['id'],
                'company_id' => (int)$apiKey['company_id'],
                'external_ref' => $externalRef,
            ]);

            $created = $ticketModel->findById($ticketId);
            $this->json([
                'success' => true,
                'data' => [
                    'id' => $ticketId,
                    'client_ticket_number' => $ticketNumber,
                    'title' => $created['title'],
                    'status' => $created['status'],
                    'priority' => $created['priority'],
                    'category' => $created['category'],
                    'company_id' => (int)$apiKey['company_id'],
                    'external_ref' => $externalRef,
                    'created_at' => $created['created_at'],
                ],
            ], 201);
        } catch (\Throwable $e) {
            Logger::error('Erro ao criar ticket via API', ['error' => $e->getMessage()]);
            $this->jsonError('internal_error', 'Erro interno ao criar o chamado.', 500);
        }
    }

    /**
     * Valida e normaliza o payload de criação de chamado. Método estático e puro
     * (sem banco/rede) para permitir teste unitário.
     *
     * Retorna um array com os campos normalizados e uma lista 'errors'
     * (cada erro: ['field'=>..., 'code'=>..., 'message'=>...]).
     */
    public static function normalizePayload(array $input): array
    {
        $errors = [];

        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        if ($title === '' || $description === '') {
            $missing = [];
            if ($title === '') $missing[] = 'title';
            if ($description === '') $missing[] = 'description';
            foreach ($missing as $f) {
                $errors[] = ['field' => $f, 'code' => 'validation_error', 'message' => 'Título e descrição são obrigatórios.'];
            }
        }

        // Corta o título no limite da coluna (VARCHAR 255).
        if (mb_strlen($title) > 255) {
            $title = mb_substr($title, 0, 255);
        }

        $priority = $input['priority'] ?? 'medium';
        if (!in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            $errors[] = ['field' => 'priority', 'code' => 'invalid_value', 'message' => 'Prioridade inválida. Use: ' . implode(', ', self::ALLOWED_PRIORITIES) . '.'];
        }

        $category = trim((string)($input['category'] ?? ''));
        if (mb_strlen($category) > 100) {
            $category = mb_substr($category, 0, 100);
        }

        $externalRef = isset($input['external_ref']) ? trim((string)$input['external_ref']) : '';
        if ($externalRef !== '' && mb_strlen($externalRef) > 191) {
            $externalRef = mb_substr($externalRef, 0, 191);
        }

        return [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'category' => $category !== '' ? $category : null,
            'requester_name' => trim((string)($input['requester_name'] ?? '')),
            'requester_company' => trim((string)($input['requester_company'] ?? '')),
            'external_ref' => $externalRef !== '' ? $externalRef : null,
            'errors' => $errors,
        ];
    }

    /**
     * Monta a descrição final anexando os dados do solicitante ao topo, quando
     * informados. Método estático e puro para permitir teste unitário.
     */
    public static function buildDescription(string $description, string $requesterName = '', string $requesterCompany = ''): string
    {
        $headerLines = [];
        if ($requesterName !== '') {
            $headerLines[] = "Solicitado por (API): {$requesterName}";
        }
        if ($requesterCompany !== '') {
            $headerLines[] = "Empresa informada: {$requesterCompany}";
        }
        if (empty($headerLines)) {
            return $description;
        }
        return implode("\n", $headerLines) . "\n\n" . $description;
    }

    // Transcrever e organizar demanda via OpenAI
    public function transcribe()
    {
        $this->requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método não permitido'], 405);
        }

        $apiKey = Config::get('openai_api_key');
        if (empty($apiKey)) {
            $this->json(['error' => 'Chave da API OpenAI não configurada.'], 400);
        }

        // Receber áudio em base64
        $input = json_decode(file_get_contents('php://input'), true);
        $audioData = $input['audio'] ?? '';

        if (empty($audioData)) {
            $this->json(['error' => 'Áudio não recebido.'], 400);
        }

        // Decodificar base64 e salvar temp
        $audioContent = base64_decode($audioData);
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.webm';
        file_put_contents($tempFile, $audioContent);

        // 1. Transcrever com Whisper
        $transcription = $this->whisperTranscribe($apiKey, $tempFile);
        unlink($tempFile);

        if (!$transcription) {
            $this->json(['error' => 'Erro na transcrição do áudio.'], 500);
        }

        // 2. Organizar com GPT
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
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
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
}
