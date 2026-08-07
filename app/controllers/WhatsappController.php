<?php

class WhatsappController extends Controller
{
    private $contactModel;
    private $messageModel;

    public function __construct()
    {
        $this->contactModel = new WhatsappContact();
        $this->messageModel = new WhatsappMessage();
    }

    /**
     * Página principal — Conexão / QR Code
     */
    public function index()
    {
        $this->requireRole(['super_admin', 'attendant']);
        $user = $this->currentUser();

        $db = Database::getInstance();
        $instances = $db->fetchAll("SELECT * FROM whatsapp_instances ORDER BY is_default DESC, created_at DESC");

        $this->view('whatsapp/connect', [
            'user' => $user,
            'instances' => $instances,
        ]);
    }

    /**
     * Chat — Interface estilo WhatsApp Web
     */
    public function chat($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        $user = $this->currentUser();

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");
        if (!$instance) {
            $instance = $db->fetch("SELECT * FROM whatsapp_instances LIMIT 1");
        }

        $labels = $this->contactModel->getAllLabels();
        $userModel = new User();
        $team = $userModel->getAttendants();
        $admins = $db->fetchAll("SELECT id, name FROM users WHERE role = 'super_admin' AND is_active = 1");
        $teamMembers = array_merge($admins, $team);

        $this->view('whatsapp/chat', [
            'user' => $user,
            'instance' => $instance,
            'labels' => $labels,
            'teamMembers' => $teamMembers,
            'activeContactId' => $contactId,
        ]);
    }

