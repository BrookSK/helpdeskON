<?php

/**
 * Gera a ata ESTRUTURADA (JSON salvo em video_recordings.summary) a partir da
 * transcrição inteira, sem corte. Acima de MeetingMinutesRules::SPLIT_LIMIT a
 * transcrição é dividida em partes: cada parte vira notas e a ata final sai
 * das notas. O cliente de IA é injetado (OpenAiClient em produção).
 */
class MeetingMinutesGenerator
{
    /**
     * Teto de saída da ata final. Um JSON truncado vira ata vazia, então há folga
     * para a ata detalhada (ações com responsável e prazo, pontos em frases).
     * O gpt-4o-mini aceita até 16.384 tokens de saída; o teto só limita, não é cobrado se não usado.
     */
    public const MAX_TOKENS = 8000;

    /** Teto de saída das notas de cada parte de uma transcrição longa. */
    public const NOTES_MAX_TOKENS = 6000;

    private const MODEL = 'gpt-4o-mini';

    private $ai;

    /** @param object $ai objeto com isConfigured() e chat($messages, $options) */
    public function __construct($ai)
    {
        $this->ai = $ai;
    }

    /**
     * @param array $ctx contexto da reunião: title, meeting_type, meeting_at,
     *                   participants (nossa equipe), organizer (nossa equipe), guests (cliente)
     * @return string JSON normalizado da ata, ou '' se a IA falhou
     */
    public function generate(string $transcript, array $ctx = []): string
    {
        if (!$this->ai->isConfigured() || trim($transcript) === '') return '';

        $header = $this->header($ctx);
        $parts = MeetingMinutesRules::splitTranscript($transcript);
        $label = 'Transcrição da reunião (formato [mm:ss] texto, ou [mm:ss] Nome: texto quando se sabe quem falou)';
        $body = $transcript;
        if (count($parts) > 1) {
            $notes = [];
            foreach ($parts as $i => $part) {
                $res = $this->ai->chat([
                    ['role' => 'system', 'content' => MeetingMinutesRules::notesSystemPrompt()],
                    ['role' => 'user', 'content' => $header . 'Parte ' . ($i + 1) . ' de ' . count($parts) . ":\n\n" . $part],
                ], ['model' => self::MODEL, 'temperature' => 0.2, 'max_tokens' => self::NOTES_MAX_TOKENS, 'timeout' => 120]);
                if (empty($res['success'])) return '';
                $notes[] = '### Parte ' . ($i + 1) . "\n" . trim((string)$res['content']);
            }
            $label = 'Notas da reunião, em ordem, feitas a partir da transcrição inteira';
            $body = implode("\n\n", $notes);
        }

        $messages = [
            ['role' => 'system', 'content' => MeetingMinutesRules::structuredSystemPrompt()],
            ['role' => 'user', 'content' => $header . $label . ":\n\n" . $body],
        ];
        $res = $this->finalChat($messages);
        // Resposta cortada no teto de tokens chega como JSON incompleto: tenta uma
        // vez pedindo uma versão mais curta, sem perder as ações pós-reunião.
        if (!empty($res['success']) && $this->truncated($res)) {
            $messages[0]['content'] .= ' A resposta anterior passou do limite de tamanho. Encurte os itens de '
                . '"topicos", "necessidades" e "escopo", mas mantenha todas as ações de "proximos_passos" e todas as decisões.';
            $res = $this->finalChat($messages);
        }
        if (empty($res['success'])) return '';

        $data = MeetingMinutesRules::normalizeStructured((string)($res['content'] ?? ''));
        if ($data === null) return '';
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function finalChat(array $messages): array
    {
        return $this->ai->chat($messages, [
            'model' => self::MODEL,
            'temperature' => 0.3,
            'max_tokens' => self::MAX_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'timeout' => 180,
        ]);
    }

    /** A IA parou no teto de tokens (finish_reason "length" da OpenAI)? */
    private function truncated(array $res): bool
    {
        return ($res['raw']['choices'][0]['finish_reason'] ?? null) === 'length';
    }

    /**
     * Cabeçalho de contexto. Separa a nossa equipe (organizador e participantes
     * internos) dos convidados externos (cliente) para a IA atribuir cada ação
     * ao lado certo.
     */
    private function header(array $ctx): string
    {
        $h = '';
        if (!empty($ctx['title']))        $h .= "Reunião: {$ctx['title']}\n";
        if (!empty($ctx['meeting_type'])) $h .= "Tipo: {$ctx['meeting_type']}\n";
        if (!empty($ctx['meeting_at']))   $h .= "Data/hora: {$ctx['meeting_at']}\n";
        if (!empty($ctx['organizer']))    $h .= "Responsável pela reunião (nossa equipe): {$ctx['organizer']}\n";
        if (!empty($ctx['participants'])) $h .= "Participantes da nossa equipe: {$ctx['participants']}\n";
        if (!empty($ctx['guests']))       $h .= "Convidados externos (cliente): {$ctx['guests']}\n";
        return $h === '' ? '' : $h . "\n";
    }
}
