<?php

class MarketingController extends Controller
{
    private $itemModel;

    // Papéis com acesso ao módulo
    private $accessRoles = ['super_admin', 'marketing'];

    // Status válidos, em ordem de fluxo
    public static $statuses = ['ideia', 'em_producao', 'aguardando_aprovacao', 'aprovado', 'agendado', 'publicado', 'rejeitado'];

    // Rótulos amigáveis dos status (para mensagens de notificação)
    public static $statusLabels = [
        'ideia' => 'Ideia',
        'em_producao' => 'Em produção',
        'aguardando_aprovacao' => 'Aguardando aprovação',
        'aprovado' => 'Aprovado',
        'agendado' => 'Agendado',
        'publicado' => 'Publicado',
        'rejeitado' => 'Rejeitado',
    ];

    public function __construct()
    {
        $this->itemModel = new MarketingItem();
    }

    private function isAdmin()
    {
        return ($this->currentUser()['role'] ?? '') === 'super_admin';
    }

    // Verifica se o usuário pode editar o item (admin ou responsável)
    private function canManage($item)
    {
        $user = $this->currentUser();
        if ($user['role'] === 'super_admin') return true;
        return $item && $item['assigned_to'] == $user['id'];
    }

    // Página principal — Calendário | Pendências | Aprovações
    public function index()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $userModel = new User();
        // Responsáveis possíveis: marketing + super_admin
        $team = $userModel->getByRoles(['super_admin', 'marketing']);

        $this->view('marketing/index', [
            'user' => $user,
            'team' => $team,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    // API: eventos do calendário + datas comemorativas (JSON)
    public function calendar()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();

        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t 23:59:59');

        $filters = [];
        // Marketing enxerga tudo do calendário, mas pode filtrar por si mesmo se quiser
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];

        $items = $this->itemModel->getForCalendar($start . ' 00:00:00', $end . ' 23:59:59', $filters);
        $holidays = $this->itemModel->getHolidays(substr($start, 0, 10), substr($end, 0, 10));

        $events = array_map(function ($it) {
            return [
                'id' => $it['id'],
                'title' => $it['title'],
                'scheduled_at' => $it['scheduled_at'],
                'status' => $it['status'],
                'social_network' => $it['social_network'],
                'assigned_name' => $it['assigned_name'] ?? 'Sem responsável',
            ];
        }, $items);

        $this->json(['events' => $events, 'holidays' => $holidays]);
    }

    // API: listar itens por seção (pendencias/aprovacoes)
    public function items()
    {
        $this->requireRole($this->accessRoles);
        $user = $this->currentUser();
        $section = $_GET['section'] ?? 'pendencias';

        $filters = [];
        // Marketing só vê os próprios itens nas listas
        if ($user['role'] === 'marketing') {
            $filters['assigned_to'] = $user['id'];
        }

        // Pendências: datas comemorativas do mês + itens em ideia + ajustes solicitados
        if ($section === 'pendencias') {
            $ref = !empty($_GET['ref']) ? $_GET['ref'] : date('Y-m');
            $monthStart = $ref . '-01';
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $holidays = $this->itemModel->getHolidays($monthStart, $monthEnd);
            $items = $this->itemModel->getPendencias($filters);
            $this->json(['items' => $items, 'holidays' => $holidays, 'ref' => $ref]);
        }

        if (!empty($_GET['status']) && in_array($_GET['status'], self::$statuses)) {
            // Aba de um status específico
            $filters['status'] = $_GET['status'];
        } elseif ($section === 'aprovacoes') {
            // Aprovações: apenas super_admin vê a fila; itens aguardando aprovação
            $filters['status'] = 'aguardando_aprovacao';
        }

        $items = $this->itemModel->getList($filters);
        $this->json(['items' => $items]);
    }

    // API: obter um item com anexos
    public function get($id = null)
    {
        $this->requireRole($this->accessRoles);
        if (!$id) $this->json(['error' => 'ID não informado'], 400);

        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);