    /**
     * API: Listar contatos (AJAX)
     */
    public function contacts()
    {
        $this->requireRole(['super_admin', 'attendant']);

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");
        if (!$instance) {
            $instance = $db->fetch("SELECT * FROM whatsapp_instances LIMIT 1");
        }
        if (!$instance) {
            $this->json(['contacts' => [], 'groups' => []]);
        }

        $filters = [];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['label_id'])) $filters['label_id'] = $_GET['label_id'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
        if (!empty($_GET['service_status'])) $filters['service_status'] = $_GET['service_status'];

        // Tipo: contacts, groups ou all
        $type = $_GET['type'] ?? 'all';

        if ($type === 'all') {
            $contacts = $this->contactModel->getAll($instance['id'], $filters, 'contacts');
            $groups = $this->contactModel->getAll($instance['id'], $filters, 'groups');
            $this->json(['contacts' => $contacts, 'groups' => $groups]);
        } else {
            $results = $this->contactModel->getAll($instance['id'], $filters, $type);
            $this->json($results);
        }
    }

    /**
     * API: Atualizar status de atendimento
     */
    public function updateServiceStatus($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $status = $_POST['service_status'] ?? '';
        $result = $this->contactModel->updateServiceStatus($contactId, $status);

        if ($result === false) {
            $this->json(['error' => 'Status inválido'], 400);
        }

        $this->json(['success' => true]);
    }

    /**
     * API: Buscar mensagens de um contato (AJAX)
     */
    public function messages($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $beforeId = $_GET['before_id'] ?? null;
        $messages = $this->messageModel->getByContact($contactId, 50, $beforeId);

        // Marcar como lidas
        $this->messageModel->markAsRead($contactId);
        $this->contactModel->clearUnread($contactId);

        $this->json($messages);
    }

    /**
     * API: Polling — novas mensagens
     */
    public function poll($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $afterId = $_GET['after_id'] ?? 0;
        $messages = $this->messageModel->getNewMessages($contactId, $afterId);

        $this->json($messages);
    }

    /**
     * API: Enviar mensagem de texto
     */
    public function send()
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $contactId = $_POST['contact_id'] ?? null;
        $text = trim($_POST['message'] ?? '');

        if (!$contactId || empty($text)) {
            $this->json(['error' => 'Contato e mensagem obrigatórios'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $api = EvolutionApi::fromInstance($contact['instance_id']);
        if (!$api) $this->json(['error' => 'Instância não encontrada'], 400);

        // Enviar via Evolution API
        $result = $api->sendText($contact['remote_jid'], $text);

        if (isset($result['error']) && $result['error']) {
            $this->json(['error' => $result['message'] ?? 'Erro ao enviar'], 500);
        }

        // Salvar no banco
        $messageId = $this->messageModel->create([
            'instance_id' => $contact['instance_id'],
            'contact_id' => $contactId,
            'remote_jid' => $contact['remote_jid'],
            'message_id' => $result['key']['id'] ?? uniqid('sent_'),
            'from_me' => 1,
            'message_type' => 'text',
            'message_text' => $text,
            'sender_name' => $this->currentUser()['name'],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        // Atualizar última mensagem do contato
        $this->contactModel->updateLastMessage($contactId, date('Y-m-d H:i:s'));

        $this->json([
            'success' => true,
            'message' => [
                'id' => $messageId,
                'from_me' => 1,
                'message_type' => 'text',
                'message_text' => $text,
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * API: Enviar mídia (upload)
     */
    public function sendMedia()
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $contactId = $_POST['contact_id'] ?? null;
        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Nenhum arquivo enviado'], 400);
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = $file['type'];

        // Determinar tipo de mídia
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $mediaType = 'image';
        } elseif (in_array($ext, ['mp4', 'avi', 'mov', '3gp'])) {
            $mediaType = 'video';
        } elseif (in_array($ext, ['mp3', 'ogg', 'wav', 'aac', 'm4a'])) {
            $mediaType = 'audio';
        } else {
            $mediaType = 'document';
        }

        // Salvar arquivo localmente
        $fileName = uniqid() . '_' . time() . '.' . $ext;
        $uploadDir = PUBLIC_PATH . '/uploads/whatsapp_media/' . date('Y-m');
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filePath = 'uploads/whatsapp_media/' . date('Y-m') . '/' . $fileName;
        move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $filePath);

        // Converter para base64 e enviar
        $base64 = base64_encode(file_get_contents(PUBLIC_PATH . '/' . $filePath));
        $dataUri = "data:{$mime};base64,{$base64}";

        $api = EvolutionApi::fromInstance($contact['instance_id']);
        $caption = $_POST['caption'] ?? '';

        if ($mediaType === 'audio') {
            $result = $api->sendAudio($contact['remote_jid'], $dataUri);
        } else {
            $result = $api->sendMedia($contact['remote_jid'], $mediaType, $dataUri, $caption, $file['name']);
        }

        // Salvar no banco
        $msgType = $mediaType === 'image' ? 'image' : ($mediaType === 'video' ? 'video' : ($mediaType === 'audio' ? 'audio' : 'document'));
        $messageId = $this->messageModel->create([
            'instance_id' => $contact['instance_id'],
            'contact_id' => $contactId,
            'remote_jid' => $contact['remote_jid'],
            'message_id' => $result['key']['id'] ?? uniqid('sent_'),
            'from_me' => 1,
            'message_type' => $msgType,
            'message_text' => $caption,
            'media_url' => $filePath,
            'media_mime_type' => $mime,
            'media_filename' => $file['name'],
            'sender_name' => $this->currentUser()['name'],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $this->contactModel->updateLastMessage($contactId, date('Y-m-d H:i:s'));

        $this->json([
            'success' => true,
            'message' => [
                'id' => $messageId,
                'from_me' => 1,
                'message_type' => $msgType,
                'message_text' => $caption,
                'media_url' => $filePath,
                'media_filename' => $file['name'],
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * API: Detalhes do contato
     */
    public function contactDetail($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $labels = $this->contactModel->getLabels($contactId);
        $contact['labels'] = $labels;

        $this->json($contact);
    }

    /**
     * API: Atualizar contato (notas, atribuição)
     */
    public function updateContact($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $data = [];
        if (isset($_POST['contact_name'])) $data['contact_name'] = trim($_POST['contact_name']);
        if (isset($_POST['internal_notes'])) $data['internal_notes'] = trim($_POST['internal_notes']);
        if (isset($_POST['assigned_to'])) $data['assigned_to'] = $_POST['assigned_to'] ?: null;
        if (isset($_POST['service_status'])) $data['service_status'] = $_POST['service_status'] ?: 'novo';

        if (!empty($data)) {
            $db = Database::getInstance();
            $db->update('whatsapp_contacts', $data, 'id = ?', [$contactId]);
        }

        $this->json(['success' => true]);
    }

    /**
     * API: Adicionar/remover etiqueta de um contato
     */
    public function toggleLabel()
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $contactId = $_POST['contact_id'] ?? null;
        $labelId = $_POST['label_id'] ?? null;
        $action = $_POST['action'] ?? 'add'; // add ou remove

        if (!$contactId || !$labelId) {
            $this->json(['error' => 'Contato e etiqueta obrigatórios'], 400);
        }

        if ($action === 'remove') {
            $this->contactModel->removeLabel($contactId, $labelId);
        } else {
            $this->contactModel->addLabel($contactId, $labelId);
        }

        $this->json(['success' => true]);
    }

    /**
     * API: Criar nova etiqueta
     */
    public function createLabel()
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#6c757d';

        if (empty($name)) $this->json(['error' => 'Nome obrigatório'], 400);

        $user = $this->currentUser();
        $id = $this->contactModel->createLabel($name, $color, $user['id']);

        $this->json(['success' => true, 'label' => ['id' => $id, 'name' => $name, 'color' => $color]]);
    }

    // =========================================
    // INSTÂNCIA — GERENCIAMENTO
    // =========================================

    /**
     * API: Criar nova instância
     */
    public function createInstance()
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $instanceName = trim($_POST['instance_name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $apiUrl = trim($_POST['api_url'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($instanceName) || empty($apiUrl) || empty($apiKey)) {
            $this->json(['error' => 'Nome, URL e API Key são obrigatórios'], 400);
        }

        $user = $this->currentUser();

        // Criar na Evolution API
        $api = new EvolutionApi($apiUrl, $apiKey, $instanceName);
        $webhookUrl = baseUrl('whatsapp/webhook');
        $result = $api->createInstance($instanceName, $webhookUrl);

        if (isset($result['error']) && $result['error']) {
            $this->json(['error' => 'Erro na Evolution API: ' . ($result['message'] ?? 'Desconhecido')], 500);
        }

        // Salvar no banco
        $db = Database::getInstance();
        $id = $db->insert('whatsapp_instances', [
            'instance_name' => $instanceName,
            'display_name' => $displayName ?: $instanceName,
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'user_id' => $user['id'],
            'connection_status' => 'close',
            'is_default' => 0,
        ]);

        $this->json(['success' => true, 'instance_id' => $id, 'result' => $result]);
    }

    /**
     * API: Conectar instância (gerar QR Code)
     */
    public function connect($instanceId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if (!$instanceId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$instance) $this->json(['error' => 'Instância não encontrada'], 404);

        $api = new EvolutionApi($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        $result = $api->connectInstance();

        // Atualizar status
        $db->update('whatsapp_instances', ['connection_status' => 'connecting'], 'id = ?', [$instanceId]);

        $this->json($result);
    }

    /**
     * API: Verificar status da conexão
     */
    public function status($instanceId = null)
    {
        $this->requireRole(['super_admin', 'attendant']);
        if (!$instanceId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$instance) $this->json(['error' => 'Instância não encontrada'], 404);

        $api = new EvolutionApi($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        $result = $api->connectionState();

        // Atualizar status no banco
        $state = $result['instance']['state'] ?? $result['state'] ?? 'close';
        $db->update('whatsapp_instances', ['connection_status' => $state], 'id = ?', [$instanceId]);

        $this->json(['state' => $state, 'result' => $result]);
    }

    /**
     * API: Desconectar instância
     */
    public function disconnect($instanceId = null)
    {
        $this->requireRole(['super_admin']);
        if (!$instanceId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$instance) $this->json(['error' => 'Instância não encontrada'], 404);

        $api = new EvolutionApi($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        $api->logoutInstance();

        $db->update('whatsapp_instances', ['connection_status' => 'close'], 'id = ?', [$instanceId]);
        $this->json(['success' => true]);
    }

    /**
     * API: Definir instância como padrão
     */
    public function setDefault($instanceId = null)
    {
        $this->requireRole(['super_admin']);
        if (!$instanceId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $db->query("UPDATE whatsapp_instances SET is_default = 0");
        $db->update('whatsapp_instances', ['is_default' => 1], 'id = ?', [$instanceId]);

        $this->json(['success' => true]);
    }

    /**
     * API: Deletar instância
     */
    public function deleteInstance($instanceId = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$instanceId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$instance) $this->json(['error' => 'Instância não encontrada'], 404);

        // Deletar na Evolution API
        $api = new EvolutionApi($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        $api->deleteInstance();

        // Deletar do banco (cascade remove contatos e mensagens)
        $db->delete('whatsapp_instances', 'id = ?', [$instanceId]);

        $this->json(['success' => true]);
    }

    // =========================================
    // WEBHOOK — Recebe mensagens da Evolution API
    // =========================================

    /**
     * Webhook público para receber eventos da Evolution API
     */
    public function webhook()
    {
        // Webhook não requer login — é chamado pela Evolution API
        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload) {
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // Detectar tipo de evento
        $event = $payload['event'] ?? '';

        // Normalizar nome do evento
        $event = str_replace('_', '.', strtolower($event));
        if ($event === 'messages.upsert' || $event === 'messages_upsert') {
            $event = 'messages.upsert';
        }

        switch ($event) {
            case 'messages.upsert':
                $this->handleMessageUpsert($payload);
                break;
            case 'connection.update':
                $this->handleConnectionUpdate($payload);
                break;
            case 'qrcode.updated':
                $this->handleQrCodeUpdate($payload);
                break;
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /**
     * Processa mensagem recebida via webhook
     */
    private function handleMessageUpsert($payload)
    {
        $instanceName = $payload['instance'] ?? $payload['instanceName'] ?? '';

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE instance_name = ?", [$instanceName]);
        if (!$instance) return;

        // Detectar formato do payload (V1/V2)
        $messages = [];
        if (isset($payload['data']['messages'])) {
            $messages = $payload['data']['messages'];
        } elseif (isset($payload['data'][0])) {
            $messages = $payload['data'];
        } elseif (isset($payload['data']['key'])) {
            $messages = [$payload['data']];
        }

        foreach ($messages as $msg) {
            $this->processMessage($instance, $msg);
        }
    }

    /**
     * Processa uma mensagem individual
     */
    private function processMessage($instance, $msg)
    {
        $key = $msg['key'] ?? [];
        $remoteJid = $key['remoteJid'] ?? '';
        $fromMe = $key['fromMe'] ?? false;
        $messageId = $key['id'] ?? '';

        if (empty($remoteJid) || empty($messageId)) return;

        // Ignorar mensagens de status/broadcast
        if (strpos($remoteJid, 'status@') !== false || strpos($remoteJid, 'broadcast') !== false) return;

        // Detectar tipo e texto da mensagem
        $message = $msg['message'] ?? [];
        $msgType = 'text';
        $msgText = '';
        $mediaUrl = null;
        $mediaMime = null;
        $mediaFilename = null;

        if (isset($message['conversation'])) {
            $msgText = $message['conversation'];
        } elseif (isset($message['extendedTextMessage']['text'])) {
            $msgText = $message['extendedTextMessage']['text'];
        } elseif (isset($message['imageMessage'])) {
            $msgType = 'image';
            $msgText = $message['imageMessage']['caption'] ?? '';
            $mediaMime = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (isset($message['audioMessage'])) {
            $msgType = 'audio';
            $mediaMime = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
        } elseif (isset($message['videoMessage'])) {
            $msgType = 'video';
            $msgText = $message['videoMessage']['caption'] ?? '';
            $mediaMime = $message['videoMessage']['mimetype'] ?? 'video/mp4';
        } elseif (isset($message['documentMessage'])) {
            $msgType = 'document';
            $mediaFilename = $message['documentMessage']['fileName'] ?? 'document';
            $mediaMime = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
        } elseif (isset($message['stickerMessage'])) {
            $msgType = 'sticker';
        } elseif (isset($message['reactionMessage'])) {
            // Ignorar reações por ora
            return;
        } elseif (isset($message['protocolMessage']) || isset($message['senderKeyDistributionMessage'])) {
            // Mensagens de sistema — ignorar
            return;
        }

        // Dados do contato
        $pushName = $msg['pushName'] ?? '';
        $isGroup = strpos($remoteJid, '@g.us') !== false;
        $participantJid = $key['participant'] ?? null;

        // Normalizar JID
        $api = new EvolutionApi();
        $normalizedJid = $api->normalizeJid($remoteJid);
        $phone = $api->extractPhone($normalizedJid);

        // Upsert contato
        $contactId = $this->contactModel->upsert($instance['id'], $normalizedJid, [
            'phone' => $phone,
            'push_name' => $pushName ?: null,
            'is_group' => $isGroup ? 1 : 0,
            'last_message_at' => date('Y-m-d H:i:s'),
        ], $pushName);

        // Download de mídia base64 se disponível
        if (isset($msg['message']['base64'])) {
            $mediaData = $msg['message']['base64'];
            $mediaDir = PUBLIC_PATH . '/uploads/whatsapp_media/' . date('Y-m');
            if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
            $ext = explode('/', $mediaMime ?? 'application/octet-stream')[1] ?? 'bin';
            $ext = preg_replace('/;.*/', '', $ext); // limpar charset do mime
            $filename = uniqid() . '.' . $ext;
            file_put_contents($mediaDir . '/' . $filename, base64_decode($mediaData));
            $mediaUrl = 'uploads/whatsapp_media/' . date('Y-m') . '/' . $filename;
        }

        // Salvar mensagem
        $timestamp = isset($msg['messageTimestamp'])
            ? date('Y-m-d H:i:s', intval($msg['messageTimestamp']))
            : date('Y-m-d H:i:s');

        $this->messageModel->create([
            'instance_id' => $instance['id'],
            'contact_id' => $contactId,
            'remote_jid' => $normalizedJid,
            'message_id' => $messageId,
            'from_me' => $fromMe ? 1 : 0,
            'message_type' => $msgType,
            'message_text' => $msgText,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMime,
            'media_filename' => $mediaFilename,
            'quoted_message_id' => $msg['message']['extendedTextMessage']['contextInfo']['stanzaId'] ?? null,
            'sender_name' => $pushName,
            'participant_jid' => $participantJid,
            'timestamp' => $timestamp,
        ]);

        // Incrementar não lidas (se não for de mim)
        if (!$fromMe) {
            $this->contactModel->incrementUnread($contactId);
        }
    }

    /**
     * Processa atualização de conexão
     */
    private function handleConnectionUpdate($payload)
    {
        $instanceName = $payload['instance'] ?? $payload['instanceName'] ?? '';
        $state = $payload['data']['state'] ?? $payload['state'] ?? '';

        if (empty($instanceName) || empty($state)) return;

        $db = Database::getInstance();
        $db->update('whatsapp_instances', ['connection_status' => $state], 'instance_name = ?', [$instanceName]);
    }

    /**
     * Processa atualização de QR Code
     */
    private function handleQrCodeUpdate($payload)
    {
        $instanceName = $payload['instance'] ?? $payload['instanceName'] ?? '';
        $qrCode = $payload['data']['qrcode'] ?? $payload['qrcode'] ?? '';

        if (empty($instanceName)) return;

        $db = Database::getInstance();
        $db->update('whatsapp_instances', [
            'qr_code' => $qrCode,
            'connection_status' => 'connecting',
        ], 'instance_name = ?', [$instanceName]);
    }

    /**
     * API: Adicionar contato ao CRM
     */
    public function addToCrm()
    {
        $this->requireRole(['super_admin', 'attendant']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $contactId = $_POST['contact_id'] ?? null;
        $columnId = $_POST['column_id'] ?? null;
        $boardId = $_POST['board_id'] ?? null;

        if (!$contactId || (!$columnId && !$boardId)) {
            $this->json(['error' => 'Contato e coluna/board obrigatórios'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $crmModel = new CrmBoard();

        // Se só passou board, pegar primeira coluna
        if (!$columnId && $boardId) {
            $columns = $crmModel->getColumns($boardId);
            if (empty($columns)) $this->json(['error' => 'Board sem colunas'], 400);
            $columnId = $columns[0]['id'];
        }

        $user = $this->currentUser();
        $cardId = $crmModel->createCard([
            'column_id' => $columnId,
            'contact_id' => $contactId,
            'title' => $contact['contact_name'] ?: $contact['push_name'] ?: $contact['phone'],
            'phone' => $contact['phone'],
            'assigned_to' => $contact['assigned_to'],
            'created_by' => $user['id'],
        ]);

        $this->json(['success' => true, 'card_id' => $cardId]);
    }
}
