<?php

/**
 * Client HTTP para Evolution API v2
 * Encapsula toda a comunicação com a Evolution API
 */
class EvolutionApi
{
    private $baseUrl;
    private $apiKey;
    private $instanceName;

    public function __construct($baseUrl = null, $apiKey = null, $instanceName = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? Config::get('evolution_api_url'), '/');
        $this->apiKey = $apiKey ?? Config::get('evolution_api_key');
        $this->instanceName = $instanceName ?? Config::get('evolution_instance_name');
    }

    /**
     * Configura instância dinâmica (para multi-instância)
     */
    public function setInstance($instanceName)
    {
        $this->instanceName = $instanceName;
        return $this;
    }

    // =========================================
    // INSTÂNCIA / CONEXÃO
    // =========================================

    /**
     * Criar nova instância na Evolution API
     */
    public function createInstance($instanceName, $webhook = null)
    {
        $payload = [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        if ($webhook) {
            $payload['webhook'] = [
                'url' => $webhook,
                'byEvents' => false,
                'base64' => true,
                'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'MESSAGES_DELETE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
            ];
        }

        return $this->request('POST', '/instance/create', $payload);
    }

    /**
     * Conectar instância (gera QR Code)
     */
    public function connectInstance($instanceName = null)
    {
        $name = $instanceName ?? $this->instanceName;
        return $this->request('GET', "/instance/connect/{$name}");
    }

    /**
     * Verificar status da conexão
     */
    public function connectionState($instanceName = null)
    {
        $name = $instanceName ?? $this->instanceName;
        return $this->request('GET', "/instance/connectionState/{$name}");
    }

    /**
     * Reiniciar instância
     */
    public function restartInstance($instanceName = null)
    {
        // Evolution API v2 usa POST para reiniciar a instância.
        $name = $instanceName ?? $this->instanceName;
        return $this->request('POST', "/instance/restart/{$name}");
    }

    /**
     * Desconectar (logout) instância
     */
    public function logoutInstance($instanceName = null)
    {
        $name = $instanceName ?? $this->instanceName;
        return $this->request('DELETE', "/instance/logout/{$name}");
    }

    /**
     * Deletar instância
     */
    public function deleteInstance($instanceName = null)
    {
        $name = $instanceName ?? $this->instanceName;
        return $this->request('DELETE', "/instance/delete/{$name}");
    }

    /**
     * Listar todas as instâncias
     */
    public function fetchInstances()
    {
        return $this->request('GET', '/instance/fetchInstances');
    }

    // =========================================
    // ENVIO DE MENSAGENS
    // =========================================

    /**
     * Enviar mensagem de texto
     */
    public function sendText($number, $text)
    {
        return $this->request('POST', "/message/sendText/{$this->instanceName}", [
            'number' => $this->normalizeNumber($number),
            'text' => $text,
        ]);
    }

    /**
     * Enviar mídia (imagem, vídeo, documento)
     */
    public function sendMedia($number, $mediaType, $url, $caption = '', $fileName = '')
    {
        $payload = [
            'number' => $this->normalizeNumber($number),
            'mediatype' => $mediaType,
            'media' => $url,
        ];

        if ($caption) $payload['caption'] = $caption;
        if ($fileName) $payload['fileName'] = $fileName;

        return $this->request('POST', "/message/sendMedia/{$this->instanceName}", $payload);
    }

    /**
     * Enviar áudio (PTT - push to talk)
     */
    public function sendAudio($number, $audioBase64)
    {
        return $this->request('POST', "/message/sendWhatsAppAudio/{$this->instanceName}", [
            'number' => $this->normalizeNumber($number),
            'audio' => $audioBase64,
        ]);
    }

    /**
     * Enviar documento
     */
    public function sendDocument($number, $url, $fileName, $caption = '')
    {
        return $this->sendMedia($number, 'document', $url, $caption, $fileName);
    }

    /**
     * Enviar imagem
     */
    public function sendImage($number, $url, $caption = '')
    {
        return $this->sendMedia($number, 'image', $url, $caption);
    }

    // =========================================
    // CHAT / CONTATOS
    // =========================================

    /**
     * Buscar chats
     */
    public function findChats()
    {
        return $this->request('GET', "/chat/findChats/{$this->instanceName}");
    }

    /**
     * Buscar mensagens de um JID
     */
    public function findMessages($remoteJid, $limit = 50)
    {
        return $this->request('POST', "/chat/findMessages/{$this->instanceName}", [
            'where' => ['key' => ['remoteJid' => $remoteJid]],
            'limit' => $limit,
        ]);
    }

    /**
     * Buscar contatos
     */
    public function findContacts()
    {
        return $this->request('GET', "/chat/findContacts/{$this->instanceName}");
    }

    /**
     * Verificar se números possuem WhatsApp
     */
    public function checkIsWhatsapp($numbers)
    {
        if (!is_array($numbers)) $numbers = [$numbers];
        return $this->request('POST', "/chat/whatsappNumbers/{$this->instanceName}", [
            'numbers' => $numbers,
        ]);
    }

    /**
     * Marcar mensagem como lida
     */
    public function markAsRead($remoteJid, $messageId)
    {
        return $this->request('PUT', "/chat/markMessageAsRead/{$this->instanceName}", [
            'readMessages' => [
                ['remoteJid' => $remoteJid, 'id' => $messageId],
            ],
        ]);
    }

    /**
     * Buscar foto de perfil
     */
    public function fetchProfilePicture($number)
    {
        return $this->request('GET', "/chat/fetchProfilePictureUrl/{$this->instanceName}", [
            'number' => $this->normalizeNumber($number),
        ]);
    }

    // =========================================
    // UTILIDADES
    // =========================================

    /**
     * Normaliza número para formato WhatsApp
     */
    public function normalizeNumber($number)
    {
        // Se já é um JID completo, retorna como está
        if (strpos($number, '@') !== false) {
            return $number;
        }

        // Remove tudo exceto números
        $phone = preg_replace('/[^0-9]/', '', $number);

        // Adiciona DDI Brasil se não tiver
        if (!str_starts_with($phone, '55') && strlen($phone) <= 11) {
            $phone = '55' . $phone;
        }

        return $phone;
    }

    /**
     * Normaliza JID
     */
    public function normalizeJid($jid)
    {
        $numberOnly = preg_replace('/@(s\.whatsapp\.net|g\.us|lid|c\.us|broadcast)$/', '', $jid);
        if (strpos($jid, '@g.us') !== false) {
            return $numberOnly . '@g.us';
        }
        return $numberOnly . '@s.whatsapp.net';
    }

    /**
     * Extrai número de um JID
     */
    public function extractPhone($jid)
    {
        return preg_replace('/@.*$/', '', $jid);
    }

    // =========================================
    // HTTP REQUEST (interno)
    // =========================================

    /**
     * Realiza requisição HTTP para a Evolution API
     */
    private function request($method, $endpoint, $data = null)
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'apikey: ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
                if ($data) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => true, 'message' => 'cURL Error: ' . $error, 'http_code' => 0];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            return [
                'error' => true,
                'message' => $decoded['message'] ?? 'HTTP Error ' . $httpCode,
                'http_code' => $httpCode,
                'response' => $decoded,
            ];
        }

        return $decoded ?? ['raw' => $response];
    }

    /**
     * Factory: cria instância da API a partir de dados do banco
     */
    public static function fromInstance($instanceId)
    {
        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE id = ?", [$instanceId]);

        if (!$instance) return null;

        return new self($instance['api_url'], $instance['api_key'], $instance['instance_name']);
    }

    /**
     * Factory: retorna API da instância padrão
     */
    public static function getDefault()
    {
        $db = Database::getInstance();
        $instance = $db->fetch("SELECT * FROM whatsapp_instances WHERE is_default = 1 LIMIT 1");

        if ($instance) {
            return new self($instance['api_url'], $instance['api_key'], $instance['instance_name']);
        }

        // Fallback: usar configurações globais
        return new self();
    }
}
