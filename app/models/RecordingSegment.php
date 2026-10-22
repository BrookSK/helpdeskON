<?php

/**
 * Segmentos só de áudio de uma gravação (tabela video_recording_segments) e a
 * fila de transcrição no servidor que o cron transcribeRecordings consome.
 *
 * Os arquivos ficam fora de public/, em storage/recording-audio/<recToken>/.
 * O caminho gravado no banco é relativo a storage/ (ver absolutePath).
 * As decisões de estado ficam em TranscriptionQueueRules.
 */
class RecordingSegment
{
    private $db;

    /** @var string Raiz dos arquivos (storage/ em produção; temp nos testes). */
    private $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->db = Database::getInstance();
        $this->storageRoot = rtrim($storageRoot ?? BASE_PATH . '/storage', '/\\');
    }

    public function absolutePath(string $relative): string
    {
        return $this->storageRoot . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }

    /**
     * Liga os segmentos de áudio da sessão à gravação publicada: move cada
     * arquivo para storage/recording-audio/<recToken>/, cria a linha do segmento
     * (idempotente por recording_id + seq) e coloca a gravação na fila.
     * Devolve quantos segmentos novos foram ligados.
     */
    public function attachFromSession(RecordingSessionStore $store, int $recordingId, string $recToken): int
    {
        if (!preg_match('/^[A-Za-z0-9]+$/', $recToken)) {
            throw new InvalidArgumentException('Token de gravação inválido.');
        }
        $relDir = 'recording-audio/' . $recToken;
        $absDir = $this->absolutePath($relDir);
        $attached = 0;

        foreach ($store->audioSegments() as $seg) {
            $file = basename($seg['path']);
            $rel = $relDir . '/' . $file;
            $dest = $this->absolutePath($rel);
            if (is_file($seg['path'])) {
                if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                    throw new RuntimeException('Não foi possível criar o diretório dos segmentos de áudio.');
                }
                if (!@rename($seg['path'], $dest)) {
                    if (!@copy($seg['path'], $dest)) continue;
                    @unlink($seg['path']);
                }
            }
            if (!is_file($dest)) continue;

            $stmt = $this->db->query(
                "INSERT IGNORE INTO video_recording_segments
                    (recording_id, seq, start_sec, file_path, file_size, mime_type, sha256, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                [$recordingId, $seg['seq'], $seg['start_sec'], $rel, $seg['size'], $seg['mime'], $seg['sha256']]
            );
            if ($stmt->rowCount() > 0) $attached++;
        }

        if ($attached > 0) {
            // Gravação já transcrita fica como está: o segmento atrasado não refaz a minuta.
            $this->db->query(
                "UPDATE video_recordings SET transcribe_status = 'queued', transcribe_error = NULL
                 WHERE id = ? AND transcribe_status IN ('none','queued','error')",
                [$recordingId]
            );
        }
        return $attached;
    }

    public function listByRecording(int $recordingId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM video_recording_segments WHERE recording_id = ? ORDER BY seq",
            [$recordingId]
        );
    }

    /** Devolve para a fila os segmentos presos em 'processing' além do prazo. */
    public function releaseStale(int $minutes = TranscriptionQueueRules::STALE_LOCK_MINUTES): int
    {
        return $this->db->query(
            "UPDATE video_recording_segments SET status = 'pending', locked_at = NULL
             WHERE status = 'processing' AND (locked_at IS NULL OR locked_at < ?)",
            [date('Y-m-d H:i:s', time() - $minutes * 60)]
        )->rowCount();
    }

    /**
     * Reserva até $limit segmentos pendentes, um por vez. A reserva só vale se
     * o UPDATE condicional afetou a linha: duas execuções simultâneas nunca
     * pegam o mesmo segmento.
     */
    public function reserve(int $limit = TranscriptionQueueRules::BATCH_SIZE): array
    {
        $reserved = [];
        $skip = [];
        while (count($reserved) < $limit) {
            $params = [];
            $notIn = '';
            if ($skip) {
                $notIn = ' AND id NOT IN (' . implode(',', array_fill(0, count($skip), '?')) . ')';
                $params = $skip;
            }
            $row = $this->db->fetch(
                "SELECT id FROM video_recording_segments WHERE status = 'pending'{$notIn} ORDER BY recording_id, seq LIMIT 1",
                $params
            );
            if (!$row) break;
            $id = (int)$row['id'];
            $skip[] = $id;
            $ok = $this->db->query(
                "UPDATE video_recording_segments SET status = 'processing', locked_at = ?, attempts = attempts + 1
                 WHERE id = ? AND status = 'pending'",
                [date('Y-m-d H:i:s'), $id]
            )->rowCount();
            if ($ok === 1) {
                $reserved[] = $this->db->fetch("SELECT * FROM video_recording_segments WHERE id = ?", [$id]);
            }
        }
        return $reserved;
    }

    public function markDone(int $id, string $text, array $segments): void
    {
        $this->db->query(
            "UPDATE video_recording_segments
             SET status = 'done', locked_at = NULL, text = ?, segments_json = ?, last_error = NULL
             WHERE id = ?",
            [$text, json_encode($segments, JSON_UNESCAPED_UNICODE), $id]
        );
    }

    /** Registra a falha: volta para a fila ou fica em 'error' após o limite. */
    public function markFailed(int $id, string $error): string
    {
        $row = $this->db->fetch("SELECT attempts FROM video_recording_segments WHERE id = ?", [$id]);
        $status = TranscriptionQueueRules::statusAfterFailure((int)($row['attempts'] ?? 0));
        $this->db->query(
            "UPDATE video_recording_segments SET status = ?, locked_at = NULL, last_error = ? WHERE id = ?",
            [$status, TranscriptionQueueRules::truncateError($error), $id]
        );
        return $status;
    }

    /** Estado da transcrição da gravação calculado a partir dos segmentos. */
    public function recordingStatus(int $recordingId): string
    {
        $rows = $this->db->fetchAll("SELECT status FROM video_recording_segments WHERE recording_id = ?", [$recordingId]);
        return TranscriptionQueueRules::recordingStatus(array_column($rows, 'status'));
    }
}
