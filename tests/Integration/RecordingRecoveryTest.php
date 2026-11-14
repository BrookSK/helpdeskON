<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use RecordingRecovery;
use RecordingSegment;
use RecordingTranscriber;
use Database;

/**
 * Recuperação de gravação publicada sem duração e sem transcrição
 * (recover_recording.php + RecordingRecovery), contra helpdesk_on_test.
 *
 * O teste com FFmpeg de verdade só roda com FFMPEG_TEST_BIN apontando para o
 * binário; sem ele é pulado (o FFmpeg não faz parte do ambiente de testes).
 */
final class RecordingRecoveryTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private int $ownerId;
    private int $roomId;
    private string $storage;
    /** @var string[] arquivos/pastas reais criados (public/uploads, storage/) */
    private array $cleanup = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        $u = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_rec_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $token = $this->rooms->create([
            'title' => 'Sala recuperação', 'created_by' => $this->ownerId, 'max_participants' => 8,
            'allow_recording' => 1, 'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->rooms->findByToken($token)['id'];
        $this->storage = sys_get_temp_dir() . '/rrec_' . bin2hex(random_bytes(6));
        @mkdir($this->storage, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE s FROM video_recording_segments s JOIN video_recordings r ON r.id = s.recording_id WHERE r.room_id = ?", [$this->roomId]);
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('users', 'id = ?', [$this->ownerId]);
        $this->rrmdir($this->storage);
        foreach ($this->cleanup as $p) is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        @rmdir(BASE_PATH . '/storage/recovery-backup'); // só se ficou vazia
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function gravacao(string $status = 'none'): array
    {
        $tok = $this->rooms->generateToken();
        $this->rooms->addRecording([
            'room_id' => $this->roomId, 'token' => $tok,
            'file_path' => 'recordings/rec_' . $this->roomId . '_' . $tok . '.webm',
            'mime_type' => 'video/webm', 'recorded_by' => $this->ownerId,
        ]);
        if ($status !== 'none') {
            $this->db->update('video_recordings', ['transcribe_status' => $status], 'token = ?', [$tok]);
        }
        return $this->rooms->findRecordingByToken($tok);
    }

    /** Grava N trechos falsos em storage/recording-audio/<token>/ e devolve a lista. */
    private function trechos(string $tok, int $n): array
    {
        $dir = $this->storage . '/recording-audio/' . $tok;
        @mkdir($dir, 0775, true);
        $list = [];
        for ($i = 1; $i <= $n; $i++) {
            $file = sprintf('seg_%06d.webm', $i);
            file_put_contents($dir . '/' . $file, "audio{$i}");
            $list[] = ['file' => $file, 'start' => ($i - 1) * 60.0, 'end' => $i * 60.0];
        }
        return $list;
    }

    public function testTrechosEntramNaFilaComInicioEHashSemDuplicar(): void
    {
        $rec = $this->gravacao();
        $list = $this->trechos($rec['token'], 3);
        $r = new RecordingRecovery($this->storage);

        $this->assertSame(3, $r->enqueueSegments((int)$rec['id'], 'recording-audio/' . $rec['token'], $list, 'audio/webm'));
        $this->assertSame(0, $r->enqueueSegments((int)$rec['id'], 'recording-audio/' . $rec['token'], $list, 'audio/webm'));
        $this->assertSame(3, $r->segmentCount((int)$rec['id']));

        $rows = (new RecordingSegment($this->storage))->listByRecording((int)$rec['id']);
        $this->assertSame([1, 2, 3], array_map('intval', array_column($rows, 'seq')));
        $this->assertSame(['0.000', '60.000', '120.000'], array_column($rows, 'start_sec'));
        $this->assertSame(hash('sha256', 'audio2'), $rows[1]['sha256']);
        $this->assertSame('audio/webm', $rows[0]['mime_type']);
        $this->assertSame('pending', $rows[0]['status']);
        $this->assertSame('queued', $this->rooms->findRecordingByToken($rec['token'])['transcribe_status']);
    }

    public function testTrechoSemArquivoFicaDeFora(): void
    {
        $rec = $this->gravacao();
        $list = $this->trechos($rec['token'], 2);
        unlink($this->storage . '/recording-audio/' . $rec['token'] . '/seg_000002.webm');
        $r = new RecordingRecovery($this->storage);
        $this->assertSame(1, $r->enqueueSegments((int)$rec['id'], 'recording-audio/' . $rec['token'], $list, 'audio/webm'));
    }

    public function testGravacaoJaTranscritaNaoVoltaParaAFila(): void
    {
        $rec = $this->gravacao('done');
        $list = $this->trechos($rec['token'], 1);
        (new RecordingRecovery($this->storage))->enqueueSegments((int)$rec['id'], 'recording-audio/' . $rec['token'], $list, 'audio/webm');
        $this->assertSame('done', $this->rooms->findRecordingByToken($rec['token'])['transcribe_status']);
    }

    public function testFilaDoCronTranscreveOsTrechosComOTempoDaReuniao(): void
    {
        $rec = $this->gravacao('error');
        $list = $this->trechos($rec['token'], 3);
        (new RecordingRecovery($this->storage))->enqueueSegments((int)$rec['id'], 'recording-audio/' . $rec['token'], $list, 'audio/webm');

        $ai = new class {
            public array $calls = [];
            public function isConfigured(): bool { return true; }
            public function transcribe($path, array $o = []): array
            {
                $f = basename($path);
                $this->calls[] = $f;
                return ['success' => true, 'text' => "fala {$f}", 'segments' => [['start' => 5, 'end' => 9, 'text' => "fala {$f}"]]];
            }
        };
        $done = [];
        $t = new RecordingTranscriber(new RecordingSegment($this->storage), $ai, function ($tok) use (&$done) { $done[] = $tok; });
        $stats = $t->run();

        $this->assertSame(3, $stats['processed']);
        $this->assertSame(1, $stats['completed']);
        $after = $this->rooms->findRecordingByToken($rec['token']);
        $this->assertSame('done', $after['transcribe_status']);
        $this->assertStringContainsString('[02:05] fala seg_000003.webm', $after['transcript']);
        $this->assertSame([$rec['token']], $done);
    }

    // ------------------------------------------------------------ script CLI

    /** @return array{0:int,1:string} código de saída, stdout+stderr */
    private function runScript(array $args): array
    {
        $env = getenv();
        $env['APP_ENV'] = 'testing';
        $proc = proc_open(array_merge([PHP_BINARY, 'recover_recording.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
        $this->assertIsResource($proc);
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $out];
    }

    public function testScriptSemArgumentosMostraOUso(): void
    {
        [$code, $out] = $this->runScript([]);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('Uso: php recover_recording.php', $out);
    }

    public function testScriptRecusaGravacaoEFfmpegInvalidosSemMexerEmNada(): void
    {
        [$code, $out] = $this->runScript(['localhost', '../../etc/passwd', 'ffmpeg']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('Não reconheci a gravação', $out);

        $rec = $this->gravacao();
        [$code, $out] = $this->runScript(['localhost', $rec['token'], BASE_PATH . '/nao-existe/ffmpeg']);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('FFmpeg não encontrado', $out);
        $this->assertSame(0, (new RecordingRecovery())->segmentCount((int)$rec['id']));
    }

    /** Fluxo completo com FFmpeg real: vídeo de 2min30 vira 3 trechos na fila. */
    public function testScriptComFfmpegRealArrumaOVideoEPoeOAudioNaFila(): void
    {
        $ff = (string) getenv('FFMPEG_TEST_BIN');
        if ($ff === '' || !is_file($ff)) $this->markTestSkipped('FFMPEG_TEST_BIN não definido.');

        $rec = $this->gravacao();
        $video = BASE_PATH . '/public/uploads/' . $rec['file_path'];
        $audioDir = BASE_PATH . '/storage/recording-audio/' . $rec['token'];
        $backup = BASE_PATH . '/storage/recovery-backup/' . basename($video);
        $this->cleanup = [$video, $video . '.recover.webm', $audioDir, $audioDir . '.tmp', $backup];

        // Reunião sintética de 150 s com vídeo VP8 e áudio Opus, escrita como
        // fluxo (saída não pesquisável, sem índice nem duração gravados no
        // cabeçalho), como o WebM que o MediaRecorder do navegador produz.
        $gen = escapeshellarg($ff) . ' -nostdin -hide_banner -loglevel error -y'
            . ' -f lavfi -i testsrc=size=320x240:rate=10 -f lavfi -i sine=frequency=440:sample_rate=48000'
            . ' -t 150 -c:v libvpx -b:v 200k -c:a libopus -f webm - > ' . escapeshellarg($video);
        shell_exec($gen);
        $this->assertFileExists($video, 'o FFmpeg de teste não gerou o vídeo');
        $this->assertGreaterThan(0, filesize($video));

        [$code, $out] = $this->runScript(['localhost', basename($video), $ff]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Vídeo arrumado: 0:02:30', $out);
        $this->assertStringContainsString('3 trechos na fila', $out);

        $after = $this->rooms->findRecordingByToken($rec['token']);
        $this->assertSame('queued', $after['transcribe_status']);
        $this->assertEqualsWithDelta(150, (int)$after['duration_sec'], 1);
        $this->assertSame(filesize($video), (int)$after['file_size']);
        $this->assertFileExists($backup, 'o original precisa ficar guardado');

        $rows = (new RecordingSegment())->listByRecording((int)$rec['id']);
        $this->assertSame([1, 2, 3], array_map('intval', array_column($rows, 'seq')));
        $this->assertEqualsWithDelta(60.0, (float)$rows[1]['start_sec'], 0.1);
        $this->assertEqualsWithDelta(120.0, (float)$rows[2]['start_sec'], 0.1);
        foreach ($rows as $r) {
            $abs = BASE_PATH . '/storage/' . $r['file_path'];
            $this->assertFileExists($abs);
            $this->assertLessThan(25 * 1024 * 1024, filesize($abs));
            $this->assertSame(hash_file('sha256', $abs), $r['sha256']);
        }
        $this->assertFileDoesNotExist($audioDir . '/list.csv');

        // Rodar de novo não refaz nem duplica.
        [$code, $out] = $this->runScript(['localhost', $rec['token'], $ff]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Vídeo já arrumado antes', $out);
        $this->assertStringContainsString('Áudio já está na fila (3 trechos)', $out);
        $this->assertCount(3, (new RecordingSegment())->listByRecording((int)$rec['id']));
    }
}
