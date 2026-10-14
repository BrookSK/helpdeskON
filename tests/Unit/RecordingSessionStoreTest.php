<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecordingSessionStore;

/**
 * Testes do armazenamento de sessão de gravação (protocolo v2).
 *
 * Cobre a parte pura (checksum, manifesto, sequência) e o fluxo real em disco
 * (append verificado, idempotência, conflito de reenvio, ordem, teto, montagem
 * com conferência de manifesto e detecção de corrupção). Usa um diretório
 * temporário próprio por teste e limpa no tearDown.
 */
final class RecordingSessionStoreTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/rss_test_' . bin2hex(random_bytes(6));
        @mkdir($this->base, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
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

    private function sess(): string
    {
        return str_repeat('a', 32); // válido: 32 chars
    }

    // ---------------- parte pura ----------------

    public function testValidacaoDeSess(): void
    {
        $this->assertTrue(RecordingSessionStore::isValidSess(str_repeat('x', 16)));
        $this->assertTrue(RecordingSessionStore::isValidSess('abc-DEF_123456789012'));
        $this->assertFalse(RecordingSessionStore::isValidSess('curto'));
        $this->assertFalse(RecordingSessionStore::isValidSess(str_repeat('x', 65)));
        $this->assertFalse(RecordingSessionStore::isValidSess('tem espaço aqui 123456'));
        $this->assertFalse(RecordingSessionStore::isValidSess(null));
    }

    public function testChecksum(): void
    {
        $bytes = 'conteudo-de-teste';
        $hash = RecordingSessionStore::hashBytes($bytes);
        $this->assertSame(64, strlen($hash));
        $this->assertTrue(RecordingSessionStore::checksumMatches($bytes, $hash));
        $this->assertTrue(RecordingSessionStore::checksumMatches($bytes, strtoupper($hash)));
        $this->assertFalse(RecordingSessionStore::checksumMatches($bytes, str_repeat('0', 64)));
        $this->assertFalse(RecordingSessionStore::checksumMatches($bytes, 'curto'));
    }

    public function testManifestoFormatoEOrdem(): void
    {
        $chunks = [
            ['seq' => 2, 'size' => 20, 'sha256' => str_repeat('b', 64)],
            ['seq' => 1, 'size' => 10, 'sha256' => str_repeat('a', 64)],
        ];
        $text = RecordingSessionStore::buildManifestText($chunks);
        $expected = '1:10:' . str_repeat('a', 64) . "\n" . '2:20:' . str_repeat('b', 64) . "\n";
        $this->assertSame($expected, $text);
        $this->assertSame(hash('sha256', $expected), RecordingSessionStore::manifestHash($chunks));
    }

    public function testSequenciaContigua(): void
    {
        $this->assertTrue(RecordingSessionStore::isContiguousSequence([1, 2, 3]));
        $this->assertTrue(RecordingSessionStore::isContiguousSequence([3, 1, 2]));
        $this->assertFalse(RecordingSessionStore::isContiguousSequence([1, 3]));
        $this->assertFalse(RecordingSessionStore::isContiguousSequence([2, 3]));
        $this->assertFalse(RecordingSessionStore::isContiguousSequence([1, 1, 2]));
        $this->assertFalse(RecordingSessionStore::isContiguousSequence([]));
    }

    public function testTetoPorEnv(): void
    {
        $this->assertSame(RecordingSessionStore::DEFAULT_MAX_SESSION_BYTES, RecordingSessionStore::resolveMaxSessionBytes(null));
        $this->assertSame(RecordingSessionStore::DEFAULT_MAX_SESSION_BYTES, RecordingSessionStore::resolveMaxSessionBytes('0'));
        $this->assertSame(123456, RecordingSessionStore::resolveMaxSessionBytes('123456'));
    }

    // ---------------- fluxo em disco ----------------

    public function testAppendConfirmaEContaBytes(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(10, 'Fulano', 'rectoken1');
        $this->assertSame(1, $store->nextSeq());

        $c1 = 'pedaco-um';
        $r = $store->appendChunk(1, $c1, RecordingSessionStore::hashBytes($c1));
        $this->assertSame('ok', $r['status']);
        $this->assertSame(strlen($c1), $r['bytes']);
        $this->assertSame(2, $store->nextSeq());

        $c2 = 'pedaco-dois';
        $store->appendChunk(2, $c2, RecordingSessionStore::hashBytes($c2));
        $this->assertSame(strlen($c1) + strlen($c2), $store->confirmedBytes());
    }

    public function testReenvioIdempotente(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(null, null, 'rectoken1');
        $c1 = 'mesmo-conteudo';
        $h = RecordingSessionStore::hashBytes($c1);
        $store->appendChunk(1, $c1, $h);
        $r = $store->appendChunk(1, $c1, $h); // reenvio idêntico
        $this->assertSame('duplicate', $r['status']);
        $this->assertSame(1, count($store->confirmedChunks()));
    }

    public function testConflitoDeReenvioComConteudoDivergente(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(null, null, 'rectoken1');
        $store->appendChunk(1, 'conteudo-A', RecordingSessionStore::hashBytes('conteudo-A'));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('seq_conflict');
        $store->appendChunk(1, 'conteudo-B', RecordingSessionStore::hashBytes('conteudo-B'));
    }

    public function testChecksumIncorretoEhRecusado(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(null, null, 'rectoken1');
        $this->expectException(\UnexpectedValueException::class);
        $store->appendChunk(1, 'conteudo', str_repeat('0', 64));
    }

    public function testForaDeOrdemEhRecusado(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(null, null, 'rectoken1');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('out_of_order:1');
        $store->appendChunk(2, 'pulou', RecordingSessionStore::hashBytes('pulou'));
    }

    public function testMontagemConfereManifestoEContagem(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(1, 'Rec', 'rectoken1');
        $parts = ['aaa', 'bbbb', 'ccccc'];
        foreach ($parts as $i => $p) {
            $store->appendChunk($i + 1, $p, RecordingSessionStore::hashBytes($p));
        }
        $chunks = $store->confirmedChunks();
        $manifest = RecordingSessionStore::manifestHash($chunks);
        $bytes = array_sum(array_map('strlen', $parts));

        $out = $store->assemble($manifest, 3, $bytes);
        $this->assertFileExists($out);
        $this->assertSame(implode('', $parts), file_get_contents($out));
    }

    public function testMontagemRecusaManifestoDivergente(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(1, 'Rec', 'rectoken1');
        $store->appendChunk(1, 'aaa', RecordingSessionStore::hashBytes('aaa'));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('manifest_mismatch');
        $store->assemble(str_repeat('f', 64), 1, 3);
    }

    public function testMontagemDetectaChunkCorrompidoNoDisco(): void
    {
        $sess = $this->sess();
        $store = new RecordingSessionStore($this->base, 7, $sess);
        $store->start(1, 'Rec', 'rectoken1');
        $store->appendChunk(1, 'aaa', RecordingSessionStore::hashBytes('aaa'));
        $chunks = $store->confirmedChunks();
        $manifest = RecordingSessionStore::manifestHash($chunks);

        // Corrompe o chunk no disco por fora.
        $chunkPath = $store->dir() . DIRECTORY_SEPARATOR . RecordingSessionStore::chunkFileName(1);
        file_put_contents($chunkPath, 'XXX');

        $this->expectException(\RuntimeException::class);
        $store->assemble($manifest, 1, 3);
    }

    public function testTetoAcumuladoPorSessao(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess(), 10);
        $store->start(null, null, 'rectoken1');
        $store->appendChunk(1, '12345', RecordingSessionStore::hashBytes('12345')); // 5 bytes
        $this->expectException(\RangeException::class);
        $this->expectExceptionMessage('session_limit');
        $store->appendChunk(2, '1234567', RecordingSessionStore::hashBytes('1234567')); // +7 = 12 > 10
    }

    public function testFinalizacaoEhIdempotentePeloToken(): void
    {
        $store = new RecordingSessionStore($this->base, 7, $this->sess());
        $store->start(null, null, 'rectoken1');
        $store->appendChunk(1, 'aaa', RecordingSessionStore::hashBytes('aaa'));
        $this->assertFalse($store->isFinalized());
        $store->markFinalized('rec-final-xyz');
        $this->assertTrue($store->isFinalized());
        $this->assertSame('rec-final-xyz', $store->publishedToken());

        // cleanup remove chunks mas preserva o journal.
        $store->cleanupChunks();
        $this->assertFileDoesNotExist($store->dir() . DIRECTORY_SEPARATOR . RecordingSessionStore::chunkFileName(1));
        $this->assertFileExists($store->dir() . DIRECTORY_SEPARATOR . 'journal.json');
    }
}
