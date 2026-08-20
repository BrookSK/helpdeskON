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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $user = $this->currentUser();

        $db = Database::getInstance();
        $instances = $db->fetchAll("SELECT wi.*, u.name as linked_user_name FROM whatsapp_instances wi LEFT JOIN users u ON wi.user_id = u.id ORDER BY wi.is_default DESC, wi.created_at DESC");

        // Lista de usuários para vincular instância (super_admin + attendant + whatsapp_agent)
        $teamMembers = $db->fetchAll("SELECT id, name, role FROM users WHERE role IN ('super_admin','attendant','whatsapp_agent') AND is_active = 1 ORDER BY name ASC");

        // Pegar instância padrão para preencher URL/Key no formulário de nova instância
        $defaultInstance = $db->fetch("SELECT api_url, api_key FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");

        $this->view('whatsapp/connect', [
            'user' => $user,
            'instances' => $instances,
            'teamMembers' => $teamMembers,
            'defaultInstance' => $defaultInstance,
        ]);
    }

    /**
     * Chat — Interface estilo WhatsApp Web
     */
    public function chat($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $user = $this->currentUser();

        $db = Database::getInstance();

        // Instância vinculada ao usuário ou sem vínculo (mesma lógica do getUserInstance)
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE user_id = ? LIMIT 1", [$user['id']]);
        if (!$instance) {
            $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 AND user_id IS NULL LIMIT 1");
        }
        if (!$instance) {
            $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE user_id IS NULL LIMIT 1");
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
     * Retorna a instância do usuário logado (ou a padrão como fallback)
     */
    private function getUserInstance()
    {
        $db = Database::getInstance();
        $user = $this->currentUser();

        // 1. Instância vinculada diretamente ao meu usuário
        $instance = $db->fetch(
            "SELECT * FROM whatsapp_instances WHERE user_id = ? LIMIT 1",
            [$user['id']]
        );
        if ($instance) return $instance;

        // 2. Instância padrão SEM vínculo a nenhum usuário (disponível para todos)
        $instance = $db->fetch(
            "SELECT * FROM whatsapp_instances WHERE is_default = 1 AND user_id IS NULL LIMIT 1"
        );
        if ($instance) return $instance;

        // 3. Qualquer instância SEM vínculo (disponível para todos)
        $instance = $db->fetch(
            "SELECT * FROM whatsapp_instances WHERE user_id IS NULL LIMIT 1"
        );

        return $instance; // Pode ser null — usuário não tem acesso a nada
    }

    /**
     * API: Listar contatos (AJAX)
     */
    public function contacts()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);

        $instance = $this->getUserInstance();
        if (!$instance) {
            $this->json(['contacts' => [], 'groups' => []]);
        }

        $user = $this->currentUser();
        $filters = [];

        // Não-admins veem apenas contatos atribuídos a si + sem dono
        if ($user['role'] !== 'super_admin') {
            // Se o frontend não enviou filtro específico, forçar filtro pelo próprio usuário + sem dono
            if (empty($_GET['assigned_to'])) {
                $filters['assigned_to_or_unassigned'] = $user['id'];
            } else {
                // Não permitir filtrar por outro usuário se não for admin
                $filters['assigned_to_or_unassigned'] = $user['id'];
            }
        } else {
            if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        }

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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $beforeId = $_GET['before_id'] ?? null;
        $messages = $this->messageModel->getByContact($contactId, 50, $beforeId);

        // Marcar como lidas
        $this->messageModel->markAsRead($contactId);
        $this->contactModel->clearUnread($contactId);

        $this->json($messages);
    }

    /**
     * API: Transcrever um áudio recebido (Whisper/OpenAI) e salvar a transcrição.
     */
    public function transcribeAudio($messageId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$messageId) $this->json(['error' => 'ID obrigatório'], 400);

        $db = Database::getInstance();
        $msg = $db->fetch("SELECT * FROM whatsapp_messages WHERE id = ?", [$messageId]);
        if (!$msg) $this->json(['error' => 'Mensagem não encontrada'], 404);
        if ($msg['message_type'] !== 'audio' || empty($msg['media_url'])) {
            $this->json(['error' => 'Mensagem não é um áudio válido'], 400);
        }

        // Se já foi transcrito, retorna o cache
        if (!empty($msg['transcription'])) {
            $this->json(['success' => true, 'transcription' => $msg['transcription']]);
        }

        $apiKey = Config::get('openai_api_key');
        if (empty($apiKey)) {
            $this->json(['error' => 'Chave da API OpenAI não configurada.'], 400);
        }

        $filePath = PUBLIC_PATH . '/' . $msg['media_url'];
        if (!file_exists($filePath)) {
            $this->json(['error' => 'Arquivo de áudio não encontrado no servidor.'], 404);
        }

        $mime = $msg['media_mime_type'] ?: 'audio/ogg';
        $ext = pathinfo($msg['media_url'], PATHINFO_EXTENSION) ?: 'ogg';

        try {
            $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
            $cfile = new CURLFile($filePath, $mime, 'audio.' . $ext);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'file' => $cfile,
                    'model' => 'whisper-1',
                    'language' => 'pt',
                ],
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
            $text = $data['text'] ?? null;

            if ($httpCode >= 400 || !$text) {
                $this->json(['error' => 'Falha na transcrição.'], 500);
            }

            $db->update('whatsapp_messages', ['transcription' => $text], 'id = ?', [$messageId]);
            $this->json(['success' => true, 'transcription' => $text]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Listar respostas rápidas
     */
    public function quickReplies()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $rows = Database::getInstance()->fetchAll("SELECT * FROM whatsapp_quick_replies ORDER BY shortcut ASC");
        foreach ($rows as &$r) {
            $r['attachment_url'] = !empty($r['attachment_path']) ? baseUrl($r['attachment_path']) : null;
        }
        unset($r);
        $this->json(['replies' => $rows]);
    }

    /**
     * API: Salvar (criar/editar) resposta rápida
     */
    public function saveQuickReply()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $id = $_POST['id'] ?? null;
        $shortcut = ltrim(trim($_POST['shortcut'] ?? ''), '/');
        $message = trim($_POST['message'] ?? '');

        // Normaliza o atalho: sem espaços, minúsculo
        $shortcut = strtolower(preg_replace('/\s+/', '', $shortcut));

        $db = Database::getInstance();
        $user = $this->currentUser();

        // Registro existente (para edição / substituição de anexo)
        $current = $id ? $db->fetch("SELECT * FROM whatsapp_quick_replies WHERE id = ?", [$id]) : null;

        // Processar upload de anexo (opcional)
        $attachment = null;
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadDir = PUBLIC_PATH . '/uploads/quick_replies';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = uniqid() . '_' . time() . ($ext ? '.' . $ext : '');
            $relPath = 'uploads/quick_replies/' . $fileName;
            if (!move_uploaded_file($file['tmp_name'], PUBLIC_PATH . '/' . $relPath)) {
                $this->json(['error' => 'Falha ao salvar o anexo.'], 500);
            }
            $attachment = [
                'attachment_path' => $relPath,
                'attachment_name' => $file['name'],
                'attachment_mime' => $file['type'],
            ];
        }

        // A mensagem só é obrigatória quando não há anexo (novo ou já existente)
        $hasAttachment = $attachment !== null || (!empty($current['attachment_path']) && empty($_POST['remove_attachment']));
        if ($shortcut === '' || ($message === '' && !$hasAttachment)) {
            $this->json(['error' => 'Informe o atalho e a mensagem ou um anexo.'], 400);
        }

        // Checar duplicidade de atalho (exceto o próprio na edição)
        $existing = $db->fetch("SELECT id FROM whatsapp_quick_replies WHERE shortcut = ?", [$shortcut]);
        if ($existing && (!$id || $existing['id'] != $id)) {
            $this->json(['error' => 'Já existe uma resposta com esse atalho.'], 400);
        }

        $data = ['shortcut' => $shortcut, 'message' => $message];

        if ($attachment) {
            // Substituindo: remove o anexo antigo do disco
            if (!empty($current['attachment_path'])) {
                @unlink(PUBLIC_PATH . '/' . $current['attachment_path']);
            }
            $data = array_merge($data, $attachment);
        } elseif (!empty($_POST['remove_attachment'])) {
            // Removendo anexo existente
            if (!empty($current['attachment_path'])) {
                @unlink(PUBLIC_PATH . '/' . $current['attachment_path']);
            }
            $data['attachment_path'] = null;
            $data['attachment_name'] = null;
            $data['attachment_mime'] = null;
        }

        if ($id) {
            $db->update('whatsapp_quick_replies', $data, 'id = ?', [$id]);
        } else {
            $data['created_by'] = $user['id'];
            $id = $db->insert('whatsapp_quick_replies', $data);
        }

        $this->json(['success' => true, 'id' => $id]);
    }

    /**
     * API: Excluir resposta rápida
     */
    public function deleteQuickReply($id = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }
        $db = Database::getInstance();
        $row = $db->fetch("SELECT attachment_path FROM whatsapp_quick_replies WHERE id = ?", [$id]);
        if (!empty($row['attachment_path'])) {
            @unlink(PUBLIC_PATH . '/' . $row['attachment_path']);
        }
        $db->delete('whatsapp_quick_replies', 'id = ?', [$id]);
        $this->json(['success' => true]);
    }

    /**
     * API: Enviar uma resposta rápida com anexo (arquivo já armazenado no servidor).
     * A legenda opcional é enviada junto com o anexo.
     */
    public function sendQuickReply()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }
        @ignore_user_abort(true);
        @set_time_limit(120);

        $contactId = $_POST['contact_id'] ?? null;
        $replyId = $_POST['reply_id'] ?? null;
        $caption = trim($_POST['caption'] ?? '');

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        // Atribui o contato ao usuário atual se estiver sem dono
        $this->autoAssignContact($contact);

        $reply = Database::getInstance()->fetch("SELECT * FROM whatsapp_quick_replies WHERE id = ?", [$replyId]);
        if (!$reply || empty($reply['attachment_path'])) {
            $this->json(['error' => 'Resposta rápida sem anexo'], 400);
        }

        $srcPath = PUBLIC_PATH . '/' . $reply['attachment_path'];
        if (!is_file($srcPath)) {
            $this->json(['error' => 'Arquivo do anexo não encontrado'], 404);
        }

        // Copiar para a pasta de mídia do WhatsApp (histórico de mensagens)
        $origName = $reply['attachment_name'] ?: basename($srcPath);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $mime = $reply['attachment_mime'] ?: 'application/octet-stream';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $mediaType = 'image';
        } elseif (in_array($ext, ['mp4', 'avi', 'mov', '3gp'])) {
            $mediaType = 'video';
        } elseif (in_array($ext, ['mp3', 'ogg', 'wav', 'aac', 'm4a'])) {
            $mediaType = 'audio';
        } else {
            $mediaType = 'document';
        }

        $fileName = uniqid() . '_' . time() . ($ext ? '.' . $ext : '');
        $uploadDir = PUBLIC_PATH . '/uploads/whatsapp_media/' . date('Y-m');
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filePath = 'uploads/whatsapp_media/' . date('Y-m') . '/' . $fileName;
        @copy($srcPath, PUBLIC_PATH . '/' . $filePath);

        $publicUrl = baseUrl($filePath);
        $msgType = $mediaType;

        $tempMsgId = uniqid('sending_');
        try {
            $messageId = $this->messageModel->create([
                'instance_id' => $contact['instance_id'],
                'contact_id' => $contactId,
                'remote_jid' => $contact['remote_jid'],
                'message_id' => $tempMsgId,
                'from_me' => 1,
                'message_type' => $msgType,
                'message_text' => $caption,
                'media_url' => $filePath,
                'media_mime_type' => $mime,
                'media_filename' => $origName,
                'sender_name' => $this->currentUser()['name'],
                'timestamp' => date('Y-m-d H:i:s'),
                'is_read' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
        $this->setAckStatusSafe($messageId, 'pending');
        $this->contactModel->updateLastMessage($contactId, date('Y-m-d H:i:s'));

        $result = null;
        try {
            $api = EvolutionApi::fromInstance($contact['instance_id']);
            if (!$api) $this->json(['error' => 'Instância não encontrada'], 400);

            if ($mediaType === 'audio') {
                $base64 = base64_encode(file_get_contents(PUBLIC_PATH . '/' . $filePath));
                $result = $api->sendAudio($contact['remote_jid'], "data:{$mime};base64,{$base64}");
            } else {
                $result = $api->sendMedia($contact['remote_jid'], $mediaType, $publicUrl, $caption, $origName);
                if (is_array($result) && isset($result['error']) && $result['error']) {
                    $base64 = base64_encode(file_get_contents(PUBLIC_PATH . '/' . $filePath));
                    $result = $api->sendMedia($contact['remote_jid'], $mediaType, "data:{$mime};base64,{$base64}", $caption, $origName);
                }
            }
        } catch (\Throwable $e) {
            $this->setAckStatusSafe($messageId, 'failed');
            $this->json(['error' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }

        if (is_array($result) && isset($result['error']) && $result['error']) {
            $this->setAckStatusSafe($messageId, 'failed');
            $this->json(['error' => 'A mídia não pôde ser entregue: ' . ($result['message'] ?? 'erro na API')], 500);
        }

        $sentMsgId = (is_array($result) && !empty($result['key']['id'])) ? $result['key']['id'] : $tempMsgId;
        try {
            Database::getInstance()->update('whatsapp_messages', ['message_id' => $sentMsgId], 'id = ?', [$messageId]);
        } catch (\Throwable $e) { /* ignora */ }
        $this->setAckStatusSafe($messageId, 'sent');

        $this->json([
            'success' => true,
            'message' => [
                'id' => $messageId,
                'from_me' => 1,
                'message_type' => $msgType,
                'message_text' => $caption,
                'media_url' => $filePath,
                'media_filename' => $origName,
                'timestamp' => date('Y-m-d H:i:s'),
                'ack_status' => 'sent',
            ],
        ]);
    }

    /**
     * API: Status (ack) das mensagens enviadas de um contato — para atualizar os checks.
     */
    public function messageStatuses($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        try {
            $rows = Database::getInstance()->fetchAll(
                "SELECT id, ack_status FROM whatsapp_messages
                 WHERE contact_id = ? AND from_me = 1
                 ORDER BY id DESC LIMIT 50",
                [$contactId]
            );
        } catch (Exception $e) {
            $rows = [];
        }
        $this->json(['statuses' => $rows]);
    }

    /**
     * API: Polling — novas mensagens
     */
    public function poll($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $afterId = $_GET['after_id'] ?? 0;
        $messages = $this->messageModel->getNewMessages($contactId, $afterId);

        // Verificar mensagens deletadas recentemente neste contato
        $db = Database::getInstance();
        $deletedIds = $db->fetchAll(
            "SELECT id, message_id FROM whatsapp_messages WHERE contact_id = ? AND is_deleted = 1 ORDER BY id DESC LIMIT 20",
            [$contactId]
        );

        $this->json([
            'messages' => $messages,
            'deleted' => array_column($deletedIds, 'id'),
        ]);
    }

    /**
     * Atribui automaticamente o contato ao usuário atual quando ninguém está atribuído.
     * Chamado ao enviar mensagens (texto, mídia ou resposta rápida).
     */
    private function autoAssignContact($contact)
    {
        if (!empty($contact['assigned_to'])) return;
        $user = $this->currentUser();
        if (empty($user['id'])) return;
        try {
            $this->contactModel->assignTo($contact['id'], $user['id']);
        } catch (\Throwable $e) { /* ignora falha de atribuição */ }
    }

    /**
     * API: Enviar mensagem de texto
     */
    public function send()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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

        // Atribui o contato ao usuário atual se estiver sem dono
        $this->autoAssignContact($contact);

        $api = EvolutionApi::fromInstance($contact['instance_id']);
        if (!$api) $this->json(['error' => 'Instância não encontrada'], 400);

        // Enviar via Evolution API
        $result = $api->sendText($contact['remote_jid'], $text);

        if (isset($result['error']) && $result['error']) {
            $this->json(['error' => $result['message'] ?? 'Erro ao enviar'], 500);
        }

        // Salvar no banco
        $sentMsgId = $result['key']['id'] ?? uniqid('sent_');
        $messageId = $this->messageModel->create([
            'instance_id' => $contact['instance_id'],
            'contact_id' => $contactId,
            'remote_jid' => $contact['remote_jid'],
            'message_id' => $sentMsgId,
            'from_me' => 1,
            'message_type' => 'text',
            'message_text' => $text,
            'sender_name' => $this->currentUser()['name'],
            'timestamp' => date('Y-m-d H:i:s'),
            'is_read' => 1,
        ]);
        $this->setAckStatusSafe($messageId, 'sent');

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
                'ack_status' => 'sent',
            ],
        ]);
    }

    /**
     * API: Enviar mídia (upload)
     */
    public function sendMedia()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        // Garante que o envio conclua no servidor mesmo se o usuário sair da tela
        // (browser aborta a requisição, mas o PHP continua e marca a mensagem como enviada)
        @ignore_user_abort(true);
        @set_time_limit(120);

        $contactId = $_POST['contact_id'] ?? null;
        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        // Atribui o contato ao usuário atual se estiver sem dono
        $this->autoAssignContact($contact);

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

        $caption = $_POST['caption'] ?? '';
        $publicUrl = baseUrl($filePath);
        $msgType = $mediaType === 'image' ? 'image' : ($mediaType === 'video' ? 'video' : ($mediaType === 'audio' ? 'audio' : 'document'));

        // 1) Persistir a mensagem IMEDIATAMENTE (status 'pending'), para que ela
        //    permaneça na conversa mesmo se o usuário sair da tela durante o envio.
        $tempMsgId = uniqid('sending_');
        try {
            $messageId = $this->messageModel->create([
                'instance_id' => $contact['instance_id'],
                'contact_id' => $contactId,
                'remote_jid' => $contact['remote_jid'],
                'message_id' => $tempMsgId,
                'from_me' => 1,
                'message_type' => $msgType,
                'message_text' => $caption,
                'media_url' => $filePath,
                'media_mime_type' => $mime,
                'media_filename' => $file['name'],
                'sender_name' => $this->currentUser()['name'],
                'timestamp' => date('Y-m-d H:i:s'),
                'is_read' => 1,
            ]);
        } catch (\Throwable $e) {
            @file_put_contents(PUBLIC_PATH . '/uploads/sendmedia_error.log', '[' . date('Y-m-d H:i:s') . '] DB ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
            $this->json(['error' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
        $this->setAckStatusSafe($messageId, 'pending');
        $this->contactModel->updateLastMessage($contactId, date('Y-m-d H:i:s'));

        // 2) Enviar via Evolution API
        $result = null;
        try {
            $api = EvolutionApi::fromInstance($contact['instance_id']);
            if (!$api) {
                $this->json(['error' => 'Instância não encontrada'], 400);
            }

            if ($mediaType === 'audio') {
                $base64 = base64_encode(file_get_contents(PUBLIC_PATH . '/' . $filePath));
                $result = $api->sendAudio($contact['remote_jid'], "data:{$mime};base64,{$base64}");
            } else {
                $result = $api->sendMedia($contact['remote_jid'], $mediaType, $publicUrl, $caption, $file['name']);
                if (is_array($result) && isset($result['error']) && $result['error']) {
                    @file_put_contents(PUBLIC_PATH . '/uploads/sendmedia_error.log', '[' . date('Y-m-d H:i:s') . '] URL FAIL, tentando base64: ' . json_encode($result) . "\n", FILE_APPEND);
                    $base64 = base64_encode(file_get_contents(PUBLIC_PATH . '/' . $filePath));
                    $result = $api->sendMedia($contact['remote_jid'], $mediaType, "data:{$mime};base64,{$base64}", $caption, $file['name']);
                }
            }
        } catch (\Throwable $e) {
            @file_put_contents(PUBLIC_PATH . '/uploads/sendmedia_error.log', '[' . date('Y-m-d H:i:s') . '] EXCEPTION: ' . $e->getMessage() . "\n", FILE_APPEND);
            $this->setAckStatusSafe($messageId, 'failed');
            $this->json(['error' => 'Erro ao enviar: ' . $e->getMessage()], 500);
        }

        // 3) Atualizar o status conforme o resultado
        if (is_array($result) && isset($result['error']) && $result['error']) {
            @file_put_contents(PUBLIC_PATH . '/uploads/sendmedia_error.log', '[' . date('Y-m-d H:i:s') . '] API ERROR: ' . json_encode($result) . "\n", FILE_APPEND);
            $this->setAckStatusSafe($messageId, 'failed');
            $this->json(['error' => 'A mídia não pôde ser entregue: ' . ($result['message'] ?? 'erro na API')], 500);
        }

        // Sucesso: atualiza o message_id real e marca como enviada
        $sentMsgId = (is_array($result) && !empty($result['key']['id'])) ? $result['key']['id'] : $tempMsgId;
        try {
            Database::getInstance()->update('whatsapp_messages', ['message_id' => $sentMsgId], 'id = ?', [$messageId]);
        } catch (\Throwable $e) { /* ignora */ }
        $this->setAckStatusSafe($messageId, 'sent');

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
                'ack_status' => 'sent',
            ],
        ]);
    }

    /**
     * API: Detalhes do contato
     */
    public function contactDetail($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        // Backfill da foto de perfil, se ainda não tiver
        if (empty($contact['profile_picture_url']) && !empty($contact['phone'])) {
            $db = Database::getInstance();
            $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$contact['instance_id']]);
            $numberForPic = !empty($contact['is_group']) ? $contact['remote_jid'] : $contact['phone'];
            if ($instance) {
                $picUrl = $this->fetchProfilePicUrl($instance, $numberForPic);
                if (!empty($picUrl)) {
                    $db->update('whatsapp_contacts', ['profile_picture_url' => $picUrl], 'id = ?', [$contactId]);
                    $contact['profile_picture_url'] = $picUrl;
                }
            }
        }

        $labels = $this->contactModel->getLabels($contactId);
        $contact['labels'] = $labels;

        $this->json($contact);
    }

    /**
     * API: Atualizar contato (notas, atribuição)
     */
    public function updateContact($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $user = $this->currentUser();
        $data = [];
        if (isset($_POST['contact_name'])) $data['contact_name'] = trim($_POST['contact_name']);
        if (isset($_POST['internal_notes'])) $data['internal_notes'] = trim($_POST['internal_notes']);
        if (isset($_POST['assigned_to'])) {
            // Não-admins só podem atribuir a si mesmos
            if ($user['role'] !== 'super_admin') {
                $data['assigned_to'] = $user['id'];
            } else {
                $data['assigned_to'] = $_POST['assigned_to'] ?: null;
            }
        }
        if (isset($_POST['service_status'])) $data['service_status'] = $_POST['service_status'] ?: 'novo';

        if (!empty($data)) {
            $db = Database::getInstance();
            $db->update('whatsapp_contacts', $data, 'id = ?', [$contactId]);

            // Se o nome do contato mudou, atualizar o título dos cards do CRM vinculados
            if (isset($data['contact_name']) && $data['contact_name'] !== '') {
                $db->update('crm_cards', ['title' => $data['contact_name']], 'contact_id = ?', [$contactId]);
            }
        }

        $this->json(['success' => true]);
    }

    /**
     * API: Adicionar/remover etiqueta de um contato
     */
    public function toggleLabel()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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
            'user_id' => !empty($_POST['user_id']) ? intval($_POST['user_id']) : $user['id'],
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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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

    /**
     * API: Atualizar instância (display_name, api_url, api_key, user_id)
     */
    public function updateInstance($instanceId = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$instanceId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        if (!$instance) $this->json(['error' => 'Instância não encontrada'], 404);

        $data = [];
        if (isset($_POST['display_name'])) $data['display_name'] = trim($_POST['display_name']);
        if (isset($_POST['api_url'])) $data['api_url'] = trim($_POST['api_url']);
        if (isset($_POST['api_key'])) $data['api_key'] = trim($_POST['api_key']);
        if (isset($_POST['user_id'])) $data['user_id'] = $_POST['user_id'] ?: null;

        if (empty($data)) {
            $this->json(['error' => 'Nada para atualizar'], 400);
        }

        $db->update('whatsapp_instances', $data, 'id = ?', [$instanceId]);
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
            case 'messages.update':
                $this->handleMessageUpdate($payload);
                break;
            case 'messages.delete':
                $this->handleMessageDelete($payload);
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

        // Ignorar mensagens enviadas por mim (fromMe) — o sistema já salva na hora do envio
        // Isso evita duplicação de mensagens enviadas pelo painel
        if ($fromMe) return;

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
            $mediaMime = $message['stickerMessage']['mimetype'] ?? 'image/webp';
        } elseif (isset($message['reactionMessage'])) {
            // Reação: emoji + referência à mensagem reagida
            $msgType = 'reaction';
            $msgText = $message['reactionMessage']['text'] ?? '';
            $reactionTargetId = $message['reactionMessage']['key']['id'] ?? null;
            // Se a reação foi removida (texto vazio), ignorar
            if ($msgText === '') return;
        } elseif (isset($message['protocolMessage'])) {
            // Detectar mensagem deletada (revoked)
            $proto = $message['protocolMessage'];
            $protoType = $proto['type'] ?? '';
            if ($protoType === 'REVOKE' || $protoType === 0 || isset($proto['key'])) {
                // Mensagem foi apagada pelo remetente
                $revokedId = $proto['key']['id'] ?? null;
                if ($revokedId) {
                    $db = Database::getInstance();
                    $db->query(
                        "UPDATE whatsapp_messages SET is_deleted = 1 WHERE message_id = ? AND instance_id = ?",
                        [$revokedId, $instance['id']]
                    );
                }
            }
            return;
        } elseif (isset($message['senderKeyDistributionMessage'])) {
            return;
        }

        // Dados do contato / remetente
        $pushName = $msg['pushName'] ?? '';
        $isGroup = strpos($remoteJid, '@g.us') !== false;
        $participantJid = $key['participant'] ?? ($msg['participant'] ?? null);

        // Para grupos, pegar nome real do remetente
        $senderName = $pushName;
        if ($isGroup && $participantJid) {
            // sender_name é quem mandou a mensagem no grupo
            $senderName = $pushName ?: $participantJid;
        }

        // Normalizar JID
        $api = new EvolutionApi();
        $normalizedJid = $api->normalizeJid($remoteJid);
        $phone = $api->extractPhone($normalizedJid);

        // Nome do grupo — SOMENTE o nome real do grupo (nunca o remetente/pushName)
        $contactName = null;
        if ($isGroup) {
            // O nome do grupo vem em campos específicos do metadata (nunca do pushName)
            $contactName = $msg['groupMetadata']['subject']
                ?? $msg['groupSubject']
                ?? null;
        } else {
            $contactName = $pushName ?: null;
        }

        // Upsert contato/grupo (não passa pushName como nome de grupo)
        $contactId = $this->contactModel->upsert($instance['id'], $normalizedJid, [
            'phone' => $phone,
            'push_name' => $isGroup ? null : ($pushName ?: null),
            'is_group' => $isGroup ? 1 : 0,
            'last_message_at' => date('Y-m-d H:i:s'),
        ], $contactName);

        // Buscar foto de perfil (lazy: só se ainda não temos)
        if (!$isGroup) {
            $existing = $this->contactModel->findById($contactId);
            if ($existing && empty($existing['profile_picture_url'])) {
                $picUrl = $this->fetchProfilePicUrl($instance, $phone);
                if (!empty($picUrl)) {
                    Database::getInstance()->update('whatsapp_contacts', ['profile_picture_url' => $picUrl], 'id = ?', [$contactId]);
                }
            }
        }

        // Para grupos: se temos o subject real, garantir que está salvo;
        // se ainda não temos um nome de grupo válido, buscar via API (lazy, uma vez).
        if ($isGroup) {
            $db = Database::getInstance();
            $existingContact = $this->contactModel->findById($contactId);
            if ($contactName) {
                // Atualiza sempre com o nome real do grupo
                $db->update('whatsapp_contacts', ['contact_name' => $contactName], 'id = ?', [$contactId]);
            } elseif ($existingContact && empty($existingContact['contact_name'])) {
                // Sem nome salvo — tentar buscar o subject do grupo na Evolution API
                $resolved = $this->resolveGroupName($instance, $normalizedJid);
                if ($resolved) {
                    $db->update('whatsapp_contacts', ['contact_name' => $resolved], 'id = ?', [$contactId]);
                }
            }
        }

        // Download de mídia base64 se disponível
        $mediaData = $msg['message']['base64'] ?? $msg['base64'] ?? null;

        // Se é mídia (incl. figurinha) mas não veio base64, buscar via API
        if (empty($mediaData) && in_array($msgType, ['image', 'audio', 'video', 'document', 'sticker']) && !empty($messageId)) {
            $mediaData = $this->fetchMediaBase64($instance, $msg);
        }

        if (!empty($mediaData)) {
            $mediaDir = PUBLIC_PATH . '/uploads/whatsapp_media/' . date('Y-m');
            if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
            $ext = explode('/', $mediaMime ?? 'application/octet-stream')[1] ?? 'bin';
            $ext = preg_replace('/;.*/', '', $ext);
            if ($msgType === 'sticker') $ext = 'webp';
            $filename = uniqid() . '.' . $ext;
            file_put_contents($mediaDir . '/' . $filename, base64_decode($mediaData));
            $mediaUrl = 'uploads/whatsapp_media/' . date('Y-m') . '/' . $filename;
        }

        // Salvar mensagem com timestamp e sender_name
        $timestamp = isset($msg['messageTimestamp'])
            ? date('Y-m-d H:i:s', intval($msg['messageTimestamp']))
            : date('Y-m-d H:i:s');

        // Para reações, o quoted_message_id aponta para a mensagem reagida
        $quotedId = $msg['message']['extendedTextMessage']['contextInfo']['stanzaId'] ?? null;
        if ($msgType === 'reaction' && !empty($reactionTargetId)) {
            $quotedId = $reactionTargetId;
        }

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
            'quoted_message_id' => $quotedId,
            'sender_name' => $senderName,
            'participant_jid' => $participantJid,
            'timestamp' => $timestamp,
        ]);

        // Incrementar não lidas (se não for de mim)
        if (!$fromMe) {
            $this->contactModel->incrementUnread($contactId);

            // Se contato está "concluido" e recebeu nova mensagem, voltar para "novo"
            $contact = $this->contactModel->findById($contactId);
            if ($contact && $contact['service_status'] === 'concluido') {
                $this->contactModel->updateServiceStatus($contactId, 'novo');
            }
        }
    }

    /**
     * Re-sincroniza os nomes reais dos grupos a partir da Evolution API (findChats).
     * Corrige grupos cujo nome foi salvo incorretamente (ex.: nome do remetente).
     */
    public function syncGroups()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);

        $instance = $this->getUserInstance();
        if (!$instance) {
            $this->json(['success' => false, 'message' => 'Nenhuma instância disponível.']);
        }

        $db = Database::getInstance();
        $groups = $db->fetchAll(
            "SELECT id, remote_jid, contact_name FROM whatsapp_contacts WHERE instance_id = ? AND is_group = 1",
            [$instance['id']]
        );

        // Buscar todos os grupos de uma vez na Evolution API (jid => subject)
        $groupMap = $this->fetchGroupsMap($instance);

        if (empty($groupMap)) {
            $this->json(['success' => false, 'message' => 'Não foi possível obter os grupos da API. Verifique a conexão da instância.']);
        }

        $updated = 0;
        foreach ($groups as $g) {
            $targetNum = preg_replace('/@.*/', '', $g['remote_jid']);
            $subject = $groupMap[$targetNum] ?? null;
            if (!empty($subject) && $subject !== $g['contact_name']) {
                $db->update('whatsapp_contacts', ['contact_name' => $subject], 'id = ?', [$g['id']]);
                $updated++;
            }
        }

        $this->json(['success' => true, 'updated' => $updated, 'total' => count($groups)]);
    }

    /**
     * Busca todos os grupos da instância na Evolution API e retorna um mapa
     * [numeroDoGrupo => subject]. Usa o endpoint /group/fetchAllGroups.
     * Chamada HTTP direta para não alterar a classe EvolutionApi.
     */
    private function fetchGroupsMap($instance)
    {
        $map = [];
        try {
            $url = rtrim($instance['api_url'], '/') . '/group/fetchAllGroups/' . $instance['instance_name'] . '?getParticipants=false';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $instance['api_key'], 'Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400 || empty($response)) {
                return $map;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) return $map;

            // A resposta pode ser uma lista direta ou vir dentro de uma chave
            $groups = $data;
            if (isset($data['groups']) && is_array($data['groups'])) {
                $groups = $data['groups'];
            }

            foreach ($groups as $grp) {
                if (!is_array($grp)) continue;
                $jid = $grp['id'] ?? $grp['jid'] ?? $grp['remoteJid'] ?? '';
                $subject = $grp['subject'] ?? $grp['name'] ?? null;
                if (!empty($jid) && !empty($subject)) {
                    $num = preg_replace('/@.*/', '', $jid);
                    $map[$num] = $subject;
                }
            }
        } catch (Exception $e) {
            // Silencioso
        }
        return $map;
    }

    /**
     * Descobre o nome (subject) real de um grupo pela Evolution API.
     * Retorna null se não encontrar.
     */
    private function resolveGroupName($instance, $groupJid)
    {
        $map = $this->fetchGroupsMap($instance);
        if (empty($map)) return null;
        $targetNum = preg_replace('/@.*/', '', $groupJid);
        return $map[$targetNum] ?? null;
    }

    /**
     * Sincroniza as fotos de perfil de todos os contatos sem foto.
     */
    public function syncPhotos()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        $instance = $this->getUserInstance();
        if (!$instance) {
            $this->json(['success' => false, 'message' => 'Nenhuma instância disponível.']);
        }

        $db = Database::getInstance();
        $contacts = $db->fetchAll(
            "SELECT id, phone, remote_jid, is_group FROM whatsapp_contacts
             WHERE instance_id = ? AND (profile_picture_url IS NULL OR profile_picture_url = '')
             LIMIT 100",
            [$instance['id']]
        );

        $updated = 0;
        foreach ($contacts as $c) {
            $number = !empty($c['is_group']) ? $c['remote_jid'] : ($c['phone'] ?: $c['remote_jid']);
            $picUrl = $this->fetchProfilePicUrl($instance, $number);
            if (!empty($picUrl)) {
                $db->update('whatsapp_contacts', ['profile_picture_url' => $picUrl], 'id = ?', [$c['id']]);
                $updated++;
            }
        }

        $this->json(['success' => true, 'updated' => $updated, 'total' => count($contacts)]);
    }

    /**
     * Tenta descobrir o nome do perfil do WhatsApp de um número.
     * Usa o endpoint de verificação de números (whatsappNumbers) que retorna o nome quando disponível.
     */
    private function fetchProfileName($instance, $number)
    {
        try {
            $num = preg_replace('/@.*/', '', $number);
            $url = rtrim($instance['api_url'], '/') . '/chat/whatsappNumbers/' . $instance['instance_name'];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['numbers' => [$num]]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $instance['api_key'], 'Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400 || empty($response)) return '';
            $data = json_decode($response, true);
            if (!is_array($data)) return '';
            foreach ($data as $item) {
                if (is_array($item) && (!empty($item['name']) || !empty($item['pushName']))) {
                    return $item['name'] ?? $item['pushName'];
                }
            }
        } catch (Exception $e) {
            // ignora
        }
        return '';
    }

    /**
     * (Re)registra o webhook da instância incluindo o evento de status das mensagens
     * (MESSAGES_UPDATE), necessário para os checks de entrega/leitura.
     * Chamada HTTP direta ao endpoint /webhook/set (não altera a classe EvolutionApi).
     */
    public function registerWebhookEvents($instanceId = null)
    {
        $this->requireRole(['super_admin']);
        $db = Database::getInstance();

        if ($instanceId) {
            $instances = $db->fetchAll("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);
        } else {
            $instances = $db->fetchAll("SELECT * FROM whatsapp_instances");
        }

        $webhookUrl = baseUrl('whatsapp/webhook');
        $events = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'];
        $results = [];

        foreach ($instances as $inst) {
            $ok = $this->setInstanceWebhook($inst, $webhookUrl, $events);
            $results[$inst['instance_name']] = $ok;
        }

        $this->json(['success' => true, 'results' => $results, 'webhook' => $webhookUrl, 'events' => $events]);
    }

    /**
     * Define o webhook + eventos de uma instância na Evolution API (tenta formatos v1 e v2).
     */
    private function setInstanceWebhook($instance, $webhookUrl, $events)
    {
        $url = rtrim($instance['api_url'], '/') . '/webhook/set/' . $instance['instance_name'];
        // Formato Evolution v2 (webhook aninhado)
        $payloads = [
            ['webhook' => ['enabled' => true, 'url' => $webhookUrl, 'byEvents' => false, 'base64' => true, 'events' => $events]],
            // Formato alternativo (v1 - campos na raiz)
            ['url' => $webhookUrl, 'webhook_by_events' => false, 'events' => $events],
        ];

        foreach ($payloads as $payload) {
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['apikey: ' . $instance['api_key'], 'Content-Type: application/json'],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 15,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode >= 200 && $httpCode < 300) {
                    return true;
                }
            } catch (Exception $e) {
                // tenta próximo formato
            }
        }
        return false;
    }

    /**
     * Baixa o base64 de uma mídia (incl. figurinha) via Evolution API,
     * quando o webhook não envia o base64 embutido.
     */
    private function fetchMediaBase64($instance, $msg)
    {
        try {
            $url = rtrim($instance['api_url'], '/') . '/chat/getBase64FromMediaMessage/' . $instance['instance_name'];
            $payload = json_encode(['message' => ['key' => $msg['key']], 'convertToMp4' => false]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $instance['api_key'], 'Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400 || empty($response)) return null;
            $data = json_decode($response, true);
            if (!is_array($data)) return null;
            return $data['base64'] ?? $data['media'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Busca a URL da foto de perfil via Evolution API (endpoint POST v2).
     * Chamada HTTP direta para não alterar a classe EvolutionApi.
     */
    private function fetchProfilePicUrl($instance, $number)
    {
        try {
            $num = preg_replace('/@.*/', '', $number);
            $url = rtrim($instance['api_url'], '/') . '/chat/fetchProfilePictureUrl/' . $instance['instance_name'];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['number' => $num]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['apikey: ' . $instance['api_key'], 'Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode >= 400 || empty($response)) return null;
            $data = json_decode($response, true);
            if (!is_array($data)) return null;
            return $data['profilePictureUrl'] ?? $data['url'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Processa atualização de status (ack) das mensagens enviadas.
     * Atualiza o checkzinho: sent (1), delivered (2), read (2 azul).
     */
    private function handleMessageUpdate($payload)
    {
        @file_put_contents(PUBLIC_PATH . '/uploads/ack_debug.log', '[' . date('Y-m-d H:i:s') . '] ' . json_encode($payload) . "\n", FILE_APPEND);

        $items = [];
        if (isset($payload['data']['messages'])) {
            $items = $payload['data']['messages'];
        } elseif (isset($payload['data'][0])) {
            $items = $payload['data'];
        } elseif (isset($payload['data'])) {
            $items = [$payload['data']];
        }

        $db = Database::getInstance();
        foreach ($items as $item) {
            $msgId = $item['key']['id'] ?? ($item['keyId'] ?? ($item['id'] ?? null));
            if (!$msgId) continue;

            // Detectar mensagem deletada/revogada
            $messageStubType = $item['messageStubType'] ?? ($item['update']['messageStubType'] ?? null);
            $status = $item['status'] ?? $item['update']['status'] ?? ($item['ack'] ?? null);

            // messageStubType REVOKE = mensagem apagada
            // Também: status 5 pode indicar deleção em algumas versões
            if ($messageStubType === 'REVOKE' || $messageStubType === 1 || $status === 5) {
                try {
                    $db->query(
                        "UPDATE whatsapp_messages SET is_deleted = 1 WHERE message_id = ?",
                        [$msgId]
                    );
                } catch (Exception $e) {}
                continue;
            }

            // Ack status update (checkzinhos)
            $raw = $status;
            $ack = $this->mapAckStatus($raw);
            if (!$ack) continue;

            try {
                $db->query(
                    "UPDATE whatsapp_messages SET ack_status = ? WHERE message_id = ? AND from_me = 1",
                    [$ack, $msgId]
                );
            } catch (Exception $e) {
                // Coluna ainda não migrada — ignora
            }
        }
    }

    /**
     * Processa evento de mensagem deletada (messages.delete)
     */
    private function handleMessageDelete($payload)
    {
        $db = Database::getInstance();

        // Formato 1: { data: { key: { id: "xxx", remoteJid: "..." } } }
        // Formato 2: { data: { message: { key: { id: "xxx" } } } }
        // Formato 3: { data: [{ key: { id: "xxx" } }] }
        $items = [];
        if (isset($payload['data']['key'])) {
            $items = [$payload['data']];
        } elseif (isset($payload['data']['message']['key'])) {
            $items = [$payload['data']['message']];
        } elseif (isset($payload['data'][0])) {
            $items = $payload['data'];
        } elseif (isset($payload['data']['id'])) {
            $items = [['key' => ['id' => $payload['data']['id']]]];
        }

        foreach ($items as $item) {
            $msgId = $item['key']['id'] ?? ($item['id'] ?? null);
            if (!$msgId) continue;

            try {
                $db->query("UPDATE whatsapp_messages SET is_deleted = 1 WHERE message_id = ?", [$msgId]);
            } catch (Exception $e) {}
        }
    }

    /**
     * Atualiza o ack_status de forma segura (ignora se a coluna ainda não existe).
     */
    private function setAckStatusSafe($messageId, $status)
    {
        try {
            Database::getInstance()->query(
                "UPDATE whatsapp_messages SET ack_status = ? WHERE id = ?",
                [$status, $messageId]
            );
        } catch (Exception $e) {
            // Coluna ainda não migrada — ignora silenciosamente
        }
    }

    /**
     * Mapeia o status recebido da Evolution para o enum interno.
     */
    private function mapAckStatus($raw)
    {
        if ($raw === null) return null;
        if (is_numeric($raw)) {
            // Padrão Baileys: 1=sent, 2=delivered, 3/4=read
            $n = (int)$raw;
            if ($n >= 4) return 'read';
            if ($n === 3) return 'read';
            if ($n === 2) return 'delivered';
            if ($n === 1) return 'sent';
            return null;
        }
        $s = strtoupper((string)$raw);
        $map = [
            'PENDING' => 'pending',
            'SERVER_ACK' => 'sent',
            'SENT' => 'sent',
            'DELIVERY_ACK' => 'delivered',
            'DELIVERED' => 'delivered',
            'READ' => 'read',
            'PLAYED' => 'read',
        ];
        return $map[$s] ?? null;
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
     * API: Iniciar conversa com um novo número.
     * Cria/localiza o contato e o retorna. Se o nome não for informado,
     * tenta usar o nome do perfil do WhatsApp (pushName) via Evolution API.
     */
    public function startConversation()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Método inválido'], 405);
        }

        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $user = $this->currentUser();

        if (empty($phone)) {
            $this->json(['error' => 'Informe o número.'], 400);
        }

        $instance = $this->getUserInstance();
        if (!$instance) {
            $this->json(['error' => 'Nenhuma instância de WhatsApp disponível.'], 400);
        }

        $api = new EvolutionApi($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        $jid = $api->normalizeJid($api->normalizeNumber($phone));
        $phoneOnly = $api->extractPhone($jid);

        // Verificar se o número tem WhatsApp e obter JID real
        try {
            $check = $api->checkIsWhatsapp([$phoneOnly]);
            if (is_array($check)) {
                foreach ($check as $item) {
                    if (!empty($item['exists']) && !empty($item['jid'])) {
                        $jid = $api->normalizeJid($item['jid']);
                        $phoneOnly = $api->extractPhone($jid);
                    }
                }
            }
        } catch (Exception $e) {
            // segue com o jid normalizado
        }

        // Se não informou nome, tentar descobrir o nome do perfil do WhatsApp
        if (empty($name)) {
            $name = $this->fetchProfileName($instance, $phoneOnly);
        }

        // Buscar a foto de perfil
        $picUrl = $this->fetchProfilePicUrl($instance, $phoneOnly);

        // Criar/localizar o contato
        $contactId = $this->contactModel->upsert($instance['id'], $jid, [
            'phone' => $phoneOnly,
            'is_group' => 0,
            'last_message_at' => date('Y-m-d H:i:s'),
        ], $name ?: null);

        // Garantir visível, com nome e foto (quando disponíveis)
        $update = ['is_archived' => 0];
        if (!empty($name)) $update['contact_name'] = $name;
        if (!empty($picUrl)) $update['profile_picture_url'] = $picUrl;
        // Auto-atribuir ao usuário que iniciou a conversa
        $update['assigned_to'] = $user['id'];
        $db = Database::getInstance();
        $db->update('whatsapp_contacts', $update, 'id = ?', [$contactId]);

        $contact = $this->contactModel->findById($contactId);
        $this->json(['success' => true, 'contact' => $contact]);
    }

    /**
     * API: Excluir contato permanentemente (apenas super_admin).
     * Remove o contato, suas mensagens, etiquetas e briefing.
     */
    public function deleteContact($contactId = null)
    {
        $this->requireRole(['super_admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $db = Database::getInstance();
        // Dependências (mensagens e vínculos de etiqueta saem por cascade via FK,
        // mas removemos explicitamente para garantir consistência)
        $db->delete('whatsapp_messages', 'contact_id = ?', [$contactId]);
        $db->delete('whatsapp_contact_labels', 'contact_id = ?', [$contactId]);
        $db->query("DELETE FROM commercial_briefings WHERE contact_id = ?", [$contactId]);
        // Desvincular de cards do CRM (não apaga o card)
        $db->query("UPDATE crm_cards SET contact_id = NULL WHERE contact_id = ?", [$contactId]);
        $db->delete('whatsapp_contacts', 'id = ?', [$contactId]);

        $this->json(['success' => true]);
    }

    /**
     * API: Obter briefing comercial de um contato
     */
    public function getBriefing($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if (!$contactId) $this->json(['error' => 'ID obrigatório'], 400);

        $briefing = $this->contactModel->getBriefing($contactId);
        $this->json(['briefing' => $briefing ?: null]);
    }

    /**
     * API: Salvar briefing comercial de um contato
     */
    public function saveBriefing($contactId = null)
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$contactId) {
            $this->json(['error' => 'Requisição inválida'], 400);
        }

        $contact = $this->contactModel->findById($contactId);
        if (!$contact) $this->json(['error' => 'Contato não encontrado'], 404);

        $temp = $_POST['lead_temperature'] ?? '';
        $data = [
            'need' => trim($_POST['need'] ?? ''),
            'main_pain' => trim($_POST['main_pain'] ?? ''),
            'current_solution' => trim($_POST['current_solution'] ?? ''),
            'expected_goal' => trim($_POST['expected_goal'] ?? ''),
            'urgency' => trim($_POST['urgency'] ?? ''),
            'investment_range' => trim($_POST['investment_range'] ?? ''),
            'decision_level' => trim($_POST['decision_level'] ?? ''),
            'lead_temperature' => in_array($temp, ['frio','morno','quente']) ? $temp : null,
            'main_objection' => trim($_POST['main_objection'] ?? ''),
            'next_step' => trim($_POST['next_step'] ?? ''),
            'next_contact_date' => !empty($_POST['next_contact_date']) ? $_POST['next_contact_date'] : null,
            'notes' => trim($_POST['notes'] ?? ''),
        ];

        $user = $this->currentUser();
        $this->contactModel->saveBriefing($contactId, $data, $user['id']);

        $this->json(['success' => true]);
    }

    /**
     * API: Adicionar contato ao CRM
     */
    public function addToCrm()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent', 'comercial']);
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

        // Se houver briefing, usar a faixa de investimento como valor do card
        $briefing = $this->contactModel->getBriefing($contactId);
        $cardValue = null;
        if ($briefing && !empty($briefing['investment_range'])) {
            // extrai números da faixa (ex.: "R$ 5.000" -> 5000)
            $num = preg_replace('/[^\d]/', '', $briefing['investment_range']);
            $cardValue = $num !== '' ? floatval($num) : null;
        }

        $user = $this->currentUser();
        $cardId = $crmModel->createCard([
            'column_id' => $columnId,
            'contact_id' => $contactId,
            'title' => $contact['contact_name'] ?: $contact['push_name'] ?: $contact['phone'],
            'phone' => $contact['phone'],
            'value' => $cardValue,
            'assigned_to' => $contact['assigned_to'],
            'created_by' => $user['id'],
        ]);

        $this->json(['success' => true, 'card_id' => $cardId]);
    }

    /**
     * API: Notificações de novas mensagens (para polling global em outras telas)
     * Retorna mensagens não lidas de contatos atribuídos ao usuário ou em_atendimento
     */
    public function notifications()
    {
        $this->requireRole(['super_admin', 'attendant', 'whatsapp_agent']);
        $user = $this->currentUser();

        $db = Database::getInstance();
        $instance = $this->getUserInstance();
        if (!$instance) {
            $this->json([]);
            return;
        }

        // Buscar contatos com mensagens não lidas atribuídos a mim (ou em_atendimento sem atribuição)
        $contacts = $db->fetchAll(
            "SELECT c.id, c.contact_name, c.push_name, c.phone, c.unread_count
             FROM whatsapp_contacts c
             WHERE c.instance_id = ? AND c.unread_count > 0 
             AND (c.assigned_to = ? OR (c.assigned_to IS NULL AND c.service_status = 'em_atendimento'))
             ORDER BY c.last_message_at DESC
             LIMIT 5",
            [$instance['id'], $user['id']]
        );

        $notifications = [];
        foreach ($contacts as $contact) {
            // Pegar última mensagem não lida
            $lastMsg = $db->fetch(
                "SELECT message_text, message_type, sender_name, timestamp 
                 FROM whatsapp_messages 
                 WHERE contact_id = ? AND from_me = 0 AND is_read = 0
                 ORDER BY id DESC LIMIT 1",
                [$contact['id']]
            );

            if ($lastMsg) {
                $name = $contact['contact_name'] ?: $contact['push_name'] ?: $contact['phone'] ?: 'Desconhecido';
                $preview = $lastMsg['message_text'] ?: ('[' . $lastMsg['message_type'] . ']');
                if (mb_strlen($preview) > 60) $preview = mb_substr($preview, 0, 60) . '...';

                $notifications[] = [
                    'contact_id' => $contact['id'],
                    'contact_name' => $name,
                    'message' => $preview,
                    'sender_name' => $lastMsg['sender_name'],
                    'timestamp' => $lastMsg['timestamp'],
                    'unread_count' => $contact['unread_count'],
                ];
            }
        }

        $this->json($notifications);
    }
}

