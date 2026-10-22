<?php

/**
 * Armazenamento de uma SESSÃO de gravação de videochamada (protocolo v2).
 *
 * Implementa o que o diagnóstico de 14/10/2026 descreve: pedaços imutáveis com
 * sequência + checksum SHA-256, journal e manifesto para idempotência, lock por
 * sessão (flock) e montagem verificada do arquivo final. Serve de fonte única de
 * verdade para o VideocallController, no mesmo padrão testável das classes *Rules.
 *
 * Layout em disco (um diretório por sessão, dentro de storage/video-recordings/):
 *   <base>/<roomId>_<sess>/
 *       journal.json            → metadados + estado dos chunks confirmados
 *       chunk_000001.bin ...     → pedaços brutos (um por sequência)
 *   O arquivo montado final é entregue a quem chama (controller publica em uploads).
 *
 * A parte PURA (checksum, manifesto, validação de sequência, nome de arquivo,
 * limites) é estática e testável sem disco. A parte com disco usa flock/rename
 * atômico e nunca confirma um chunk antes de ter persistido os bytes.
 */
class RecordingSessionStore
{
    /** Tamanho máximo de UM pedaço aceito do cliente (1 MiB + folga de multipart). */
    public const MAX_CHUNK_BYTES = 1048576; // 1 MiB

    /** Teto acumulado padrão por sessão (2 GiB). Ajustável por env. */
    public const DEFAULT_MAX_SESSION_BYTES = 2147483648; // 2 GiB

    /** Prazo (segundos) para retomar/concluir uma sessão não finalizada: 24h. */
    public const RESUME_WINDOW_SECONDS = 86400;

    /** Formato de sess aceito: 16–64 chars alfanum/hífen/underscore. */
    public const SESS_REGEX = '/^[A-Za-z0-9_-]{16,64}$/';

    /** @var string Diretório desta sessão. */
    private $dir;

    /** @var int */
    private $roomId;

    /** @var string */
    private $sess;

    /** @var int Teto acumulado efetivo (bytes). */
    private $maxSessionBytes;

    // =====================================================================
    // Parte PURA (sem disco) — testável por unidade
    // =====================================================================

    /** Um identificador de sessão está no formato aceito? */
    public static function isValidSess($sess): bool
    {
        return is_string($sess) && preg_match(self::SESS_REGEX, $sess) === 1;
    }

    /** SHA-256 em hexadecimal de uma string binária. */
    public static function hashBytes(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    /** O conteúdo bate com o checksum informado? (comparação em tempo constante.) */
    public static function checksumMatches(string $bytes, string $expectedHex): bool
    {
        $expectedHex = strtolower(trim($expectedHex));
        if (!preg_match('/^[0-9a-f]{64}$/', $expectedHex)) return false;
        return hash_equals(self::hashBytes($bytes), $expectedHex);
    }

    /** Nome determinístico do arquivo de um chunk a partir da sequência (1-based). */
    public static function chunkFileName(int $seq): string
    {
        return sprintf('chunk_%06d.bin', $seq);
    }

    /**
     * Constrói o texto do manifesto a partir dos chunks (em ordem de sequência).
     * Uma linha por pedaço: "seq:tamanho:sha256\n" (LF real), ordenado por seq.
     *
     * @param array<int,array{seq:int,size:int,sha256:string}> $chunks
     */
    public static function buildManifestText(array $chunks): string
    {
        usort($chunks, static function ($a, $b) { return $a['seq'] <=> $b['seq']; });
        $lines = '';
        foreach ($chunks as $c) {
            $lines .= ((int)$c['seq']) . ':' . ((int)$c['size']) . ':' . strtolower((string)$c['sha256']) . "\n";
        }
        return $lines;
    }

    /** SHA-256 do texto do manifesto (é esse hash que o cliente envia em `manifest`). */
    public static function manifestHash(array $chunks): string
    {
        return self::hashBytes(self::buildManifestText($chunks));
    }

    /**
     * Valida que a sequência de chunks é 1..N sem buracos e sem repetição.
     *
     * @param int[] $seqs
     */
    public static function isContiguousSequence(array $seqs): bool
    {
        if (empty($seqs)) return false;
        sort($seqs, SORT_NUMERIC);
        $expected = 1;
        foreach ($seqs as $s) {
            if ((int)$s !== $expected) return false;
            $expected++;
        }
        return true;
    }

    /** Lê o teto acumulado por sessão a partir da env (fallback no padrão). */
    public static function resolveMaxSessionBytes(?string $envValue): int
    {
        $n = (int)$envValue;
        return $n > 0 ? $n : self::DEFAULT_MAX_SESSION_BYTES;
    }

    // =====================================================================
    // Parte com disco (journal, lock, chunks) — usada pelo controller
    // =====================================================================

    public function __construct(string $baseDir, int $roomId, string $sess, ?int $maxSessionBytes = null)
    {
        if (!self::isValidSess($sess)) {
            throw new InvalidArgumentException('Sessão de gravação inválida.');
        }
        $this->roomId = $roomId;
        $this->sess = $sess;
        $this->dir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $roomId . '_' . $sess;
        $this->maxSessionBytes = $maxSessionBytes !== null && $maxSessionBytes > 0
            ? $maxSessionBytes
            : self::DEFAULT_MAX_SESSION_BYTES;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    private function journalPath(): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . 'journal.json';
    }

    private function lockPath(): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . '.lock';
    }

