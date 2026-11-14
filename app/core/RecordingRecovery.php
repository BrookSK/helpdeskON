<?php

/**
 * Recuperação de uma gravação já publicada que ficou sem duração e sem
 * transcrição (usada por recover_recording.php).
 *
 * Caso de 13/11/2026: a reunião de ~2h foi publicada a partir da sessão
 * abandonada num servidor sem FFmpeg. O WebM montado não tinha a duração no
 * cabeçalho (o player mostrava 1:53 e não avançava) e os segmentos de áudio
 * da fila não tinham subido. Com um FFmpeg instalado à mão, a recuperação:
 *   1. remuxa o vídeo (timeline e duração corretas);
 *   2. extrai o áudio em trechos de 1 minuto e os coloca na MESMA fila do cron
 *      (video_recording_segments), que transcreve e gera a minuta sozinho.
 *
 * A parte pura (lista de trechos do FFmpeg, comando, token) é estática e
 * testável sem binário; enqueueSegments usa o banco.
 */
class RecordingRecovery
{
    /** Duração de cada trecho de áudio (igual aos segmentos gravados no navegador). */
    public const SEGMENT_SECONDS = 60;

    /**
     * Formatos tentados em ordem. Opus/WebM é o mesmo dos segmentos gravados no
     * navegador; MP3 fica de reserva se o FFmpeg não tiver libopus. Os dois são
     * aceitos pelo Whisper e 1 minuto fica muito abaixo do limite de 25 MB.
     */
    public const AUDIO_FORMATS = [
        ['codec' => 'libopus', 'bitrate' => '24k', 'format' => 'webm', 'ext' => 'webm', 'mime' => 'audio/webm'],
        ['codec' => 'libmp3lame', 'bitrate' => '32k', 'format' => 'mp3', 'ext' => 'mp3', 'mime' => 'audio/mpeg'],
    ];

    private $db;
    private $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $this->db = Database::getInstance();
        $this->storageRoot = rtrim($storageRoot ?? BASE_PATH . '/storage', '/\\');
    }

    // =====================================================================
    // Parte pura
    // =====================================================================

    /**
     * Token da gravação a partir do que o usuário colou: o token, o nome do
     * arquivo (rec_141_<token>.webm) ou o link /videocall/recording/<token>.
     */
    public static function tokenFromArg(string $arg): ?string
    {
        $arg = trim($arg);
        if (preg_match('~(?:^|/)rec_\d+_([A-Za-z0-9]{8,64})\.webm$~', $arg, $m)) return $m[1];
        if (preg_match('~/videocall/recording/([A-Za-z0-9]{8,64})/?$~', $arg, $m)) return $m[1];
        if (preg_match('~^[A-Za-z0-9]{8,64}$~', $arg)) return $arg;
        return null;
    }

    /**
     * Lê a lista CSV do muxer segment do FFmpeg ("arquivo,inicio,fim" por
     * linha) e devolve [{file,start,end}] em ordem de início. Ignora linhas
     * malformadas e nomes que não sejam seg_NNNNNN.(webm|mp3), para nada fora
     * do diretório entrar na fila.
     */
    public static function parseSegmentList(string $csv): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $csv) as $line) {
            $cols = str_getcsv(trim($line), ',', '"', '\\');
            if (count($cols) < 3) continue;
            [$file, $start, $end] = $cols;
            if (!preg_match('/^seg_\d{6}\.(webm|mp3)$/', $file)) continue;
            if (!is_numeric($start) || !is_numeric($end)) continue;
            $start = max(0.0, (float)$start);
            $end = (float)$end;
            if ($end <= $start) continue;
            $out[] = ['file' => $file, 'start' => round($start, 3), 'end' => round($end, 3)];
        }
        usort($out, static function ($a, $b) { return $a['start'] <=> $b['start']; });
        return $out;
    }

    /** Comando que extrai o áudio em trechos de SEGMENT_SECONDS para $dir. */
    public static function extractAudioCommand(string $ffmpeg, string $src, string $dir, array $fmt): string
    {
        $dir = rtrim($dir, '/\\');
        return escapeshellarg($ffmpeg)
            . ' -nostdin -hide_banner -loglevel error -y'
            . ' -i ' . escapeshellarg($src)
            . ' -map 0:a:0 -vn -ac 1 -ar 16000'
            . ' -c:a ' . $fmt['codec'] . ' -b:a ' . $fmt['bitrate']
            . ' -f segment -segment_time ' . self::SEGMENT_SECONDS
            . ' -segment_format ' . $fmt['format']
            . ' -segment_start_number 1 -reset_timestamps 1'
            . ' -segment_list ' . escapeshellarg($dir . '/list.csv') . ' -segment_list_type csv'
            // O molde fica fora das aspas: no Windows o escapeshellarg troca "%"
            // por espaço. "seg_%06d.<ext>" é fixo e seguro nos dois shells.
            . ' ' . escapeshellarg($dir) . '/seg_%06d.' . $fmt['ext'];
    }

    /** "2:01:33" a partir de segundos. */
    public static function formatDuration(float $sec): string
    {
        $s = (int)round($sec);
        return sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    // =====================================================================
    // Parte com banco
    // =====================================================================

    public function findRecording(string $token): ?array
    {
        $row = $this->db->fetch("SELECT * FROM video_recordings WHERE token = ?", [$token]);
        return $row ?: null;
    }

    public function segmentCount(int $recordingId): int
    {
        $r = $this->db->fetch("SELECT COUNT(*) AS n FROM video_recording_segments WHERE recording_id = ?", [$recordingId]);
        return (int)($r['n'] ?? 0);
    }

    public function updateMedia(int $recordingId, ?float $durationSec, int $fileSize): void
    {
        $data = ['file_size' => $fileSize];
        if ($durationSec !== null && $durationSec > 0) $data['duration_sec'] = (int)round($durationSec);
        $this->db->update('video_recordings', $data, 'id = ?', [$recordingId]);
    }

    /**
     * Cria as linhas da fila para os trechos já gravados em storage/$relDir e
     * coloca a gravação em 'queued'. Idempotente (recording_id + seq). Não
     * mexe numa gravação já transcrita. Devolve quantos trechos entraram.
     */
    public function enqueueSegments(int $recordingId, string $relDir, array $list, string $mime): int
    {
        $relDir = trim(str_replace('\\', '/', $relDir), '/');
        $added = 0;
        foreach (array_values($list) as $i => $seg) {
            $rel = $relDir . '/' . $seg['file'];
            $abs = $this->storageRoot . '/' . $rel;
            if (!is_file($abs) || filesize($abs) < 1) continue;
            $stmt = $this->db->query(
                "INSERT IGNORE INTO video_recording_segments
                    (recording_id, seq, start_sec, file_path, file_size, mime_type, sha256, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                [$recordingId, $i + 1, $seg['start'], $rel, filesize($abs), $mime, hash_file('sha256', $abs)]
            );
            if ($stmt->rowCount() > 0) $added++;
        }
        if ($added > 0) {
            $this->db->query(
                "UPDATE video_recordings SET transcribe_status = 'queued', transcribe_error = NULL
                 WHERE id = ? AND transcribe_status <> 'done'",
                [$recordingId]
            );
        }
        return $added;
    }
}
