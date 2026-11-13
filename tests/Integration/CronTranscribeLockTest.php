<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use CronLock;

/**
 * cron_transcribe.php roda uma execução por vez (incidente de 13/11/2026: as
 * execuções a cada minuto se acumularam e esgotaram as conexões do banco).
 *
 * Usa processos PHP de verdade, como a tarefa agendada e o terminal SSH:
 * - com a trava ocupada, o script sai na hora, com código 0 e "skipped";
 * - um processo morto à força (kill -9) não deixa a trava presa.
 *
 * O script só é executado no caminho "ocupado", que sai antes de abrir conexão
 * ou tocar em storage/video-recordings; nada é publicado.
 */
final class CronTranscribeLockTest extends TestCase
{
    private ?CronLock $lock = null;

    protected function tearDown(): void
    {
        if ($this->lock) $this->lock->release();
        $this->lock = null;
        $dir = CronLock::defaultDir();
        @unlink($dir . DIRECTORY_SEPARATOR . CronLock::RECORDING_QUEUE . '.lock');
        @rmdir($dir); // só remove se ficou vazio
    }

    /** @return array{0:int,1:string,2:string} código de saída, stdout, stderr */
    private function runPhp(array $args, int $timeoutSec = 60): array
    {
        $env = getenv();
        $env['APP_ENV'] = 'testing';
        $proc = proc_open(array_merge([PHP_BINARY], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
        $this->assertIsResource($proc);
        $start = time();
        $out = '';
        $err = '';
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                return [(int) $st['exitcode'], $out, $err];
            }
            if (time() - $start > $timeoutSec) {
                proc_terminate($proc, 9);
                $this->fail('cron_transcribe.php não terminou em ' . $timeoutSec . 's');
            }
            usleep(50000);
        }
    }

    public function testComOutraExecucaoEmAndamentoSaiNaHoraSemErro(): void
    {
        $this->lock = CronLock::tryAcquire(CronLock::RECORDING_QUEUE);
        $this->assertNotNull($this->lock, 'a trava deveria estar livre no início do teste');

        $t0 = microtime(true);
        [$code, $out, $err] = $this->runPhp(['cron_transcribe.php', 'localhost']);
        $elapsed = microtime(true) - $t0;

        $this->assertSame(0, $code, "saída: {$out} {$err}");
        $this->assertStringContainsString('"skipped"', $out);
        $this->assertStringNotContainsString('"abandoned"', $out, 'não pode ter rodado a fila');
        $this->assertSame('', trim($err), 'sem stderr: a tarefa do Plesk em "Apenas erros" não deve mandar e-mail');
        $this->assertLessThan(10, $elapsed);
    }

    public function testProcessoMortoAForcaNaoDeixaATravaPresa(): void
    {
        $code = 'define("BASE_PATH", ' . var_export(BASE_PATH, true) . ');'
            . 'require BASE_PATH . "/app/core/CronLock.php";'
            . '$l = CronLock::tryAcquire(CronLock::RECORDING_QUEUE);'
            . 'echo $l ? "HELD\n" : "BUSY\n"; fflush(STDOUT); sleep(60);';
        $env = getenv();
        $proc = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH, $env);
        $this->assertIsResource($proc);
        try {
            $line = trim((string) fgets($pipes[1]));
            $this->assertSame('HELD', $line, 'o processo filho deveria pegar a trava');

            // Outro processo segurando: aqui não consegue.
            $this->assertNull(CronLock::tryAcquire(CronLock::RECORDING_QUEUE));
        } finally {
            proc_terminate($proc, 9);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        // Morto à força: o sistema solta a trava.
        $deadline = microtime(true) + 5;
        do {
            $this->lock = CronLock::tryAcquire(CronLock::RECORDING_QUEUE);
            if ($this->lock) break;
            usleep(100000);
        } while (microtime(true) < $deadline);
        $this->assertNotNull($this->lock, 'a trava ficou presa depois do kill');
    }
}