    /** Garante o diretório da sessão. */
    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            if (!@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
                throw new RuntimeException('Não foi possível criar o diretório da sessão de gravação.');
            }
        }
    }

    /**
     * Executa $fn com lock exclusivo da sessão (serializa append/finalize entre
     * processos que atendem a mesma sessão). Retorna o que $fn retornar.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private function withLock(callable $fn)
    {
        $this->ensureDir();
        $fp = fopen($this->lockPath(), 'c');
        if ($fp === false) {
            throw new RuntimeException('Falha ao abrir lock da sessão de gravação.');
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                throw new RuntimeException('Falha ao obter lock da sessão de gravação.');
            }
            return $fn();
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /** Lê o journal (ou estrutura inicial, se ainda não existe). */
    private function readJournal(): array
    {
        $path = $this->journalPath();
        if (!is_file($path)) {
            return [
                'room_id' => $this->roomId,
                'sess' => $this->sess,
                'started_at' => null,
                'recorded_by' => null,
                'recorded_by_name' => null,
                'chunks' => [], // seq => {size, sha256}
                'bytes' => 0,
                'finalized' => false,
                'rec_token' => null,
            ];
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /** Escreve o journal de forma atômica (tmp + rename). */
    private function writeJournal(array $journal): void
    {
        $path = $this->journalPath();
        $tmp = $path . '.tmp';
        $json = json_encode($journal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Falha ao serializar o journal.');
        }
        $fp = fopen($tmp, 'wb');
        if ($fp === false) throw new RuntimeException('Falha ao abrir journal temporário.');
        $ok = fwrite($fp, $json);
        if ($ok === false) { @fclose($fp); throw new RuntimeException('Falha ao escrever o journal.'); }
        fflush($fp);
        @fclose($fp);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Falha ao publicar o journal.');
        }
    }

    /** Inicia a sessão (idempotente): registra metadados básicos. */
    public function start(?int $recordedBy, ?string $recordedByName, string $recToken): array
    {
        return $this->withLock(function () use ($recordedBy, $recordedByName, $recToken) {
            $j = $this->readJournal();
            if (empty($j['started_at'])) {
                $j['started_at'] = date('Y-m-d H:i:s');
                $j['recorded_by'] = $recordedBy;
                $j['recorded_by_name'] = $recordedByName;
                $j['rec_token'] = $recToken;
                $this->writeJournal($j);
            }
            return $j;
        });
    }

    /** Próxima sequência esperada (1-based). */
    public function nextSeq(): int
    {
        $j = $this->readJournal();
        return count($j['chunks'] ?? []) + 1;
    }

    public function confirmedBytes(): int
    {
        $j = $this->readJournal();
        return (int)($j['bytes'] ?? 0);
    }

    /**
     * Persiste um pedaço: valida seq esperada e checksum, grava os bytes,
     * confirma no journal e só então retorna sucesso. Reenvio idempotente:
     * mesma seq + mesmo sha256 já confirmado retorna sucesso sem duplicar;
     * mesma seq com conteúdo divergente é recusada.
     *
     * @return array{status:string,seq:int,size:int,sha256:string,bytes:int}
     */
    public function appendChunk(int $seq, string $bytes, string $sha256Hex): array
    {
        if (strlen($bytes) > self::MAX_CHUNK_BYTES) {
            throw new RangeException('too_large');
        }
        if (!self::checksumMatches($bytes, $sha256Hex)) {
            throw new UnexpectedValueException('checksum_mismatch');
        }
        $sha = strtolower(trim($sha256Hex));

        return $this->withLock(function () use ($seq, $bytes, $sha) {
            $j = $this->readJournal();
            $chunks = $j['chunks'] ?? [];

            // Idempotência / conflito de reenvio.
            if (isset($chunks[$seq])) {
                if ($chunks[$seq]['sha256'] === $sha) {
                    return ['status' => 'duplicate', 'seq' => $seq, 'size' => (int)$chunks[$seq]['size'], 'sha256' => $sha, 'bytes' => (int)$j['bytes']];
                }
                throw new DomainException('seq_conflict');
            }
            // Ordem: só aceita a próxima sequência contígua.
            $expected = count($chunks) + 1;
            if ($seq !== $expected) {
                throw new DomainException('out_of_order:' . $expected);
            }
            // Teto acumulado.
            if (((int)$j['bytes'] + strlen($bytes)) > $this->maxSessionBytes) {
                throw new RangeException('session_limit');
            }

            // Escreve o chunk em arquivo exclusivo e verifica o que foi gravado.
            $chunkPath = $this->dir . DIRECTORY_SEPARATOR . self::chunkFileName($seq);
            $tmp = $chunkPath . '.tmp';
            $fp = fopen($tmp, 'wb');
            if ($fp === false) throw new RuntimeException('persist_open_failed');
            $written = fwrite($fp, $bytes);
            if ($written === false || $written !== strlen($bytes)) {
                @fclose($fp); @unlink($tmp);
                throw new RuntimeException('persist_write_failed');
            }
            fflush($fp);
            @fclose($fp);
            // Confere o que foi para o disco antes de confirmar.
            if (!is_file($tmp) || filesize($tmp) !== strlen($bytes) || self::hashBytes((string)file_get_contents($tmp)) !== $sha) {
                @unlink($tmp);
                throw new RuntimeException('persist_verify_failed');
            }
            if (!@rename($tmp, $chunkPath)) {
                @unlink($tmp);
                throw new RuntimeException('persist_publish_failed');
            }

            $chunks[$seq] = ['size' => strlen($bytes), 'sha256' => $sha];
            $j['chunks'] = $chunks;
            $j['bytes'] = (int)$j['bytes'] + strlen($bytes);
            $this->writeJournal($j);

            return ['status' => 'ok', 'seq' => $seq, 'size' => strlen($bytes), 'sha256' => $sha, 'bytes' => (int)$j['bytes']];
        });
    }

    /** Lista de chunks confirmados no formato {seq,size,sha256}, ordenada. */
    public function confirmedChunks(): array
    {
        $j = $this->readJournal();
        $out = [];
        foreach (($j['chunks'] ?? []) as $seq => $meta) {
            $out[] = ['seq' => (int)$seq, 'size' => (int)$meta['size'], 'sha256' => (string)$meta['sha256']];
        }
        usort($out, static function ($a, $b) { return $a['seq'] <=> $b['seq']; });
        return $out;
    }

    /**
     * Monta o arquivo final concatenando os chunks na ordem, conferindo o
     * manifesto esperado e a contagem/bytes informados. Devolve o caminho do
     * arquivo montado (num temp dentro do diretório da sessão). NÃO publica em
     * uploads — isso é responsabilidade do controller.
     *
     * @param string $expectedManifestHash SHA-256 do manifesto, vindo do cliente
     * @param int $expectedCount quantidade de chunks informada pelo cliente
     * @param int $expectedBytes total de bytes informado pelo cliente
     */
    public function assemble(string $expectedManifestHash, int $expectedCount, int $expectedBytes): string
    {
        return $this->withLock(function () use ($expectedManifestHash, $expectedCount, $expectedBytes) {
            $chunks = $this->confirmedChunks();
            if (empty($chunks)) throw new RuntimeException('empty_session');

            $seqs = array_map(static function ($c) { return $c['seq']; }, $chunks);
            if (!self::isContiguousSequence($seqs)) throw new DomainException('non_contiguous');
            if (count($chunks) !== $expectedCount) throw new DomainException('count_mismatch');

            $sumBytes = array_sum(array_map(static function ($c) { return $c['size']; }, $chunks));
            if ($sumBytes !== $expectedBytes) throw new DomainException('bytes_mismatch');

            $manifest = self::manifestHash($chunks);
            if (!hash_equals($manifest, strtolower(trim($expectedManifestHash)))) {
                throw new DomainException('manifest_mismatch');
            }

            $out = $this->dir . DIRECTORY_SEPARATOR . 'assembled.webm';
            $tmp = $out . '.tmp';
            $ofp = fopen($tmp, 'wb');
            if ($ofp === false) throw new RuntimeException('assemble_open_failed');
            foreach ($chunks as $c) {
                $chunkPath = $this->dir . DIRECTORY_SEPARATOR . self::chunkFileName($c['seq']);
                $data = @file_get_contents($chunkPath);
                if ($data === false || strlen($data) !== $c['size'] || self::hashBytes($data) !== $c['sha256']) {
                    @fclose($ofp); @unlink($tmp);
                    throw new RuntimeException('assemble_chunk_corrupt:' . $c['seq']);
                }
                if (fwrite($ofp, $data) === false) {
                    @fclose($ofp); @unlink($tmp);
                    throw new RuntimeException('assemble_write_failed');
                }
            }
            fflush($ofp);
            @fclose($ofp);
            if (filesize($tmp) !== $expectedBytes) {
                @unlink($tmp);
                throw new RuntimeException('assemble_size_mismatch');
            }
            if (!@rename($tmp, $out)) {
                @unlink($tmp);
                throw new RuntimeException('assemble_publish_failed');
            }
            return $out;
        });
    }

    /** Metadados persistidos (recorded_by/name, started_at, rec_token). */
    public function meta(): array
    {
        $j = $this->readJournal();
        return [
            'recorded_by' => $j['recorded_by'] ?? null,
            'recorded_by_name' => $j['recorded_by_name'] ?? null,
            'started_at' => $j['started_at'] ?? null,
            'rec_token' => $j['rec_token'] ?? null,
            'bytes' => (int)($j['bytes'] ?? 0),
        ];
    }

    /** Marca a sessão como finalizada e guarda o token publicado (idempotência). */
    public function markFinalized(string $recToken): void
    {
        $this->withLock(function () use ($recToken) {
            $j = $this->readJournal();
            $j['finalized'] = true;
            $j['rec_token'] = $recToken;
            $this->writeJournal($j);
            return null;
        });
    }

    public function isFinalized(): bool
    {
        $j = $this->readJournal();
        return !empty($j['finalized']);
    }

    public function publishedToken(): ?string
    {
        $j = $this->readJournal();
        return $j['rec_token'] ?? null;
    }

    /**
     * Remove os chunks brutos e o arquivo montado após a publicação confirmada,
     * preservando o journal pequeno (idempotência de retries). Não remove nada se
     * a sessão não estiver finalizada.
     */
    public function cleanupChunks(): void
    {
        if (!$this->isFinalized()) return;
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . 'chunk_*.bin') ?: [] as $f) {
            @unlink($f);
        }
        @unlink($this->dir . DIRECTORY_SEPARATOR . 'assembled.webm');
    }
}
