<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use VideocallController;
use RecordingMedia;
use Config;
use Database;

/**
 * Remux pelo cron das gravações publicadas sem duração
 * (VideocallController::remuxPendingRecordings), contra helpdesk_on_test.
 * Os casos com FFmpeg real só rodam com FFMPEG_TEST_BIN definido.
 */
final class RemuxPendingRecordingsTest extends TestCase
{
    private Database $db;
    private VideoRoom $rooms;
    private int $ownerId;
    private int $roomId;
    private string $markers;
    private ?string $savedPath = null;
    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', BASE_PATH . '/public');
        $this->db = Database::getInstance();
        $this->rooms = new VideoRoom();
        $row = $this->db->fetch("SELECT setting_value FROM settings WHERE setting_key = 'ffmpeg_path'");
        $this->savedPath = $row ? (string)$row['setting_value'] : null;
        $this->db->query("DELETE FROM settings WHERE setting_key = 'ffmpeg_path'");
        Config::reload();

        $u = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_remux_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $token = $this->rooms->create([
            'title' => 'Sala remux', 'created_by' => $this->ownerId, 'max_participants' => 8,
            'allow_recording' => 1, 'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->roomId = (int) $this->rooms->findByToken($token)['id'];
        $this->markers = sys_get_temp_dir() . '/rmx_' . bin2hex(random_bytes(6));
        @mkdir($this->markers, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->db->delete('video_recordings', 'room_id = ?', [$this->roomId]);
        $this->db->delete('video_rooms', 'id = ?', [$this->roomId]);
        $this->db->delete('users', 'id = ?', [$this->ownerId]);
        $this->db->query("DELETE FROM settings WHERE setting_key = 'ffmpeg_path'");
        if ($this->savedPath !== null) Config::set('ffmpeg_path', $this->savedPath);
        Config::reload();
        foreach ($this->files as $f) { @unlink($f); @unlink($f . '.remux.webm'); }
        foreach (glob($this->markers . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->markers);
    }

    private function ffmpegOrSkip(): string
    {
        $ff = (string) getenv('FFMPEG_TEST_BIN');
        if ($ff === '' || !is_file($ff)) $this->markTestSkipped('FFMPEG_TEST_BIN não definido.');
        Config::set('ffmpeg_path', $ff);
        Config::reload();
        return $ff;
    }

    /** Gravação com o arquivo em public/uploads/recordings e a marca de remux. */
    private function gravacaoMarcada(?callable $writeFile = null): array
    {
        $tok = $this->rooms->generateToken();
        $rel = 'recordings/rec_' . $this->roomId . '_' . $tok . '.webm';
        $path = BASE_PATH . '/public/uploads/' . $rel;
        $this->files[] = $path;
        $writeFile ? $writeFile($path) : file_put_contents($path, 'x');
        $this->rooms->addRecording([
            'room_id' => $this->roomId, 'token' => $tok, 'file_path' => $rel,
            'mime_type' => 'video/webm', 'recorded_by' => $this->ownerId,
        ]);
        file_put_contents($this->markers . '/' . $tok, '0');
        return ['token' => $tok, 'path' => $path];
    }

    public function testSemMarcasNaoFazNada(): void
    {
        $this->assertSame(0, (new VideocallController())->remuxPendingRecordings(1, $this->markers));
    }

    public function testSemFfmpegAMarcaFicaParaDepois(): void
    {
        if (RecordingMedia::ffmpegBin() !== null) $this->markTestSkipped('Há FFmpeg nesta máquina.');
        $g = $this->gravacaoMarcada();
        $this->assertSame(0, (new VideocallController())->remuxPendingRecordings(1, $this->markers));
        $this->assertFileExists($this->markers . '/' . $g['token']);
    }

    public function testMarcaDeGravacaoApagadaSai(): void
    {
        $this->ffmpegOrSkip();
        file_put_contents($this->markers . '/' . str_repeat('a', 32), '0');
        $this->assertSame(0, (new VideocallController())->remuxPendingRecordings(1, $this->markers));
        $this->assertFileDoesNotExist($this->markers . '/' . str_repeat('a', 32));
    }

    public function testVideoSemDuracaoEArrumadoEAMarcaSai(): void
    {
        $ff = $this->ffmpegOrSkip();
        $g = $this->gravacaoMarcada(function ($path) use ($ff) {
            // WebM escrito como fluxo, como o do MediaRecorder do navegador.
            shell_exec(escapeshellarg($ff) . ' -nostdin -hide_banner -loglevel error -y'
                . ' -f lavfi -i testsrc=size=160x120:rate=10 -f lavfi -i sine=frequency=440:sample_rate=48000'
                . ' -t 40 -c:v libvpx -b:v 100k -c:a libopus -f webm - > ' . escapeshellarg($path));
        });
        $this->assertGreaterThan(0, filesize($g['path']));

        $this->assertSame(1, (new VideocallController())->remuxPendingRecordings(1, $this->markers));
        $rec = $this->rooms->findRecordingByToken($g['token']);
        $this->assertEqualsWithDelta(40, (int)$rec['duration_sec'], 1);
        $this->assertSame(filesize($g['path']), (int)$rec['file_size']);
        $this->assertFileDoesNotExist($this->markers . '/' . $g['token']);
        $this->assertFileDoesNotExist($g['path'] . '.remux.webm');
    }

    public function testUmaPorExecucao(): void
    {
        $ff = $this->ffmpegOrSkip();
        $gen = function ($path) use ($ff) {
            shell_exec(escapeshellarg($ff) . ' -nostdin -hide_banner -loglevel error -y'
                . ' -f lavfi -i testsrc=size=160x120:rate=10 -f lavfi -i sine=frequency=440:sample_rate=48000'
                . ' -t 5 -c:v libvpx -c:a libopus -f webm - > ' . escapeshellarg($path));
        };
        $this->gravacaoMarcada($gen);
        $this->gravacaoMarcada($gen);
        $ctrl = new VideocallController();
        $this->assertSame(1, $ctrl->remuxPendingRecordings(1, $this->markers));
        $this->assertCount(1, glob($this->markers . '/*'));
        $this->assertSame(1, $ctrl->remuxPendingRecordings(1, $this->markers));
        $this->assertCount(0, glob($this->markers . '/*'));
    }

    public function testArquivoQueOFfmpegRecusaDesisteDepoisDeTresTentativasSemMexerNele(): void
    {
        $this->ffmpegOrSkip();
        $g = $this->gravacaoMarcada(function ($path) { file_put_contents($path, random_bytes(4096)); });
        $antes = hash_file('sha256', $g['path']);
        $ctrl = new VideocallController();
        for ($i = 1; $i <= RecordingMedia::PENDING_REMUX_MAX_ATTEMPTS; $i++) {
            $this->assertSame(0, $ctrl->remuxPendingRecordings(1, $this->markers));
        }
        $this->assertFileDoesNotExist($this->markers . '/' . $g['token']);
        $this->assertFileExists($this->markers . '/' . $g['token'] . '.failed');
        $this->assertSame($antes, hash_file('sha256', $g['path']), 'o vídeo original não pode ser alterado');
        // Depois de desistir não tenta mais.
        $this->assertSame(0, $ctrl->remuxPendingRecordings(1, $this->markers));
    }
}