        $item['attachments'] = $this->itemModel->getAttachments($id);
        $item['can_manage'] = $this->canManage($item);
        $item['is_admin'] = $this->isAdmin();
        $this->json(['item' => $item]);
    }

    // API: criar item
    public function create()
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $user = $this->currentUser();
        $title = trim($_POST['title'] ?? '');
        if ($title === '') $this->json(['error' => 'Título obrigatório'], 400);

        // Regra: marketing sempre vira responsável do próprio item.
        // Admin pode escolher; se não escolher, fica sem responsável.
        if ($user['role'] === 'marketing') {
            $assignedTo = $user['id'];
        } else {
            $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        }

        $data = [
            'title' => $title,
            'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
            'assigned_to' => $assignedTo,
            'created_by' => $user['id'],
            'social_network' => trim($_POST['social_network'] ?? '') ?: null,
            'briefing' => trim($_POST['briefing'] ?? '') ?: null,
            'copy' => trim($_POST['copy'] ?? '') ?: null,
            'status' => in_array($_POST['status'] ?? '', self::$statuses) ? $_POST['status'] : 'ideia',
            'holiday_id' => !empty($_POST['holiday_id']) ? intval($_POST['holiday_id']) : null,
        ];

        $id = $this->itemModel->create($data);
        $item = $this->itemModel->findById($id);

        // Notificar responsável, se houver e for diferente de quem criou
        if ($data['assigned_to'] && $data['assigned_to'] != $user['id']) {
            $this->notify($data['assigned_to'], 'Nova demanda de marketing', "{$user['name']} atribuiu a você a demanda \"{$title}\".");
            // E também no chat WhatsApp do responsável
            $this->notifyWhatsappResponsible($item, "📌 *Nova Demanda de Marketing*", "Você foi definido(a) como responsável por esta demanda.");
        }

        // Notificar o Grupo Padrão de Notificações sobre a nova demanda no calendário
        $this->notifyGroupNewItem($item, $user);

        $this->json(['success' => true, 'item' => $item]);
    }

    // API: atualizar item
    public function update($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);
        if (!$this->canManage($item)) $this->json(['error' => 'Sem permissão'], 403);

        $user = $this->currentUser();
        $isAdmin = $this->isAdmin();
        $data = [];

        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['briefing'])) $data['briefing'] = trim($_POST['briefing']) ?: null;
        if (isset($_POST['copy'])) $data['copy'] = trim($_POST['copy']) ?: null;
        if (isset($_POST['social_network'])) $data['social_network'] = trim($_POST['social_network']) ?: null;

        // Datas e responsáveis: somente admin pode alterar livremente
        if ($isAdmin) {
            if (isset($_POST['scheduled_at'])) $data['scheduled_at'] = $_POST['scheduled_at'] ?: null;
            if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        }

        // Status: marketing pode mudar entre os status de produção, mas não pode "aprovar".
        if (isset($_POST['status']) && in_array($_POST['status'], self::$statuses)) {
            $newStatus = $_POST['status'];
            $forbiddenForMarketing = ['aprovado', 'rejeitado'];
            if (!$isAdmin && in_array($newStatus, $forbiddenForMarketing)) {
                $this->json(['error' => 'Somente o administrador pode aprovar ou rejeitar o conteúdo.'], 403);
            }
            $data['status'] = $newStatus;
        }

        if (empty($data)) $this->json(['error' => 'Nenhum campo para atualizar'], 400);

        $statusChanged = isset($data['status']) && $data['status'] !== $item['status'];
        $this->itemModel->update($id, $data);
        $updatedItem = $this->itemModel->findById($id);

        // Notificar admins quando enviado para aprovação
        if (($data['status'] ?? '') === 'aguardando_aprovacao') {
            $this->notifyAdmins('Conteúdo aguardando aprovação', "{$user['name']} enviou \"{$item['title']}\" para aprovação.");
        }

        // Sempre que o status mudar, avisar o responsável no chat WhatsApp.
        // (Quem alterou não precisa ser avisado do próprio ato.)
        if ($statusChanged && $updatedItem['assigned_to'] && $updatedItem['assigned_to'] != $user['id']) {
            $label = self::$statusLabels[$data['status']] ?? $data['status'];
            $this->notifyWhatsappResponsible(
                $updatedItem,
                "🔄 *Status Atualizado — Marketing*",
                "Novo status: *{$label}*\nAlterado por: {$user['name']}"
            );
        }

        $this->json(['success' => true, 'item' => $updatedItem]);
    }

    // API: aprovar (somente admin)
    public function approve($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);

        $this->itemModel->update($id, ['status' => 'aprovado', 'review_notes' => null]);

        if ($item['assigned_to']) {
            $this->notify($item['assigned_to'], 'Conteúdo aprovado', "Sua demanda \"{$item['title']}\" foi aprovada. Já pode ser agendada.");

            // Notificar responsável via WhatsApp
            $this->notifyWhatsappApproval($this->itemModel->findById($id));
        }
        $this->json(['success' => true, 'item' => $this->itemModel->findById($id)]);
    }

    // API: solicitar ajustes (somente admin)
    public function requestChanges($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);

        $notes = trim($_POST['review_notes'] ?? '');
        $this->itemModel->update($id, ['status' => 'em_producao', 'review_notes' => $notes ?: null]);

        if ($item['assigned_to']) {
            $this->notify($item['assigned_to'], 'Ajustes solicitados', "O admin solicitou ajustes em \"{$item['title']}\"." . ($notes ? " Observação: {$notes}" : ''));
            $this->notifyWhatsappResponsible(
                $this->itemModel->findById($id),
                "✏️ *Ajustes Solicitados — Marketing*",
                "O administrador solicitou ajustes nesta demanda." . ($notes ? "\n\n*Observação:* {$notes}" : '')
            );
        }
        $this->json(['success' => true, 'item' => $this->itemModel->findById($id)]);
    }

    // API: reenviar manualmente ao responsável a notificação da demanda via WhatsApp.
    // Serve como fallback para quando o disparo automático não ocorreu por algum motivo.
    // A mensagem é montada conforme o status atual da demanda.
    public function notifyResponsible($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);
        if (!$this->canManage($item)) $this->json(['error' => 'Sem permissão'], 403);

        if (empty($item['assigned_to'])) {
            $this->json(['error' => 'Esta demanda não possui responsável para notificar.'], 400);
        }

        $userModel = new User();
        $assignedUser = $userModel->findById($item['assigned_to']);
        if (!$assignedUser || empty($assignedUser['phone'])) {
            $this->json(['error' => 'O responsável não possui telefone cadastrado para receber a notificação.'], 400);
        }

        $status = $item['status'];
        $notes = trim($item['review_notes'] ?? '');

        // Monta a mensagem conforme o status atual (mesmo conteúdo dos disparos automáticos)
        switch ($status) {
            case 'aprovado':
                $this->notifyWhatsappApproval($item);
                $sysTitle = 'Conteúdo aprovado';
                $sysMsg = "Sua demanda \"{$item['title']}\" foi aprovada. Já pode ser agendada.";
                break;

            case 'rejeitado':
                $this->notifyWhatsappResponsible(
                    $item,
                    "❌ *Conteúdo Rejeitado — Marketing*",
                    "Sua demanda foi rejeitada." . ($notes ? "\n\n*Motivo:* {$notes}" : '')
                );
                $sysTitle = 'Conteúdo rejeitado';
                $sysMsg = "Sua demanda \"{$item['title']}\" foi rejeitada." . ($notes ? " Motivo: {$notes}" : '');
                break;

            case 'em_producao':
                // Em produção com ajustes registrados = solicitação de ajustes
                if ($notes !== '') {
                    $this->notifyWhatsappResponsible(
                        $item,
                        "✏️ *Ajustes Solicitados — Marketing*",
                        "O administrador solicitou ajustes nesta demanda.\n\n*Ajustes:* {$notes}"
                    );
                    $sysTitle = 'Ajustes solicitados';
                    $sysMsg = "Ajustes na demanda \"{$item['title']}\": {$notes}";
                } else {
                    $label = self::$statusLabels[$status] ?? $status;
                    $this->notifyWhatsappResponsible(
                        $item,
                        "🔄 *Status Atualizado — Marketing*",
                        "Novo status: *{$label}*"
                    );
                    $sysTitle = 'Status atualizado';
                    $sysMsg = "A demanda \"{$item['title']}\" está com status: {$label}.";
                }
                break;

            default:
                $label = self::$statusLabels[$status] ?? $status;
                $this->notifyWhatsappResponsible(
                    $item,
                    "🔄 *Status Atualizado — Marketing*",
                    "Novo status: *{$label}*"
                );
                $sysTitle = 'Status atualizado';
                $sysMsg = "A demanda \"{$item['title']}\" está com status: {$label}.";
                break;
        }

        $this->notify($item['assigned_to'], $sysTitle, $sysMsg);

        $this->json(['success' => true]);
    }

    // API: rejeitar conteúdo (somente admin)
    public function reject($id = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);

        $notes = trim($_POST['review_notes'] ?? '');
        $this->itemModel->update($id, ['status' => 'rejeitado', 'review_notes' => $notes ?: null]);

        if ($item['assigned_to']) {
            $this->notify($item['assigned_to'], 'Conteúdo rejeitado', "Sua demanda \"{$item['title']}\" foi rejeitada." . ($notes ? " Motivo: {$notes}" : ''));
            $this->notifyWhatsappResponsible(
                $this->itemModel->findById($id),
                "❌ *Conteúdo Rejeitado — Marketing*",
                "Sua demanda foi rejeitada." . ($notes ? "\n\n*Motivo:* {$notes}" : '')
            );
        }
        $this->json(['success' => true, 'item' => $this->itemModel->findById($id)]);
    }

    // API: excluir item (admin ou responsável)
    public function delete($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);
        if (!$this->canManage($item)) $this->json(['error' => 'Sem permissão'], 403);

        // Remover anexos físicos
        foreach ($this->itemModel->getAttachments($id) as $att) {
            $full = PUBLIC_PATH . '/' . $att['file_path'];
            if (is_file($full)) @unlink($full);
        }
        $this->itemModel->delete($id);
        $this->json(['success' => true]);
    }

    // API: upload de anexo
    public function upload($id = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $item = $this->itemModel->findById($id);
        if (!$item) $this->json(['error' => 'Item não encontrado'], 404);
        if (!$this->canManage($item)) $this->json(['error' => 'Sem permissão'], 403);

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhum arquivo enviado'], 400);
        }
        $file = $_FILES['file'];
        if ($file['size'] > 20 * 1024 * 1024) {
            $this->json(['error' => 'Arquivo muito grande (máx 20MB)'], 400);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . ($ext ? '.' . $ext : '');
        $uploadDir = PUBLIC_PATH . '/uploads/marketing';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filePath = 'uploads/marketing/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath)) {
            $this->json(['error' => 'Erro ao salvar arquivo'], 500);
        }

        $attId = $this->itemModel->addAttachment([
            'item_id' => $id,
            'user_id' => $this->currentUser()['id'],
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
        ]);

        $this->json([
            'success' => true,
            'attachment' => [
                'id' => $attId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_type' => $file['type'],
            ],
        ]);
    }

    // API: deletar anexo
    public function deleteAttachment($attId = null)
    {
        $this->requireRole($this->accessRoles);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$attId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $att = $this->itemModel->findAttachment($attId);
        if (!$att) $this->json(['error' => 'Anexo não encontrado'], 404);

        $item = $this->itemModel->findById($att['item_id']);
        if (!$this->canManage($item)) $this->json(['error' => 'Sem permissão'], 403);

        $full = PUBLIC_PATH . '/' . $att['file_path'];
        if (is_file($full)) @unlink($full);
        $this->itemModel->deleteAttachment($attId);
        $this->json(['success' => true]);
    }

    // API (admin): pedir para a IA identificar datas comemorativas do período e inserir no calendário.
    // A IA NÃO cria briefing/copy — apenas datas relevantes para marketing.
    public function generateHolidays()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $year = intval($_POST['year'] ?? date('Y'));
        if ($year < 2020 || $year > 2100) $year = intval(date('Y'));

        $apiKey = Config::get('openai_api_key');
        if (empty($apiKey)) {
            $this->json(['error' => 'Chave da API OpenAI não configurada em Configurações.'], 400);
        }

        $prompt = "Liste as principais datas comemorativas e datas relevantes para marketing no Brasil "
            . "durante TODO o ano de {$year} (de janeiro a dezembro). Inclua datas comerciais (ex: Black Friday, "
            . "Dia do Cliente, Dia das Mães, Dia dos Namorados, Natal), datas profissionais e comemorativas conhecidas. "
            . "Retorne o maior número possível de datas relevantes para marketing, cobrindo todos os meses. "
            . "Responda APENAS um JSON válido no formato: "
            . '{"dates":[{"title":"Dia do Cliente","date":"YYYY-MM-DD","category":"comercial"}]}. '
            . "Não invente datas sem relevância. Use a categoria: comercial, profissional ou comemorativa.";

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Você é um assistente que responde somente com JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 4000,
                    'response_format' => ['type' => 'json_object'],
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400 || !$response) {
                $this->json(['error' => 'Falha ao consultar a IA.'], 500);
            }

            $body = json_decode($response, true);
            $content = $body['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($content, true);
            $dates = $parsed['dates'] ?? [];

            $inserted = 0;
            $skipped = 0;
            foreach ($dates as $d) {
                $title = trim($d['title'] ?? '');
                $date = trim($d['date'] ?? '');
                if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
                // Garante que a data pertence ao ano solicitado
                if (substr($date, 0, 4) !== (string)$year) continue;
                if ($this->itemModel->holidayExists($date, $title)) { $skipped++; continue; }
                $this->itemModel->addHoliday([
                    'title' => $title,
                    'holiday_date' => $date,
                    'category' => trim($d['category'] ?? '') ?: null,
                ]);
                $inserted++;
            }

            $this->json(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped, 'year' => $year]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    // ===== Helpers de notificação =====

    /**
     * Envia notificação via WhatsApp para o responsável quando um post é aprovado.
     * Valida que o responsável existe e possui telefone cadastrado antes do envio.
     */
    private function notifyWhatsappApproval($item)
    {
        if (empty($item['assigned_to'])) return;

        try {
            $userModel = new User();
            $assignedUser = $userModel->findById($item['assigned_to']);

            // Validar: responsável precisa existir e ter telefone cadastrado
            if (!$assignedUser || empty($assignedUser['phone'])) return;

            $rede = $item['social_network'] ?? 'Não definida';
            $dataAgendamento = !empty($item['scheduled_at'])
                ? date('d/m/Y H:i', strtotime($item['scheduled_at']))
                : 'A definir';

            $whatsMessage = "✅ *Conteúdo Aprovado — Marketing*\n\n"
                . "*Título:* {$item['title']}\n"
                . "*Rede social:* {$rede}\n"
                . "*Agendamento:* {$dataAgendamento}\n\n"
                . "Sua demanda foi aprovada e já pode ser agendada para publicação.";

            WhatsappNotifier::sendToPhone(
                $assignedUser['phone'],
                $whatsMessage,
                $assignedUser['name']
            );
        } catch (\Throwable $e) {
            // Silencioso — WhatsApp é canal complementar, não deve bloquear a aprovação
        }
    }

    /**
     * Envia uma mensagem no chat WhatsApp do responsável pela demanda.
     * $header e $body compõem a mensagem; os dados da demanda são anexados abaixo.
     */
    private function notifyWhatsappResponsible($item, $header, $body)
    {
        if (empty($item['assigned_to'])) return;

        try {
            $userModel = new User();
            $assignedUser = $userModel->findById($item['assigned_to']);
            if (!$assignedUser || empty($assignedUser['phone'])) return;

            $rede = $item['social_network'] ?? 'Não definida';
            $dataAgendamento = !empty($item['scheduled_at'])
                ? date('d/m/Y H:i', strtotime($item['scheduled_at']))
                : 'A definir';

            $whatsMessage = $header . "\n\n"
                . "*Título:* {$item['title']}\n"
                . "*Rede social:* {$rede}\n"
                . "*Agendamento:* {$dataAgendamento}\n\n"
                . $body;

            WhatsappNotifier::sendToPhone($assignedUser['phone'], $whatsMessage, $assignedUser['name']);
        } catch (\Throwable $e) {
            // Silencioso — WhatsApp é canal complementar
        }
    }

    /**
     * Notifica o Grupo Padrão de Notificações (WhatsApp) sobre uma nova demanda no calendário.
     */
    private function notifyGroupNewItem($item, $creator)
    {
        try {
            $rede = $item['social_network'] ?? 'Não definida';
            $dataAgendamento = !empty($item['scheduled_at'])
                ? date('d/m/Y H:i', strtotime($item['scheduled_at']))
                : 'A definir';
            $responsavel = $item['assigned_name'] ?? 'Sem responsável';
            $statusLabel = self::$statusLabels[$item['status']] ?? $item['status'];

            $msg = "🗓️ *Nova Demanda no Calendário de Marketing*\n\n"
                . "*Título:* {$item['title']}\n"
                . "*Rede social:* {$rede}\n"
                . "*Agendamento:* {$dataAgendamento}\n"
                . "*Responsável:* {$responsavel}\n"
                . "*Status:* {$statusLabel}\n"
                . "*Criada por:* {$creator['name']}";

            WhatsappNotifier::sendToDefaultGroup($msg);
        } catch (\Throwable $e) {
            // Silencioso
        }
    }

    private function notify($userId, $title, $message)
    {
        try {
            Database::getInstance()->insert('notifications', [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => 'system',
            ]);
        } catch (\Throwable $e) { /* ignora */ }
    }

    private function notifyAdmins($title, $message)
    {
        try {
            $admins = Database::getInstance()->fetchAll("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1");
            foreach ($admins as $a) {
                $this->notify($a['id'], $title, $message);
            }
        } catch (\Throwable $e) { /* ignora */ }
    }
}
