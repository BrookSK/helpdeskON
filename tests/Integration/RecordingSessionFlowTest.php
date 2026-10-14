<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use RecordingSessionStore;
use Database;

/**
 * Integração do protocolo v2 de gravação com o banco helpdesk_on_test.
 *
 * Exercita o fluxo real: sessão em disco (RecordingSessionStore) recebe chunks
 * verificados, monta o arquivo com conferência de manifesto e o resultado é
 * registrado via VideoRoom::addRecording — o MESMO ponto que a esteira
 * (transcrição/ata/CRM) consome. Garante que uma gravação finalizada pelo v2
 * fica visível por findRecordingByToken/listRecordings exatamente como antes.
 *
 * Cria os próprios dados (usuário + sala) no setUp e limpa tudo no tearDown.
 */
final class RecordingSessionFlowTest extends TestCase
{
    private Database $db;
    private VideoRoom $model;
    private int $ownerId;
    private array $roomIds = [];
    private array $recTokens = [];
    private string $sessBase;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->model = new VideoRoom();
        $u = uniqid();
        $this->ownerId = (int) $this->db->insert('users', [
            'name' => "Dono {$u}", 'email' => "dono_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->sessBase = sys_get_temp_dir() . '/rsf_' . bin2hex(random_bytes(6));
        @mkdir($this->sessBase, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->roomIds as $id) {
            try { $this->db->delete('video_recordings', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_rooms', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->ownerId]); } catch (\Throwable $e) {}
        $this->rrmdir($this->sessBase);
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

    private function novaSala(array $overrides = []): array
    {
        $token = $this->model->create(array_merge([
            'title' => 'Sala v2', 'created_by' => $this->ownerId,
            'max_participants' => 8, 'allow_recording' => 1, 'allow_presentation' => 1,
            'status' => 'active', 'visibility' => 'public',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ], $overrides));
        $room = $this->model->findByToken($token);
        $this->roomIds[] = (int) $room['id'];
        return $room;
    }

    public function testFluxoCompletoV2GeraGravacaoVisivel(): void
    {
        $room = $this->novaSala();
        $sess = str_repeat('f', 32);
        $store = new RecordingSessionStore($this->sessBase, (int)$room['id'], $sess);
        $recToken = $this->model->generateToken();
        $store->start($this->ownerId, 'Dono', $recToken);

        // Envia 3 pedaços verificados.
        $parts = ['AAAA', 'BBBBBB', 'CCCCCCCC'];
        foreach ($parts as $i => $p) {
            $store->appendChunk($i + 1, $p, RecordingSessionStore::hashBytes($p));
        }

        // Monta conferindo o manifesto (como o recFinalizeV2 faz).
        $chunks = $store->confirmedChunks();
        $manifest = RecordingSessionStore::manifestHash($chunks);
        $bytes = array_sum(array_map('strlen', $parts));
        $assembled = $store->assemble($manifest, 3, $bytes);
        $this->assertFileExists($assembled);
        $this->assertSame(implode('', $parts), file_get_contents($assembled));

        // Publica via o MESMO addRecording que a esteira consome.
        $this->model->addRecording([
            'room_id' => (int)$room['id'],
            'token' => $recToken,
            'file_path' => 'recordings/rec_' . $room['id'] . '_' . $recToken . '.webm',
            'file_size' => $bytes,
            'mime_type' => 'video/webm',
            'duration_sec' => 12,
            'recorded_by' => $this->ownerId,
            'recorded_by_name' => 'Dono',
        ]);
        $this->recTokens[] = $recToken;
        $store->markFinalized($recToken);
        $store->cleanupChunks();

        // A gravação precisa estar visível exatamente como no fluxo legado.
        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertNotNull($rec);
        $this->assertSame((int)$room['id'], (int)$rec['room_id']);
        $this->assertSame(12, (int)$rec['duration_sec']);

        $lista = $this->model->listRecordings((int)$room['id']);
        $this->assertContains($recToken, array_column($lista, 'token'));

        // Idempotência de finalização: o token publicado é estável.
        $this->assertTrue($store->isFinalized());
        $this->assertSame($recToken, $store->publishedToken());
        // Chunks removidos, journal preservado.
        $this->assertFileExists($store->dir() . DIRECTORY_SEPARATOR . 'journal.json');
    }

    public function testManifestoDivergenteNaoPublica(): void
    {
        $room = $this->novaSala();
        $sess = str_repeat('e', 32);
        $store = new RecordingSessionStore($this->sessBase, (int)$room['id'], $sess);
        $store->start($this->ownerId, 'Dono', $this->model->generateToken());
        $store->appendChunk(1, 'AAAA', RecordingSessionStore::hashBytes('AAAA'));

        // Manifesto errado deve impedir a montagem — nada é publicado no banco.
        try {
            $store->assemble(str_repeat('0', 64), 1, 4);
            $this->fail('deveria ter recusado o manifesto divergente');
        } catch (\DomainException $e) {
            $this->assertSame('manifest_mismatch', $e->getMessage());
        }
        $antes = $this->model->listRecordings((int)$room['id']);
        $this->assertCount(0, $antes);
    }
}
