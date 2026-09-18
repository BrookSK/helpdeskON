<?php

/**
 * Cliente reutilizável da OpenAI (Chat Completions).
 *
 * Centraliza a chamada HTTP que hoje está duplicada em ApiController,
 * WhatsappController e MarketingController. Usa a chave de settings.openai_api_key
 * (via Config). Não guarda estado sensível.
 *
 * Uso:
 *   $ai = new OpenAiClient();
 *   if ($ai->isConfigured()) {
 *       $res = $ai->chat([
 *           ['role' => 'system', 'content' => '...'],
 *           ['role' => 'user',   'content' => '...'],
 *       ], ['model' => 'gpt-4o-mini', 'temperature' => 0.7, 'max_tokens' => 400]);
 *       // $res = ['success' => bool, 'content' => string, 'error' => string|null, 'raw' => array]
 *   }
 */
class OpenAiClient
{
    private $apiKey;
    private $endpoint = 'https://api.openai.com/v1/chat/completions';
    private $defaultModel = 'gpt-4o-mini';

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey !== null ? trim((string) $apiKey) : trim((string) Config::get('openai_api_key'));
    }

    /** Há chave configurada? */
    public function isConfigured()
    {
        return $this->apiKey !== '';
    }

    /**
     * Envia mensagens ao endpoint de chat e retorna o conteúdo textual da resposta.
     *
     * @param array $messages  [ ['role'=>'system'|'user'|'assistant', 'content'=>string], ... ]
     * @param array $options    model, temperature, max_tokens, response_format, timeout
     * @return array { success: bool, content: string, error: ?string, raw: array }
     */
    public function chat(array $messages, array $options = [])
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'content' => '', 'error' => 'Chave da API OpenAI não configurada.', 'raw' => []];
        }

        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $messages,
        ];
        if (isset($options['temperature'])) $payload['temperature'] = (float) $options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int) $options['max_tokens'];
        if (isset($options['response_format'])) $payload['response_format'] = $options['response_format'];

        $timeout = (int) ($options['timeout'] ?? 30);

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'content' => '', 'error' => 'Falha de conexão com a OpenAI: ' . $curlErr, 'raw' => []];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'content' => '', 'error' => 'Resposta inválida da OpenAI.', 'raw' => []];
        }

        if ($httpCode < 200 || $httpCode >= 300 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('Erro HTTP ' . $httpCode);
            return ['success' => false, 'content' => '', 'error' => $msg, 'raw' => $data];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'content' => trim((string) $content), 'error' => null, 'raw' => $data];
    }

    /**
     * Transcreve um arquivo de áudio/vídeo usando o Whisper da OpenAI
     * (endpoint /v1/audio/transcriptions). Aceita webm/mp4/mp3/wav etc.
     *
     * @param string $filePath Caminho absoluto do arquivo local.
     * @param array  $options   model (default whisper-1), language, timeout
     * @return array { success: bool, text: string, error: ?string }
     */
    public function transcribe($filePath, array $options = [])
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'text' => '', 'error' => 'Chave da API OpenAI não configurada.'];
        }
        if (!is_file($filePath)) {
            return ['success' => false, 'text' => '', 'error' => 'Arquivo não encontrado para transcrição.'];
        }

        $model = $options['model'] ?? 'whisper-1';
        $timeout = (int) ($options['timeout'] ?? 300);
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $det = finfo_file($fi, $filePath);
            finfo_close($fi);
            if ($det) $mime = $det;
        }

        $post = [
            'model' => $model,
            'file' => new \CURLFile($filePath, $mime, basename($filePath)),
        ];
        if (!empty($options['language'])) $post['language'] = $options['language'];
        // verbose_json devolve segmentos com marca de tempo (start/end).
        if (!empty($options['verbose'])) {
            $post['response_format'] = 'verbose_json';
            $post['timestamp_granularities[]'] = 'segment';
        }

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => $post, // multipart/form-data automático
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'text' => '', 'error' => 'Falha de conexão com a OpenAI: ' . $curlErr];
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'text' => '', 'error' => 'Resposta inválida da OpenAI.'];
        }
        if ($httpCode < 200 || $httpCode >= 300 || isset($data['error'])) {
            $msg = $data['error']['message'] ?? ('Erro HTTP ' . $httpCode);
            return ['success' => false, 'text' => '', 'error' => $msg];
        }
        $segments = [];
        if (!empty($data['segments']) && is_array($data['segments'])) {
            foreach ($data['segments'] as $s) {
                $segments[] = [
                    'start' => (float) ($s['start'] ?? 0),
                    'end' => (float) ($s['end'] ?? 0),
                    'text' => trim((string) ($s['text'] ?? '')),
                ];
            }
        }
        return ['success' => true, 'text' => trim((string) ($data['text'] ?? '')), 'segments' => $segments, 'error' => null];
    }
}
