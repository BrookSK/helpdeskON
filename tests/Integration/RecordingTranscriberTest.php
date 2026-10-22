<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use RecordingSegment;
use RecordingTranscriber;
use Database;

/** Cliente de IA falso: devolve respostas programadas por nome de arquivo. */
final class FakeWhisper
{
    public array $calls = [];
    /** @var array<string,array<int,array>> respostas por arquivo, consumidas em ordem */
    public array $script = [];

    public function isConfigured(): bool { return true; }

    public function transcribe($path, array $options = []): array
    {
        $file = basename($path);
        $this->calls[] = $file;
        $queue = $this->script[$file] ?? [];
        $next = array_shift($queue);
        $this->script[$file] = $queue;
        return $next ?? ['success' => true, 'text' => "texto {$file}", 'segments' => [['start' => 1, 'end' => 2, 'text' => "texto {$file}"]]];
    }
}

/**
 * Fila de transcrição no servidor (cron transcribeRecordings) com o banco de
 * teste e a OpenAI substituída por um falso.
 */
final class RecordingTranscriberTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private string $storage;
    private int $ownerId;
    private int $roomId;
    private array $completed = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        // Isola a fila: este teste é o único dono dos segmentos pendentes.
        $this->db->query("DELETE FROM video_recording_segments");
        $u = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $token = $this->rooms->create([
            'title' => 'Sala fila', 'created_by' => $this->ownerId, 'max_participants' => 8,
            'allow_recording' => 1, 'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->rooms->findByToken($token)['id'];
        $this->storage = sys_get_temp_dir() . '/rtq_' . bin2hex(random_bytes(6));
        @mkdir($this->storage, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->db->query("DELETE s FROM video_recording_segments s JOIN video_recordings r ON r.id = s.recording_id WHERE r.room_id = ?", [$this->roomId]);
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('users', 'id = ?', [$this->ownerId]);
        foreach (glob($this->storage . '/*/*') ?: [] as $f) @unlink($f);
        foreach (glob($this->storage . '/*') ?: [] as $d) @rmdir($d);
        @rmdir($this->storage);
    }

    /** Cria uma gravação na fila com N segmentos de 600 s. */
    private function gravacaoNaFila(int $n): array
    {
        $tok = $this->rooms->generateToken();
        $this->rooms->addRecording([
            'room_id' => $this->roomId, 'token' => $tok, 'file_path' => "recordings/{$tok}.webm",
            'mime_type' => 'video/webm', 'transcribe_status' => 'queued',
        ]);
        $rec = $this->rooms->findRecordingByToken($tok);
        @mkdir($this->storage . '/' . $tok, 0775, true);
        for ($i = 1; $i <= $n; $i++) {
            $rel = sprintf('%s/seg_%06d.webm', $tok, $i);
            file_put_contents($this->storage . '/' . $rel, "audio{$i}");
            $this->db->insert('video_recording_segments', [
                'recording_id' => $rec['id'], 'seq' => $i, 'start_sec' => ($i - 1) * 600,
                'file_path' => $rel, 'status' => 'pending',
            ]);
        }
        return $rec;
    }

    private function transcriber(FakeWhisper $ai): RecordingTranscriber
    {
        return new RecordingTranscriber(new RecordingSegment($this->storage), $ai, function ($t) { $this->completed[] = $t; });
    }

    public function testSucessoEmLotesJuntaNaOrdemEConcluiUmaVez(): void
    {
        $rec = $this->gravacaoNaFila(4);
        $ai = new FakeWhisper();

        $s1 = $this->transcriber($ai)->run(3);
        $this->assertSame(3, $s1['processed']);
        $this->assertSame('processing', $this->rooms->findRecordingByToken($rec['token'])['transcribe_status']);
        $this->assertSame([], $this->completed);

        $s2 = $this->transcriber($ai)->run(3);
        $this->assertSame(1, $s2['processed']);
        $this->assertSame(1, $s2['completed']);
        $this->assertSame(['seg_000001.webm', 'seg_000002.webm', 'seg_000003.webm', 'seg_000004.webm'], $ai->calls);

        $after = $this->rooms->findRecordingByToken($rec['token']);
        $this->assertSame('done', $after['transcribe_status']);
        $this->assertStringContainsString('[30:01] texto seg_000004.webm', $after['transcript']);
        $this->assertSame([$rec['token']], $this->completed);

        // Nova execução sem trabalho: nada é reenviado nem concluído de novo.
        $this->transcriber($ai)->run(3);
        $this->assertCount(4, $ai->calls);
        $this->assertSame([$rec['token']], $this->completed);
    }

    public function testErroTemporarioTentaDeNovoSemReenviarOsConcluidos(): void
    {
        $rec = $this->gravacaoNaFila(2);
        $ai = new FakeWhisper();
        $ai->script['seg_000002.webm'] = [['success' => false, 'error' => 'HTTP 500']];

        $s1 = $this->transcriber($ai)->run(3);
        $this->assertSame(1, $s1['failed']);
        $rows = (new RecordingSegment($this->storage))->listByRecording((int)$rec['id']);
        $this->assertSame('pending', $rows[1]['status']);
        $this->assertSame('Segmento 2: HTTP 500', $rows[1]['last_error']);

        $this->transcriber($ai)->run(3);
        $this->assertSame(['seg_000001.webm', 'seg_000002.webm', 'seg_000002.webm'], $ai->calls);
        $this->assertSame('done', $this->rooms->findRecordingByToken($rec['token'])['transcribe_status']);
    }

    public function testFalhaDefinitivaDepoisDeTresTentativas(): void
    {
        $rec = $this->gravacaoNaFila(1);
        $ai = new FakeWhisper();
        $erro = ['success' => false, 'error' => 'arquivo inválido'];
        $ai->script['seg_000001.webm'] = [$erro, $erro, $erro];

        $this->transcriber($ai)->run(3);
        $this->transcriber($ai)->run(3);
        $s3 = $this->transcriber($ai)->run(3);
        $this->assertSame(1, $s3['errors']);

        $after = $this->rooms->findRecordingByToken($rec['token']);
        $this->assertSame('error', $after['transcribe_status']);
        $this->assertSame('Segmento 1: arquivo inválido', $after['transcribe_error']);
        // Quarta execução não tenta de novo.
        $this->transcriber($ai)->run(3);
        $this->assertCount(3, $ai->calls);
        $this->assertSame([], $this->completed);
    }

    public function testReservaNaoEntregaOMesmoSegmentoDuasVezes(): void
    {
        $this->gravacaoNaFila(2);
        $seg = new RecordingSegment($this->storage);
        $a = $seg->reserve(1);
        $b = $seg->reserve(3);
        $this->assertCount(1, $a);
        $this->assertCount(1, $b);
        $this->assertNotSame($a[0]['id'], $b[0]['id']);
        $this->assertSame([], $seg->reserve(3));
    }

    public function testReservaPresaVoltaParaFila(): void
    {
        $rec = $this->gravacaoNaFila(1);
        $seg = new RecordingSegment($this->storage);
        $seg->reserve(1);
        $this->db->query("UPDATE video_recording_segments SET locked_at = ? WHERE recording_id = ?", [date('Y-m-d H:i:s', time() - 900), $rec['id']]);
        $this->assertSame(1, $seg->releaseStale());
        $this->assertCount(1, $seg->reserve(1));
    }
}
