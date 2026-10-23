<?php

/**
 * Regras PURAS da fila de transcrição no servidor (segmentos só de áudio).
 *
 * O cron transcribeRecordings reserva segmentos, envia ao Whisper e, quando
 * todos os segmentos de uma gravação terminam, junta o texto. Aqui ficam as
 * decisões sem banco nem API: estado depois de uma falha, reserva presa, estado
 * da gravação a partir dos segmentos e junção com o deslocamento de tempo.
 */
class TranscriptionQueueRules
{
    /** Tentativas por segmento antes de marcar a falha definitiva. */
    public const MAX_ATTEMPTS = 3;

    /** Minutos até uma reserva "processing" ser considerada presa. */
    public const STALE_LOCK_MINUTES = 10;

    /**
     * Segmentos enviados à API por execução do cron (segmentos de 1 minuto).
     * O lote é reservado de uma vez: precisa terminar bem antes de
     * STALE_LOCK_MINUTES, senão outra execução pegaria o mesmo segmento.
     */
    public const BATCH_SIZE = 6;

    /** Limite de cada chamada de transcrição de um segmento, em segundos. */
    public const SEGMENT_TIMEOUT_SEC = 90;

    /** Estado do segmento depois de uma falha, dado o total de tentativas já feitas. */
    public static function statusAfterFailure(int $attempts): string
    {
        return $attempts >= self::MAX_ATTEMPTS ? 'error' : 'pending';
    }

    /** A reserva feita em $lockedAt já passou do prazo e pode voltar para a fila? */
    public static function isStaleLock(?string $lockedAt, int $now, int $staleMinutes = self::STALE_LOCK_MINUTES): bool
    {
        if ($lockedAt === null || $lockedAt === '') return true;
        $ts = strtotime($lockedAt);
        if ($ts === false) return true;
        return ($now - $ts) >= $staleMinutes * 60;
    }

    /**
     * Estado da transcrição da gravação a partir dos estados dos segmentos:
     * - sem segmentos → 'none' (fica no fluxo manual);
     * - algum pendente ou transcrevendo → 'queued' ou 'processing';
     * - algum com falha definitiva → 'error';
     * - todos concluídos → 'done'.
     *
     * @param string[] $statuses
     */
    public static function recordingStatus(array $statuses): string
    {
        if (empty($statuses)) return 'none';
        if (in_array('processing', $statuses, true)) return 'processing';
        if (in_array('pending', $statuses, true)) {
            return in_array('done', $statuses, true) || in_array('error', $statuses, true) ? 'processing' : 'queued';
        }
        if (in_array('error', $statuses, true)) return 'error';
        return 'done';
    }

    /**
     * Junta os segmentos transcritos na ordem de seq, somando o início de cada
     * segmento aos tempos dos trechos (tempo relativo ao começo da reunião).
     *
     * @param array<int,array{seq:int,start_sec:float|string,text?:string,segments?:array}> $segments
     * @return array{segments:array<int,array{start:float,end:float,text:string}>,text:string}
     */
    public static function merge(array $segments): array
    {
        usort($segments, static function ($a, $b) { return (int)$a['seq'] <=> (int)$b['seq']; });
        $out = [];
        $texts = [];
        foreach ($segments as $seg) {
            $offset = (float)($seg['start_sec'] ?? 0);
            $parts = is_array($seg['segments'] ?? null) ? $seg['segments'] : [];
            if (empty($parts) && trim((string)($seg['text'] ?? '')) !== '') {
                // Sem trechos com tempo: usa o texto inteiro no início do segmento.
                $parts = [['start' => 0, 'end' => 0, 'text' => (string)$seg['text']]];
            }
            foreach ($parts as $p) {
                $text = trim((string)($p['text'] ?? ''));
                if ($text === '') continue;
                $out[] = [
                    'start' => round((float)($p['start'] ?? 0) + $offset, 3),
                    'end' => round((float)($p['end'] ?? 0) + $offset, 3),
                    'text' => $text,
                ];
            }
            $t = trim((string)($seg['text'] ?? ''));
            if ($t !== '') $texts[] = $t;
        }
        return ['segments' => $out, 'text' => implode("\n", $texts)];
    }

    /**
     * Limpa os trechos da transcrição: remove vazios e só pontuação (silêncio
     * vira "..." no Whisper), ordena por início e tira repetições consecutivas.
     */
    public static function cleanSegments($segments): array
    {
        if (!is_array($segments)) return [];
        $clean = [];
        foreach ($segments as $s) {
            $text = trim((string)($s['text'] ?? ''));
            // remove segmentos vazios ou compostos só de pontuação/reticências
            $stripped = preg_replace('/[\p{P}\p{Z}\s]+/u', '', $text);
            if ($stripped === '' || $stripped === null) continue;
            $start = (float)($s['start'] ?? 0);
            $end = (float)($s['end'] ?? $start);
            if ($end < $start) $end = $start;
            $clean[] = ['start' => $start, 'end' => $end, 'text' => $text];
        }
        // ordena por início (e por fim como desempate)
        usort($clean, static function ($a, $b) {
            if ($a['start'] === $b['start']) return $a['end'] <=> $b['end'];
            return $a['start'] <=> $b['start'];
        });
        // remove repetições consecutivas do mesmo texto no ~mesmo tempo
        $out = [];
        foreach ($clean as $seg) {
            $prev = end($out);
            if ($prev !== false && $prev['text'] === $seg['text'] && abs($prev['start'] - $seg['start']) < 1.0) continue;
            $out[] = $seg;
        }
        return $out;
    }

    /** Texto plano com marca de tempo [mm:ss] ou [h:mm:ss], um trecho por linha. */
    public static function transcriptText(array $segments): string
    {
        $lines = [];
        foreach ($segments as $s) {
            $lines[] = '[' . self::formatTime((float)($s['start'] ?? 0)) . '] ' . trim((string)($s['text'] ?? ''));
        }
        return trim(implode("\n", $lines));
    }

    public static function formatTime(float $sec): string
    {
        $sec = max(0, (int)round($sec));
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $s = $sec % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }

    /** Mensagem de erro curta para guardar na coluna (VARCHAR 500). */
    public static function truncateError(string $msg): string
    {
        $msg = trim(preg_replace('/\s+/', ' ', $msg));
        return mb_substr($msg, 0, 500);
    }
}
