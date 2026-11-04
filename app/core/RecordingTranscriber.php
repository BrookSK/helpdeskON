<?php

/**
 * Processa a fila de transcrição no servidor (uma execução do cron).
 *
 * 1. devolve para a fila segmentos presos em "processing";
 * 2. reserva até BATCH_SIZE segmentos (reserva atômica no RecordingSegment);
 * 3. envia cada um ao Whisper e guarda o texto, ou registra a falha;
 * 4. quando todos os segmentos de uma gravação terminam, junta o texto com o
 *    deslocamento de tempo, grava a transcrição e chama $onComplete(token)
 *    uma única vez (resumo e minuta em rascunho).
 *
 * O cliente de IA é injetado (OpenAiClient em produção, um falso nos testes).
 */
class RecordingTranscriber
{
    private $segments;
    private $ai;
    private $db;
    /** @var callable|null */
    private $onComplete;

    /**
     * @param RecordingSegment $segments
     * @param object $ai objeto com isConfigured() e transcribe($path, $options)
     * @param callable|null $onComplete recebe o token da gravação transcrita
     */
    public function __construct(RecordingSegment $segments, $ai, ?callable $onComplete = null)
    {
        $this->segments = $segments;
        $this->ai = $ai;
        $this->db = Database::getInstance();
        $this->onComplete = $onComplete;
    }

    /** @return array{released:int,processed:int,failed:int,completed:int,errors:int} */
    public function run(int $limit = TranscriptionQueueRules::BATCH_SIZE): array
    {
        $stats = ['released' => 0, 'processed' => 0, 'failed' => 0, 'completed' => 0, 'errors' => 0];
        if (!$this->ai->isConfigured()) return $stats;

        $stats['released'] = $this->segments->releaseStale();
        $touched = [];
        foreach ($this->segments->reserve($limit) as $seg) {
            $recId = (int)$seg['recording_id'];
            $touched[$recId] = true;
            $this->db->query(
                "UPDATE video_recordings SET transcribe_status = 'processing' WHERE id = ? AND transcribe_status = 'queued'",
                [$recId]
            );

            $path = $this->segments->absolutePath((string)$seg['file_path']);
            // Segmento de 1 minuto responde em segundos. O limite por chamada mantém
            // o lote inteiro abaixo de STALE_LOCK_MINUTES no pior caso.
            $r = is_file($path)
                ? $this->ai->transcribe($path, ['language' => 'pt', 'verbose' => true, 'timeout' => TranscriptionQueueRules::SEGMENT_TIMEOUT_SEC])
                : ['success' => false, 'error' => 'Arquivo do segmento não encontrado.'];

            if (!empty($r['success'])) {
                $this->segments->markDone((int)$seg['id'], (string)($r['text'] ?? ''), TranscriptionQueueRules::cleanSegments($r['segments'] ?? []));
                $stats['processed']++;
            } else {
                $this->segments->markFailed((int)$seg['id'], 'Segmento ' . $seg['seq'] . ': ' . ($r['error'] ?? 'falha na transcrição'));
                $stats['failed']++;
            }
        }

        foreach (array_keys($touched) as $recId) {
            $status = $this->segments->recordingStatus($recId);
            if ($status === 'done' && $this->complete($recId)) $stats['completed']++;
            if ($status === 'error' && $this->fail($recId)) $stats['errors']++;
        }
        return $stats;
    }

    /** Junta os segmentos e grava a transcrição. Só uma execução conclui a gravação. */
    private function complete(int $recId): bool
    {
        $rows = $this->segments->listByRecording($recId);
        $parts = array_map(static function ($r) {
            return [
                'seq' => (int)$r['seq'],
                'start_sec' => (float)$r['start_sec'],
                'text' => (string)$r['text'],
                'segments' => json_decode((string)$r['segments_json'], true) ?: [],
            ];
        }, $rows);
        $merged = TranscriptionQueueRules::merge($parts);
        $segments = TranscriptionQueueRules::cleanSegments($merged['segments']);
        $transcript = TranscriptionQueueRules::transcriptText($segments);

        $ok = $this->db->query(
            "UPDATE video_recordings
             SET transcript = ?, transcript_json = ?, transcribe_status = 'done', transcribe_error = NULL, transcribed_at = ?
             WHERE id = ? AND transcribe_status <> 'done'",
            [$transcript, json_encode($segments, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), $recId]
        )->rowCount();
        if ($ok !== 1) return false;

        if ($this->onComplete && $transcript !== '') {
            $rec = $this->db->fetch("SELECT token FROM video_recordings WHERE id = ?", [$recId]);
            try { ($this->onComplete)((string)$rec['token']); } catch (\Throwable $e) {
                if (class_exists('Logger')) Logger::error('RecordingTranscriber: pós-transcrição falhou', ['rec' => $recId, 'error' => $e->getMessage()]);
            }
        }
        return true;
    }

    /** Falha definitiva: guarda o motivo do primeiro segmento com erro. */
    private function fail(int $recId): bool
    {
        $row = $this->db->fetch(
            "SELECT last_error FROM video_recording_segments WHERE recording_id = ? AND status = 'error' ORDER BY seq LIMIT 1",
            [$recId]
        );
        return $this->db->query(
            "UPDATE video_recordings SET transcribe_status = 'error', transcribe_error = ? WHERE id = ? AND transcribe_status <> 'done'",
            [TranscriptionQueueRules::truncateError((string)($row['last_error'] ?? 'Falha na transcrição.')), $recId]
        )->rowCount() === 1;
    }
}
