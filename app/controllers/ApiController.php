<?php

class ApiController extends Controller
{
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
