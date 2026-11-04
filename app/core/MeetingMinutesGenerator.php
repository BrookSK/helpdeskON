<?php

/**
 * Gera a ata ESTRUTURADA (JSON salvo em video_recordings.summary) a partir da
 * transcrição inteira, sem corte. Acima de MeetingMinutesRules::SPLIT_LIMIT a
 * transcrição é dividida em partes: cada parte vira notas e a ata final sai
 * das notas. O cliente de IA é injetado (OpenAiClient em produção).
 */
class MeetingMinutesGenerator
{
    private $ai;

    /** @param object $ai objeto com isConfigured() e chat($messages, $options) */
    public function __construct($ai)
    {
        $this->ai = $ai;
    }

    /**
     * @param array $ctx contexto da reunião: title, meeting_type, meeting_at, participants
     * @return string JSON normalizado da ata, ou '' se a IA falhou
     */
    public function generate(string $transcript, array $ctx = []): string
    {
        if (!$this->ai->isConfigured() || trim($transcript) === '') return '';

        $parts = MeetingMinutesRules::splitTranscript($transcript);
        $label = 'Transcrição da reunião (formato [mm:ss] texto)';
        $body = $transcript;
        if (count($parts) > 1) {
            $notes = [];
            foreach ($parts as $i => $part) {
                $res = $this->ai->chat([
                    ['role' => 'system', 'content' => MeetingMinutesRules::notesSystemPrompt()],
                    ['role' => 'user', 'content' => 'Parte ' . ($i + 1) . ' de ' . count($parts) . ":\n\n" . $part],
                ], ['model' => 'gpt-4o-mini', 'temperature' => 0.2, 'max_tokens' => 3000, 'timeout' => 120]);
                if (empty($res['success'])) return '';
                $notes[] = '### Parte ' . ($i + 1) . "\n" . trim((string)$res['content']);
            }
            $label = 'Notas da reunião, em ordem, feitas a partir da transcrição inteira';
            $body = implode("\n\n", $notes);
        }

        $res = $this->ai->chat([
            ['role' => 'system', 'content' => MeetingMinutesRules::structuredSystemPrompt()],
            ['role' => 'user', 'content' => $this->header($ctx) . $label . ":\n\n" . $body],
        ], [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'max_tokens' => 3000,
            'response_format' => ['type' => 'json_object'],
            'timeout' => 120,
        ]);
        if (empty($res['success'])) return '';

        $data = MeetingMinutesRules::normalizeStructured((string)($res['content'] ?? ''));
        if ($data === null) return '';
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function header(array $ctx): string
    {
        $h = '';
        if (!empty($ctx['title']))        $h .= "Reunião: {$ctx['title']}\n";
        if (!empty($ctx['meeting_type'])) $h .= "Tipo: {$ctx['meeting_type']}\n";
        if (!empty($ctx['meeting_at']))   $h .= "Data/hora: {$ctx['meeting_at']}\n";
        if (!empty($ctx['participants'])) $h .= "Participantes: {$ctx['participants']}\n";
        return $h === '' ? '' : $h . "\n";
    }
}
