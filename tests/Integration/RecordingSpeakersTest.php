<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use RecordingSessionStore;
use RecordingSegment;
use RecordingTranscriber;
use Database;

/**
 * Nome de quem falou na transcrição: os intervalos chegam com cada segmento de
 * áudio, viram storage/recording-audio/<token>/speakers.json na publicação e a
 * fila do cron põe o nome em cada trecho. Banco helpdesk_on_test, OpenAI falsa.
 */
final class RecordingSpeakersTest extends TestCase
{
    private Database $db;
    private VideoRoom $model;
    private int $ownerId;
    private int $roomId;
    private string $sessBase;
    private string $storage;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new VideoRoom();
        // Isola a fila: este teste é o único dono dos segmentos pendentes.
        $this->db->query('DELETE FROM video_recording_segments');
        $u = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_spk_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $token = $this->model->create([
            'title' => 'Sala nomes', 'created_by' => $this->ownerId, 'max_participants' => 8,
            'allow_recording' => 1, 'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->model->findByToken($token)['id'];
        $this->sessBase = sys_get_temp_dir() . '/rspk_s_' . bin2hex(random_bytes(6));
        $this->storage = sys_get_temp_dir() . '/rspk_a_' . bin2hex(random_bytes(6));
        @mkdir($this->sessBase, 0775, true);
        @mkdir($this->storage, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->db->query('DELETE s FROM video_recording_segments s JOIN video_recordings r ON r.id = s.recording_id WHERE r.room_id = ?', [$this->roomId]);
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('users', 'id = ?', [$this->ownerId]);
        $this->rrmdir($this->sessBase);
        $this->rrmdir($this->storage);
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

    /** Sessão com N segmentos de 60 s; $speakers[seq] = JSON enviado pelo navegador. */
    private function sessao(string $sess, int $n, array $speakers): array
    {
        $store = new RecordingSessionStore($this->sessBase, $this->roomId, $sess);
        $recToken = $this->model->generateToken();
        $store->start($this->ownerId, 'Dono', $recToken);
        for ($i = 1; $i <= $n; $i++) {
            $bytes = "AUDIO{$i}";
            $r = $store->appendAudioSegment($i, $bytes, RecordingSessionStore::hashBytes($bytes), ($i - 1) * 60, 'audio/webm;codecs=opus', $speakers[$i] ?? '');
            $this->assertSame('ok', $r['status']);
        }
        $this->model->addRecording([
            'room_id' => $this->roomId, 'token' => $recToken,
            'file_path' => 'recordings/rec_' . $this->roomId . '_' . $recToken . '.webm',
            'mime_type' => 'video/webm', 'recorded_by' => $this->ownerId,
        ]);
        $store->markFinalized($recToken);
        $rec = $this->model->findRecordingByToken($recToken);
        (new RecordingSegment($this->storage))->attachFromSession($store, (int)$rec['id'], $recToken);
        return [$store, $rec];
    }

    /** Whisper falso: um trecho de 2 s a 10 s do início de cada segmento. */
    private function fakeAi(): object
    {
        return new class {
            public function isConfigured(): bool { return true; }
            public function transcribe($path, array $o = []): array
            {
                $n = (int) preg_replace('/\D/', '', basename($path));
                return ['success' => true, 'text' => "fala {$n}", 'segments' => [['start' => 2, 'end' => 10, 'text' => "fala {$n}"]]];
            }
        };
    }

    private function transcrever(array $rec): array
    {
        $t = new RecordingTranscriber(new RecordingSegment($this->storage), $this->fakeAi());
        for ($i = 0; $i < 5 && $this->model->findRecordingByToken($rec['token'])['transcribe_status'] !== 'done'; $i++) {
            $t->run();
        }
        return $this->model->findRecordingByToken($rec['token']);
    }

    public function testTranscricaoTrazONomeDeQuemFalouEmCadaTrecho(): void
    {
        [, $rec] = $this->sessao(str_repeat('a', 32), 2, [
            1 => json_encode([['s' => 0.5, 'e' => 11, 'n' => 'Lucas Vacari']]),
            // Narciso fala de 61 s a 70 s (dentro do 2º segmento); Lucas só um pouco.
            2 => json_encode([['s' => 60, 'e' => 61.5, 'n' => 'Lucas Vacari'], ['s' => 61.5, 'e' => 70, 'n' => 'Narciso']]),
        ]);

        $file = $this->storage . '/recording-audio/' . $rec['token'] . '/' . RecordingSegment::SPEAKERS_FILE;
        $this->assertFileExists($file);
        $this->assertCount(3, json_decode(file_get_contents($file), true));

        $after = $this->transcrever($rec);
        $this->assertSame('done', $after['transcribe_status']);
        $this->assertStringContainsString('[00:02] Lucas Vacari: fala 1', $after['transcript']);
        $this->assertStringContainsString('[01:02] Narciso: fala 2', $after['transcript']);
        $json = json_decode($after['transcript_json'], true);
        $this->assertSame(['Lucas Vacari', 'Narciso'], array_column($json, 'speaker'));
    }

    public function testGravacaoSemNomesFicaComoAntes(): void
    {
        [, $rec] = $this->sessao(str_repeat('b', 32), 1, []);
        $this->assertFileDoesNotExist($this->storage . '/recording-audio/' . $rec['token'] . '/' . RecordingSegment::SPEAKERS_FILE);
        $after = $this->transcrever($rec);
        $this->assertStringContainsString('[00:02] fala 1', $after['transcript']);
        $this->assertArrayNotHasKey('speaker', json_decode($after['transcript_json'], true)[0]);
    }

    public function testNomesInvalidosNaoImpedemOSegmento(): void
    {
        [$store, $rec] = $this->sessao(str_repeat('c', 32), 1, [1 => '{quebrado']);
        $this->assertCount(1, $store->audioSegments());
        $this->assertSame([], $store->speakerTimeline());
        $this->assertSame('queued', $this->model->findRecordingByToken($rec['token'])['transcribe_status']);
    }

    public function testSegmentoAtrasadoAcrescentaNomesAoArquivo(): void
    {
        [$store, $rec] = $this->sessao(str_repeat('d', 32), 1, [1 => json_encode([['s' => 1, 'e' => 5, 'n' => 'Lucas Vacari']])]);
        // O último segmento chega depois da publicação (rede lenta de quem gravou).
        $store->appendAudioSegment(2, 'AUDIO2', RecordingSessionStore::hashBytes('AUDIO2'), 60, 'audio/webm',
            json_encode([['s' => 62, 'e' => 66, 'n' => 'Renato']]));
        (new RecordingSegment($this->storage))->attachFromSession($store, (int)$rec['id'], $rec['token']);

        $names = array_column(json_decode(file_get_contents($this->storage . '/recording-audio/' . $rec['token'] . '/' . RecordingSegment::SPEAKERS_FILE), true), 'n');
        $this->assertSame(['Lucas Vacari', 'Renato'], $names);
    }

    public function testReenvioDoMesmoSegmentoNaoDuplicaNomes(): void
    {
        $store = new RecordingSessionStore($this->sessBase, $this->roomId, str_repeat('e', 32));
        $store->start($this->ownerId, 'Dono', $this->model->generateToken());
        $spk = json_encode([['s' => 1, 'e' => 5, 'n' => 'Lucas Vacari']]);
        $store->appendAudioSegment(1, 'AUDIO1', RecordingSessionStore::hashBytes('AUDIO1'), 0, 'audio/webm', $spk);
        $this->assertSame('duplicate', $store->appendAudioSegment(1, 'AUDIO1', RecordingSessionStore::hashBytes('AUDIO1'), 0, 'audio/webm', $spk)['status']);
        $this->assertCount(1, $store->speakerTimeline());
    }
}
