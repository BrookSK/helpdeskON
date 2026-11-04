<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use VideoRoom;
use RecordingSessionStore;
use RecordingSegment;
use VideocallController;
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
    private string $storageBase;
    /** @var string[] Arquivos publicados em public/uploads e storage/ pelos testes. */
    private array $publishedFiles = [];

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
        $this->storageBase = sys_get_temp_dir() . '/rsf_storage_' . bin2hex(random_bytes(6));
        @mkdir($this->storageBase, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->roomIds as $id) {
            foreach ($this->db->fetchAll('SELECT id, token, file_path FROM video_recordings WHERE room_id = ?', [$id]) as $r) {
                try { $this->db->delete('video_recording_segments', 'recording_id = ?', [$r['id']]); } catch (\Throwable $e) {}
                @unlink(BASE_PATH . '/public/uploads/' . $r['file_path']);
                $this->rrmdir(BASE_PATH . '/storage/recording-audio/' . $r['token']);
            }
            try { $this->db->delete('video_recordings', 'room_id = ?', [$id]); } catch (\Throwable $e) {}
            try { $this->db->delete('video_rooms', 'id = ?', [$id]); } catch (\Throwable $e) {}
        }
        try { $this->db->delete('users', 'id = ?', [$this->ownerId]); } catch (\Throwable $e) {}
        $this->rrmdir($this->sessBase);
        $this->rrmdir($this->storageBase);
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

    private function audioSeg(RecordingSessionStore $store, int $seq, string $bytes, float $start): array
    {
        return $store->appendAudioSegment($seq, $bytes, RecordingSessionStore::hashBytes($bytes), $start, 'audio/webm;codecs=opus');
    }

    public function testSegmentoDeAudioRecusaHashErradoTamanhoETipoESemDuplicar(): void
    {
        $room = $this->novaSala();
        $store = new RecordingSessionStore($this->sessBase, (int)$room['id'], str_repeat('a', 32));
        $store->start($this->ownerId, 'Dono', $this->model->generateToken());

        try {
            $store->appendAudioSegment(1, 'OPUS1', str_repeat('0', 64), 0, 'audio/webm');
            $this->fail('deveria recusar o hash errado');
        } catch (\UnexpectedValueException $e) {
            $this->assertSame('checksum_mismatch', $e->getMessage());
        }

        $grande = str_repeat('x', RecordingSessionStore::MAX_AUDIO_SEGMENT_BYTES + 1);
        try {
            $store->appendAudioSegment(1, $grande, RecordingSessionStore::hashBytes($grande), 0, 'audio/webm');
            $this->fail('deveria recusar o segmento grande demais');
        } catch (\RangeException $e) {
            $this->assertSame('too_large', $e->getMessage());
        }
        unset($grande);

        try {
            $store->appendAudioSegment(1, 'OPUS1', RecordingSessionStore::hashBytes('OPUS1'), 0, 'video/webm');
            $this->fail('deveria recusar tipo que não é áudio');
        } catch (\DomainException $e) {
            $this->assertSame('invalid_mime', $e->getMessage());
        }

        $this->assertSame('ok', $this->audioSeg($store, 1, 'OPUS1', 0)['status']);
        $this->assertSame('duplicate', $this->audioSeg($store, 1, 'OPUS1', 0)['status']);
        try {
            $this->audioSeg($store, 1, 'OUTRO', 0);
            $this->fail('deveria recusar conteúdo diferente na mesma sequência');
        } catch (\DomainException $e) {
            $this->assertSame('seq_conflict', $e->getMessage());
        }
        $this->assertCount(1, $store->audioSegments());
    }

    public function testPublicacaoLigaSegmentosEColocaNaFila(): void
    {
        $room = $this->novaSala();
        $store = new RecordingSessionStore($this->sessBase, (int)$room['id'], str_repeat('b', 32));
        $recToken = $this->model->generateToken();
        $store->start($this->ownerId, 'Dono', $recToken);
        // Segmentos podem chegar fora de ordem.
        $this->audioSeg($store, 2, 'SEGUNDO', 300.5);
        $this->audioSeg($store, 1, 'PRIMEIRO', 0);

        $this->model->addRecording([
            'room_id' => (int)$room['id'], 'token' => $recToken,
            'file_path' => 'recordings/rec_' . $room['id'] . '_' . $recToken . '.webm',
            'mime_type' => 'video/webm', 'recorded_by' => $this->ownerId,
        ]);
        $store->markFinalized($recToken);
        $rec = $this->model->findRecordingByToken($recToken);

        $segModel = new RecordingSegment($this->storageBase);
        $this->assertSame(2, $segModel->attachFromSession($store, (int)$rec['id'], $recToken));
        // Repetir não duplica.
        $this->assertSame(0, $segModel->attachFromSession($store, (int)$rec['id'], $recToken));

        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame('queued', $rec['transcribe_status']);
        $rows = $segModel->listByRecording((int)$rec['id']);
        $this->assertSame([1, 2], array_map('intval', array_column($rows, 'seq')));
        $this->assertSame('300.500', $rows[1]['start_sec']);
        $this->assertSame('pending', $rows[0]['status']);
        $this->assertSame('PRIMEIRO', file_get_contents($segModel->absolutePath($rows[0]['file_path'])));
        $this->assertFileDoesNotExist($store->audioDir() . '/seg_000001.webm');
    }

    public function testSessaoAbandonadaPublicadaUmaVezComoIncompleta(): void
    {
        if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', BASE_PATH . '/public');
        $room = $this->novaSala();
        $store = new RecordingSessionStore($this->sessBase, (int)$room['id'], str_repeat('c', 32));
        $recToken = $this->model->generateToken();
        $store->start($this->ownerId, 'Dono', $recToken);
        $store->appendChunk(1, 'VIDEO1', RecordingSessionStore::hashBytes('VIDEO1'));
        $store->appendChunk(2, 'VIDEO2', RecordingSessionStore::hashBytes('VIDEO2'));
        $this->audioSeg($store, 1, 'AUDIO1', 0);

        $ctrl = new VideocallController();
        // Sessão ainda ativa: nada é publicado.
        $this->assertSame(0, $ctrl->finalizeAbandonedSessions(900, $this->sessBase));
        $this->assertEmpty($this->model->findRecordingByToken($recToken));

        // Sem atividade há 16 minutos.
        $journal = $store->dir() . '/journal.json';
        $j = json_decode(file_get_contents($journal), true);
        $j['updated_at'] = time() - 960;
        file_put_contents($journal, json_encode($j));

        $this->assertSame(1, $ctrl->finalizeAbandonedSessions(900, $this->sessBase));
        $this->assertSame(0, $ctrl->finalizeAbandonedSessions(900, $this->sessBase));

        $lista = $this->model->listRecordings((int)$room['id']);
        $this->assertCount(1, $lista);
        $rec = $this->model->findRecordingByToken($recToken);
        $this->assertSame(1, (int)$rec['is_incomplete']);
        $this->assertSame('queued', $rec['transcribe_status']);
        $this->assertSame('VIDEO1VIDEO2', file_get_contents(BASE_PATH . '/public/uploads/' . $rec['file_path']));
        $this->assertCount(1, (new RecordingSegment())->listByRecording((int)$rec['id']));
        $this->assertTrue($store->isFinalized());
    }
}
