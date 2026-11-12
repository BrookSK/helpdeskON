<?php

/**
 * Estado da transcrição exposto pelo link público da gravação
 * (/videocall/shareStatus/<token>). Devolve só o que a página pública já
 * mostra: a transcrição quando pronta e a minuta só depois de enviada ao
 * cliente. Nunca devolve rascunho da minuta nem detalhe interno do erro.
 */
class RecordingShareRules
{
    public const ERROR_MESSAGE = 'A transcrição falhou.';

    /** @return array{status:string,transcript_json:?array,minutes_structured:?array,error_message:?string} */
    public static function publicStatus(array $rec): array
    {
        $status = (string)($rec['transcribe_status'] ?? 'none');
        $transcript = null;
        if ($status === 'done' && !empty($rec['transcript_json'])) {
            $decoded = json_decode((string)$rec['transcript_json'], true);
            $transcript = is_array($decoded) ? $decoded : null;
        }
        $minutes = null;
        if ($status === 'done' && !empty($rec['minutes_sent_at']) && !empty($rec['summary'])) {
            $decoded = json_decode((string)$rec['summary'], true);
            $minutes = (is_array($decoded) && isset($decoded['resumo'])) ? $decoded : null;
        }
        return [
            'status' => $status,
            'transcript_json' => $transcript,
            'minutes_structured' => $minutes,
            'error_message' => $status === 'error' ? self::ERROR_MESSAGE : null,
        ];
    }
}
